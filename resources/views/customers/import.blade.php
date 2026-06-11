@extends('layouts.app')

@section('title', 'Batch Import Pelanggan - Whusnet Operasional')
@section('page_title', 'Batch Import Pelanggan')

@section('content')
<!-- Breadcrumbs & Header -->
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
        <a href="/customers" class="hover:text-slate-700 transition-colors">Daftar Pelanggan</a>
        <span>/</span>
        <span class="text-slate-600">Batch Import</span>
    </nav>
    <h1 class="text-xl font-bold text-slate-800 tracking-tight">Import Pelanggan Baru</h1>
    <p class="text-xs text-slate-500 mt-1">Salin data dari Excel/CSV atau unggah file secara langsung</p>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
    
    <!-- Left Column: Instructions & Column Format -->
    <div class="lg:col-span-1 flex flex-col gap-6">
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm space-y-4">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Format Kolom Data</h4>
            <p class="text-xs text-slate-500 leading-relaxed">Pastikan data Anda memiliki urutan kolom sebagai berikut, atau baris pertama berisi nama kolom ini:</p>
            
            <div class="space-y-2.5">
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">1. No</span>
                    <span class="text-[10px] text-slate-400">Nomor urut (diabaikan)</span>
                </div>
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">2. ID</span>
                    <span class="text-[10px] text-slate-400">Nomor Identitas (NIK) *</span>
                </div>
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">3. NAMA</span>
                    <span class="text-[10px] text-slate-400">Nama lengkap *</span>
                </div>
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">4. DESA</span>
                    <span class="text-[10px] text-slate-400">Nama Desa *</span>
                </div>
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">5. PAKET</span>
                    <span class="text-[10px] text-slate-400">Kode Paket Internet *</span>
                </div>
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">6. HP</span>
                    <span class="text-[10px] text-slate-400">Nomor HP/Telepon *</span>
                </div>
                <div class="flex items-center justify-between text-xs p-2 bg-slate-50 rounded border border-slate-100">
                    <span class="font-bold text-slate-700">7. KOORDINAT</span>
                    <span class="text-[10px] text-slate-400">Format: lat, long</span>
                </div>
            </div>
            <div class="text-[10px] text-slate-400 italic">
                * Wajib diisi. Jika nama desa atau paket tidak cocok, Anda dapat memetakan secara manual di tabel pratinjau.
            </div>
        </div>
    </div>

    <!-- Right Column: Upload Tabs & Preview Area -->
    <div class="lg:col-span-3 flex flex-col gap-6">
        
        <!-- Input Selector Card -->
        <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
            <!-- Tabs Nav -->
            <div class="border-b border-slate-200 bg-slate-50 flex">
                <button type="button" onclick="switchMethod('upload')" id="tab-upload" class="px-5 py-3 text-xs font-semibold border-b-2 border-sky-600 text-sky-600 focus:outline-none cursor-pointer">
                    Metode A: Upload File (Excel / CSV)
                </button>
                <button type="button" onclick="switchMethod('paste')" id="tab-paste" class="px-5 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer">
                    Metode B: Copy-Paste Data
                </button>
            </div>

            <!-- Tab Content Panel -->
            <div class="p-6">
                <!-- Method A: Upload File -->
                <div id="panel-upload" class="method-panel space-y-4">
                    <div class="border-2 border-dashed border-slate-200 hover:border-sky-400 transition-colors rounded-lg p-8 text-center bg-slate-50/50 relative">
                        <input type="file" id="file-input" accept=".xlsx, .xls, .csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="handleFileSelect(event)">
                        <div class="flex flex-col items-center">
                            <svg class="h-10 w-10 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <span class="block text-xs font-bold text-slate-700">Tarik & Letakkan File Anda di sini</span>
                            <span class="block text-[10px] text-slate-400 mt-1">Mendukung format file .XLSX, .XLS, dan .CSV (Maksimal 5MB)</span>
                            <span class="inline-block mt-4 bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 text-[10px] font-bold px-3 py-1.5 rounded transition-colors">
                                Pilih File Dari Komputer
                            </span>
                        </div>
                    </div>
                    <div id="file-info-container" class="hidden flex items-center justify-between text-xs p-3 bg-sky-50 border border-sky-100 rounded-md text-sky-800">
                        <span class="font-medium font-mono" id="file-name-text">filename.xlsx</span>
                        <button type="button" onclick="resetFileSelection()" class="text-sky-600 hover:text-sky-800 font-bold focus:outline-none">Hapus</button>
                    </div>
                </div>

                <!-- Method B: Copy Paste -->
                <div id="panel-paste" class="method-panel space-y-4 hidden">
                    <div>
                        <label for="paste-textarea" class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wide">Tempel (Paste) data dari Excel / Spreadsheet:</label>
                        <textarea id="paste-textarea" rows="8" class="w-full text-xs font-mono p-3 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" placeholder="Salin baris data dari Excel Anda lalu tempel di sini.&#10;Contoh format:&#10;1	3502181010900001	Fajar Pratama	Babadan	WHUS-LITE	08123456789	-7.86940,111.46210"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="handlePasteInput()" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-4 rounded transition-colors cursor-pointer focus:outline-none">
                            Proses & Pratinjau Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics & Loading indicators -->
        <div id="preview-section" class="hidden space-y-6">
            <!-- Loading Indicator -->
            <div id="loading-indicator" class="hidden bg-white border border-slate-200 rounded-lg p-8 text-center shadow-sm">
                <svg class="animate-spin h-8 w-8 text-sky-600 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="block text-xs font-semibold text-slate-700">Menganalisis & memvalidasi data...</span>
            </div>

            <!-- Stats Metrics -->
            <div id="metrics-container" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 p-4 rounded-lg shadow-sm">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Baris</span>
                    <span class="block text-xl font-extrabold text-slate-800 mt-1 font-mono data-text" id="metric-total">0</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-lg shadow-sm">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Siap Di-Import</span>
                    <span class="block text-xl font-extrabold text-green-600 mt-1 font-mono data-text" id="metric-ready">0</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-lg shadow-sm">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Butuh Resolusi</span>
                    <span class="block text-xl font-extrabold text-amber-500 mt-1 font-mono data-text" id="metric-warning">0</span>
                </div>
                <div class="bg-white border border-slate-200 p-4 rounded-lg shadow-sm">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Error Format</span>
                    <span class="block text-xl font-extrabold text-red-600 mt-1 font-mono data-text" id="metric-error">0</span>
                </div>
            </div>

            <!-- Table Preview Card -->
            <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Pratinjau & Validasi Data Pelanggan</h3>
                    <div class="text-[10px] text-slate-500 font-medium">Selesaikan baris peringatan (amber) sebelum mengimport data</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse text-slate-700">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-[10px] uppercase">
                                <th class="px-4 py-3 w-10 text-center">NO</th>
                                <th class="px-4 py-3 w-36">NIK / ID</th>
                                <th class="px-4 py-3 w-48">NAMA LENGKAP</th>
                                <th class="px-4 py-3 w-56">DESA</th>
                                <th class="px-4 py-3 w-56">PAKET</th>
                                <th class="px-4 py-3 w-32">NOMOR HP</th>
                                <th class="px-4 py-3 w-36">KOORDINAT</th>
                                <th class="px-4 py-3">LOG / CATATAN VALIDASI</th>
                            </tr>
                        </thead>
                        <tbody id="preview-table-body" class="divide-y divide-slate-100">
                            <!-- Rows will be injected by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer with submit form -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
                    <span class="text-xs text-slate-500" id="submit-summary-text">0 dari 0 pelanggan valid</span>
                    
                    <form action="/customers/import/confirm" method="POST" id="confirm-form">
                        @csrf
                        <input type="hidden" name="rows" id="confirm-rows-json">
                        <button type="submit" id="btn-submit-import" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 disabled:text-slate-400 text-white text-xs font-semibold py-2 px-5 rounded transition-all cursor-pointer focus:outline-none shadow-sm disabled:cursor-not-allowed">
                            <svg id="btn-submit-import-spinner" class="hidden animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="btn-submit-import-text">Mulai Import Data Pelanggan</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<!-- Load SheetJS library from CDN -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    let currentMethod = 'upload';
    let validatedRows = [];
    
    // DB lists injected
    const allVillages = @json($villages);
    const allPackages = @json($packages);

    function switchMethod(method) {
        currentMethod = method;
        document.querySelectorAll('.method-panel').forEach(el => el.classList.add('hidden'));
        
        if (method === 'upload') {
            document.getElementById('panel-upload').classList.remove('hidden');
            document.getElementById('tab-upload').className = "px-5 py-3 text-xs font-semibold border-b-2 border-sky-600 text-sky-600 focus:outline-none cursor-pointer";
            document.getElementById('tab-paste').className = "px-5 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer";
        } else {
            document.getElementById('panel-paste').classList.remove('hidden');
            document.getElementById('tab-paste').className = "px-5 py-3 text-xs font-semibold border-b-2 border-sky-600 text-sky-600 focus:outline-none cursor-pointer";
            document.getElementById('tab-upload').className = "px-5 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer";
        }
    }

    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Show file info container
        document.getElementById('file-name-text').textContent = `${file.name} (${formatBytes(file.size)})`;
        document.getElementById('file-info-container').classList.remove('hidden');

        // Show loading and preview section
        document.getElementById('preview-section').classList.remove('hidden');
        document.getElementById('loading-indicator').classList.remove('hidden');
        document.getElementById('metrics-container').classList.add('hidden');
        document.getElementById('preview-table-body').innerHTML = '';

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, {type: 'array'});
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                
                // Convert sheet to raw array
                const rawRows = XLSX.utils.sheet_to_json(worksheet, {header: 1});
                processRawArray(rawRows);
            } catch (err) {
                console.error("Gagal membaca file excel:", err);
                alert("Format file tidak didukung atau rusak.");
                resetFileSelection();
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function resetFileSelection() {
        document.getElementById('file-input').value = '';
        document.getElementById('file-info-container').classList.add('hidden');
        document.getElementById('preview-section').classList.add('hidden');
        validatedRows = [];
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function handlePasteInput() {
        const text = document.getElementById('paste-textarea').value;
        if (!text.trim()) {
            alert("Silakan tempel (paste) data Anda terlebih dahulu.");
            return;
        }

        document.getElementById('preview-section').classList.remove('hidden');
        document.getElementById('loading-indicator').classList.remove('hidden');
        document.getElementById('metrics-container').classList.add('hidden');
        document.getElementById('preview-table-body').innerHTML = '';

        const lines = text.split(/\r?\n/);
        const rawRows = [];
        
        let delimiter = '\t';
        const firstLine = lines[0];
        if (firstLine.includes('\t')) {
            delimiter = '\t';
        } else if (firstLine.includes(';')) {
            delimiter = ';';
        } else if (firstLine.includes(',')) {
            delimiter = ',';
        }

        lines.forEach(line => {
            if (line.trim() !== '') {
                const cells = line.split(delimiter).map(c => c.trim().replace(/^["']|["']$/g, ''));
                rawRows.push(cells);
            }
        });

        processRawArray(rawRows);
    }

    function processRawArray(rawRows) {
        if (rawRows.length < 2) {
            alert("Baris data tidak mencukupi (minimal 2 baris termasuk header).");
            document.getElementById('preview-section').classList.add('hidden');
            return;
        }
        
        // Scan header row to look for field indices
        const headers = rawRows[0].map(h => String(h).trim().toLowerCase());
        
        let colNo = headers.indexOf('no');
        let colId = headers.findIndex(h => h.includes('id') || h.includes('nik'));
        let colNama = headers.findIndex(h => h.includes('nama') || h.includes('name'));
        let colDesa = headers.findIndex(h => h.includes('desa') || h.includes('village'));
        let colPaket = headers.findIndex(h => h.includes('paket') || h.includes('package'));
        let colHp = headers.findIndex(h => h.includes('hp') || h.includes('telepon') || h.includes('phone') || h.includes('wa'));
        let colKoordinat = headers.findIndex(h => h.includes('koordinat') || h.includes('coords') || h.includes('location'));

        // Fallbacks if not found by name, assume fixed order: No, ID, NAMA, DESA, PAKET, HP, KOORDINAT
        if (colNo === -1) colNo = 0;
        if (colId === -1) colId = 1;
        if (colNama === -1) colNama = 2;
        if (colDesa === -1) colDesa = 3;
        if (colPaket === -1) colPaket = 4;
        if (colHp === -1) colHp = 5;
        if (colKoordinat === -1) colKoordinat = 6;

        const parsedRows = [];
        for (let i = 1; i < rawRows.length; i++) {
            const row = rawRows[i];
            if (row.length === 0 || row.every(cell => cell === null || cell === undefined || String(cell).trim() === '')) {
                continue; // Skip empty rows
            }

            parsedRows.push({
                no: row[colNo] !== undefined ? String(row[colNo]).trim() : String(i),
                id: row[colId] !== undefined ? String(row[colId]).trim() : '',
                nama: row[colNama] !== undefined ? String(row[colNama]).trim() : '',
                desa: row[colDesa] !== undefined ? String(row[colDesa]).trim() : '',
                paket: row[colPaket] !== undefined ? String(row[colPaket]).trim() : '',
                hp: row[colHp] !== undefined ? String(row[colHp]).trim() : '',
                koordinat: row[colKoordinat] !== undefined ? String(row[colKoordinat]).trim() : '',
            });
        }

        // Send to Validation API
        fetch('/customers/import/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ rows: parsedRows })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('loading-indicator').classList.add('hidden');
            if (data.success) {
                validatedRows = data.rows;
                document.getElementById('metrics-container').classList.remove('hidden');
                renderPreviewTable();
            } else {
                alert("Gagal memvalidasi data: " + data.message);
                resetFileSelection();
            }
        })
        .catch(err => {
            console.error("API error:", err);
            alert("Terjadi kesalahan jaringan saat memvalidasi data.");
            document.getElementById('loading-indicator').classList.add('hidden');
            resetFileSelection();
        });
    }

    function renderPreviewTable() {
        const tbody = document.getElementById('preview-table-body');
        tbody.innerHTML = '';

        let readyCount = 0;
        let warningCount = 0;
        let errorCount = 0;

        validatedRows.forEach((row, index) => {
            const tr = document.createElement('tr');
            
            // Apply background color based on status
            if (row.status_row === 'error') {
                tr.className = "bg-red-50/20";
                errorCount++;
            } else if (row.status_row === 'warning') {
                tr.className = "bg-amber-50/20";
                warningCount++;
            } else {
                tr.className = "bg-white";
                readyCount++;
            }

            // Columns rendering
            // 1. NO
            const tdNo = document.createElement('td');
            tdNo.className = "px-4 py-3 text-center font-mono text-slate-400";
            tdNo.textContent = index + 1;
            tr.appendChild(tdNo);

            // 2. ID/NIK
            const tdId = document.createElement('td');
            tdId.className = "px-4 py-3 font-mono";
            tdId.textContent = row.identity_number || '-';
            tr.appendChild(tdId);

            // 3. NAMA
            const tdNama = document.createElement('td');
            tdNama.className = "px-4 py-3 font-semibold text-slate-800";
            tdNama.textContent = row.full_name;
            tr.appendChild(tdNama);

            // 4. DESA
            const tdDesa = document.createElement('td');
            tdDesa.className = "px-4 py-3";
            if (!row.village_id) {
                // Render dropdown village selector
                const select = document.createElement('select');
                select.className = "w-full text-xs font-sans px-2 py-1 border border-amber-300 bg-amber-50/50 rounded focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500/25";
                select.onchange = (e) => mapVillageRow(index, e.target.value);
                
                const optDefault = document.createElement('option');
                optDefault.value = "";
                optDefault.textContent = `-- Pilih Desa (Asli: ${row.original_desa}) --`;
                optDefault.disabled = true;
                optDefault.selected = true;
                select.appendChild(optDefault);

                allVillages.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = `${v.name} (${v.district ? v.district.name : 'N/A'})`;
                    select.appendChild(opt);
                });
                tdDesa.appendChild(select);
            } else {
                tdDesa.className = "px-4 py-3 font-medium text-slate-700";
                tdDesa.textContent = `${row.village_name} (${row.district_id ? getDistrictName(row.district_id) : 'N/A'})`;
            }
            tr.appendChild(tdDesa);

            // 5. PAKET
            const tdPaket = document.createElement('td');
            tdPaket.className = "px-4 py-3";
            if (!row.internet_package_id) {
                // Render dropdown package selector
                const select = document.createElement('select');
                select.className = "w-full text-xs font-sans px-2 py-1 border border-amber-300 bg-amber-50/50 rounded focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500/25";
                select.onchange = (e) => mapPackageRow(index, e.target.value);
                
                const optDefault = document.createElement('option');
                optDefault.value = "";
                optDefault.textContent = `-- Pilih Paket (Asli: ${row.original_paket}) --`;
                optDefault.disabled = true;
                optDefault.selected = true;
                select.appendChild(optDefault);

                allPackages.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.package_code} - ${p.name}`;
                    select.appendChild(opt);
                });
                tdPaket.appendChild(select);
            } else {
                tdPaket.className = "px-4 py-3 font-medium text-slate-700";
                tdPaket.textContent = row.package_code;
            }
            tr.appendChild(tdPaket);

            // 6. HP
            const tdHp = document.createElement('td');
            tdHp.className = "px-4 py-3 font-mono";
            tdHp.textContent = row.phone;
            tr.appendChild(tdHp);

            // 7. KOORDINAT
            const tdKoordinat = document.createElement('td');
            tdKoordinat.className = "px-4 py-3 font-mono text-slate-500";
            tdKoordinat.textContent = (row.latitude && row.longitude) ? `${row.latitude}, ${row.longitude}` : '-';
            tr.appendChild(tdKoordinat);

            // 8. LOG CATATAN
            const tdLog = document.createElement('td');
            tdLog.className = "px-4 py-3";
            if (row.status_row === 'error') {
                const span = document.createElement('span');
                span.className = "inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-100";
                span.textContent = row.errors.join(' | ');
                tdLog.appendChild(span);
            } else if (row.status_row === 'warning') {
                const div = document.createElement('div');
                div.className = "flex flex-col gap-1";
                row.warnings.forEach(w => {
                    const span = document.createElement('span');
                    span.className = "inline-flex items-center self-start gap-1.5 px-2 py-0.5 rounded text-[9px] font-semibold bg-amber-50 text-amber-700 border border-amber-100";
                    span.textContent = w;
                    div.appendChild(span);
                });
                tdLog.appendChild(div);
            } else {
                const span = document.createElement('span');
                span.className = "inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100";
                span.textContent = "VALID";
                tdLog.appendChild(span);
            }
            tr.appendChild(tdLog);

            tbody.appendChild(tr);
        });

        // Update metrics
        document.getElementById('metric-total').textContent = validatedRows.length;
        document.getElementById('metric-ready').textContent = readyCount;
        document.getElementById('metric-warning').textContent = warningCount;
        document.getElementById('metric-error').textContent = errorCount;

        // Update submit button state
        const btnSubmit = document.getElementById('btn-submit-import');
        const summaryText = document.getElementById('submit-summary-text');
        
        const totalImportable = readyCount + warningCount; // Warnings can still be imported, but we block if there are errors or unresolved warning lists
        
        summaryText.textContent = `${readyCount} dari ${validatedRows.length} baris siap di-import.`;

        // If there are errors (red) or unresolved warnings (e.g. missing village/package IDs), disable button
        const hasUnresolvedWarnings = validatedRows.some(row => !row.village_id || !row.internet_package_id);
        const hasErrors = errorCount > 0;

        if (hasErrors || hasUnresolvedWarnings || validatedRows.length === 0) {
            btnSubmit.disabled = true;
            if (hasUnresolvedWarnings) {
                summaryText.innerHTML = `<span class="text-amber-600 font-semibold">Terdapat desa atau paket yang belum cocok. Pilih secara manual di tabel.</span>`;
            } else if (hasErrors) {
                summaryText.innerHTML = `<span class="text-red-600 font-semibold">Terdapat error data wajib. Mohon hapus data error dari file/spreadsheet Anda.</span>`;
            }
        } else {
            btnSubmit.disabled = false;
            summaryText.innerHTML = `<span class="text-green-600 font-semibold">Semua baris valid! Siap meng-import ${validatedRows.length} pelanggan.</span>`;
            
            // Set hidden field json value
            document.getElementById('confirm-rows-json').value = JSON.stringify(validatedRows);
        }
    }

    function mapVillageRow(index, villageId) {
        const village = allVillages.find(v => v.id == villageId);
        if (village && validatedRows[index]) {
            validatedRows[index].village_id = village.id;
            validatedRows[index].village_name = village.name;
            validatedRows[index].district_id = village.district_id;
            // set city_id to 1 (Ponorogo)
            validatedRows[index].city_id = 1;
            
            // Remove the unmatched village warning
            validatedRows[index].warnings = validatedRows[index].warnings.filter(w => !w.includes("Desa '"));
            
            // Check status row
            recheckRowStatus(index);
            renderPreviewTable();
        }
    }

    function mapPackageRow(index, packageId) {
        const pkg = allPackages.find(p => p.id == packageId);
        if (pkg && validatedRows[index]) {
            validatedRows[index].internet_package_id = pkg.id;
            validatedRows[index].package_code = pkg.package_code;

            // Remove unmatched package warning
            validatedRows[index].warnings = validatedRows[index].warnings.filter(w => !w.includes("Paket '"));

            recheckRowStatus(index);
            renderPreviewTable();
        }
    }

    function recheckRowStatus(index) {
        const row = validatedRows[index];
        if (row.errors.length > 0) {
            row.status_row = 'error';
        } else if (row.warnings.length > 0 || !row.village_id || !row.internet_package_id) {
            row.status_row = 'warning';
        } else {
            row.status_row = 'valid';
        }
    }

    function getDistrictName(districtId) {
        // District id from village object
        const village = allVillages.find(v => v.district_id == districtId);
        return (village && village.district) ? village.district.name : 'N/A';
    }

    document.getElementById('confirm-form').addEventListener('submit', function() {
        const btnSubmit = document.getElementById('btn-submit-import');
        const spinner = document.getElementById('btn-submit-import-spinner');
        const buttonText = document.getElementById('btn-submit-import-text');

        btnSubmit.disabled = true;
        spinner.classList.remove('hidden');
        buttonText.textContent = 'Mengimport...';
    });
</script>
@endsection
