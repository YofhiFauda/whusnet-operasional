<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 dari docs/plan/ANALISA_INDEX_DATABASE.md — index P0/P1/P2 + hapus
 * index redundan di `tasks`.
 *
 * Sengaja ditulis sebagai migration tambahan, BUKAN diedit ke migration
 * `create_*` aslinya seperti disarankan §14 dokumen. Alasannya: mengedit
 * migration yang sudah tercatat di tabel `migrations` mewajibkan
 * `migrate:fresh`, dan isi DB development saat ini 100% hasil import legacy
 * yang mahal untuk diulang. Kalau nanti memang mau skema yang bisa dibaca
 * sekali jalan, gabungkan file ini ke migration asalnya bersamaan dengan
 * import ulang yang memang sudah terjadwal.
 *
 * Prasyarat yang sudah dikerjakan lebih dulu dan tidak boleh dibalik
 * urutannya: Fase 2 mengganti seluruh `whereDate()` jadi perbandingan rentang.
 * Selama kolom masih dibungkus `DATE(kolom)`, index tanggal di bawah ini tidak
 * akan pernah dipilih optimizer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── P0 — akar masalah kuadratik ──────────────────────────────

        Schema::table('invoices', function (Blueprint $table) {
            // Melayani KETIGA query guard duplikat sekaligus:
            // InvoiceObserver::creating() (2 query) + GenerateMonthlyInvoicesCommand.
            // Guard kedua memakai prefix (customer_id, billing_period).
            //
            // NON-UNIQUE, dan itu disengaja. Migration 2026_07_21_164556 sudah
            // memutuskan unique (customer_id, invoice_type, billing_period) tidak
            // boleh dipasang: invoice berstatus `batal` akan menempati slot
            // periode selamanya dan memblokir penggantinya, sementara MySQL tidak
            // punya partial index. Keputusan itu tetap berlaku — index ini hanya
            // mempercepat lookup, tidak menolak apa pun.
            $table->index(['customer_id', 'billing_period', 'invoice_type'], 'invoices_customer_period_type_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            // Tabel yang paling cepat tumbuh di sistem, tapi sebelumnya cuma
            // punya PRIMARY + FK user_id.
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_idx');
            $table->index(['module', 'action', 'created_at'], 'audit_logs_module_action_idx');
            $table->index('created_at', 'audit_logs_created_at_idx');
        });

        // Kolom legacy: seluruh jalur import melakukan lookup PER BARIS ke
        // kolom-kolom ini. Tanpa index, import N baris ke tabel berisi M baris
        // = N × M row-read, dan biayanya naik kuadratik seiring import berjalan.
        // Semua non-unique — kolomnya nullable dan data legacy punya duplikat
        // historis yang sah.
        Schema::table('customers', function (Blueprint $table) {
            $table->index('old_customer_id', 'customers_old_customer_id_idx');
            $table->index('old_request_id', 'customers_old_request_id_idx');
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->index('old_request_id', 'customer_services_old_request_id_idx');
            $table->index('old_cost_id', 'customer_services_old_cost_id_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('old_invoice_id', 'invoices_old_invoice_id_idx');
            $table->index('old_cost_id', 'invoices_old_cost_id_idx');
            $table->index('old_request_id', 'invoices_old_request_id_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('old_payment_id', 'payments_old_payment_id_idx');
            $table->index('old_transaction_id', 'payments_old_transaction_id_idx');
            $table->index('old_request_id', 'payments_old_request_id_idx');
        });

        Schema::table('internet_packages', function (Blueprint $table) {
            $table->index('old_package_id', 'internet_packages_old_package_id_idx');
        });

        // ── P1 — list & laporan ──────────────────────────────────────
        // Urutan kolom penting: pop_id di depan karena scope POP selalu ada
        // (HasPopScope dipakai di hampir semua list), status/tanggal menyusul
        // karena opsional. Index (status, pop_id) TIDAK setara dan tidak akan
        // terpakai saat filter status kosong.

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['pop_id', 'status'], 'customers_pop_status_idx');
            $table->index(['pop_id', 'data_completeness_status'], 'customers_pop_completeness_idx');
            // Antrean survey FOP: filter status, urut created_at terlama di atas.
            $table->index(['status', 'created_at'], 'customers_status_created_idx');
            $table->index(['pop_id', 'registration_date'], 'customers_pop_registration_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['pop_id', 'billing_period'], 'invoices_pop_period_idx');
            $table->index(['pop_id', 'issue_date'], 'invoices_pop_issue_idx');
            $table->index(['invoice_status', 'due_date'], 'invoices_status_due_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['pop_id', 'payment_date'], 'payments_pop_date_idx');
            $table->index(['payment_status', 'payment_date'], 'payments_status_date_idx');
            $table->index(['customer_id', 'payment_date'], 'payments_customer_date_idx');
        });

        // ── P2 — operasional lapangan ────────────────────────────────

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['task_type', 'status', 'completed_at'], 'tasks_type_status_completed_idx');
        });

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->index(['pop_id', 'task_date', 'status'], 'fop_tasks_pop_date_status_idx');
            $table->index(['customer_id', 'category', 'status'], 'fop_tasks_customer_cat_status_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'notifiable_type', 'read_at'], 'notifications_unread_idx');
        });

        Schema::table('customer_status_logs', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'customer_status_logs_customer_created_idx');
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->index(['customer_id', 'service_status'], 'customer_services_customer_status_idx');
        });

        // ── Hapus index redundan di `tasks` ──────────────────────────
        // Keempatnya prefix persis dari composite yang sudah ada. Optimizer
        // tidak akan pernah memilih index yang lebih sempit ketika superset-nya
        // tersedia, tapi setiap INSERT/UPDATE tetap membayar pemeliharaan B-tree
        // dan memakan halaman buffer pool. `tasks` ditulis sangat sering (setiap
        // start/complete/pending/cancel/reassign lewat TaskService, plus
        // sinkronisasi balik dari FopTask).
        //
        // tasks.pop_id dan tasks.customer_id punya FK constraint, jadi MySQL
        // menuntut ada index yang menopangnya. Aman karena composite penggantinya
        // menempatkan kolom itu di posisi paling kiri:
        //   pop_id      → tasks_pop_status_scheduled_idx (pop_id, status, scheduled_at)
        //   customer_id → tasks_customer_type_idx        (customer_id, task_type)
        //
        // DIPERTAHANKAN: tasks_scheduled_at_index & tasks_task_type_index —
        // keduanya bukan prefix index mana pun, dan task_type dipakai sendirian
        // di FopDashboardController.
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_status_index');       // ⊂ tasks_status_scheduled_idx
            $table->dropIndex('tasks_pop_id_index');       // ⊂ tasks_pop_status_scheduled_idx
            $table->dropIndex('tasks_pop_status_idx');     // ⊂ tasks_pop_status_scheduled_idx
            $table->dropIndex('tasks_customer_id_index');  // ⊂ tasks_customer_type_idx
        });
    }

    public function down(): void
    {
        // PENTING — kenapa blok ini ada, jangan dihapus.
        //
        // Saat composite di up() dipasang, MySQL menyerap index FK single-column
        // yang lama: `invoices_customer_id_foreign`, `payments_pop_id_foreign`,
        // dan seterusnya hilang, dan FK-nya kini ditopang composite yang baru
        // (leftmost-nya memang kolom FK itu). Efek sampingnya, menghapus composite
        // secara langsung ditolak MySQL dengan:
        //
        //   SQLSTATE[HY000] 1553 Cannot drop index '...': needed in a foreign key constraint
        //
        // Jadi penopang single-column-nya harus dikembalikan LEBIH DULU, baru
        // composite-nya boleh dibuang.
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('customer_id', 'invoices_customer_id_foreign');
            $table->index('pop_id', 'invoices_pop_id_foreign');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('customer_id', 'payments_customer_id_foreign');
            $table->index('pop_id', 'payments_pop_id_foreign');
        });

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->index('customer_id', 'fop_tasks_customer_id_foreign');
            $table->index('pop_id', 'fop_tasks_pop_id_foreign');
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->index('customer_id', 'customer_services_customer_id_foreign');
        });

        Schema::table('customer_status_logs', function (Blueprint $table) {
            $table->index('customer_id', 'customer_status_logs_customer_id_foreign');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('status', 'tasks_status_index');
            $table->index('pop_id', 'tasks_pop_id_index');
            $table->index(['pop_id', 'status'], 'tasks_pop_status_idx');
            $table->index('customer_id', 'tasks_customer_id_index');
            $table->dropIndex('tasks_type_status_completed_idx');
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropIndex('customer_services_customer_status_idx');
            $table->dropIndex('customer_services_old_request_id_idx');
            $table->dropIndex('customer_services_old_cost_id_idx');
        });

        Schema::table('customer_status_logs', function (Blueprint $table) {
            $table->dropIndex('customer_status_logs_customer_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_unread_idx');
        });

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropIndex('fop_tasks_pop_date_status_idx');
            $table->dropIndex('fop_tasks_customer_cat_status_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_pop_date_idx');
            $table->dropIndex('payments_status_date_idx');
            $table->dropIndex('payments_customer_date_idx');
            $table->dropIndex('payments_old_payment_id_idx');
            $table->dropIndex('payments_old_transaction_id_idx');
            $table->dropIndex('payments_old_request_id_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_customer_period_type_idx');
            $table->dropIndex('invoices_pop_period_idx');
            $table->dropIndex('invoices_pop_issue_idx');
            $table->dropIndex('invoices_status_due_idx');
            $table->dropIndex('invoices_old_invoice_id_idx');
            $table->dropIndex('invoices_old_cost_id_idx');
            $table->dropIndex('invoices_old_request_id_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_pop_status_idx');
            $table->dropIndex('customers_pop_completeness_idx');
            $table->dropIndex('customers_status_created_idx');
            $table->dropIndex('customers_pop_registration_idx');
            $table->dropIndex('customers_old_customer_id_idx');
            $table->dropIndex('customers_old_request_id_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_auditable_idx');
            $table->dropIndex('audit_logs_module_action_idx');
            $table->dropIndex('audit_logs_created_at_idx');
        });

        Schema::table('internet_packages', function (Blueprint $table) {
            $table->dropIndex('internet_packages_old_package_id_idx');
        });
    }
};
