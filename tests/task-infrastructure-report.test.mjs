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
