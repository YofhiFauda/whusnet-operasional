// Tingkat dropdown lokasi bertingkat (rancangan hierarki §10.2). Nama input
// server: loc_{level}. Nilai: ID, 'none' (Dikosongkan), atau '' (Belum dipilih).
export const LOCATION_LEVELS = ['pop', 'mini_pop', 'device', 'slot', 'port', 'odp'];

const LEVEL_LABELS = { pop: 'POP', mini_pop: 'Mini POP', device: 'OLT/Switch', slot: 'Slot', port: 'PON/Port', odp: 'ODP' };
const LEVEL_KIND = { pop: 'pop', mini_pop: 'mini_pop', device: 'node', slot: 'slot', port: 'port', odp: 'node' };
const DEPENDENT_FIELDS = ['serial_id', 'old_serial_id', 'port_id', 'slot_id', 'target_node_id', 'target_port_id', 'end_from', 'end_to'];

function levelState(overrides = {}) {
    return { options: [], allow_none: true, disabled: false, loading: false, ...overrides };
}

function initLocation(row, scope) {
    const hasLoc = LOCATION_LEVELS.some((level) => row[`loc_${level}`] !== undefined && row[`loc_${level}`] !== '');
    row.location_mode = row.location_mode || (scope && (hasLoc || !row.network_node_id) ? 'hierarchy' : 'legacy');
    row.loc = {};
    row.levels = {};
    LOCATION_LEVELS.forEach((level, depth) => {
        const fixed = scope?.path?.[level];
        row.loc[level] = fixed ? String(fixed.id) : String(row[`loc_${level}`] ?? '');
        row.levels[level] = fixed && depth <= scope.depth
            ? levelState({ options: [{ id: fixed.id, label: fixed.label }], allow_none: false })
            : levelState();
    });
    row.assets = row.assets || { direct: [], descendant_count: 0, breadcrumb: '' };
    DEPENDENT_FIELDS.forEach((field) => { row[field] = row[field] === undefined || row[field] === null ? '' : String(row[field]); });
    return row;
}

