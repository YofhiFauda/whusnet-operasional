import test from 'node:test';
import assert from 'node:assert/strict';
import { infrastructureReport } from '../resources/js/task-infrastructure-report.js';

const config = {
    rows: [],
    summary: '',
    materialAction: '',
    taskType: 'infr',
    nodes: [{ id: 1, label: 'ODC Utama', type: 'odc', parent_id: null }, { id: 2, label: 'ODP Anak', type: 'odp', parent_id: 1 }],
    serials: [{ id: 10, label: 'Modul · SN 10' }],
    oldSerials: [{ id: 11, label: 'Modul · SN 11', node_id: 1 }],
    items: [{ id: 20, label: 'Closure · sisa 2 pcs' }],
    rolls: [{ id: 30, label: 'Feeder · sisa 20 m' }],
    ports: [{ id: 40, label: '1', node_id: 1, used: false }, { id: 41, label: '2', node_id: 1, used: true }, { id: 42, label: '1', node_id: 2, used: false }],
    slots: [],
    sources: [{ id: 50, label: 'DPL-50 · Closure' }],
};

test('O-REQ dimulai tanpa perubahan aset dan INFR meminta tindakan', () => {
    assert.equal(infrastructureReport({ ...config, taskType: 'oreq' }).mode, 'none');
    const report = infrastructureReport(config);
    assert.equal(report.mode, 'actions');
    assert.match(report.validate(), /ringkasan pekerjaan/);
    report.summary = 'Port jaringan diperiksa.';
    assert.match(report.validate(), /Tambahkan tindakan/);
});

test('setiap baris tindakan divalidasi sebelum tinjauan dan lokasi membatasi SN/port', () => {
    const report = infrastructureReport(config);
    report.summary = 'Perangkat lama diganti dan koneksi normal.';
    report.rows.push({ action_key: 'key-1', action_type: 'replace', network_node_id: '1', old_serial_id: '', serial_id: '', result_note: '' });
    assert.match(report.validate(), /SN lama/);
    assert.deepEqual(report.serialsAt(report.rows[0]).map((serial) => serial.id), [11]);
    report.rows[0].old_serial_id = '11';
    report.rows[0].serial_id = '10';
    assert.match(report.validate(), /hasil tindakan/);
    report.rows[0].result_note = 'Koneksi normal.';
    assert.equal(report.validate(), '');
    assert.deepEqual(report.portsAt(1, 'port_connect').map((port) => port.id), [40]);
    assert.deepEqual(report.portsAt(1, 'port_release').map((port) => port.id), [41]);

    report.rows[0].network_node_id = '2';
    report.nodeChanged(report.rows[0]);
    assert.equal(report.rows[0].serial_id, '');
    assert.equal(report.rows[0].old_serial_id, '');
});

test('pilihan SN mengikuti tipe node dan kartu OLT hanya menerima modul yang cocok', () => {
    const report = infrastructureReport({
        ...config,
        nodes: [{ id: 3, label: 'Kartu OLT', type: 'olt_card', type_id: 7, parent_id: 1 }],
        serials: [
            { id: 10, label: 'Modul cocok', item_node_type_id: 7 },
            { id: 12, label: 'Modul salah', item_node_type_id: 8 },
            { id: 13, label: 'Barang umum', item_node_type_id: null },
        ],
    });
    assert.deepEqual(report.serialsFor({ network_node_id: '3' }).map((serial) => serial.id), [10]);
});

const hierarchyConfig = {
    ...config,
    scope: {
        depth: 1,
        breadcrumb: 'C1 → C1-M1',
        path: { pop: { id: 1, label: 'C1' }, mini_pop: { id: 5, label: 'C1-M1' } },
    },
    serials: [
        { id: 60, label: 'Rak · SN 60', location_kind: 'mini_pop' },
        { id: 61, label: 'Kartu · SN 61', location_kind: 'slot' },
        { id: 62, label: 'Chassis · SN 62', location_kind: null, item_node_type_code: 'olt_chassis' },
    ],
};

