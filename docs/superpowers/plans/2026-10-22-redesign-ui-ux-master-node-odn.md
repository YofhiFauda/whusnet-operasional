# Redesign UI/UX Master Node Jaringan & ODN Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan perombakan UI/UX antarmuka Master Node Jaringan & ODN WHUSNET agar modern, bersih, intuitif, berarsitektur Single Panel (Card Budget = 1), beradius seragam 8px (`rounded-lg`), berkontras sempurna pada tema Light & Dark, serta responsif di desktop, tablet, dan smartphone dengan Mobile Segmented Switcher.

**Architecture:** Menerapkan pola antarmuka Type A Operational Workspace sesuai `design-system/whusnet-operasional/Design.md` (Naked Page Header, Flat Summary KPI Strip, Split Dual-Pane Workspace). Pada mobile & tablet (<1024px), interaksi ditingkatkan dengan Alpine.js Master-Detail Segmented Switcher sehingga teknisi lapangan tidak perlu melakukan scroll vertikal panjang untuk mengakses detail port ODP. Seluruh token warna dialihkan ke palet semantik resmi: Solid Sky Blue (`#0284C7`), Slate Neutral (`#0F172A`/`#F8FAFC`), dan JetBrains Mono (`font-mono`) untuk nilai teknis.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS v4, Alpine.js, Lucide SVG Icons, PHPUnit 12.

**Spec:** `design-system/whusnet-operasional/Design.md` (khususnya §1.3 Page Type System, §1.5 Universal Prohibited Patterns, §2 Design Tokens, §9 Halaman POP & Node Jaringan).

## Global Constraints

- **Single Panel Architecture (Card Budget = 1)**: Dilarang membungkus Page Header, Summary Strip, dan Filter Bar dengan card terpisah (wajib *naked*).
- **Radius 8px Seragam (`rounded-lg`)**: Seluruh kontainer, modal, kartu port, input, select, dan tombol wajib menggunakan `rounded-lg` (8px). Dilarang keras memakai `rounded-2xl` atau `rounded-xl`.
- **Warna Aksen Solid (No Gradients)**: Tombol utama dan highlight menggunakan warna solid `bg-sky-600 hover:bg-sky-700` (`#0284C7`), dilarang menggunakan gradien (`bg-gradient-to-r`).
- **Font Monospace Konsisten**: Kode node (`ODP-PWK-001`), nomor port (`Port 08`), koordinat geospasial (`latitude/longitude`), dan CID pelanggan wajib menggunakan `font-mono` (`JetBrains Mono`, `tabular-nums`).
- **Kepatuhan Light & Dark Mode**: Setiap permukaan dan teks wajib berpasangan dengan token gelap atau menggunakan kelas semantik (`bg-surface`, `bg-surface-muted`, `border-border`, `text-text-main`, `text-text-muted`). Dilarang ada `bg-white` atau `border-slate-200` telanjang tanpa pendamping dark mode.
- **Tanpa Emoji sebagai Icon**: Seluruh representasi status dan tipe node menggunakan icon SVG (Lucide), bukan karakter emoji.

## Review Focus

- Mobile/tablet viewport switching: Memastikan pemilihan node pada pohon secara otomatis membuka tab inspector pada layar kecil (<1024px) dengan tombol kembali yang jelas.
- Touch target port: Memastikan kartu port fisik memiliki target sentuh minimal 44px agar mudah di-tap oleh teknisi lapangan.
- Red Zone warning integrity: Memastikan ambang batas okupansi $\ge 80\%$ tampil tegas melalui badge dan progress bar tanpa mewarnai seluruh background section.
- Kontras Dark Mode: Memastikan teks sekunder, nomor port, dan koordinat memiliki kontras minimal 4.5:1 (WCAG AA) pada mode gelap.
- Regresi hierarki OLT Chassis / Slot / PON: Memastikan sub-inspector untuk perangkat modular (OLT chassis) tetap terhubung mulus tanpa merusak state Alpine.js.

---

### Task 1: Standardisasi Radius & Token Warna Semantik (Fix Radius & Dark Mode Surface)

**Files:**
- Modify: `resources/views/network/nodes/index.blade.php:235-245,325-395`
- Modify: `resources/views/network/nodes/show.blade.php:1-90`
- Test: `tests/Feature/OdpNetworkManagementTest.php`

