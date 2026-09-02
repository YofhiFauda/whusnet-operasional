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
            .map(r => [r.invoice_id, r.amount, r.payment_method, r.collected_date].join(':'))
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
        return {
            invoice_id: parseInt(tr.querySelector('.cb-row-checkbox').value, 10),
            // Kolom nominal bermasking ribuan (data-rupiah): parseFloat langsung
            // atas "150.000" menghasilkan 150 — pembayaran 1.000× lebih kecil,
            // tanpa error. Server tetap menormalkan ulang (RupiahInput).
            amount: window.Rupiah.angka(tr.querySelector('.cb-amount').value),
            payment_method: tr.querySelector('.cb-method').value,
            collected_date: tr.querySelector('.cb-collected-date').value,
        };
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
     * Nominal per baris dulu ditahan atribut `max` milik input number. Sejak
     * kolomnya jadi teks bermasking ribuan, batas itu harus dicek sendiri —
     * kalau tidak, kelebihan bayar lolos diam-diam ke jalur batch yang memang
     * tidak menyediakan overpay.
     *
     * @return {boolean} true kalau semua baris masih dalam batas.
     */
    function cbBarisValid(trs) {
        for (const tr of trs) {
            const input = tr.querySelector('.cb-amount');
            const nilai = window.Rupiah.angka(input.value);
            const batas = parseFloat(input.dataset.max);

            if (isNaN(nilai) || nilai < 1) {
                cbShowAlert('Nominal wajib diisi minimal Rp 1.', true);
                input.focus();
                return false;
            }

            if (!isNaN(batas) && nilai > batas) {
                cbShowAlert('Nominal melebihi sisa tagihan (Rp ' + Math.round(batas).toLocaleString('id-ID') + ').', true);
                input.focus();
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
        btn.disabled = true;
        btn.textContent = 'Memproses...';

        cbPost([cbRowToPayload(tr)], btn, 'Bayar');
    }

    // Bayar massal — semua baris yang dicentang.
    function cbSubmitBatch() {
        const trs = Array.from(document.querySelectorAll('.cb-row-checkbox:checked'))
            .map(checkbox => checkbox.closest('tr'));

        if (trs.length === 0) return;
        if (!cbBarisValid(trs)) return;

        const rows = trs.map(cbRowToPayload);

        const btn = document.getElementById('cb-submit');
        btn.disabled = true;
        btn.textContent = 'Memproses...';

        cbPost(rows, btn, 'Bayar Massal (Baris Terpilih)');
    }
</script>
