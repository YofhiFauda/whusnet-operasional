{{-- Skrip daftar pelanggan: navigasi keyboard tabel + seluruh perilaku modal
     Quick Hub / Network Assignment. Pasangan wajib _list_table + _quick_hub_modal. --}}
@include('customers.partials._list_density_script')
<script>
    /* ── Navigasi keyboard tabel ── */
    (function () {
        const table = document.getElementById('customerTable');
        if (!table) return;

        let activeRow = -1;
        // Baris data ditandai [data-customer-row] — baris "tidak ada data" tidak
        // punya penanda ini, jadi tidak ikut jadi target navigasi.
        const rowEls = () => Array.from(table.querySelectorAll('tbody tr[data-customer-row]'));

        function setActiveRow(i) {
            const rows = rowEls();
            if (!rows.length) return;
            activeRow = Math.min(Math.max(0, i), rows.length - 1);
            rows.forEach(r => r.classList.remove('row-active'));
            const el = rows[activeRow];
            el.classList.add('row-active');
            el.scrollIntoView({ block: 'nearest' });
        }

        function anyModalOpen() {
            return !document.getElementById('actions-modal')?.classList.contains('hidden')
                || !document.getElementById('network-modal-wrapper')?.classList.contains('hidden');
        }

        document.addEventListener('keydown', e => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)
                        || document.activeElement.isContentEditable;

            if (e.key === 'Escape') {
                const actionsModal = document.getElementById('actions-modal');
                if (actionsModal && !actionsModal.classList.contains('hidden')) {
                    e.preventDefault();
                    closeActionsModal();
                    return;
                }
                const netModal = document.getElementById('network-modal-wrapper');
                if (netModal && !netModal.classList.contains('hidden')) {
                    e.preventDefault();
                    closeNetworkAssignmentModal();
                    return;
                }
            }

            if (e.altKey && e.key.toLowerCase() === 'n') {
                const addLink = document.querySelector('a[href="/customers/create"]');
                if (addLink) { e.preventDefault(); window.location = addLink.href; }
                return;
            }

            if (typing || anyModalOpen()) return;

            const rows = rowEls();
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault(); setActiveRow(activeRow < 0 ? 0 : activeRow + 1); break;
                case 'ArrowUp':
                    e.preventDefault(); setActiveRow(activeRow < 0 ? 0 : activeRow - 1); break;
                case 'Home':
                    if (!rows.length) return; e.preventDefault(); setActiveRow(0); break;
                case 'End':
                    if (!rows.length) return; e.preventDefault(); setActiveRow(rows.length - 1); break;
                // PageUp/PageDown pindah halaman paginasi. Sudah didokumentasikan
                // di modal Pintasan (layouts/app.blade.php) tapi belum pernah
                // diimplementasikan di halaman ini.
                case 'PageUp': {
                    const prev = document.getElementById('paginatePrev');
                    if (prev && prev.tagName === 'A' && prev.href) { e.preventDefault(); window.location = prev.href; }
                    break;
                }
                case 'PageDown': {
                    const next = document.getElementById('paginateNext');
                    if (next && next.tagName === 'A' && next.href) { e.preventDefault(); window.location = next.href; }
                    break;
                }
                case 'Enter': {
                    if (activeRow < 0) return;
                    e.preventDefault();
                    const actionBtn = rows[activeRow].querySelector('button[onclick^="openActionsModal"]');
                    if (actionBtn) actionBtn.click();
                    break;
                }
            }
        });
    })();

    let selectedCustomerData = {};

    // Cuma kelas STATE yang ditukar, bukan className utuh. Menimpa className
    // penuh (versi lama) ikut menghapus kelas responsif tab (text-[11px]
    // sm:text-xs, sm:px-4, snap-start) — begitu user pindah tab sekali, header
    // tab langsung berantakan di layar kecil.
    const TAB_ACTIVE_CLASSES = ['font-bold', 'border-sky-600', 'text-sky-600', 'dark:text-sky-400', 'bg-white', 'dark:bg-slate-800'];
    const TAB_INACTIVE_CLASSES = ['font-medium', 'border-transparent', 'text-slate-500', 'hover:text-slate-700', 'dark:hover:text-slate-300'];

    function switchActionTab(tabName) {
        const tabs = ['finance', 'technical', 'field', 'profile'];
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            const content = document.getElementById(`tab-content-${t}`);
            const isActive = t === tabName;

            if (btn) {
                btn.classList.remove(...(isActive ? TAB_INACTIVE_CLASSES : TAB_ACTIVE_CLASSES));
                btn.classList.add(...(isActive ? TAB_ACTIVE_CLASSES : TAB_INACTIVE_CLASSES));
            }
            if (content) content.classList.toggle('hidden', !isActive);
        });
    }

    function showModalToast(msg) {
        const toast = document.getElementById('modal-toast');
        const text = document.getElementById('modal-toast-text');
        if (!toast || !text) return;
        text.innerText = msg;
        toast.classList.remove('hidden');
        toast.classList.add('flex');
        setTimeout(() => {
            toast.classList.add('hidden');
            toast.classList.remove('flex');
        }, 3000);
    }

    function toggleWaDropdown() {
        const dropdown = document.getElementById('wa-menu-dropdown');
        if (dropdown) dropdown.classList.toggle('hidden');
    }

    // Tombol WA di bar aksi footer memakai dropdown template yang SUDAH ADA di
    // tab Ringkasan — bukan salinan kedua. Menduplikasi dropdown berarti dua
    // elemen dengan id btn-wa-* yang sama, dan href template cuma keisi di salah
    // satunya. Jadi: pindah tab, scroll ke atas, lalu buka dropdown aslinya.
    function focusWaTemplates() {
        switchActionTab('finance');

        const dropdown = document.getElementById('wa-menu-dropdown');
        const container = document.getElementById('wa-dropdown-container');
        if (!dropdown || !container) return;

        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        dropdown.classList.remove('hidden');
    }

    document.addEventListener('click', function(e) {
        const container = document.getElementById('wa-dropdown-container');
        const dropdown = document.getElementById('wa-menu-dropdown');
        // [data-wa-trigger] dikecualikan: klik tombol WA di footer membuka
        // dropdown lalu event-nya bubble ke sini, dan tanpa pengecualian ini
        // dropdown-nya langsung ketutup lagi di klik yang sama.
        if (container && dropdown && !container.contains(e.target) && !e.target.closest('[data-wa-trigger]')) {
            dropdown.classList.add('hidden');
        }
    });

    function getWaLink(type) {
        if (!selectedCustomerData.phone) return '#';
        let cleanPhone = selectedCustomerData.phone.replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);
        const name = selectedCustomerData.name || 'Pelanggan';
        const code = selectedCustomerData.code || '';
        const price = selectedCustomerData.price || '';
        const dueDate = selectedCustomerData.dueDate || '';

        let msg = '';
        if (type === 'reminder') {
            msg = `Halo Kak ${name} (${code}), menginformasikan tagihan internet Whusnet untuk bulan ini sebesar ${price} dengan jatuh tempo ${dueDate}. Pembayaran dapat dilakukan via Kasir POP atau Transfer. Terima kasih!`;
        } else if (type === 'confirmation') {
            msg = `Halo Kak ${name} (${code}), pembayaran tagihan internet Whusnet sebesar ${price} telah kami terima. Terima kasih telah berlangganan Whusnet!`;
        } else if (type === 'isolir') {
            msg = `Halo Kak ${name} (${code}), menginformasikan layanan internet Whusnet saat ini tertangguh (isolir) karena telah melewati jatuh tempo. Mohon lakukan konfirmasi pembayaran untuk aktivasi kembali.`;
        } else {
            msg = `Halo Kak ${name} (${code}), ada yang bisa kami bantu terkait layanan internet Whusnet?`;
        }
        return 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent(msg);
    }

    function openActionsModal(button) {
        const modal = document.getElementById('actions-modal');
        if (!modal) return;

        selectedCustomerData = {
            id: button.getAttribute('data-id'),
            code: button.getAttribute('data-code'),
            name: button.getAttribute('data-name'),
            nik: button.getAttribute('data-nik') || '-',
            phone: button.getAttribute('data-phone') || '',
            email: button.getAttribute('data-email') || '-',
            status: button.getAttribute('data-status') || '-',
            rawStatus: button.getAttribute('data-raw-status') || 'active',
            pop: button.getAttribute('data-pop') || '-',
            reg: button.getAttribute('data-reg') || '-',
            package: button.getAttribute('data-package') || '-',
            bandwidth: button.getAttribute('data-bandwidth') || '-',
            price: button.getAttribute('data-price') || '-',
            dueDate: button.getAttribute('data-due-date') || '-',
            address: button.getAttribute('data-address') || '-',
            landmark: button.getAttribute('data-landmark') || '-',
            rtRw: button.getAttribute('data-rt-rw') || '-',
            village: button.getAttribute('data-village') || '-',
            district: button.getAttribute('data-district') || '-',
            city: button.getAttribute('data-city') || '-',
            postalCode: button.getAttribute('data-postal-code') || '-',
            lat: button.getAttribute('data-lat') || '',
            lng: button.getAttribute('data-lng') || '',
            completenessPct: button.getAttribute('data-completeness-pct') || '0',
            completenessStatus: button.getAttribute('data-completeness-status') || 'Draft',
            pppoe: button.getAttribute('data-pppoe') || '-',
            ip: button.getAttribute('data-ip') || '-',
            vlan: button.getAttribute('data-vlan') || '-',
            onu: button.getAttribute('data-onu') || '-',
            onuBrand: button.getAttribute('data-onu-brand') || '-',
            router: button.getAttribute('data-router') || '-',
            routerBrand: button.getAttribute('data-router-brand') || '-',
            contract: button.getAttribute('data-contract') || '-',
            distribution: button.getAttribute('data-distribution') || '-',
            // URL aksi dirender server-side (route()) di tombolnya, bukan dirakit
            // di JS dari id. ADHOC-20 langkah 3.
            paymentInfoUrl: button.getAttribute('data-payment-info-url') || '',
            networkUpdateUrl: button.getAttribute('data-network-update-url') || '',
            networkDataUrl: button.getAttribute('data-network-data-url') || '',
            detailUrl: button.getAttribute('data-detail-url') || '',
        };

        const setElemText = (id, txt) => {
            const el = document.getElementById(id);
            if (el) el.innerText = txt;
        };

        // Header & Bindings
        setElemText('actions-modal-title', selectedCustomerData.name);
        setElemText('actions-modal-code', selectedCustomerData.code);
        
        const avatarEl = document.getElementById('actions-modal-avatar');
        if (avatarEl && selectedCustomerData.name) {
            avatarEl.innerText = selectedCustomerData.name.substring(0, 2).toUpperCase();
        }

        const badgeEl = document.getElementById('actions-modal-status-badge');
        if (badgeEl) {
            const statusLabelSpan = badgeEl.querySelector('span:last-child') || badgeEl;
            statusLabelSpan.innerText = selectedCustomerData.status.toUpperCase();
            if (selectedCustomerData.rawStatus === 'active') {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            } else if (selectedCustomerData.rawStatus === 'suspended') {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
            } else {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
            }
        }

        const fullLoc = `${selectedCustomerData.pop} (${selectedCustomerData.village})`;
        setElemText('actions-modal-location-text', fullLoc);

        // WA Links
        const waReminder = document.getElementById('btn-wa-reminder');
        const waConfirmation = document.getElementById('btn-wa-confirmation');
        const waIsolir = document.getElementById('btn-wa-isolir');
        if (waReminder) waReminder.href = getWaLink('reminder');
        if (waConfirmation) waConfirmation.href = getWaLink('confirmation');
        if (waIsolir) waIsolir.href = getWaLink('isolir');

        // Maps Link
        const fieldMapsBtn = document.getElementById('btn-field-launch-maps');
        let mapsUrl = '#';
        if (selectedCustomerData.lat && selectedCustomerData.lng) {
            mapsUrl = `https://www.google.com/maps/search/?api=1&query=${selectedCustomerData.lat},${selectedCustomerData.lng}`;
        } else {
            const queryAddr = `${selectedCustomerData.address}, ${selectedCustomerData.village}, ${selectedCustomerData.district}`;
            mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(queryAddr)}`;
        }
        if (fieldMapsBtn) fieldMapsBtn.href = mapsUrl;

        // Toggle Status Button Text
        const isActiveService = selectedCustomerData.rawStatus === 'active';
        const toggleBtnText = document.getElementById('btn-hub-toggle-status-text');
        if (toggleBtnText) {
            toggleBtnText.innerText = isActiveService ? 'Isolir Layanan' : 'Aktifkan Layanan';
        }
        // Label kembar di bar aksi footer (mobile/tablet) — versinya dipendekkan
        // karena slotnya cuma selebar ikon.
        const footerToggleText = document.getElementById('btn-hub-footer-toggle-text');
        if (footerToggleText) {
            footerToggleText.innerText = isActiveService ? 'Isolir' : 'Aktifkan';
        }

        // Pre-fill Static Data
        setElemText('hub-fin-package', selectedCustomerData.package);
        setElemText('hub-fin-price', selectedCustomerData.price);
        setElemText('hub-fin-due-date', selectedCustomerData.dueDate);

        setElemText('hub-tech-pppoe', selectedCustomerData.pppoe);
        setElemText('hub-tech-ip', selectedCustomerData.ip);
        setElemText('hub-tech-vlan', selectedCustomerData.vlan);
        setElemText('hub-tech-bandwidth', selectedCustomerData.bandwidth);
        setElemText('hub-tech-onu', selectedCustomerData.onu);
        setElemText('hub-tech-router', selectedCustomerData.router);
        setElemText('hub-tech-distribution', selectedCustomerData.distribution);
        setElemText('hub-tech-contract', selectedCustomerData.contract);

        setElemText('hub-field-address-full', `${selectedCustomerData.address !== '-' ? selectedCustomerData.address + ', ' : ''}Kel. ${selectedCustomerData.village}, Kec. ${selectedCustomerData.district}`);
        setElemText('hub-field-village', selectedCustomerData.village);
        setElemText('hub-field-district', selectedCustomerData.district);
        setElemText('hub-field-city', selectedCustomerData.city);
        setElemText('hub-field-postal-code', selectedCustomerData.postalCode);
        setElemText('hub-field-coords', (selectedCustomerData.lat && selectedCustomerData.lng) ? `${selectedCustomerData.lat}, ${selectedCustomerData.lng}` : 'Belum Diatur');

        setElemText('hub-prof-fullname', selectedCustomerData.name);
        setElemText('hub-prof-nik', selectedCustomerData.nik);
        setElemText('hub-prof-cid', selectedCustomerData.code);
        setElemText('hub-prof-phone', selectedCustomerData.phone || '-');
        setElemText('hub-prof-email', selectedCustomerData.email || '-');
        setElemText('hub-prof-reg', selectedCustomerData.reg);
        setElemText('hub-prof-completeness-status', selectedCustomerData.completenessStatus);
        setElemText('hub-prof-completeness-bar-text', selectedCustomerData.completenessPct + '%');
        const compBar = document.getElementById('hub-prof-completeness-bar');
        if (compBar) compBar.style.width = selectedCustomerData.completenessPct + '%';

        switchActionTab('finance');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        // Set initial state saat data tagihan dimuat
        togglePaymentFormState(false, 'Memuat data tagihan pelanggan...');
        // Struk & berkas ikut direset — kalau tidak, data pelanggan sebelumnya
        // masih nempel selama fetch berjalan.
        setLatestReceipt(null);
        renderHubDocuments(null, null);

        // Fetch Live Payment Info
        const loadingEl = document.getElementById('modal-hub-loading');
        if (loadingEl) loadingEl.classList.remove('hidden');

        // URL dari atribut data-* yang dirender route() di tombolnya — sama seperti
        // aksi tulis, biar tidak ada definisi path yang hidup dua kali.
        fetch(selectedCustomerData.paymentInfoUrl)
            .then(res => res.json())
            .then(data => {
                if (loadingEl) loadingEl.classList.add('hidden');
                const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);

                if (data.invoice_id) {
                    setElemText('hub-invoice-period-badge', `Periode: ${data.billing_period || '-'}`);
                    setElemText('hub-fin-due-date', data.due_date || selectedCustomerData.dueDate);
                    setElemText('hub-fin-arrears', data.total_piutang > 0 ? formatRp(data.total_piutang) : 'Rp 0');
                    setElemText('hub-fin-total-pay', formatRp(data.remaining_amount));

                    // URL dari server (payment_store_url), BUKAN dirakit di sini.
                    // Tanpa URL-nya form dibiarkan mati — lebih baik tombol tidak
                    // jalan daripada POST nyasar ke URL halaman daftar.
                    const payForm = document.getElementById('payment-form');
                    if (payForm && data.payment_store_url) {
                        payForm.action = data.payment_store_url;
                    }
                    
                    // Enable form jika pelanggan punya tagihan aktif
                    togglePaymentFormState(true);

                    const amountInput = document.getElementById('payment_amount');
                    if (amountInput) {
                        // Nilai dari AJAX ikut dimasking supaya sisa tagihan
                        // tampil 150.000, bukan 150000 di sebelah field yang
                        // seluruh isinya berformat ribuan.
                        amountInput.value = window.Rupiah
                            ? window.Rupiah.formatDariServer(String(data.remaining_amount))
                            : data.remaining_amount;
                    }
                } else {
                    setElemText('hub-invoice-period-badge', 'Tidak Ada Tagihan Aktif');
                    setElemText('hub-fin-total-pay', 'Rp 0');
                    setElemText('hub-fin-arrears', 'Rp 0');

                    // Disable form jika belum ada tagihan
                    togglePaymentFormState(false, 'Pelanggan ini belum memiliki tagihan aktif untuk dibayar.');
                }

                const tbody = document.getElementById('hub-recent-payments-body');
                if (tbody) {
                    if (data.recent_payments && data.recent_payments.length > 0) {
                        tbody.innerHTML = data.recent_payments.map(p => `
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="py-2.5 px-3 font-mono text-slate-600 dark:text-slate-300">${p.date}</td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-slate-800 dark:text-white">${p.invoice_number}</td>
                                <td class="py-2.5 px-3"><span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded text-[10px] font-semibold border border-slate-200 dark:border-slate-700">${p.method}</span></td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">${formatRp(p.amount)}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <a href="${p.receipt_url}" target="_blank" class="inline-flex items-center justify-center p-1 rounded text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors" title="Cetak Struk Pembayaran Ini">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                                    </a>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-400">Belum ada riwayat pembayaran.</td></tr>';
                    }
                }

                // Struk hanya bisa dicetak kalau pelanggan sudah pernah bayar —
                // recent_payments sudah urut terbaru dari server.
                setLatestReceipt(data.recent_payments && data.recent_payments.length > 0
                    ? data.recent_payments[0].receipt_url
                    : null);

                renderHubDocuments(data.documents, data.documents_upload_url);

                if (data.technical) {
                    setElemText('hub-tech-pppoe', data.technical.pppoe_username || selectedCustomerData.pppoe);
                    setElemText('hub-tech-ip', data.technical.ip_address || selectedCustomerData.ip);
                    setElemText('hub-tech-onu', data.technical.onu_sn || selectedCustomerData.onu);
                    setElemText('hub-tech-router', data.technical.router_sn || selectedCustomerData.router);
                    setElemText('hub-tech-distribution', data.technical.distribution || selectedCustomerData.distribution);
                }
            })
            .catch(err => {
                console.error(err);
                if (loadingEl) loadingEl.classList.add('hidden');
                togglePaymentFormState(false, 'Gagal memuat informasi tagihan.');
                setLatestReceipt(null);
            });
    }

    // URL struk pembayaran terakhir pelanggan yang lagi dibuka di Modal Hub.
    // Direset tiap modal dibuka supaya tidak mencetak struk pelanggan sebelumnya.
    let latestReceiptUrl = null;

    function setLatestReceipt(url) {
        latestReceiptUrl = url || null;
        const btns = document.querySelectorAll('.btn-print-receipt-action, #btn-print-receipt, #btn-hub-footer-print-receipt');
        btns.forEach(btn => {
            btn.disabled = !latestReceiptUrl;
            btn.title = latestReceiptUrl
                ? 'Cetak struk pembayaran terakhir'
                : 'Belum ada pembayaran yang bisa dicetak';
        });
    }

    function printLatestReceipt() {
        if (!latestReceiptUrl) {
            showModalToast('Belum ada pembayaran yang bisa dicetak.');

            return;
        }
        window.open(latestReceiptUrl, '_blank');
    }

    function renderHubDocuments(documents, uploadUrl) {
        document.querySelectorAll('.hub-document-form').forEach(form => {
            form.action = uploadUrl || '';
            form.reset();
        });

        ['rumah'].forEach(type => {
            const doc = documents ? documents[type] : null;
            const badge = document.getElementById(`hub-doc-${type}-badge`);
            const link = document.getElementById(`hub-doc-${type}-link`);
            const empty = document.getElementById(`hub-doc-${type}-empty`);

            if (doc && doc.exists) {
                if (badge) {
                    badge.textContent = 'Ada';
                    badge.className = 'text-[10px] px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-semibold shrink-0';
                }
                if (link) {
                    link.href = doc.url;
                    link.classList.remove('hidden');
                    link.classList.add('flex');
                }
                if (empty) empty.classList.add('hidden');
            } else {
                if (badge) {
                    badge.textContent = 'Belum';
                    badge.className = 'text-[10px] px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-semibold shrink-0';
                }
                if (link) {
                    link.href = '#';
                    link.classList.add('hidden');
                    link.classList.remove('flex');
                }
                if (empty) empty.classList.remove('hidden');
            }
        });
    }

    function togglePaymentFormState(enabled, message = '') {
        const payForm = document.getElementById('payment-form');
        if (!payForm) return;

        const amountInput = document.getElementById('payment_amount');
        const methodSelect = payForm.querySelector('select[name="payment_method"]');
        const dateInput = document.getElementById('payment_date');
        const submitBtn = payForm.querySelector('button[type="submit"]');
        const noticeEl = document.getElementById('payment-form-notice');
        const noticeText = document.getElementById('payment-form-notice-text');

        if (enabled) {
            if (amountInput) {
                amountInput.disabled = false;
                amountInput.classList.remove('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (methodSelect) {
                methodSelect.disabled = false;
                methodSelect.classList.remove('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (dateInput) {
                dateInput.disabled = false;
                dateInput.classList.remove('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }
            if (noticeEl) noticeEl.classList.add('hidden');
        } else {
            if (amountInput) {
                amountInput.disabled = true;
                amountInput.value = '';
                amountInput.classList.add('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (methodSelect) {
                methodSelect.disabled = true;
                methodSelect.classList.add('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (dateInput) {
                dateInput.disabled = true;
                dateInput.classList.add('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }
            if (noticeEl) {
                if (noticeText && message) noticeText.innerText = message;
                noticeEl.classList.remove('hidden');
            }
        }
    }

    function closeActionsModal() {
        const modal = document.getElementById('actions-modal');
        if (modal) modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        // Dropdown template WA ikut ditutup — kalau tidak, dia masih terbuka
        // waktu modal dibuka lagi untuk pelanggan lain.
        const waDropdown = document.getElementById('wa-menu-dropdown');
        if (waDropdown) waDropdown.classList.add('hidden');
    }

    function copyTechInfo() {
        const textToCopy = `[DATA TEKNIS PELANGGAN]
Nama: ${selectedCustomerData.name} (${selectedCustomerData.code})
POP: ${selectedCustomerData.pop}
PPPoE: ${selectedCustomerData.pppoe}
IP: ${selectedCustomerData.ip}
ONU SN: ${selectedCustomerData.onu}
Router SN: ${selectedCustomerData.router}
ODP/Distribusi: ${selectedCustomerData.distribution}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            showModalToast('Kredensial teknis berhasil disalin!');
        });
    }

    function triggerHubToggleConnection() {
        const isCurrentActive = selectedCustomerData.rawStatus === 'active';
        const actionText = isCurrentActive ? 'mengisolir / menonaktifkan' : 'mengaktifkan kembali';

        if (window.confirmAction) {
            if (confirm(`Apakah Anda yakin ingin ${actionText} koneksi internet untuk ${selectedCustomerData.name}?`)) {
                showModalToast(`Status layanan ${selectedCustomerData.name} berhasil diubah.`);
            }
        } else if (confirm(`Apakah Anda yakin ingin ${actionText} koneksi internet untuk ${selectedCustomerData.name}?`)) {
            showModalToast(`Status layanan ${selectedCustomerData.name} berhasil diubah.`);
        }
    }

    function triggerDetail() {
        if (!selectedCustomerData.detailUrl) return;
        window.location.href = selectedCustomerData.detailUrl;
    }

    // Jembatan Modal Hub → Modal Atur Jaringan. Dua modal tidak boleh tampil
    // bersamaan (keduanya z-50 + backdrop), jadi hub ditutup dulu baru network dibuka.
    function triggerNetworkAssignmentFromHub() {
        if (!selectedCustomerData || !selectedCustomerData.networkUpdateUrl) return;
        const updateUrl = selectedCustomerData.networkUpdateUrl;
        const dataUrl = selectedCustomerData.networkDataUrl;
        closeActionsModal();
        openNetworkAssignmentModal(updateUrl, dataUrl);
    }

    function triggerEdit() {
        window.location.href = '/customers/' + selectedCustomerData.id + '/edit';
    }

    function triggerTerminate() {
        closeActionsModal();
        if (!selectedCustomerData.detailUrl) return;
        if (confirm(`Apakah Anda yakin ingin melakukan PEMUTUSAN / TERMINASI untuk ${selectedCustomerData.name}?`)) {
            window.location.href = selectedCustomerData.detailUrl + '#terminate';
        }
    }

@include('customers.partials._network_assignment_js')
</script>