**Interfaces:**
- Consumes: Tailwind CSS theme tokens (`bg-surface`, `bg-surface-muted`, `border-border`, `text-text-main`, `text-text-muted`, `sky-600`).
- Produces: Komponen inspector dan container dengan border radius seragam 8px (`rounded-lg`) dan eliminasi style ad-hoc `bg-white dark:bg-slate-800/90 rounded-2xl`.

- [ ] **Step 1: Audit dan ganti kelas `rounded-2xl` & `rounded-xl` di inspector `index.blade.php`**
  Ubah kontainer `#node-inspector` dari:
  `<section class="flex-1 w-full bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/90 dark:border-slate-700 shadow-sm flex flex-col overflow-hidden" id="node-inspector">`
  Menjadi:
  `<section class="flex-1 w-full bg-surface rounded-lg border border-border shadow-2xs flex flex-col overflow-hidden" id="node-inspector">`

- [ ] **Step 2: Bersihkan token warna ad-hoc pada `resources/views/network/nodes/show.blade.php`**
  Ganti kontainer beradius 16px (`rounded-2xl`) dan border ad-hoc menjadi `rounded-lg border-border bg-surface`.
  Pastikan tombol aksi kembali dan hapus menggunakan `rounded-lg` dengan skema `bg-surface border-border hover:bg-surface-muted`.

- [ ] **Step 3: Jalankan test feature untuk memastikan struktur view tidak menyebabkan error Blade**
  Run: `php artisan test tests/Feature/OdpNetworkManagementTest.php --filter=test_index_menampilkan_pohon_node_dengan_anak_bertingkat`
  Expected: PASS

- [ ] **Step 4: Commit perubahan radius & surface token**
  ```bash
  git add resources/views/network/nodes/index.blade.php resources/views/network/nodes/show.blade.php
  git commit -m "style(network-nodes): standardize radius to 8px and enforce semantic surface tokens"
  ```

---

### Task 2: Implementasi Master-Detail Segmented Switcher untuk Mobile & Tablet

**Files:**
- Modify: `resources/views/network/nodes/index.blade.php:36-60,150-165,235-265`
- Test: `tests/Feature/OdpNetworkManagementTest.php`

**Interfaces:**
- Consumes: Alpine.js reactive state (`activeMobileTab: 'tree' | 'inspector'`).
- Produces: UI responsif multi-device: split-pane pada desktop (`lg:flex`), dan segmented view switcher pada mobile/tablet (`< lg`) yang berpindah otomatis saat node diklik.

- [ ] **Step 1: Tambahkan state `activeMobileTab` pada inisialisasi Alpine.js `index.blade.php`**
  Tambahkan properti `activeMobileTab: 'tree'` dan fungsi helper `selectNode(id)`:
  ```javascript
  selectNode(id) {
      this.selectedNodeId = id;
      this.selectedSlotId = null;
      this.selectedPonId = null;
      if (window.innerWidth < 1024) {
          this.activeMobileTab = 'inspector';
      }
  }
  ```

- [ ] **Step 2: Sisipkan Segmented Switcher Bar di bawah Summary Strip (hanya tampil di layar `< lg`)**
  Terapkan segmented control pill:
  ```html
  <div class="flex lg:hidden bg-surface-muted p-1 rounded-lg border border-border mb-4">
      <button type="button" @click="activeMobileTab = 'tree'"
              :class="activeMobileTab === 'tree' ? 'bg-surface text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main'"
              class="flex-1 py-2 text-xs rounded-md text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/></svg>
          <span>Pohon Topologi Node</span>
      </button>
      <button type="button" @click="activeMobileTab = 'inspector'"
              :class="activeMobileTab === 'inspector' ? 'bg-surface text-primary font-bold shadow-xs' : 'text-text-muted hover:text-text-main'"
              class="flex-1 py-2 text-xs rounded-md text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <span>Detail &amp; Port ODP</span>
      </button>
  </div>
  ```

- [ ] **Step 3: Pasang conditional display pada Panel Kiri dan Panel Kanan**
  - Panel Kiri: `:class="{ 'hidden lg:flex': activeMobileTab !== 'tree', 'flex': activeMobileTab === 'tree' }"`
  - Panel Kanan: `:class="{ 'hidden lg:flex': activeMobileTab !== 'inspector', 'flex': activeMobileTab === 'inspector' }"`
  - Di header Panel Kanan, tambahkan tombol kembali untuk mobile:
    ```html
    <button type="button" @click="activeMobileTab = 'tree'" class="lg:hidden inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
        &larr; Pilih Node Lain
    </button>
    ```

