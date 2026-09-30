{{-- Header + tombol Import/Tambah. Tombolnya digerbangi permission masing-masing
     (customers.import.view / customers.create), jadi aman ikut di halaman Putus &
     Gagal juga — role yang cuma boleh melihat arsip tidak melihatnya. --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">{{ $pageTitle }}</h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola data pelanggan, jaringan distribusi, status layanan internet, dan penagihan.</p>
    </div>
</div>
