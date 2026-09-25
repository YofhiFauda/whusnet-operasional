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
                || !document.getElementById('network-modal-wrapper')?.classList.contains('hidden')
                || !document.getElementById('package-change-modal-wrapper')?.classList.contains('hidden');
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
                const pkgModal = document.getElementById('package-change-modal-wrapper');
                if (pkgModal && !pkgModal.classList.contains('hidden')) {
                    e.preventDefault();
                    closePackageChangeModal();
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
    let hubCustomerBalance = 0;
    let hubRemainingAmount = 0;
    let hubNextInstallment = 1;
    let hubPaymentStoreUrl = null;

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
        }, 3500);
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

    /* ── Helper Form Pembayaran Modal Hub (Setara quick-payment-modal) ── */
    function hubTogglePaymentMethodFields() {
        const methodSelect = document.getElementById('payment_method');
        if (!methodSelect) return;
        const method = methodSelect.value;
        const transferFields = document.getElementById('hub-pay-transfer-fields');
        const collectorFields = document.getElementById('hub-pay-collector-fields');
        const bankAccount = document.getElementById('hub_bank_account_id');
        const senderFields = document.getElementById('hub-pay-sender-fields');
        const senderName = document.getElementById('hub_sender_name');
        const collector = document.getElementById('hub_collected_by');

        const isTransfer = method === 'transfer';
        const isKolektor = method === 'kolektor';
        const isLainnya = method === 'lainnya';

        if (transferFields) transferFields.classList.toggle('hidden', !isTransfer);
        if (bankAccount) {
            bankAccount.required = isTransfer;
            // Form ini dikirim via FormData(form) — field tersembunyi ikut
            // terkirim. `disabled` mencegah id rekening/nama pengirim sisa
            // pilihan sebelumnya ikut tersimpan saat metode diganti.
            bankAccount.disabled = !isTransfer;
        }

        const acceptsSender = isTransfer || isKolektor;
        if (senderFields) senderFields.classList.toggle('hidden', !acceptsSender);
        if (senderName) senderName.disabled = !acceptsSender;

        if (collectorFields) collectorFields.classList.toggle('hidden', !isKolektor);
        if (collector) collector.required = isKolektor;

        // Metode Lainnya wajib menjelaskan metode apa persisnya — reuse
        // field Catatan yang sudah ada (PaymentMethod::requiresDescription()).
        const note = document.getElementById('hub_note');
        const noteLabel = document.getElementById('hub_note_label');
        if (note) {
            note.required = isLainnya;
            note.placeholder = isLainnya ? 'Jelaskan metode pembayaran (mis. OVO, Dana, GoPay)...' : 'Catatan pembayaran...';
        }
        if (noteLabel) noteLabel.textContent = isLainnya ? 'Keterangan Metode (wajib)' : 'Catatan';

        hubRefreshInstallmentHint();
    }

    function hubPopulateCollectors(collectors) {
        const select = document.getElementById('hub_collected_by');
        if (!select) return;
        select.innerHTML = '<option value="">Pilih kolektor...</option>';
        (collectors || []).forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            select.appendChild(opt);
        });
    }

    function hubPopulateBankAccounts(bankAccounts) {
        const select = document.getElementById('hub_bank_account_id');
        if (!select) return;
        select.innerHTML = '<option value="">Pilih rekening...</option>';
        (bankAccounts || []).forEach((account) => {
            const opt = document.createElement('option');
            opt.value = account.id;
            opt.textContent = account.name;
            select.appendChild(opt);
        });
    }

    function hubFormatRupiah(num) {
        return 'Rp ' + Math.round(num).toLocaleString('id-ID');
    }

    function hubApplyCustomerBalance(balance) {
        hubCustomerBalance = parseFloat(balance) || 0;
        const block = document.getElementById('hub-pay-balance-block');
        const availText = document.getElementById('hub-pay-balance-available');
        const useBalanceCheck = document.getElementById('hub-pay-use-balance');
        const useBalanceWrap = document.getElementById('hub-pay-use-balance-wrap');
        const useBalanceInput = document.getElementById('hub-pay-use-balance-amount');

        if (block) block.classList.toggle('hidden', hubCustomerBalance <= 0);
        if (availText) availText.textContent = hubFormatRupiah(hubCustomerBalance);
        if (useBalanceCheck) useBalanceCheck.checked = false;
        if (useBalanceWrap) useBalanceWrap.classList.add('hidden');
        if (useBalanceInput) useBalanceInput.value = '';
    }

    function hubUseBalanceAmount() {
        const check = document.getElementById('hub-pay-use-balance');
        if (!check || !check.checked) return 0;

        const input = document.getElementById('hub-pay-use-balance-amount');
        if (!input) return 0;
        const raw = input.value;
        const nilai = window.Rupiah ? window.Rupiah.angka(raw) : parseFloat(raw);

        return isNaN(nilai) ? 0 : Math.min(nilai, hubCustomerBalance);
    }

    function hubApplyBalanceToAmount() {
        const useBal = hubUseBalanceAmount();
        hubSetNominal(Math.max(0, hubRemainingAmount - useBal));
        hubRefreshInstallmentHint();
    }

    function hubNominal() {
        const raw = document.getElementById('payment_amount')?.value || '';
        return window.Rupiah ? window.Rupiah.angka(raw) : parseFloat(raw);
    }

    function hubNominalPolos() {
        const raw = document.getElementById('payment_amount')?.value || '';
        return window.Rupiah ? window.Rupiah.polos(raw) : raw;
    }

    function hubSetNominal(nilai) {
        const input = document.getElementById('payment_amount');
        if (!input) return;
        const teks = String(nilai ?? '');
        input.value = window.Rupiah ? window.Rupiah.formatDariServer(teks) : teks;
    }

    function hubRefreshInstallmentHint() {
        const installmentHint = document.getElementById('hub-pay-installment-hint');
        const settleHint = document.getElementById('hub-pay-settle-hint');
        const overpayHint = document.getElementById('hub-pay-overpay-hint');

        if (installmentHint) installmentHint.classList.add('hidden');
        if (settleHint) settleHint.classList.add('hidden');
        if (overpayHint) overpayHint.classList.add('hidden');

        const totalAmount = hubNominal() + hubUseBalanceAmount();

        if (isNaN(totalAmount) || totalAmount <= 0) {
            return;
        }

        if (totalAmount > hubRemainingAmount) {
            const overpay = Math.round((totalAmount - hubRemainingAmount) * 100) / 100;
            if (overpayHint) {
                overpayHint.textContent = hubFormatRupiah(hubRemainingAmount) + ' diterapkan ke tagihan (Lunas), ' +
                    hubFormatRupiah(overpay) + ' tercatat sebagai lebih bayar.';
                overpayHint.classList.remove('hidden');
            }
            return;
        }

        const leftover = Math.round((hubRemainingAmount - totalAmount) * 100) / 100;

        if (leftover > 0) {
            if (installmentHint) {
                installmentHint.textContent = 'Tercatat sebagai Cicilan Ke-' + hubNextInstallment +
                    '. Tagihan jadi berstatus Sebagian, sisa setelah ini: ' + hubFormatRupiah(leftover) + '.';
                installmentHint.classList.remove('hidden');
            }
        } else {
            if (settleHint) {
                settleHint.textContent = 'Pembayaran ini melunasi tagihan. Status jadi Lunas.';
                settleHint.classList.remove('hidden');
            }
        }
    }

    function updateHubStatusUi(rawStatus) {
        selectedCustomerData.rawStatus = rawStatus;
        const isActive = rawStatus === 'active';
        const isSuspended = rawStatus === 'suspended';

        // Update Modal Status Badge
        const badgeEl = document.getElementById('actions-modal-status-badge');
        if (badgeEl) {
            const statusLabelSpan = badgeEl.querySelector('span:last-child') || badgeEl;
            statusLabelSpan.innerText = isSuspended ? 'ISOLIR' : (isActive ? 'ACTIVE' : rawStatus.toUpperCase());
            if (isActive) {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            } else if (isSuspended) {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
            } else {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
            }
        }

        // Update Button Top
        const toggleBtn = document.getElementById('btn-hub-toggle-status');
        const toggleBtnText = document.getElementById('btn-hub-toggle-status-text');
        if (toggleBtn && toggleBtnText) {
            toggleBtnText.innerText = isActive ? 'Isolir Layanan' : 'Aktifkan Layanan';
            if (isActive) {
                toggleBtn.className = 'flex-1 min-w-[140px] h-11 px-2.5 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-amber-100 transition-all btn-interactive touch-target';
            } else {
                toggleBtn.className = 'flex-1 min-w-[140px] h-11 px-2.5 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-emerald-100 transition-all btn-interactive touch-target';
            }
        }

        // Update Footer Toggle Button
        const footerToggleText = document.getElementById('btn-hub-footer-toggle-text');
        if (footerToggleText) {
            footerToggleText.innerText = isActive ? 'Isolir' : 'Aktifkan';
        }

        // WA template update
        const waIsolir = document.getElementById('btn-wa-isolir');
        if (waIsolir) waIsolir.href = getWaLink('isolir');
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
            vlan: button.getAttribute('data-vlan') || '-',
            onu: button.getAttribute('data-onu') || '-',
            onuBrand: button.getAttribute('data-onu-brand') || '-',
            router: button.getAttribute('data-router') || '-',
            routerBrand: button.getAttribute('data-router-brand') || '-',
            contract: button.getAttribute('data-contract') || '-',
            distribution: button.getAttribute('data-distribution') || '-',
            paymentInfoUrl: button.getAttribute('data-payment-info-url') || '',
            networkUpdateUrl: button.getAttribute('data-network-update-url') || '',
            networkDataUrl: button.getAttribute('data-network-data-url') || '',
            detailUrl: button.getAttribute('data-detail-url') || '',
            packageUpdateUrl: button.getAttribute('data-package-update-url') || '',
            currentPackageId: button.getAttribute('data-current-package-id') || '',
            toggleSuspendUrl: button.getAttribute('data-toggle-suspend-url') || '',
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

        updateHubStatusUi(selectedCustomerData.rawStatus);

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

        // Pre-fill Static Data
        setElemText('hub-fin-package', selectedCustomerData.package);
        setElemText('hub-fin-price', selectedCustomerData.price);
        setElemText('hub-fin-due-date', selectedCustomerData.dueDate);

        setElemText('hub-tech-pppoe', selectedCustomerData.pppoe);
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

        // Reset Payment Form Inputs
        const payMethod = document.getElementById('payment_method');
        if (payMethod) payMethod.value = 'cash';
        const senderInput = document.getElementById('hub_sender_name');
        if (senderInput) senderInput.value = '';
        const noteInput = document.getElementById('hub_note');
        if (noteInput) noteInput.value = '';
        const allocInput = document.getElementById('hub_allocation');
        if (allocInput) allocInput.value = 'Tagihan Bulanan';
        const errorBox = document.getElementById('hub-pay-error');
        if (errorBox) errorBox.classList.add('hidden');
        
        hubApplyCustomerBalance(0);
        hubPopulateCollectors([]);
        hubPopulateBankAccounts([]);
        hubTogglePaymentMethodFields();

        switchActionTab('finance');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        // Set initial state saat data tagihan dimuat
        togglePaymentFormState(false, 'Memuat data tagihan pelanggan...');
        setLatestReceipt(null);
        renderHubDocuments(null, null);

        // Fetch Live Payment Info
        loadHubPaymentInfo();
    }

    function loadHubPaymentInfo() {
        if (!selectedCustomerData.paymentInfoUrl) return;

        const loadingEl = document.getElementById('modal-hub-loading');
        if (loadingEl) loadingEl.classList.remove('hidden');

        fetch(selectedCustomerData.paymentInfoUrl)
            .then(res => res.json())
            .then(data => {
                if (loadingEl) loadingEl.classList.add('hidden');
                const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);

                hubRemainingAmount = parseFloat(data.remaining_amount) || 0;
                hubPaymentStoreUrl = data.payment_store_url || null;

                if (data.invoice_id) {
                    const elPeriod = document.getElementById('hub-invoice-period-badge');
                    if (elPeriod) elPeriod.innerText = `Periode: ${data.billing_period || '-'}`;
                    const elDue = document.getElementById('hub-fin-due-date');
                    if (elDue) elDue.innerText = data.due_date || selectedCustomerData.dueDate;
                    const elArrears = document.getElementById('hub-fin-arrears');
                    if (elArrears) elArrears.innerText = data.total_piutang > 0 ? formatRp(data.total_piutang) : 'Rp 0';
                    const elTotalPay = document.getElementById('hub-fin-total-pay');
                    if (elTotalPay) elTotalPay.innerText = formatRp(hubRemainingAmount);

                    const payForm = document.getElementById('payment-form');
                    if (payForm && data.payment_store_url) {
                        payForm.action = data.payment_store_url;
                    }
                    
                    togglePaymentFormState(true);
                    hubSetNominal(hubRemainingAmount);

                    hubApplyCustomerBalance(data.customer_balance);
                    hubPopulateCollectors(data.available_collectors);
                    hubPopulateBankAccounts(data.available_bank_accounts);

                    const validPayments = Array.isArray(data.recent_payments)
                        ? data.recent_payments
                        : [];
                    hubNextInstallment = validPayments.length + 1;
                    hubRefreshInstallmentHint();
                } else {
                    const elPeriod = document.getElementById('hub-invoice-period-badge');
                    if (elPeriod) elPeriod.innerText = 'Tidak Ada Tagihan Aktif';
                    const elTotalPay = document.getElementById('hub-fin-total-pay');
                    if (elTotalPay) elTotalPay.innerText = 'Rp 0';
                    const elArrears = document.getElementById('hub-fin-arrears');
                    if (elArrears) elArrears.innerText = 'Rp 0';

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

                setLatestReceipt(data.recent_payments && data.recent_payments.length > 0
                    ? data.recent_payments[0].receipt_url
                    : null);

                renderHubDocuments(data.documents, data.documents_upload_url);

                if (data.technical) {
                    const elPppoe = document.getElementById('hub-tech-pppoe');
                    if (elPppoe) elPppoe.innerText = data.technical.pppoe_username || selectedCustomerData.pppoe;
                    const elOnu = document.getElementById('hub-tech-onu');
                    if (elOnu) elOnu.innerText = data.technical.onu_sn || selectedCustomerData.onu;
                    const elRouter = document.getElementById('hub-tech-router');
                    if (elRouter) elRouter.innerText = data.technical.router_sn || selectedCustomerData.router;
                    const elDist = document.getElementById('hub-tech-distribution');
                    if (elDist) elDist.innerText = data.technical.distribution || selectedCustomerData.distribution;
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
        const methodSelect = document.getElementById('payment_method');
        const dateInput = document.getElementById('payment_date');
        const submitBtn = document.getElementById('hub-pay-submit-btn');
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
        const waDropdown = document.getElementById('wa-menu-dropdown');
        if (waDropdown) waDropdown.classList.add('hidden');
    }

    function copyTechInfo() {
        const textToCopy = `[DATA TEKNIS PELANGGAN]
Nama: ${selectedCustomerData.name} (${selectedCustomerData.code})
POP: ${selectedCustomerData.pop}
PPPoE: ${selectedCustomerData.pppoe}
ONU SN: ${selectedCustomerData.onu}
Router SN: ${selectedCustomerData.router}
ODP/Distribusi: ${selectedCustomerData.distribution}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            showModalToast('Kredensial teknis berhasil disalin!');
        });
    }

    /* ── Live Listeners Form Pembayaran ── */
    document.getElementById('payment_amount')?.addEventListener('input', hubRefreshInstallmentHint);

    document.getElementById('hub-pay-use-balance')?.addEventListener('change', function(e) {
        const wrap = document.getElementById('hub-pay-use-balance-wrap');
        if (wrap) wrap.classList.toggle('hidden', !e.target.checked);

        const useBalInput = document.getElementById('hub-pay-use-balance-amount');
        if (e.target.checked) {
            const max = Math.min(hubCustomerBalance, hubRemainingAmount);
            if (useBalInput) {
                useBalInput.value = window.Rupiah ? window.Rupiah.formatDariServer(String(max)) : String(max);
            }
        } else {
            if (useBalInput) useBalInput.value = '';
        }
        hubApplyBalanceToAmount();
    });

    document.getElementById('hub-pay-use-balance-amount')?.addEventListener('input', hubApplyBalanceToAmount);

    /* ── Submit Handler Pembayaran Cepat via AJAX ── */
    document.getElementById('payment-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!hubPaymentStoreUrl) return;

        const submitBtn = document.getElementById('hub-pay-submit-btn');
        const spinner = document.getElementById('hub-pay-spinner');
        const submitIcon = document.getElementById('hub-pay-submit-icon');
        const submitText = document.getElementById('hub-pay-submit-text');
        const errorBox = document.getElementById('hub-pay-error');

        if (errorBox) errorBox.classList.add('hidden');

        const nominal = hubNominal();
        const useBalance = hubUseBalanceAmount();
        if ((isNaN(nominal) || nominal < 1) && useBalance < 1) {
            if (errorBox) {
                errorBox.textContent = 'Nominal pembayaran wajib diisi minimal Rp 1 atau menggunakan Saldo Pelanggan.';
                errorBox.classList.remove('hidden');
            }
            return;
        }

        const methodSelect = document.getElementById('payment_method');
        if (methodSelect && methodSelect.value === 'lainnya' && !document.getElementById('hub_note').value.trim()) {
            if (errorBox) {
                errorBox.textContent = 'Jelaskan metode pembayarannya di kolom Catatan untuk metode Lainnya.';
                errorBox.classList.remove('hidden');
            }
            document.getElementById('hub_note').focus();
            return;
        }

        // Loading UI
        if (submitBtn) submitBtn.disabled = true;
        if (spinner) spinner.classList.remove('hidden');
        if (submitIcon) submitIcon.classList.add('hidden');
        if (submitText) submitText.innerText = 'Menyimpan...';

        const csrfToken = document.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
        const formData = new FormData(this);
        formData.set('amount', String(hubNominalPolos()));
        if (useBalance > 0) {
            formData.set('use_balance_amount', String(useBalance));
        }

        fetch(hubPaymentStoreUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const message = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal mencatat pembayaran.');
                throw new Error(message);
            }
            return data;
        })
        .then(data => {
            showModalToast(data.message || 'Pembayaran berhasil dicatat!');
            loadHubPaymentInfo();
        })
        .catch(err => {
            if (errorBox) {
                errorBox.textContent = err.message || 'Terjadi kesalahan saat mencatat pembayaran.';
                errorBox.classList.remove('hidden');
            }
        })
        .finally(() => {
            if (submitBtn) submitBtn.disabled = false;
            if (spinner) spinner.classList.add('hidden');
            if (submitIcon) submitIcon.classList.remove('hidden');
            if (submitText) submitText.innerText = 'Simpan Pembayaran';
        });
    });

    /* ── Fungsi Nyata Suspend / Isolir Layanan Pelanggan ── */
    function triggerHubToggleConnection() {
        if (!selectedCustomerData.id) return;
        const isCurrentActive = selectedCustomerData.rawStatus === 'active';
        const isSuspended = selectedCustomerData.rawStatus === 'suspended';

        if (!isCurrentActive && !isSuspended) {
            showModalToast('Layanan hanya dapat diisolir atau diaktifkan untuk pelanggan aktif atau terisolir.');
            return;
        }

        const actionText = isCurrentActive ? 'mengisolir / menonaktifkan' : 'mengaktifkan kembali';

        // window.Dialog — modal konfirmasi standar app (lihat layouts/app.blade.php).
        // Dulu pakai confirm() bawaan browser: gak konsisten sama modal lain di
        // app ini, dan di beberapa kombinasi browser/extension bisa ke-suppress
        // diam-diam sehingga tombol kelihatan "gak ngapa-ngapain".
        window.Dialog.show({
            title: isCurrentActive ? 'Isolir Layanan' : 'Aktifkan Kembali Layanan',
            message: `Apakah Anda yakin ingin ${actionText} koneksi internet untuk pelanggan "${selectedCustomerData.name}"?`,
            icon: 'warning',
            buttons: [
                { text: 'Batal', type: 'secondary', onClick: () => window.Dialog.close() },
                {
                    text: isCurrentActive ? 'Ya, Isolir' : 'Ya, Aktifkan',
                    type: isCurrentActive ? 'danger' : 'primary',
                    onClick: () => {
                        window.Dialog.close();
                        submitHubToggleConnection(isCurrentActive);
                    },
                },
            ],
        });
    }

    function submitHubToggleConnection(isCurrentActive) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
        const btnTop = document.getElementById('btn-hub-toggle-status');
        const btnText = document.getElementById('btn-hub-toggle-status-text');

        if (btnTop) btnTop.disabled = true;
        if (btnText) btnText.innerText = 'Memproses...';

        {{-- Target POST dirender server-side (data-toggle-suspend-url di
             tombol Aksi) — jangan rakit URL-nya di sini. ADHOC-20 langkah 3. --}}
        fetch(selectedCustomerData.toggleSuspendUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                _token: csrfToken,
                note: isCurrentActive ? 'Isolir Layanan dari List Pelanggan' : 'Aktivasi Kembali Layanan dari List Pelanggan'
            })
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'Gagal mengubah status layanan.');
            }
            return data;
        })
        .then(data => {
            updateHubStatusUi(data.raw_status);
            showModalToast(data.message || 'Status layanan berhasil diperbarui.');

            // Update status badge di tabel list utama secara realtime jika elemennya ada
            const tableRows = document.querySelectorAll(`button[data-id="${selectedCustomerData.id}"]`);
            tableRows.forEach(btn => {
                btn.setAttribute('data-raw-status', data.raw_status);
                btn.setAttribute('data-status', data.status_label);
                const tr = btn.closest('tr');
                if (tr) {
                    const statusTd = tr.querySelector('td:nth-last-child(2)');
                    if (statusTd) {
                        const isNowActive = data.raw_status === 'active';
                        const isNowSuspended = data.raw_status === 'suspended';
                        statusTd.innerHTML = `
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${isNowActive ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : (isNowSuspended ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800')}">
                                <span class="w-1.5 h-1.5 rounded-full ${isNowActive ? 'bg-emerald-500 animate-pulse-glow' : (isNowSuspended ? 'bg-amber-500' : 'bg-rose-500')} mr-1.5"></span>
                                <span>${data.status_label}</span>
                            </span>
                        `;
                    }
                }
            });
        })
        .catch(err => {
            console.error(err);
            showModalToast(err.message || 'Terjadi kesalahan sistem.');
            updateHubStatusUi(selectedCustomerData.rawStatus);
        })
        .finally(() => {
            if (btnTop) btnTop.disabled = false;
        });
    }

    function triggerDetail() {
        if (!selectedCustomerData.detailUrl) return;
        window.location.href = selectedCustomerData.detailUrl;
    }

    // Jembatan Modal Hub → Modal Atur Jaringan.
    function triggerNetworkAssignmentFromHub() {
        if (!selectedCustomerData || !selectedCustomerData.networkUpdateUrl) return;
        const updateUrl = selectedCustomerData.networkUpdateUrl;
        const dataUrl = selectedCustomerData.networkDataUrl;
        closeActionsModal();
        openNetworkAssignmentModal(updateUrl, dataUrl);
    }

    // Jembatan Modal Hub → Modal Ganti Paket. Data (paket/harga aktif) sudah
    // ada di selectedCustomerData (dari data-* baris tabel), jadi tidak perlu
    // fetch ulang seperti Atur Jaringan.
    function triggerPackageChangeFromHub() {
        if (!selectedCustomerData || !selectedCustomerData.packageUpdateUrl) return;
        closeActionsModal();
        openPackageChangeModal(
            selectedCustomerData.packageUpdateUrl,
            selectedCustomerData.currentPackageId,
            selectedCustomerData.package,
            selectedCustomerData.price,
            selectedCustomerData.name
        );
    }

    /* ── Modal Ganti Paket Internet ──
       @param {string} updateUrl PUT target (customers.package.update), dirender
       server-side lewat data-package-update-url di tombol pemanggil. JANGAN
       merakit path dari id pelanggan di sini — ADHOC-20 langkah 3. */
    function openPackageChangeModal(updateUrl, currentPackageId, currentPackageLabel, currentPriceLabel, customerName) {
        const wrapper = document.getElementById('package-change-modal-wrapper');
        const form = document.getElementById('package-change-form');
        const select = document.getElementById('pkg-select');
        const nameEl = document.getElementById('pkg-customer-name');
        const currentPackageEl = document.getElementById('pkg-current-package');
        const currentPriceEl = document.getElementById('pkg-current-price');

        if (!wrapper || !form) return;
        if (!updateUrl) {
            if (window.Toast) {
                window.Toast.error('Aksi Gagal', 'Target penyimpanan paket tidak dikenal. Muat ulang halaman.');
            }

            return;
        }

        form.action = updateUrl;
        if (select) select.value = currentPackageId || '';
        if (nameEl) nameEl.textContent = customerName || '-';
        if (currentPackageEl) currentPackageEl.textContent = currentPackageLabel || '-';
        if (currentPriceEl) currentPriceEl.textContent = currentPriceLabel || '-';

        wrapper.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closePackageChangeModal() {
        const wrapper = document.getElementById('package-change-modal-wrapper');
        if (wrapper) wrapper.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function triggerEdit() {
        window.location.href = '/customers/' + selectedCustomerData.id + '/edit';
    }

    function triggerTerminate() {
        closeActionsModal();
        if (!selectedCustomerData.detailUrl) return;
        // Konfirmasi (+ alasan wajib) sudah ditangani panel "Putus Langganan"
        // di halaman Detail (window.confirmDelete) — bukan di sini, biar gak
        // dobel dialog dan halaman Detail tetap satu-satunya tempat forms
        // mutasi data ini benar-benar dirender (lihat customers/show.blade.php).
        window.location.href = selectedCustomerData.detailUrl + '#terminate';
    }

@include('customers.partials._network_assignment_js')
</script>