- [ ] **Step 4: Jalankan pengujian browser / test feature untuk memastikan event trigger bekerja**
  Run: `php artisan test tests/Feature/OdpNetworkManagementTest.php`
  Expected: PASS (27 tests)

- [ ] **Step 5: Commit fitur responsif mobile switcher**
  ```bash
  git add resources/views/network/nodes/index.blade.php
  git commit -m "feat(network-nodes): add mobile/tablet master-detail segmented view switcher"
  ```

---

### Task 3: Refinement Topology Tree Navigator & Hierarchy Items

**Files:**
- Modify: `resources/views/network/nodes/_hierarchy-item.blade.php:1-120`
- Modify: `resources/views/network/nodes/_tree-node.blade.php:1-100`
- Test: `tests/Feature/OdpNetworkManagementTest.php`

**Interfaces:**
- Consumes: Node model data (`code`, `name`, `nodeType`, `ports_count`, `used_ports_count`).
- Produces: Baris pohon dengan indentasi vertikal 1px rapi, badge kapasitas mini (`12/16`), icon SVG semantik per tipe node, dan active state non-intrusif (`bg-sky-50 dark:bg-sky-950/40 border-l-2 border-sky-600`).

- [ ] **Step 1: Audit icon dan hierarki warna tipe node**
  Ganti icon ad-hoc dengan SVG Lucide semantik yang seragam (Server, OLT, ODC, ODP).
  Pastikan badge kapasitas mini `used/total` menggunakan font monospace dengan format:
  `<span class="text-[10px] font-mono font-medium px-1.5 py-0.5 rounded bg-surface border border-border tabular-nums">...</span>`

- [ ] **Step 2: Perbaiki active state baris pohon**
  Pastikan baris node yang dipilih (`selectedNodeId === node.id`) memiliki visual feedback yang jelas:
  `bg-sky-50/70 dark:bg-sky-950/40 text-primary border-l-2 border-primary` tanpa layout shifting.

- [ ] **Step 3: Hubungkan klik baris pohon dengan method `selectNode(node.id)`**
  Ganti `@click="selectedNodeId = ..."` dengan `@click="selectNode({{ $node->id }})"` sehingga navigasi di mobile langsung berpindah tab ke inspector.

- [ ] **Step 4: Jalankan test hierarki**
  Run: `php artisan test tests/Feature/OdpNetworkManagementTest.php --filter=test_index_menampilkan_server_di_atas_pop_lalu_cabang_mini_pop_dan_olt`
  Expected: PASS

- [ ] **Step 5: Commit pembaruan pohon topologi**
  ```bash
  git add resources/views/network/nodes/_hierarchy-item.blade.php resources/views/network/nodes/_tree-node.blade.php
  git commit -m "refactor(network-nodes): polish topology tree styling and mini capacity indicators"
  ```

---

### Task 4: Ergonomi Hardware Patch Panel Matrix & Indikator Port

**Files:**
- Modify: `resources/views/network/nodes/index.blade.php:440-570`
- Test: `tests/Feature/OdpNetworkManagementTest.php`

**Interfaces:**
- Consumes: Port relationship (`ports`, `activeAssignment`, `is_used`).
- Produces: Grid patch panel responsif dengan target sentuh $\ge 44\text{px}$, filter status port (`Semua`, `Tersedia`, `Terpakai`), dan pembeda visual tegas antara Port Pelanggan vs Sub-Node Uplink vs Port Rusak.

- [ ] **Step 1: Optimasi ukuran dan touch-target kartu port**
  Ubah kartu port fisik agar memiliki tinggi minimum `min-h-[84px]`, padding `p-3.5`, dan transisi hover yang halus (`transition-colors duration-150`).
  Pastikan font nomor port menggunakan `font-mono font-bold text-xs tabular-nums text-primary`.

- [ ] **Step 2: Pertegas pembeda visual penghuni port**
  - Jika port terhubung ke **Pelanggan**: tampilkan nama pelanggan (`font-semibold text-xs text-text-main`), kode CID (`font-mono text-[11px] text-text-muted`), dan badge status hijau/merah.
  - Jika port terhubung ke **Sub-Node (Uplink)**: tampilkan ikon cabang $\rightarrow$ dengan kode sub-node dalam warna `text-sky-600 dark:text-sky-400 font-mono`.
  - Jika port **Tersedia**: tampilkan border putus-putus/halus dengan label `+ Sambungkan` yang muncul jelas saat hover/focus.