export function infrastructureReport(config) {
    const scope = config.scope || null;
    const rows = (config.rows || []).map((row) => initLocation({
        ...row,
        action_type: row.action_type || 'inspect',
        network_node_id: String(row.network_node_id || ''),
        material_kind: row.serial_id ? 'serial' : row.item_id ? 'quantity' : row.roll_id ? 'roll' : 'serial',
    }, scope));

    return {
        ...config,
        scope,
        levelNames: LEVEL_LABELS,
        locationLevels: LOCATION_LEVELS,
        rows,

        init() {
            this.rows.forEach((row) => this.bootLocation(row));
        },

        rootDepth() {
            return this.scope ? this.scope.depth : -1;
        },

        levelLocked(row, level) {
            return LOCATION_LEVELS.indexOf(level) <= this.rootDepth();
        },

        // Tingkat bisa dipilih bila semua induknya sudah berisi ID (bukan
        // Dikosongkan/Belum dipilih) dan server tidak menonaktifkannya.
        levelEnabled(row, level) {
            const depth = LOCATION_LEVELS.indexOf(level);
            if (row.location_mode !== 'hierarchy' || depth <= this.rootDepth()) return false;
            for (let index = 0; index < depth; index++) {
                const value = row.loc[LOCATION_LEVELS[index]];
                if (value === '' || value === 'none') return false;
            }
            return !row.levels[level].disabled;
        },

        levelChanged(row, level) {
            const depth = LOCATION_LEVELS.indexOf(level);
            LOCATION_LEVELS.slice(depth + 1).forEach((child) => {
                row.loc[child] = '';
                row.levels[child] = levelState();
            });
            DEPENDENT_FIELDS.forEach((field) => { row[field] = ''; });
            row.assets = { direct: [], descendant_count: 0, breadcrumb: '' };
            this.reviewing = false;
            const value = row.loc[level];
            if (value !== '' && value !== 'none' && depth < LOCATION_LEVELS.length - 1) {
                this.loadLevel(row, LOCATION_LEVELS[depth + 1], value);
            }
            this.loadAssets(row);
        },

        bootLocation(row) {
            if (row.location_mode !== 'hierarchy' || !this.scope) return;
            const next = LOCATION_LEVELS[this.rootDepth() + 1];
            const parent = row.loc[LOCATION_LEVELS[this.rootDepth()]];
            if (next) this.loadLevel(row, next, parent);
            this.loadAssets(row);
        },

        async loadLevel(row, level, parent) {
            if (!this.locationOptionsUrl || typeof fetch === 'undefined') return;
            const state = row.levels[level];
            state.loading = true;
            try {
                const url = new URL(this.locationOptionsUrl, window.location.origin);
                url.searchParams.set('level', level);
                url.searchParams.set('parent', parent);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = response.ok ? await response.json() : { options: [], allow_none: false, disabled: true };
                Object.assign(state, { options: data.options, allow_none: data.allow_none, disabled: data.disabled });
            } finally {
                state.loading = false;
            }
        },

        async loadAssets(row) {
            const location = this.deepestLocation(row);
            if (!location || !this.locationAssetsUrl || typeof fetch === 'undefined') return;
            const url = new URL(this.locationAssetsUrl, window.location.origin);
            url.searchParams.set('kind', location.kind);
            url.searchParams.set('id', location.id);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (response.ok) row.assets = await response.json();
        },

        deepestLocation(row) {
            if (row.location_mode !== 'hierarchy') return null;
            let found = null;
            for (const level of LOCATION_LEVELS) {
                const value = row.loc[level];
                if (value === '' || value === 'none') break;
                found = { kind: LEVEL_KIND[level], id: value };
            }
            return found;
        },

        deepestType(row) {
            let type = null;
            for (const level of LOCATION_LEVELS) {
                const value = row.loc[level];
                if (value === '' || value === 'none') break;
                type = row.levels[level].options.find((option) => String(option.id) === String(value))?.type ?? null;
            }
            return type;
        },

        locationLabel(row) {
            if (row.location_mode !== 'hierarchy') return this.nodeName(row.network_node_id);
            const parts = [];
            for (const level of LOCATION_LEVELS) {
                const value = row.loc[level];
                if (value === '' || value === 'none') break;
                parts.push(row.levels[level].options.find((option) => String(option.id) === String(value))?.label || value);
            }
            return parts.join(' → ') || 'Lokasi belum dipilih';
        },

        // Nilai yang dikirim server: hanya tingkat yang terisi (ID atau none).
        locationInputs(row) {
            const inputs = {};
            if (row.location_mode !== 'hierarchy') return inputs;
            for (const level of LOCATION_LEVELS) {
                const value = row.loc[level];
                if (value === '') break;
                inputs[`loc_${level}`] = value;
                if (value === 'none') break;
            }
            return inputs;
        },

        locationError(row) {
            if (row.location_mode !== 'hierarchy') return '';
            for (let depth = this.rootDepth() + 1; depth < LOCATION_LEVELS.length; depth++) {
                const level = LOCATION_LEVELS[depth];
                const value = row.loc[level];
                if (value === 'none') return '';
                if (value === '') {
                    if (!this.levelEnabled(row, level)) return '';
                    return `${LEVEL_LABELS[level]} belum dipilih. Pilih lokasi atau «Dikosongkan».`;
                }
            }
            return '';
        },

        endOptions(row) {
            const options = [];
            for (const level of LOCATION_LEVELS) {
                const value = row.loc[level];
                if (value === '' || value === 'none') break;
                const label = row.levels[level].options.find((option) => String(option.id) === String(value))?.label || value;
                options.push({ value: `${level === 'mini_pop' ? 'mini_pop' : LEVEL_KIND[level]}:${value}`, label: `${LEVEL_LABELS[level]} · ${label}` });
            }
            return options;
        },
        mode: config.materialAction ? 'correction' : rows.length ? 'actions' : config.taskType === 'oreq' ? 'none' : 'actions',
        summary: config.summary || '',
        customerModemChoice: config.customerModemChoice || 'tidak_ganti',
        customerRoll: String(config.customerRoll || ''),
        correction: {
            action: config.materialAction || '',
            source: String(config.materialSource || ''),
            qty: config.materialQty || '',
            reason: config.materialReason || '',
            node: String(config.materialNode || ''),
            roll: String(config.materialRoll || ''),
        },
        reviewing: false,
        submitting: false,
        formError: '',

        actionNames: {
            inspect: 'Periksa lokasi',
            install: 'Pasang barang jaringan',
            retrieve: 'Cabut perangkat ber-SN',
            replace: 'Ganti perangkat ber-SN',
            port_connect: 'Sambung kabel antar node',
            port_release: 'Lepas sambungan kabel',
        },

        actionHints: {
            inspect: 'Catat kondisi node atau port. Stok tidak berubah.',
            install: 'Pilih satu barang dari Gudang yang ditinggal di titik jaringan.',
            retrieve: 'SN lama kembali ke custody teknisi.',
            replace: 'Cabut SN lama dan pasang SN baru pada lokasi yang sama.',
            port_connect: 'Hubungkan port asal dengan port pada node anak.',
            port_release: 'Putuskan sambungan port yang sedang dipakai.',
        },

        stockEffects: {
            inspect: 'Stok tidak berubah.',
            install: 'Barang keluar dari custody tim dan tercatat di lokasi ini.',
            retrieve: 'SN dicabut dan menunggu pemeriksaan Gudang.',
            replace: 'SN lama dicabut; SN baru keluar dari custody tim.',
            port_connect: 'Stok tidak berubah; port menjadi terhubung.',
            port_release: 'Stok tidak berubah; port dibebaskan.',
        },

        newKey() {
            if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                const value = Math.floor(Math.random() * 16);
                return (char === 'x' ? value : (value & 3) | 8).toString(16);
            });
        },

        setMode(mode) {
            this.mode = mode;
            this.reviewing = false;
            this.formError = '';
        },

        addAction(type) {
            this.mode = 'actions';
            const row = initLocation({
                action_key: this.newKey(),
                action_type: type,
                network_node_id: '',
                material_kind: 'serial',
                result_note: '',
            }, this.scope);
            this.rows.push(row);
            this.bootLocation(this.rows[this.rows.length - 1]);
            this.reviewing = false;
            this.$nextTick?.(() => this.$refs.actionList?.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        setLocationMode(row, mode) {
            row.location_mode = mode;
            row.network_node_id = '';
            DEPENDENT_FIELDS.forEach((field) => { row[field] = ''; });
            if (mode === 'hierarchy') this.bootLocation(row);
            this.reviewing = false;
        },

        removeAction(index) {
            this.rows.splice(index, 1);
            this.reviewing = false;
        },

        node(row) {
            return this.nodes.find((node) => String(node.id) === String(row.network_node_id));
        },

        nodeName(id) {
            return this.nodes.find((node) => String(node.id) === String(id))?.label || 'Lokasi belum dipilih';
        },

        isCard(row) {
            return this.node(row)?.type === 'olt_card';
        },

        nodeChanged(row) {
            row.old_serial_id = '';
            row.serial_id = '';
            row.port_id = '';
            row.slot_id = '';
            row.target_node_id = '';
            row.target_port_id = '';
            if (this.isCard(row)) row.material_kind = 'serial';
            this.reviewing = false;
        },

        serialsFor(row) {
            if (row.location_mode === 'hierarchy') {
                // Barang baru hanya yang cocok dengan jenis lokasi akhir
                // (Posisi Pemasangan Master Barang; kosong = node).
                const location = this.deepestLocation(row);
                if (!location || location.kind === 'pop') return [];
                const type = this.deepestType(row);
                return this.serials.filter((serial) => (serial.location_kind || 'node') === location.kind
                    && (location.kind !== 'node' || !serial.item_node_type_code || serial.item_node_type_code === type));
            }
            const node = this.node(row);
            if (!node) return [];
            return this.serials.filter((serial) => node.type === 'olt_card'
                ? String(serial.item_node_type_id) === String(node.type_id)
                : !serial.item_node_type_id || String(serial.item_node_type_id) === String(node.type_id));
        },

        serialsAt(row) {
            if (row.location_mode === 'hierarchy') {
                return (row.assets?.direct || []).map((serial) => ({ id: serial.id, label: `${serial.item || 'Aset'} · SN ${serial.serial_number}` }));
            }
            return this.oldSerials.filter((serial) => String(serial.node_id) === String(row.network_node_id));
        },

        // Port ODP/node lama (network_node_ports) — di mode hierarki diambil
        // dari node terdalam yang dipilih (mis. ODP di bawah PON).
        portNodeId(row) {
            if (row.location_mode !== 'hierarchy') return row.network_node_id;
            const location = this.deepestLocation(row);
            return location?.kind === 'node' ? location.id : '';
        },

        portsAt(nodeId, type) {
            return this.ports.filter((port) => String(port.node_id) === String(nodeId)
                && (type === 'port_connect' ? !port.used : type === 'port_release' ? port.used : true));
        },

        targetNodes(row) {
            return this.nodes.filter((node) => String(node.parent_id) === String(row.network_node_id));
        },

        slotsFor(row) {
            const parentId = this.node(row)?.parent_id;
            return this.slots.filter((slot) => String(slot.chassis_id) === String(parentId)
                && (!slot.card_node_id || String(slot.card_node_id) === String(row.network_node_id)));
        },

        optionName(options, id) {
            return options.find((option) => String(option.id) === String(id))?.label || 'Belum dipilih';
        },

        assetSummary(row) {
            if (row.action_type === 'install') {
                if (row.material_kind === 'quantity') return `${this.optionName(this.items, row.item_id)} · ${row.qty || 0} unit`;
                if (row.material_kind === 'roll') return `${this.optionName(this.rolls, row.roll_id)} · ${row.meters || 0} meter`;
                return this.optionName(this.serials, row.serial_id);
            }
            if (row.action_type === 'retrieve') return this.optionName(this.oldSerials, row.old_serial_id);
            if (row.action_type === 'replace') {
                return `${this.optionName(this.oldSerials, row.old_serial_id)} → ${this.optionName(this.serials, row.serial_id)}`;
            }
            if (row.action_type === 'port_connect') {
                return `Port ${this.optionName(this.ports, row.port_id)} → ${this.nodeName(row.target_node_id)}, port ${this.optionName(this.ports, row.target_port_id)}`;
            }
            if (row.action_type === 'port_release' || row.port_id) return `Port ${this.optionName(this.ports, row.port_id)}`;
            return 'Tanpa perubahan stok';
        },

        validate() {
            if (!this.summary.trim()) return 'Isi ringkasan pekerjaan terlebih dahulu.';
            if (this.mode === 'actions' && this.rows.length === 0) {
                return 'Tambahkan tindakan yang dilakukan, atau pilih Tanpa tindakan jaringan.';
            }
            if (this.mode === 'correction') {
                if (!this.correction.action || !this.correction.source) return 'Pilih jenis koreksi dan transaksi asal.';
                if (this.correction.qty === '' || Number(this.correction.qty) < (this.correction.action === 'retrieve' ? 0.01 : 0)) {
                    return 'Isi jumlah material yang benar.';
                }
                if (this.correction.action === 'correct' && !this.correction.node) return 'Pilih lokasi pencatatan yang benar.';
                if (!this.correction.reason.trim()) return 'Jelaskan alasan koreksi atau pencabutan.';
            }
            if (this.mode !== 'actions') return '';
            for (let index = 0; index < this.rows.length; index++) {
                const row = this.rows[index];
                const label = `Tindakan ${index + 1}`;
                const needsLocation = this.taskType === 'infr' || row.action_type !== 'inspect';
                if (row.location_mode === 'hierarchy') {
                    const error = this.locationError(row);
                    if (error && (needsLocation || this.deepestLocation(row))) return `${label}: ${error}`;
                    if (row.action_type === 'install' && row.material_kind === 'serial' && this.deepestLocation(row)?.kind === 'pop') {
                        return `${label}: aset ber-SN tidak dipasang langsung di POP Cabang — pilih Mini POP atau perangkat.`;
                    }
                } else if ((!row.network_node_id && needsLocation)
                    || (row.port_id && !row.network_node_id)) return `${label}: pilih titik jaringan.`;
                if (row.action_type === 'install') {
                    if (this.isCard(row) && row.material_kind !== 'serial') return `${label}: kartu OLT harus memakai SN.`;
                    if (row.material_kind === 'serial' && !row.serial_id) return `${label}: pilih SN barang yang dipasang.`;
                    if (row.material_kind === 'quantity' && (!row.item_id || Number(row.qty) <= 0)) return `${label}: pilih barang dan isi jumlahnya.`;
                    if (row.material_kind === 'roll' && (!row.roll_id || Number(row.meters) <= 0)) return `${label}: pilih roll dan isi meter yang dipakai.`;
                }
                if (['retrieve', 'replace'].includes(row.action_type) && !row.old_serial_id) return `${label}: pilih SN lama pada lokasi ini.`;
                if (row.action_type === 'replace' && !row.serial_id) return `${label}: pilih SN pengganti.`;
                if (['install', 'replace'].includes(row.action_type) && this.isCard(row) && !row.slot_id) {
                    return `${label}: pilih slot pada chassis OLT.`;
                }
                if (['port_connect', 'port_release'].includes(row.action_type) && !row.port_id) return `${label}: pilih port asal.`;
                if (row.action_type === 'port_connect' && (!row.target_node_id || !row.target_port_id)) {
                    return `${label}: pilih node dan port tujuan.`;
                }
                if (!String(row.result_note || '').trim()) return `${label}: tulis hasil tindakan.`;
            }
            return '';
        },

        review() {
            this.formError = this.validate();
            if (this.formError) {
                this.$nextTick(() => this.$refs.formError?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }
            this.reviewing = true;
            this.$nextTick(() => this.$refs.review?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },

        submit() {
            if (this.submitting) return;
            if (!this.reviewing) {
                this.review();
                return;
            }
            this.formError = this.validate();
            if (this.formError) {
                this.reviewing = false;
                return;
            }
            this.submitting = true;
            this.$refs.form.submit();
        },
    };
}