test('dropdown bertingkat mengunci akar scope dan menolak Belum dipilih', () => {
    const report = infrastructureReport(hierarchyConfig);
    report.summary = 'Pasang rak di Mini POP.';
    report.addAction('install');
    const row = report.rows[0];
    assert.equal(row.location_mode, 'hierarchy');
    assert.equal(row.loc.pop, '1');
    assert.equal(row.loc.mini_pop, '5');
    assert.equal(report.levelLocked(row, 'mini_pop'), true);
    assert.equal(report.levelEnabled(row, 'device'), true);
    assert.equal(report.levelEnabled(row, 'slot'), false);
    row.serial_id = '60';
    row.result_note = 'Terpasang.';
    assert.match(report.validate(), /OLT\/Switch belum dipilih/);

    row.loc.device = 'none';
    report.levelChanged(row, 'device');
    assert.equal(row.serial_id, '', 'Ganti lokasi mengosongkan SN pilihan lama.');
    row.serial_id = '60';
    assert.equal(report.validate(), '');
    assert.deepEqual(report.deepestLocation(row), { kind: 'mini_pop', id: '5' });
    assert.deepEqual(report.locationInputs(row), { loc_pop: '1', loc_mini_pop: '5', loc_device: 'none' });
    assert.deepEqual(report.serialsFor(row).map((serial) => serial.id), [60]);
});

test('ganti induk menghapus anak, SN, port, dan ujung sambungan', () => {
    const report = infrastructureReport(hierarchyConfig);
    report.addAction('install');
    const row = report.rows[0];
    row.loc.device = '9';
    row.levels.device.options = [{ id: 9, label: 'OLT-A', type: 'olt_chassis' }];
    report.levelChanged(row, 'device');
    row.loc.slot = '3';
    row.serial_id = '61';
    row.old_serial_id = '70';
    row.port_id = '4';
    row.end_from = 'port:2';
    report.levelChanged(row, 'slot');
    assert.equal(row.serial_id, '');

    row.serial_id = '61';
    row.loc.device = 'none';
    report.levelChanged(row, 'device');
    assert.equal(row.loc.slot, '');
    assert.equal(row.serial_id, '');
    assert.equal(row.old_serial_id, '');
    assert.equal(row.port_id, '');
    assert.equal(row.end_from, '');
    assert.equal(report.levelEnabled(row, 'slot'), false);
});

test('SN baru mengikuti jenis lokasi akhir dan tipe perangkat', () => {
    const report = infrastructureReport(hierarchyConfig);
    report.addAction('install');
    const row = report.rows[0];
    row.loc.device = '9';
    row.levels.device.options = [{ id: 9, label: 'OLT-A', type: 'olt_chassis' }];
    report.levelChanged(row, 'device');
    row.loc.slot = 'none';
    report.levelChanged(row, 'slot');
    assert.deepEqual(report.serialsFor(row).map((serial) => serial.id), [62]);

    row.loc.slot = '3';
    report.levelChanged(row, 'slot');
    row.loc.port = 'none';
    report.levelChanged(row, 'port');
    assert.deepEqual(report.serialsFor(row).map((serial) => serial.id), [61]);
});

test('jalur Switch berhenti di Port tanpa memaksa ODP', () => {
    const report = infrastructureReport(hierarchyConfig);
    report.summary = 'Cek port switch.';
    report.addAction('inspect');
    const row = report.rows[0];
    row.result_note = 'Normal';
    row.loc.device = '8';
    row.levels.device.options = [{ id: 8, label: 'SW-B', type: 'switch_mini_pop' }];
    report.levelChanged(row, 'device');
    row.loc.slot = '2';
    report.levelChanged(row, 'slot');
    row.loc.port = '4';
    row.levels.odp.disabled = true;
    report.levelChanged(row, 'port');
    row.levels.odp.disabled = true;
    assert.equal(report.levelEnabled(row, 'odp'), false);
    assert.equal(report.validate(), '');
    assert.deepEqual(report.deepestLocation(row), { kind: 'port', id: '4' });
});

test('koreksi material memerlukan asal dan alasan tanpa baris pemasangan', () => {
    const report = infrastructureReport(config);
    report.summary = 'Jumlah fisik berbeda dari catatan.';
    report.setMode('correction');
    report.correction.action = 'correct';
    report.correction.source = '50';
    report.correction.qty = '2';
    assert.match(report.validate(), /lokasi pencatatan/);
    report.correction.node = '1';
    report.correction.reason = 'Jumlah awal salah.';
    assert.equal(report.validate(), '');
});