- [ ] **Step 3: Periksa kontras Dark Mode pada kartu port**
  Pastikan kombinasi warna pada mode gelap tidak pudar:
  `dark:bg-surface-muted/40 dark:border-border dark:text-text-main dark:hover:border-sky-500`.

- [ ] **Step 4: Jalankan test port management**
  Run: `php artisan test tests/Feature/OdpNetworkManagementTest.php --filter=test_kapasitas_odp_bisa_ditambah_tanpa_menghapus_port_lama`
  Expected: PASS

- [ ] **Step 5: Commit penyempurnaan patch panel**
  ```bash
  git add resources/views/network/nodes/index.blade.php
  git commit -m "feat(network-nodes): enhance hardware patch panel grid and touch target ergonomics"
  ```

---

### Task 5: Harmonisasi Slot / PON Inspector & Detail View (`show.blade.php`)

**Files:**
- Modify: `resources/views/network/nodes/_slot-inspector.blade.php`
- Modify: `resources/views/network/nodes/_pon-inspector.blade.php`
- Modify: `resources/views/network/nodes/_device-slots.blade.php`
- Modify: `resources/views/network/nodes/show.blade.php`
- Test: `tests/Feature/OdpNetworkManagementTest.php`
- Test: `tests/Feature/Warehouse/NetworkNodeAssetViewTest.php`

**Interfaces:**
- Consumes: OLT Chassis slots (`infrastructureSlots`), PON ports (`ports`), dan data aset terpasang.
- Produces: Panel inspeksi modular beradius 8px (`rounded-lg`), bebas dari double-card nesting, dan selaras dengan `Design.md`.

- [ ] **Step 1: Periksa dan standarisasi radius 8px pada `_slot-inspector.blade.php` dan `_pon-inspector.blade.php`**
  Pastikan header slot, card PON, dan tabel port di dalam inspector menggunakan `rounded-lg` dan `border-border`.
  Hapus background kontainer bersarang yang tidak diperlukan.

- [ ] **Step 2: Harmonisasi `show.blade.php`**
  Pastikan ringkasan lokasi fisik dan kartu aset terpasang di `show.blade.php` mengikuti gaya Single Panel Type B dengan divider 1px (`border-border`), font mono pada koordinat Google Maps, dan tombol aksi beradius 8px.

- [ ] **Step 3: Jalankan test aset dan slot**
  Run: `php artisan test tests/Feature/Warehouse/NetworkNodeAssetViewTest.php`
  Expected: PASS (4 tests)

- [ ] **Step 4: Commit perapian slot/PON dan show view**
  ```bash
  git add resources/views/network/nodes/_slot-inspector.blade.php resources/views/network/nodes/_pon-inspector.blade.php resources/views/network/nodes/_device-slots.blade.php resources/views/network/nodes/show.blade.php
  git commit -m "style(network-nodes): harmonize slot/pon inspector and show view with enterprise design tokens"
  ```

---

### Task 6: Verifikasi Akhir, Format Kode, dan Validasi Regresi

**Files:**
- Format: `resources/views/network/nodes/`
- Test: `tests/Feature/OdpNetworkManagementTest.php`
- Test: `tests/Feature/Warehouse/NetworkNodeAssetViewTest.php`
- Test: `tests/Feature/Warehouse/AuthorizesNetworkNodeScopeTest.php`

**Interfaces:**
- Consumes: Seluruh komponen view yang telah dimodifikasi.
- Produces: Tampilan Master Node yang bersih, responsif, terverifikasi bebas regresi, dan terformat rapi sesuai Laravel Pint.

- [ ] **Step 1: Jalankan Laravel Pint untuk memastikan format kode Blade/PHP sesuai standar**
  Run: `vendor/bin/pint --format agent`
  Expected: Clean exit tanpa linting error.

- [ ] **Step 2: Jalankan seluruh test suite terkait modul Network Node**
  Run: `php artisan test tests/Feature/OdpNetworkManagementTest.php tests/Feature/Warehouse/NetworkNodeAssetViewTest.php tests/Feature/Warehouse/AuthorizesNetworkNodeScopeTest.php`
  Expected: All PASS.

- [ ] **Step 3: Uji build aset frontend Vite**
  Run: `npm run build`
  Expected: Build sukses tanpa peringatan sintaks Tailwind atau CSS invalid.

- [ ] **Step 4: Commit hasil verifikasi akhir**
  ```bash
  git commit --allow-empty -m "chore(network-nodes): complete ui/ux redesign verification and code formatting"
  ```
