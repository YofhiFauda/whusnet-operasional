
    /*
     * Lucide inline SVG.
     * Semua ikon 24x24 stroke-based, kecuali WhatsApp yang memang glyph brand
     * (filled) dan tidak ada di Lucide.
     *
     * Ikon disuntik lewat placeholder <span data-icon="nama">, bukan ditulis
     * langsung sebagai <svg> di markup — supaya baris markup tetap pendek dan
     * ikon yang sama tidak diduplikasi puluhan kali.
     */
    const LUCIDE = {
        'x':              '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'menu':           '<path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>',
        'chevron-down':   '<path d="m6 9 6 6 6-6"/>',
        'chevron-right':  '<path d="m9 18 6-6-6-6"/>',
        'chevron-left':   '<path d="m15 18-6-6 6-6"/>',
        'chart-pie':      '<path d="M21 12c.552 0 1.005-.449.95-.998a10 10 0 0 0-8.953-8.951c-.55-.055-.998.398-.998.95v8a1 1 0 0 0 1 1z"/><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>',
        'users':          '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus':      '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'user-cog':       '<circle cx="18" cy="15" r="3"/><circle cx="9" cy="7" r="4"/><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="m21.7 16.4-.9-.3"/><path d="m15.2 13.9-.9-.3"/><path d="m16.6 18.7.3-.9"/><path d="m19.1 12.2.3-.9"/><path d="m19.6 18.7-.4-1"/><path d="m16.8 12.3-.4-1"/><path d="m14.3 16.6 1-.4"/><path d="m20.7 13.8 1-.4"/>',
        'circle-user':    '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="10" r="3"/><path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/>',
        'receipt-text':   '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M14 8H8"/><path d="M16 12H8"/><path d="M13 16H8"/>',
        'wallet':         '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        'list-checks':    '<path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>',
        'headset':        '<path d="M3 11h3a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M21 11h-3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1z"/><path d="M3 11a9 9 0 1 1 18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/>',
        'database':       '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
        'chart-line':     '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/>',
        'log-out':        '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
        'house':          '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'clock':          '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'maximize':       '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'minimize':       '<path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/>',
        'bell':           '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        'upload':         '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'plus':           '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'search':         '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'rotate-cw':      '<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>',
        'folder-open':    '<path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>',
        'square-pen':     '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>',
        'circle-check':   '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        // Brand glyph — filled, bukan stroke. Ditandai lewat FILLED_ICONS.
        'whatsapp':       '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.886-9.885 9.886m8.413-18.297A11.8 11.8 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.9 11.9 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413"/>'
    };

    const FILLED_ICONS = ['whatsapp'];

    /**
     * Suntik SVG ke setiap <span data-icon> di dalam root.
     * Idempoten — span yang sudah terisi dilewati, jadi aman dipanggil ulang
     * setiap kali tabel di-render.
     */
    function renderIcons(root = document) {
        root.querySelectorAll('[data-icon]').forEach(el => {
            if (el.firstElementChild) return;

            const name = el.dataset.icon;
            const body = LUCIDE[name];
            if (!body) return;

            const filled = FILLED_ICONS.includes(name);
            el.innerHTML =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" ' +
                'width="1em" height="1em" aria-hidden="true" ' +
                (filled
                    ? 'fill="currentColor">'
                    : 'fill="none" stroke="currentColor" stroke-width="2" ' +
                      'stroke-linecap="round" stroke-linejoin="round">') +
                body + '</svg>';
        });
    }

    /** Ganti ikon sebuah placeholder lalu render ulang. */
    function setIcon(el, name) {
        if (!el) return;
        el.dataset.icon = name;
        el.innerHTML = '';
        renderIcons(el.parentElement || document);
    }

    // Initial dataset matching provided image mockup
    let rawCustomers = [
        { id: 'C1X4ARQ030804', name: 'Ardiyanto Cahyo Nugroho', pop: 'Jetis', desa: 'Jetis', paket: 'PK000003 - Up to 5 Mbps 165k', hp: '628563652226', completeness: 83, status: 'Active', connection: true },
        { id: 'C1X4CRQ030805', name: 'Hanif Saifulloh', pop: 'Jetis', desa: 'Winong', paket: 'PK000003 - Up to 5 Mbps 165k', hp: '6285855033646', completeness: 83, status: 'Active', connection: true },
        { id: 'C1X4CRQ030806', name: 'Siti Juariyah', pop: 'Jetis', desa: 'Wonoketro', paket: 'PK000015 - Barbar UpTo 10M', hp: '628523330575', completeness: 83, status: 'Active', connection: true },
        { id: 'J06RQ00306', name: 'Wahyu Aulia Zahro', pop: 'Sandya', desa: 'Banyudono', paket: 'PK000002 - Up to 2 Mbps 110k', hp: '6282233357153', completeness: 75, status: 'Active', connection: true },
        { id: 'C1X4CRQ030807', name: 'Luluk Afiah Al Farida', pop: 'Jetis', desa: 'Wonoketro', paket: 'PK000015 - Barbar UpTo 10M', hp: '6285815406169', completeness: 83, status: 'Active', connection: true },
        { id: 'J06RQ00308', name: 'Purnama Ayu Lestari Putri', pop: 'Sandya', desa: 'Surodikraman', paket: 'PK000002 - Up to 2 Mbps 110k', hp: '6281774844126', completeness: 83, status: 'Active', connection: true },
        { id: 'C1X4BRQ030810', name: 'Yuni Astuti', pop: 'Jetis', desa: 'Wonoketro', paket: 'PK000015 - Barbar UpTo 10M', hp: '6285233652416', completeness: 75, status: 'Active', connection: true },
        { id: 'C08RQ003011', name: 'Syahrulia Enggewati', pop: 'Jetis', desa: 'Wonoketro', paket: 'PK000004 - Up to 10 Mbps 198k', hp: '628233677890', completeness: 83, status: 'Active', connection: true },
        { id: 'C1X4ARQ030812', name: 'Wahyu Yogi Nugroho', pop: 'Jetis', desa: 'Wonoketro', paket: 'PK000004 - Up to 10 Mbps 198k', hp: '6285714197717', completeness: 83, status: 'Active', connection: true },
        { id: 'J1X6ARQ030812', name: 'Ragil Cahya Adi Prastya', pop: 'Sandya', desa: 'Kauman', paket: 'PK000001 - default', hp: '62895397947938', completeness: 75, status: 'Active', connection: true }
    ];

    // Fullscreen toggle function for full view screenshot reference
    function toggleFullScreen() {
        const icon = document.getElementById('fsIcon');
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().then(() => {
                setIcon(icon, 'minimize');
                showToast('Mode Fullscreen Aktif. Tekan Esc untuk keluar.');
            }).catch(() => {
                showToast('Klik tombol Preview di pojok kanan atas editor untuk tampilan fullscreen penuh.');
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().then(() => {
                    setIcon(icon, 'maximize');
                });
            }
        }
    }

    let currentFilterTab = 'all';

    // Render Table Row Elements
    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';

        if(data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="py-12 text-center text-slate-400">
                        <span data-icon="folder-open" class="text-3xl mb-2 mx-auto"></span>
                        Tidak ada data pelanggan yang sesuai dengan filter.
                    </td>
                </tr>
            `;
            document.getElementById('totalCount').innerText = 0;
            renderIcons(tbody);
            return;
        }

        document.getElementById('totalCount').innerText = data.length.toLocaleString('id-ID');

        data.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-skybrand-50/40 transition-colors group";

            // Status Badge Styling
            const isSuspend = item.status === 'Suspend';
            const statusBadgeClass = isSuspend 
                ? 'bg-amber-50 text-amber-700 border-amber-200' 
                : 'bg-emerald-50 text-emerald-700 border-emerald-200';

            // Kelengkapan Progress Dots
            const dotCount = 5;
            const filledDots = Math.round((item.completeness / 100) * dotCount);
            let dotsHtml = '';
            for(let i=0; i<dotCount; i++) {
                if(i < filledDots) {
                    dotsHtml += `<span class="w-2 h-2 rounded-full bg-emerald-500"></span>`;
                } else {
                    dotsHtml += `<span class="w-2 h-2 rounded-full bg-slate-200"></span>`;
                }
            }

            tr.innerHTML = `
                <td class="py-3.5 px-4 text-center text-slate-400 font-medium">${index + 1}</td>
                <td class="py-3.5 px-4 font-mono text-[11px] font-semibold text-skybrand-600">${item.id}</td>
                <td class="py-3.5 px-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-slate-100 text-slate-600 font-bold text-[10px] flex items-center justify-center flex-shrink-0 border border-slate-200">
                            ${getInitials(item.name)}
                        </div>
                        <span class="font-medium text-slate-900 group-hover:text-skybrand-700 transition-colors">${item.name}</span>
                    </div>
                </td>
                <td class="py-3.5 px-4">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-medium">${item.pop}</span>
                </td>
                <td class="py-3.5 px-4 text-slate-600">${item.desa}</td>
                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600">${item.paket}</td>
                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600">
                    <a href="https://wa.me/${item.hp}" target="_blank" class="hover:text-emerald-600 flex items-center gap-1">
                        <span data-icon="whatsapp" class="text-emerald-500 text-sm"></span>
                        <span>${item.hp}</span>
                    </a>
                </td>
                <td class="py-3.5 px-4 text-center">
                    <div class="inline-flex flex-col items-center gap-1">
                        <span class="text-[10px] font-bold text-slate-500">${item.completeness}%</span>
                        <div class="flex items-center gap-1">
                            ${dotsHtml}
                        </div>
                    </div>
                </td>
                <td class="py-3.5 px-4 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${statusBadgeClass}">
                        <span class="w-1.5 h-1.5 rounded-full ${isSuspend ? 'bg-amber-500' : 'bg-emerald-500'} mr-1.5"></span>
                        ${item.status}
                    </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                    <!-- Toggle Switch -->
                    <button onclick="toggleConnection(${index})" class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${item.connection ? 'bg-skybrand-500' : 'bg-slate-300'}">
                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-xs ring-0 transition duration-200 ease-in-out ${item.connection ? 'translate-x-4' : 'translate-x-0'}"></span>
                    </button>
                </td>
                <td class="py-3.5 px-4 text-center">
                    <div class="relative inline-block text-left">
                        <button onclick="alert('Menu Aksi untuk ' + '${item.name}')" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-skybrand-600 font-semibold text-xs flex items-center gap-1.5 transition-all shadow-2xs">
                            <span data-icon="square-pen" class="text-[11px]"></span>
                            <span>Action</span>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Baris dibuat lewat innerHTML, jadi placeholder ikon di dalamnya
        // masih kosong sampai di-render di sini.
        renderIcons(tbody);
    }

    // Helper: Initials generator
    function getInitials(name) {
        return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    }

    // Filter Logic
    function filterTable() {
        const searchVal = document.getElementById('searchInput').value.toLowerCase();
        const popVal = document.getElementById('filterPop').value.toLowerCase();
        const kecamatanVal = document.getElementById('filterKecamatan').value.toLowerCase();
        const paketVal = document.getElementById('filterPaket').value.toLowerCase();
        const kelengkapanVal = document.getElementById('filterKelengkapan').value;

        const filtered = rawCustomers.filter(item => {
            // Tab Status filter
            if(currentFilterTab === 'active' && item.status !== 'Active') return false;
            if(currentFilterTab === 'suspend' && item.status !== 'Suspend') return false;

            // Search query
            const matchesSearch = item.name.toLowerCase().includes(searchVal) || 
                                  item.id.toLowerCase().includes(searchVal) ||
                                  item.hp.includes(searchVal) ||
                                  item.desa.toLowerCase().includes(searchVal);

            // Select filters
            const matchesPop = !popVal || item.pop.toLowerCase() === popVal;
            const matchesKecamatan = !kecamatanVal || item.desa.toLowerCase() === kecamatanVal;
            const matchesPaket = !paketVal || item.paket.toLowerCase().includes(paketVal);
            const matchesKelengkapan = !kelengkapanVal || item.completeness.toString() === kelengkapanVal;

            return matchesSearch && matchesPop && matchesKecamatan && matchesPaket && matchesKelengkapan;
        });

        renderTable(filtered);
    }

    // Tab switcher
    function setActiveTab(tab) {
        currentFilterTab = tab;
        const tabs = ['all', 'active', 'suspend'];
        
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-${t}`);
            if(t === tab) {
                btn.className = "px-4 py-2 rounded-lg bg-white text-skybrand-700 shadow-xs transition-all flex items-center gap-2";
            } else {
                btn.className = "px-4 py-2 rounded-lg text-slate-600 hover:text-slate-900 transition-all flex items-center gap-2";
            }
        });

        filterTable();
    }

    // Toggle Connection Action
    function toggleConnection(index) {
        rawCustomers[index].connection = !rawCustomers[index].connection;
        rawCustomers[index].status = rawCustomers[index].connection ? 'Active' : 'Suspend';
        
        showToast(`Status koneksi ${rawCustomers[index].name} diperbarui!`);
        updateStats();
        filterTable();
    }

    // Reset Filters
    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterPop').value = '';
        document.getElementById('filterKecamatan').value = '';
        document.getElementById('filterPaket').value = '';
        document.getElementById('filterKelengkapan').value = '';
        setActiveTab('all');
        showToast('Filter telah di-reset ke standar.');
    }

    // Stats Counter Dynamic Updater
    function updateStats() {
        const total = rawCustomers.length;
        const active = rawCustomers.filter(c => c.status === 'Active').length;
        const suspend = rawCustomers.filter(c => c.status === 'Suspend').length;

        // Locale id-ID — pemisah ribuan titik, konsisten dengan format Rupiah.
        document.getElementById('stat-total').innerText = total.toLocaleString('id-ID');
        document.getElementById('stat-active').innerText = active.toLocaleString('id-ID');
        document.getElementById('stat-suspend').innerText = suspend.toLocaleString('id-ID');
    }

    // Sidebar Mobile Toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        sidebar.classList.toggle('-translate-x-full');
        backdrop.classList.toggle('hidden');
    }

    // Submenu Toggle
    function toggleSubmenu(id) {
        const menu = document.getElementById(id);
        const arrow = document.getElementById('pelanggan-arrow');
        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
    }

    // Modal Control
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }

    function openImportModal() {
        showToast('Fitur upload berkas import disiapkan!');
    }

    function handleAddCustomer(e) {
        e.preventDefault();
        const newObj = {
            id: document.getElementById('newId').value,
            name: document.getElementById('newName').value,
            pop: document.getElementById('newPop').value,
            desa: document.getElementById('newDesa').value,
            paket: document.getElementById('newPaket').value,
            hp: document.getElementById('newHp').value,
            completeness: 83,
            status: 'Active',
            connection: true
        };

        rawCustomers.unshift(newObj);
        updateStats();
        filterTable();
        closeAddModal();
        showToast('Pelanggan baru berhasil ditambahkan!');
    }

    // Toast Notification logic
    function showToast(msg) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMsg').innerText = msg;
        toast.classList.remove('translate-y-20', 'opacity-0');
        
        setTimeout(() => {
            toast.classList.add('translate-y-20', 'opacity-0');
        }, 3000);
    }

    // Esc keluar dari fullscreen tanpa lewat tombol — ikon harus ikut balik.
    document.addEventListener('fullscreenchange', () => {
        setIcon(document.getElementById('fsIcon'),
                document.fullscreenElement ? 'minimize' : 'maximize');
    });

    // Initialize Page
    window.onload = function() {
        renderIcons();
        renderTable(rawCustomers);
        updateStats();
    };
