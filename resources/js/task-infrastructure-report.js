export function infrastructureReport(config) {
    const rows = (config.rows || []).map((row) => ({
        ...row,
        action_type: row.action_type || 'inspect',
        network_node_id: String(row.network_node_id || ''),
        material_kind: row.serial_id ? 'serial' : row.item_id ? 'quantity' : row.roll_id ? 'roll' : 'serial',
    }));

    return {
        ...config,
        rows,
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
            if (window.crypto?.randomUUID) return window.crypto.randomUUID();
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
            this.rows.push({
                action_key: this.newKey(),
                action_type: type,
                network_node_id: '',
                material_kind: 'serial',
                result_note: '',
            });
            this.reviewing = false;
            this.$nextTick(() => this.$refs.actionList?.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
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
            const node = this.node(row);
            if (!node) return [];
            return this.serials.filter((serial) => node.type === 'olt_card'
                ? String(serial.item_node_type_id) === String(node.type_id)
                : !serial.item_node_type_id || String(serial.item_node_type_id) === String(node.type_id));
        },

        serialsAt(row) {
            return this.oldSerials.filter((serial) => String(serial.node_id) === String(row.network_node_id));
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
                if ((!row.network_node_id && (this.taskType === 'infr' || row.action_type !== 'inspect'))
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
