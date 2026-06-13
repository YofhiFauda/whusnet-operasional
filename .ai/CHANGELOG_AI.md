# AI Changelog

Catat semua perubahan yang dibuat oleh AI agent di file ini.

Format:

## YYYY-MM-DD HH:mm - Codex CLI

### Task
...

### File yang Diubah
- ...

### Ringkasan Perubahan
...

### Cara Test
...

### Catatan
...

## 2026-06-12 16:45 - Codex CLI

### Task
Fix review note S5-T004 terkait kejelasan tampilan PPN di detail tagihan.

### File yang Diubah
- resources/views/invoices/show.blade.php
- tests/Feature/InvoiceListTest.php
- .ai/CHANGELOG_AI.md
- .ai/SESSION_STATE.md

### Ringkasan Perubahan
Label PPN pada detail tagihan diperjelas menjadi `PPN (%)` karena nilai `ppn` invoice disimpan dan diuji sebagai persentase, bukan nominal rupiah. Test detail invoice ditambah assertion label PPN agar tidak kembali ambigu.

### Cara Test
- `$compiled = Join-Path $env:TEMP ('whusnet-views-' + [guid]::NewGuid().ToString()); New-Item -ItemType Directory -Force $compiled | Out-Null; $env:VIEW_COMPILED_PATH=$compiled; php artisan test tests/Feature/InvoiceListTest.php`
- Hasil: 3 passed, 18 assertions.

### Catatan
Tidak ada perubahan kalkulasi invoice, struktur database, atau fitur pembayaran. Run awal tanpa `VIEW_COMPILED_PATH` temp gagal karena Laravel tidak bisa rename compiled Blade di folder `storage/framework/testing/views` pada Windows (`Access is denied`), bukan karena assertion fix.
