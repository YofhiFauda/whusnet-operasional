{{-- Perilaku modal "Atur Jaringan & Mini POP". SENGAJA tanpa tag <script>:
     partial ini disisipkan ke DALAM blok <script> milik halaman pemanggil
     (List Pelanggan, Antrean Verifikasi & Pemasangan). Pasangannya WAJIB
     customers.partials._network_assignment_modal — fungsi di sini mencari
     elemen #network-modal-wrapper dkk dari markup itu. --}}
    /* ── Modal Atur Mini POP & Jaringan ──
       @param {string} updateUrl PUT target (customers.network-assignment.update)
       @param {string} dataUrl   GET sumber isi dropdown (customers.network-assignment.data)
       Keduanya dirender server-side lewat route() di tombol pemanggil. JANGAN
       kembali merakit path network-assignment dari id pelanggan di sini — path
       route tidak boleh diduplikasi di klien, dan form yang action-nya gagal
       terisi diam-diam POST ke URL halaman daftar. ADHOC-20 langkah 3. */
    function openNetworkAssignmentModal(updateUrl, dataUrl) {
        const wrapper = document.getElementById('network-modal-wrapper');
        const form = document.getElementById('network-assignment-form');
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        const distSelect = document.getElementById('na-distribution-select');
        const warning = document.getElementById('na-blocked-warning');
        const submitBtn = document.getElementById('na-submit-btn');

        const custNameEl = document.getElementById('na-customer-name');
        const custCidEl = document.getElementById('na-customer-cid');
        const popNameEl = document.getElementById('na-pop-name');

        if (!wrapper || !form) return;
        if (!updateUrl || !dataUrl) {
            if (window.Toast) {
                window.Toast.error('Aksi Gagal', 'Target penyimpanan jaringan tidak dikenal. Muat ulang halaman.');
            }

            return;
        }

        form.action = updateUrl;
        miniPopSelect.innerHTML = '<option value="">Memuat...</option>';
        distSelect.innerHTML = '<option value="">—</option>';
        custNameEl.textContent = 'Memuat...';
        custCidEl.textContent = '—';
        popNameEl.textContent = '—';
        warning.classList.add('hidden');
        submitBtn.disabled = true;

        fetch(dataUrl, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                custNameEl.textContent = data.customer_name;
                custCidEl.textContent = data.customer_cid || 'DRAFT';
                popNameEl.textContent = `${data.pop_name} (${data.pop_code})`;

                miniPopSelect.innerHTML = '<option value="">— Belum di-assign —</option>';
                data.mini_pops.forEach(mp => {
                    const opt = document.createElement('option');
                    opt.value = mp.id;
                    opt.textContent = `[${mp.pop_code}] ${mp.name}`;
                    if (data.current.mini_pop_id === mp.id) opt.selected = true;
                    miniPopSelect.appendChild(opt);
                });

                distSelect.dataset.allOptions = JSON.stringify(data.distributions);
                distSelect.dataset.currentDistributionId = data.current.distribution_id ?? '';
                renderDistributionOptions();

                if (!data.editable) {
                    warning.classList.remove('hidden');
                    submitBtn.disabled = true;
                } else {
                    submitBtn.disabled = false;
                }
            })
            .catch(() => {
                custNameEl.textContent = 'Gagal memuat data. Coba lagi.';
            });

        wrapper.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeNetworkAssignmentModal() {
        const wrapper = document.getElementById('network-modal-wrapper');
        if (wrapper) wrapper.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function renderDistributionOptions() {
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        const distSelect = document.getElementById('na-distribution-select');
        if (!miniPopSelect || !distSelect) return;
        const selectedMiniPopId = miniPopSelect.value;
        const currentDistributionId = distSelect.dataset.currentDistributionId || '';
        const allDistributions = JSON.parse(distSelect.dataset.allOptions || '[]');

        distSelect.innerHTML = '<option value="">— Belum di-assign —</option>';
        allDistributions
            .filter(d => String(d.pop_id) === String(selectedMiniPopId))
            .forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name ? `[${d.code}] ${d.name}` : d.code;
                if (String(currentDistributionId) === String(d.id)) opt.selected = true;
                distSelect.appendChild(opt);
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        if (miniPopSelect) {
            miniPopSelect.addEventListener('change', renderDistributionOptions);
        }
    });
