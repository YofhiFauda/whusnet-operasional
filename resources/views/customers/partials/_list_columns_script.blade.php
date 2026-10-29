{{-- Drag-drop urutan kolom List Pelanggan (HTML5 native, tanpa dependensi).
     Aktif hanya bila tabel punya data-columns-save-url (List Pelanggan utama).
     Urutan DOM header → disimpan ke server; sel baris ikut dipindah sesuai
     data-col supaya tampilan langsung berubah tanpa reload. URL simpan
     dirender server (route()), bukan dirakit di sini. Kolom Aksi tidak
     draggable dan selalu terakhir. --}}
<script>
    (function () {
        const table = document.getElementById('customerTable');
        const saveUrl = table?.dataset.columnsSaveUrl;
        if (!table || !saveUrl) return;

        const headRow = table.tHead.rows[0];
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        let dragged = null;

        function clearMarks() {
            headRow.querySelectorAll('th').forEach(h => h.classList.remove('opacity-40', 'border-l-2', 'border-r-2', 'border-sky-500'));
        }

        function moveCells(key, targetKey, before) {
            table.tBodies[0].querySelectorAll('tr[data-customer-row]').forEach(row => {
                const cell = row.querySelector(`td[data-col="${key}"]`);
                const ref = row.querySelector(`td[data-col="${targetKey}"]`);
                if (!cell || !ref) return;
                before ? ref.before(cell) : ref.after(cell);
            });
        }

        function persist() {
            const keys = [...headRow.querySelectorAll('th[data-col]')]
                .map(h => h.dataset.col)
                .filter(k => k !== 'aksi');

            fetch(saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ columns: keys }),
            }).then(res => {
                if (!res.ok) throw new Error('gagal');
            }).catch(() => {
                alert('Urutan kolom gagal disimpan. Halaman dimuat ulang.');
                window.location.reload();
            });
        }

        headRow.querySelectorAll('th[draggable="true"]').forEach(th => {
            th.addEventListener('dragstart', e => {
                dragged = th;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', th.dataset.col);
                th.classList.add('opacity-40');
            });
            th.addEventListener('dragend', () => { dragged = null; clearMarks(); });
            th.addEventListener('dragover', e => {
                if (!dragged || th === dragged) return;
                e.preventDefault();
                const before = e.offsetX < th.offsetWidth / 2;
                clearMarks();
                dragged.classList.add('opacity-40');
                th.classList.add('border-sky-500', before ? 'border-l-2' : 'border-r-2');
            });
            th.addEventListener('drop', e => {
                if (!dragged || th === dragged) return;
                e.preventDefault();
                const before = e.offsetX < th.offsetWidth / 2;
                const key = dragged.dataset.col;
                before ? th.before(dragged) : th.after(dragged);
                moveCells(key, th.dataset.col, before);
                clearMarks();
                dragged = null;
                persist();
            });
        });
    })();
</script>
