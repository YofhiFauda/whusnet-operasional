<?php

namespace App\Enums;

enum ActionCode: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case IMPORT = 'import';
    case EXPORT = 'export';
    case PRINT = 'print';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case ACTIVATE = 'activate';
    case DEACTIVATE = 'deactivate';
    case ASSIGN = 'assign';
    case VALIDATE = 'validate';
    case CANCEL = 'cancel';
    case UPLOAD = 'upload';
    case DOWNLOAD = 'download';
    case VIEW_SENSITIVE = 'view_sensitive';
    case UPDATE_SENSITIVE = 'update_sensitive';
    case RETRIEVE = 'retrieve';

    /**
     * Kolektor mencatat pembayaran dari Worklist-nya sendiri. SENGAJA bukan
     * CREATE: `kolektor.create` ambigu (bikin kolektor, atau bikin
     * pembayaran?), dan kewenangannya jauh lebih sempit ketimbang
     * `payments.create` — cuma invoice pelanggan yang ter-assign ke dirinya.
     *
     * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §14.1.
     */
    case PAY = 'pay';

    /**
     * Kolektor menyerahkan hasil tagihannya ke admin. Terpisah dari PAY:
     * boleh menagih tak otomatis boleh menyetor, dan sebaliknya — mis. saat
     * seorang kolektor sementara dilarang memegang kas.
     */
    case DEPOSIT = 'deposit';

    /**
     * Kolektor mencatat hasil kunjungan yang TIDAK menghasilkan uang
     * (tidak ada orang / menolak / janji bayar). Terpisah dari PAY karena
     * inilah kewajiban pelaporan yang justru harus tetap jalan waktu kolektor
     * pulang dengan tangan kosong.
     */
    case VISIT = 'visit';

    /**
     * Sales melewati tahap survey lapangan saat Registrasi Pelanggan —
     * data survey (ODP terdekat, estimasi kabel, tingkat kesulitan, foto
     * rumah/ODP, titik koordinat) diinput langsung di form registrasi.
     * Pelanggan lompat ke antrean ACC Admin, gak pernah masuk antrean Survey
     * teknisi. Permission sempit & terpisah dari `customers.create` biasa
     * karena membuka tahap workflow, bukan cuma isi field pelanggan.
     */
    case SKIP_SURVEY = 'skip_survey';

    /**
     * Konfirmasi FISIK "barang sudah nyampe" (Transfer Pusat→Cabang). SENGAJA
     * bukan APPROVE: itu keputusan setuju/tolak berbasis kebijakan, ini
     * pengakuan kejadian fisik oleh penerima sendiri (acknowledgment digital
     * — docs/plan/warehouse/kontrol-anti-manipulasi.md §4), gak ada opsi
     * "tolak seluruhnya", cuma partial-match kalau SN gak cocok.
     */
    case RECEIVE = 'receive';
}
