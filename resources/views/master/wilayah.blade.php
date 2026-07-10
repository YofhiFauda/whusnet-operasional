@extends('layouts.app')

@section('title', 'Master Data Wilayah - Whusnet Operasional')
@section('page_title', 'Master Data Wilayah')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Panel: Search & Region Tree -->
    <div class="lg:col-span-2 flex flex-col gap-6">
        <!-- Search Card -->
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Cari Wilayah Layanan</h3>
            <div class="relative">
                <input type="text" id="region-search" placeholder="Cari nama desa, kecamatan, atau kode pos..." class="w-full text-sm pl-10 pr-4 py-2.5 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-2">Pencarian berjalan secara real-time via API.</p>
        </div>

        <!-- Tree View Card -->
        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden flex-1 min-h-[400px]">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kecamatan & Kelurahan / Desa</span>
                <span id="region-count-badge" class="px-2 py-0.5 bg-sky-100 text-sky-700 rounded text-xs font-mono data-text"></span>
            </div>
            
            <div id="region-tree-loading" class="hidden py-12 flex flex-col items-center justify-center text-slate-400">
                <svg class="animate-spin h-8 w-8 text-sky-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs">Memuat data...</span>
            </div>

            <div id="region-tree-content" class="p-6 divide-y divide-slate-100 max-h-[600px] overflow-y-auto">
                <!-- Data populated dynamically via JS -->
            </div>
        </div>
    </div>

    <!-- Right Panel: Statistics & Detail Info -->
    <div class="flex flex-col gap-6">
        <!-- Quick stats -->
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Informasi Wilayah Layanan</h3>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-xs text-slate-500">Kecamatan Terdata</span>
                    <span id="total-districts-count" class="font-mono font-medium text-slate-900 data-text">0</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-xs text-slate-500">Desa/Kelurahan Terdata</span>
                    <span id="total-villages-count" class="font-mono font-medium text-slate-900 data-text">0</span>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-sky-50/50 border border-sky-100 rounded-md">
                <h4 class="text-xs font-semibold text-sky-800 mb-1 flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Fungsi Master Data
                </h4>
                <p class="text-xs text-sky-700 leading-relaxed">
                    Data wilayah ini tersinkronisasi secara otomatis saat melakukan pendaftaran pelanggan baru untuk keperluan validasi koordinat lat/long dan plotting ODP terdekat.
                </p>
            </div>
        </div>

        <!-- Selected District Detail Card -->
        <div class="bg-white border border-slate-200 rounded-lg p-6 hidden" id="district-detail-card">
            <h3 class="text-sm font-semibold text-slate-700 mb-3" id="detail-district-name">Kecamatan</h3>
            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">DAFTAR DESA / KODE POS</span>
            <div class="max-h-64 overflow-y-auto border border-slate-100 rounded-md divide-y divide-slate-100 text-xs text-slate-700" id="detail-villages-list">
                <!-- Populated via JS on district click -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById('region-search');
        const treeContent = document.getElementById('region-tree-content');
        const treeLoading = document.getElementById('region-tree-loading');
        
        let typingTimer;
        
        // Initial Fetch
        fetchRegions('');

        // Real-time search with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                fetchRegions(searchInput.value);
            }, 300);
        });

        function fetchRegions(query) {
            treeContent.classList.add('hidden');
            treeLoading.classList.remove('hidden');
            
            fetch('/master/wilayah?search=' + encodeURIComponent(query), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(res => {
                treeLoading.classList.add('hidden');
                treeContent.classList.remove('hidden');
                
                const cities = res.data || [];
                renderTree(cities);
            })
            .catch(err => {
                console.error(err);
                treeLoading.classList.add('hidden');
                treeContent.innerHTML = `<p class="text-center text-red-500 py-6 text-sm">Gagal memuat data wilayah.</p>`;
                treeContent.classList.remove('hidden');
            });
        }

        function renderTree(cities) {
            treeContent.innerHTML = '';
            
            if (cities.length === 0) {
                treeContent.innerHTML = '<p class="text-center text-slate-400 py-8 text-sm">Tidak ada wilayah yang ditemukan.</p>';
                document.getElementById('region-count-badge').innerText = '0 Kecamatan';
                return;
            }
            
            let totalDistricts = 0;
            let totalVillages = 0;
            
            cities.forEach(city => {
                const districts = city.districts || [];
                totalDistricts += districts.length;
                
                // City Header
                const cityHeader = document.createElement('div');
                cityHeader.className = 'px-4 py-2.5 bg-slate-100/80 border border-slate-200 rounded-md text-xs font-bold text-slate-700 mb-2 mt-4 first:mt-0 uppercase tracking-wider flex items-center justify-between';
                cityHeader.innerHTML = `<span>${city.name}</span> <span class="text-[10px] font-mono text-slate-500 font-normal bg-white px-2 py-0.5 rounded border border-slate-200">${districts.length} Kec</span>`;
                treeContent.appendChild(cityHeader);
                
                districts.forEach(district => {
                    const villages = district.villages || [];
                    totalVillages += villages.length;
                    
                    // Create District Accordion Block
                    const block = document.createElement('div');
                    block.className = 'py-4 first:pt-0 last:pb-0';
                    
                    // Header row
                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between cursor-pointer hover:text-sky-600 transition-colors group';
                    
                    const titleWrapper = document.createElement('div');
                    titleWrapper.className = 'flex items-center gap-2.5';
                    titleWrapper.innerHTML = `
                        <svg class="h-4 w-4 text-slate-400 group-hover:text-sky-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                        <span class="font-medium text-slate-800 text-sm group-hover:text-sky-600 transition-colors">Kec. ${district.name}</span>
                    `;
                    
                    const badge = document.createElement('span');
                    badge.className = 'px-2 py-0.5 bg-slate-100 text-slate-500 rounded text-[10px] font-mono data-text';
                    badge.innerText = `${villages.length} Desa`;
                    
                    header.appendChild(titleWrapper);
                    header.appendChild(badge);
                    
                    // Village items container
                    const itemsContainer = document.createElement('div');
                    itemsContainer.className = 'mt-3 pl-7 space-y-1.5 hidden';
                    
                    villages.forEach(village => {
                        const item = document.createElement('div');
                        item.className = 'flex items-center justify-between text-xs py-1.5 px-3 bg-slate-50/70 border border-slate-100/50 rounded-md hover:bg-slate-100 transition-colors';
                        item.innerHTML = `
                            <span class="text-slate-700 font-medium">${village.name}</span>
                            <span class="text-[10px] text-slate-400 font-mono data-text">${village.postal_code || '-'}</span>
                        `;
                        itemsContainer.appendChild(item);
                    });
                    
                    // Accordion expand/collapse trigger
                    titleWrapper.addEventListener('click', function() {
                        const isExpanded = !itemsContainer.classList.contains('hidden');
                        const svg = titleWrapper.querySelector('svg');
                        
                        if (isExpanded) {
                            itemsContainer.classList.add('hidden');
                            svg.classList.remove('rotate-180');
                        } else {
                            itemsContainer.classList.remove('hidden');
                            svg.classList.add('rotate-180');
                            showDistrictDetail(district);
                        }
                    });
                    
                    block.appendChild(header);
                    block.appendChild(itemsContainer);
                    treeContent.appendChild(block);
                });
            });

            // Update stats
            document.getElementById('region-count-badge').innerText = `${totalDistricts} Kecamatan`;
            document.getElementById('total-districts-count').innerText = totalDistricts;
            document.getElementById('total-villages-count').innerText = totalVillages;
        }

        function showDistrictDetail(district) {
            const card = document.getElementById('district-detail-card');
            const nameEl = document.getElementById('detail-district-name');
            const listEl = document.getElementById('detail-villages-list');
            
            nameEl.innerText = `Kecamatan ${district.name}`;
            listEl.innerHTML = '';
            
            const villages = district.villages || [];
            if (villages.length === 0) {
                listEl.innerHTML = '<div class="p-3 text-center text-slate-400">Tidak ada kelurahan/desa</div>';
            } else {
                villages.forEach(village => {
                    const row = document.createElement('div');
                    row.className = 'p-3 flex justify-between items-center';
                    row.innerHTML = `
                        <span class="font-medium text-slate-800">${village.name}</span>
                        <span class="font-mono text-slate-400 data-text">${village.postal_code || '-'}</span>
                    `;
                    listEl.appendChild(row);
                });
            }
            
            card.classList.remove('hidden');
        }
    });
</script>
@endsection
