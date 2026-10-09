{{--
    Skrip batch bayar kolektor — dipakai dua halaman berbeda audiens
    (Worksheet Admin & Worklist Kolektor) dengan markup tabel yang sama.
    Yang beda cuma URL tujuan, karena jalur kolektor SENGAJA tanpa parameter
    {collector} (docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9).

    Variabel yang wajib dikirim pemanggil:
      $storeUrl     — endpoint POST batch
      $keyPrefix    — awalan idempotency_key (biar jejaknya kebaca di DB)
      $colspan      — jumlah kolom tabel, untuk baris "kosong"
      $emptyMessage — teks saat semua baris habis dibayar
--}}
<script>
    {{-- JSON_UNESCAPED_SLASHES supaya URL-nya terbaca apa adanya di sumber
         halaman ("/collector-worklist/pay", bukan "\/collector-worklist\/pay").
         Bukan kosmetik: ada test yang mengunci halaman kolektor menunjuk rute
         self-service, dan URL ter-escape bikin jaminan itu tak bisa dibaca. --}}
    const CB_STORE_URL = @json($storeUrl, JSON_UNESCAPED_SLASHES);
    const CB_KEY_PREFIX = @json($keyPrefix);
    const CB_COLSPAN = {{ $colspan }};
    const CB_EMPTY_MESSAGE = @json($emptyMessage);

    // Idempotency key melekat pada ISI KIRIMAN, bukan pada tab.
    //
    // Dua kesalahan yang pernah terjadi, dan kenapa bentuknya sekarang begini:
    //
    //  1. Awalnya tiap panggilan mint key baru. Retry setelah kegagalan jadi
    //     batch BARU — untuk kegagalan sesudah commit, pelanggan terkredit dua
    //     kali.
    //  2. Lalu key dibuat satu dan dipakai ulang sampai sukses. Itu memperbaiki
    //     (1) tapi melahirkan yang lebih buruk: kolektor menekan Bayar di baris
    //     A lalu baris B sebelum jawaban A tiba — keduanya mengirim key yang
    //     sama, server menjawab `already_processed` untuk B, muncul toast
    //     hijau, dan UANG BARIS B TAK PERNAH TERCATAT.
    //
    // Sekarang key diturunkan dari tanda tangan barisnya. Retry kiriman yang
    // sama memakai key yang sama (aman dari (1)); kiriman baris lain punya key
    // sendiri (aman dari (2)). Begitu sukses, tanda tangannya dibuang supaya
    // pembayaran berikutnya dengan angka identik — cicilan 50rb dua kali di
    // hari yang sama — tetap dianggap kiriman baru.
    const cbPendingKeys = new Map();

    function cbSignature(rows) {
        return rows
            .map(r => [r.invoice_id, r.amount, r.use_balance_amount ?? 0, r.payment_method, r.bank_account_id ?? '', r.collected_date].join(':'))
            .sort()
            .join('|');
    }

    function cbKeyFor(signature) {
        if (! cbPendingKeys.has(signature)) {
            cbPendingKeys.set(
                signature,
                CB_KEY_PREFIX + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10)
            );
        }

        return cbPendingKeys.get(signature);
    }

    function cbToggleAll(checkbox) {
        document.querySelectorAll('.cb-row-checkbox').forEach(el => { el.checked = checkbox.checked; });
        const desktopAll = document.getElementById('cb-select-all');
        const mobileAll = document.getElementById('cb-select-all-mobile');
        if (desktopAll) desktopAll.checked = checkbox.checked;
        if (mobileAll) mobileAll.checked = checkbox.checked;
        cbUpdateCount();
    }

    function cbUpdateCount() {
        const total = document.querySelectorAll('.cb-row-checkbox').length;
        const checked = document.querySelectorAll('.cb-row-checkbox:checked').length;

        const desktopAll = document.getElementById('cb-select-all');
        const mobileAll = document.getElementById('cb-select-all-mobile');
        const isAllChecked = total > 0 && checked === total;
        if (desktopAll) desktopAll.checked = isAllChecked;
        if (mobileAll) mobileAll.checked = isAllChecked;

        const counter = document.getElementById('cb-count');
        if (counter) counter.textContent = checked + ' baris dipilih';

        const btn = document.getElementById('cb-submit');
        if (btn) btn.disabled = checked <= 1;

        const bar = document.getElementById('cb-floating-bar');
        if (bar) {
            if (checked > 1) {
                bar.classList.remove('hidden');
            } else {
                bar.classList.add('hidden');
            }
        }
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('cb-row-checkbox')) {
            cbUpdateCount();
        }

        // "Pakai saldo" dicentang/dilepas → hitung ulang pelanggan itu saja.
        if (e.target.classList.contains('cb-use-saldo')) {
            const tr = e.target.closest('tr');
            cbAutoFillSaldo(tr ? tr.dataset.customerId : null);
            cbRefreshAllHints();
        }
    });

    // Pakai komponen Toast global (resources/views/components/toast.blade.php),
    // bukan div buatan sendiri — hasil bayar adalah umpan balik sesaat, dan
    // dua halaman ini harus terlihat sama dengan sisa aplikasi. Fallback ke
    // panel statis cuma untuk jaga-jaga kalau Toast belum termuat.
    function cbShowAlert(message, isError) {
        if (window.Toast && typeof window.Toast.show === 'function') {
            window.Toast.show(
                isError ? 'error' : 'success',
                isError ? 'Pembayaran gagal dicatat' : 'Pembayaran tercatat',
                message,
                isError ? 12000 : 6000
            );

            return;
        }

        const alertEl = document.getElementById('batch-alert');
        if (! alertEl) return;

        alertEl.textContent = message;
        alertEl.className = 'mb-6 text-sm rounded-lg border p-4 ' + (isError
            ? 'bg-rose-50 border-rose-200 text-rose-800'
            : 'bg-emerald-50 border-emerald-200 text-emerald-800');
        alertEl.classList.remove('hidden');
    }

    function cbRowToPayload(tr) {
        const noteInput = tr.querySelector('.cb-note');
        const bankInput = tr.querySelector('.cb-bank');
        const senderInput = tr.querySelector('.cb-sender');
        const method = tr.querySelector('.cb-method').value;

        return {
            invoice_id: parseInt(tr.querySelector('.cb-row-checkbox').value, 10),
            // Kolom nominal bermasking ribuan (data-rupiah): parseFloat langsung
            // atas "150.000" menghasilkan 150 — pembayaran 1.000× lebih kecil,
            // tanpa error. Server tetap menormalkan ulang (RupiahInput).
            amount: window.Rupiah.angka(tr.querySelector('.cb-amount').value),
            use_balance_amount: cbSaldoOf(tr),
            payment_method: method,
            // Hanya dikirim untuk Transfer — rekening tujuan dari master.
            bank_account_id: method === 'transfer' && bankInput && bankInput.value ? parseInt(bankInput.value, 10) : null,
            sender_name: method === 'transfer' && senderInput ? senderInput.value.trim() : '',
            collected_date: tr.querySelector('.cb-collected-date').value,
            // Wajib untuk metode Lainnya — dicek cbBarisValid() sebelum
            // submit, dan lagi di CollectorPaymentService (server otoritatif).
            note: noteInput ? noteInput.value.trim() : '',
        };
    }

    // Saldo yang dipakai baris ini (0 kalau kolom saldo tidak tampil).
    function cbSaldoOf(tr) {
        const saldoInput = tr.querySelector('.cb-saldo');
        const checkbox = tr.querySelector('.cb-use-saldo');
        if (!saldoInput || (checkbox && !checkbox.checked)) return 0;

        return window.Rupiah.angka(saldoInput.value) || 0;
    }

    // Tunai + saldo = total yang menutup tagihan baris ini.
    function cbTotalOf(tr) {
        const amount = window.Rupiah.angka(tr.querySelector('.cb-amount').value) || 0;
        return amount + cbSaldoOf(tr);
    }

    // Sisa tagihan baris ini, dari data-max yang disegarkan cbApplyResults().
    function cbRemainingOf(tr) {
        return parseFloat(tr.querySelector('.cb-amount').dataset.max);
    }

    // Lebih bayar = total di atas sisa tagihan. Dibulatkan ke sen supaya
    // "bayar pas" tak menghasilkan Rp0,000001 hantu (sama seperti server).
    function cbOverpayOf(tr) {
        const over = cbTotalOf(tr) - cbRemainingOf(tr);
        return over > 0 ? Math.round(over * 100) / 100 : 0;
    }

    // Pratinjau di bawah input, sama maknanya dengan form Bayar admin:
    // lunas / cicilan (sisa) / lebih bayar. Tanpa nomor cicilan — jalur batch
    // tidak punya urutan cicilan seperti form admin.
    function cbRefreshHint(tr) {
        if (!tr) return;
        const hint = tr.querySelector('.cb-hint');
        if (!hint) return;

        const total = cbTotalOf(tr);
        const remaining = cbRemainingOf(tr);
        const fmt = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

        // Satu baris pendek (chip) supaya tabel tidak melebar; penjelasan
        // lengkap ada di tooltip (title).
        hint.className = 'cb-hint hidden inline-block text-[10px] leading-tight font-semibold px-1.5 py-0.5 rounded';
        if (!(total > 0) || isNaN(remaining)) return;

        if (total > remaining) {
            hint.textContent = 'Melebihi sisa ' + fmt(total - remaining);
            hint.title = 'Tidak bisa diproses di sini. Kelebihan hanya bisa dicatat lewat Tagihan admin.';
            hint.classList.add('text-rose-700', 'bg-rose-50');
        } else if (total < remaining) {
            hint.textContent = 'Cicilan · sisa ' + fmt(remaining - total);
            hint.title = 'Tagihan jadi Sebagian, sisa setelah ini ' + fmt(remaining - total) + '.';
            hint.classList.add('text-amber-800', 'bg-amber-50');
        } else {
            hint.textContent = 'Lunas';
            hint.title = 'Pembayaran ini melunasi tagihan.';
            hint.classList.add('text-emerald-700', 'bg-emerald-50');
        }
        hint.classList.remove('hidden');
    }

    function cbRefreshAllHints() {
        document.querySelectorAll('tr[data-invoice-row]').forEach(cbRefreshHint);
    }

    // Saldo terisi otomatis seperti form Bayar admin: saldo = min(sisa saldo,
    // sisa tagihan), nominal tunai = sisa tagihan dikurangi saldo. Saldo satu
    // pelanggan dibagi antar tagihannya (satu saldo, banyak baris), jadi
    // baris berikutnya hanya mendapat sisa saldo yang belum dipakai baris sebelumnya.
    // Format ribuan seperti input lain (150.000), bukan angka mentah (150000).
    function cbSetRupiah(input, value) {
        input.value = window.Rupiah.format(String(Math.max(0, Math.round(value))));
    }

    // Hanya baris yang saldonya dicentang ("Pakai saldo") yang dihitung. Baris
    // lain: saldo 0, nominal = sisa tagihan. `onlyCustomerId` membatasi hitung
    // ulang ke satu pelanggan (dipakai saat checkbox-nya diklik), supaya nominal
    // hasil ketikan kasir di baris lain tidak tertimpa.
    function cbAutoFillSaldo(onlyCustomerId = null) {
        const assigned = {};

        document.querySelectorAll('tr[data-invoice-row]').forEach(tr => {
            const saldoInput = tr.querySelector('.cb-saldo');
            if (!saldoInput) return;

            const customerId = tr.dataset.customerId;
            if (onlyCustomerId !== null && customerId !== String(onlyCustomerId)) return;

            const checkbox = tr.querySelector('.cb-use-saldo');
            const remaining = cbRemainingOf(tr);
            const balance = parseFloat(saldoInput.dataset.balance) || 0;
            const available = Math.max(0, balance - (assigned[customerId] || 0));
            const useSaldo = checkbox && checkbox.checked;
            const saldo = useSaldo ? Math.max(0, Math.min(available, remaining)) : 0;

            if (useSaldo) assigned[customerId] = (assigned[customerId] || 0) + saldo;
            saldoInput.closest('.cb-saldo-wrap')?.classList.toggle('hidden', !useSaldo);
            cbSetRupiah(saldoInput, saldo);
            cbSetRupiah(tr.querySelector('.cb-amount'), remaining - saldo);
        });
    }

    // Saldo diubah kasir → nominal tunai ikut menyesuaikan sisa tagihan.
    function cbOnSaldoChange(saldoInput) {
        const tr = saldoInput.closest('tr');
        if (!tr) return;

        const max = parseFloat(saldoInput.dataset.max) || 0;
        const saldo = Math.min(cbSaldoOf(tr), max);
        cbSetRupiah(tr.querySelector('.cb-amount'), cbRemainingOf(tr) - saldo);
    }

    // Konfirmasi lebih bayar sebelum kiriman dikirim — modal yang sama
    // maksudnya dengan form Bayar admin. `submit` baru jalan kalau kasir
    // menekan Lanjutkan; Batal membiarkan baris tetap bisa diubah.
    let cbPendingSubmit = null;

    function cbConfirmOverpay(trs, submit) {
        const overpay = trs.reduce((sum, tr) => sum + cbOverpayOf(tr), 0);

        if (overpay <= 0) {
            submit();
            return;
        }

        document.getElementById('cb-overpay-message').textContent =
            'Lebih bayar Rp ' + Math.round(overpay).toLocaleString('id-ID') + ' akan masuk saldo pelanggan. Lanjutkan?';
        cbPendingSubmit = submit;
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'cb-overpay-confirm' }));
    }

    function cbProceedOverpay() {
        const submit = cbPendingSubmit;
        cbPendingSubmit = null;
        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'cb-overpay-confirm' }));
        if (submit) submit();
    }

    function cbCancelOverpay() {
        cbPendingSubmit = null;
        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'cb-overpay-confirm' }));
    }

    // Metode Lainnya menampilkan input keterangan di baris yang sama
    // (PaymentMethod::requiresDescription()); Transfer menampilkan pilihan
    // rekening & nama pengirim (PaymentMethod::requiresBankDetails()). Yang
    // tidak relevan disembunyikan & dikosongkan supaya tak ikut terkirim.
    function cbToggleNote(select) {
        const tr = select.closest('tr');
        if (!tr) return;

        const noteInput = tr.querySelector('.cb-note');
        const bankInput = tr.querySelector('.cb-bank');
        const senderInput = tr.querySelector('.cb-sender');

        const isLainnya = select.value === 'lainnya';
        const isTransfer = select.value === 'transfer';

        if (noteInput) {
            noteInput.classList.toggle('hidden', !isLainnya);
            if (!isLainnya) noteInput.value = '';
        }
        if (bankInput) {
            bankInput.classList.toggle('hidden', !isTransfer);
            if (!isTransfer) bankInput.value = '';
        }
        if (senderInput) {
            senderInput.classList.toggle('hidden', !isTransfer);
            if (!isTransfer) senderInput.value = '';
        }
    }

    function cbPost(rows, submittingBtn, restoreLabel) {
        const signature = cbSignature(rows);

        fetch(CB_STORE_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ idempotency_key: cbKeyFor(signature), rows: rows }),
        })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    const detail = (data.failures || []).map(f => f.reason).filter(Boolean).join(' | ');
                    throw new Error((data.message || 'Gagal diproses.') + (detail ? ' — ' + detail : ''));
                }
                return data;
            })
            .then((data) => {
                // Tanda tangan ini selesai. Kiriman berikutnya dengan angka
                // yang persis sama (cicilan 50rb kedua di hari yang sama)
                // dianggap kiriman baru, bukan pengulangan.
                cbPendingKeys.delete(signature);
                cbShowAlert(data.message, false);
                // Daftar cuma nampilin tagihan yang masih ada sisa — patch/hapus
                // baris pakai data.results (per-invoice, dari
                // Invoice::recalculateFromPayments()) daripada reload.
                cbApplyResults(data.results || []);

                // Saldo yang terpakai tidak bisa dipatch di sisi klien (saldo
                // tersisa tak ikut dikirim server per baris), jadi halaman
                // disegarkan supaya "maks saldo" & pratinjau tak basi. Toast
                // sempat tampil dulu.
                if (rows.some(r => r.use_balance_amount > 0)) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            })
            .catch((err) => {
                cbShowAlert(err.message, true);
                if (submittingBtn) {
                    submittingBtn.disabled = false;
                    submittingBtn.textContent = restoreLabel;
                }
            });
    }

    // Lunas/batal → baris hilang (bukan tujuan penagihan lagi). Masih ada sisa
    // (cicilan sebagian) → sisa & input nominal disegarkan biar bisa lanjut
    // menagih sisanya tanpa reload.
    function cbApplyResults(results) {
        results.forEach(function (result) {
            const tr = document.querySelector('tr[data-invoice-row="' + result.invoice_id + '"]');
            if (!tr) return;

            if (result.invoice_status === 'lunas' || result.invoice_status === 'batal') {
                tr.remove();
                return;
            }

            const sisaCell = tr.querySelector('.cb-sisa');
            if (sisaCell) {
                sisaCell.textContent = 'Rp ' + Math.round(result.remaining_amount).toLocaleString('id-ID');
            }

            const amountInput = tr.querySelector('.cb-amount');
            if (amountInput) {
                amountInput.dataset.max = result.remaining_amount;
                // TIDAK dibulatkan: `data-max` membawa nilai eksak, jadi sisa
                // ber-sen yang dibulatkan naik langsung ditolak cbBarisValid()
                // — baris yang tak disentuh siapa pun jadi tak bisa dibayar.
                amountInput.value = window.Rupiah.formatDariServer(String(result.remaining_amount));
            }

            const btn = tr.querySelector('button');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Bayar';
            }

            const checkbox = tr.querySelector('.cb-row-checkbox');
            if (checkbox) {
                checkbox.checked = false;
            }
        });

        cbUpdateCount();
        cbAutoFillSaldo();
        cbRefreshAllHints();

        const batchBtn = document.getElementById('cb-submit');
        if (batchBtn) {
            batchBtn.textContent = 'Bayar Massal (Baris Terpilih)';
        }

        if (document.querySelectorAll('tbody tr[data-invoice-row]').length === 0) {
            const tbody = document.querySelector('table tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="' + CB_COLSPAN + '" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400"></td></tr>';
                tbody.querySelector('td').textContent = CB_EMPTY_MESSAGE;
            }
        }
    }

    /**
     * Nominal boleh melebihi `data-max` (sisa tagihan baris itu) — ADHOC-84
     * §2.5/§4.4: kelebihannya otomatis dipisah jadi overpay & masuk saldo
     * pelanggan di server (`CollectorPaymentService::record()`, pola sama
     * `PaymentService::record()` jalur admin), bukan ditolak. Cuma batas
     * bawah (minimal Rp 1) yang masih ditegakkan di sini.
     *
     * @return {boolean} true kalau semua baris valid.
     */
    function cbBarisValid(trs) {
        // Saldo satu pelanggan dibagi antar tagihannya — total yang dipakai
        // tidak boleh melebihi saldo yang ada (server mengecek ulang juga).
        const saldoPerCustomer = {};
        for (const tr of trs) {
            const saldoInput = tr.querySelector('.cb-saldo');
            if (!saldoInput) continue;
            const customerId = tr.dataset.customerId;
            saldoPerCustomer[customerId] = (saldoPerCustomer[customerId] || 0) + cbSaldoOf(tr);
            if (saldoPerCustomer[customerId] > (parseFloat(saldoInput.dataset.balance) || 0)) {
                cbShowAlert('Total saldo yang dipakai untuk pelanggan ini melebihi saldonya.', true);
                saldoInput.focus();
                return false;
            }
        }

        for (const tr of trs) {
            const input = tr.querySelector('.cb-amount');
            const nilai = window.Rupiah.angka(input.value);

            if (isNaN(nilai) || nilai < 0) {
                cbShowAlert('Nominal tidak valid.', true);
                input.focus();
                return false;
            }

            // Batch tidak menerima lebih bayar: tunai + saldo tak boleh melebihi sisa.
            if (cbTotalOf(tr) > cbRemainingOf(tr) + 0.001) {
                cbShowAlert('Nominal tunai ditambah saldo melebihi sisa tagihan. Kelebihan hanya bisa dicatat lewat Tagihan admin.', true);
                input.focus();
                return false;
            }

            // Tunai boleh 0 kalau saldo menutup — yang wajib minimal Rp 1 total.
            if (cbTotalOf(tr) < 1) {
                cbShowAlert('Nominal wajib diisi minimal Rp 1, atau pakai Saldo Pelanggan.', true);
                input.focus();
                return false;
            }

            const saldoInput = tr.querySelector('.cb-saldo');
            if (saldoInput && cbSaldoOf(tr) > parseFloat(saldoInput.dataset.max)) {
                cbShowAlert('Saldo yang dipakai melebihi saldo tersedia.', true);
                saldoInput.focus();
                return false;
            }

            const method = tr.querySelector('.cb-method').value;
            const noteInput = tr.querySelector('.cb-note');
            if (method === 'lainnya' && (!noteInput || !noteInput.value.trim())) {
                cbShowAlert('Metode Lainnya wajib diisi keterangannya (mis. OVO, Dana, GoPay).', true);
                if (noteInput) noteInput.focus();
                return false;
            }

            const bankInput = tr.querySelector('.cb-bank');
            if (method === 'transfer' && (!bankInput || !bankInput.value)) {
                cbShowAlert('Pilih rekening tujuan untuk metode Transfer.', true);
                if (bankInput) bankInput.focus();
                return false;
            }
        }

        return true;
    }

    // Bayar 1-by-1 — langsung submit satu baris, tanpa perlu centang.
    function cbSubmitSingle(invoiceId) {
        const tr = document.querySelector('tr[data-invoice-row="' + invoiceId + '"]');
        if (!tr) return;

        if (!cbBarisValid([tr])) return;

        const btn = tr.querySelector('button');

        cbConfirmOverpay([tr], () => {
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            cbPost([cbRowToPayload(tr)], btn, 'Bayar');
        });
    }

    // Bayar massal — semua baris yang dicentang.
    function cbSubmitBatch() {
        const trs = Array.from(document.querySelectorAll('.cb-row-checkbox:checked'))
            .map(checkbox => checkbox.closest('tr'));

        if (trs.length === 0) return;
        if (!cbBarisValid(trs)) return;

        const btn = document.getElementById('cb-submit');

        cbConfirmOverpay(trs, () => {
            const rows = trs.map(cbRowToPayload);

            btn.disabled = true;
            btn.textContent = 'Memproses...';

            cbPost(rows, btn, 'Bayar Massal (Baris Terpilih)');
        });
    }

    // Ketik nominal/saldo → pratinjau baris itu ikut berubah.
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('cb-saldo')) {
            cbOnSaldoChange(e.target);
        }
        if (e.target.classList.contains('cb-amount') || e.target.classList.contains('cb-saldo')) {
            cbRefreshHint(e.target.closest('tr'));
        }
    });

    cbAutoFillSaldo();
    cbRefreshAllHints();

</script>
