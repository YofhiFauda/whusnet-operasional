<?php

namespace App\Support;

/**
 * Katalog kolom List Pelanggan — satu-satunya sumber label, default, dan cara
 * sort. Dipakai untuk whitelist: key dari klien (simpan preferensi maupun
 * `?sort=`) HARUS ada di sini, jadi nama kolom tidak pernah dirangkai mentah
 * ke SQL.
 *
 * Tipe `sort`:
 *  - column   : kolom milik `customers`
 *  - relation : subquery korelasi ke tabel lain (BUKAN JOIN — JOIN menyeret
 *               kolom `name` tabel master ke hasil select pelanggan)
 *  - invoice  : subquery ke tagihan terbaru (id terbesar = latestOfMany)
 *
 * Kolom `aksi` bukan bagian katalog: selalu dirender terakhir dan tidak bisa
 * disembunyikan/dipindah.
 */
final class CustomerListColumns
{
    public const TABLE_KEY = 'customers.list';

    /**
     * @return array<string, array{label: string, default: bool, align: string, sort: array<string, string>|null, with?: string}>
     */
    public static function all(): array
    {
        return [
            'cid' => ['label' => 'CID (ID Pelanggan)', 'default' => true, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'customer_code']],
            'nama' => ['label' => 'Nama Lengkap', 'default' => true, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'full_name']],
            'pop_desa' => ['label' => 'POP & Desa', 'default' => true, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'pops', 'column' => 'name', 'fk' => 'pop_id']],
            'paket' => ['label' => 'Paket Internet', 'default' => true, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'internet_packages', 'column' => 'name', 'fk' => 'internet_package_id']],
            'telepon' => ['label' => 'No. Telepon', 'default' => true, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'primary_phone']],
            'jatuh_tempo' => ['label' => 'Jatuh Tempo', 'default' => true, 'align' => 'left', 'sort' => ['type' => 'invoice', 'column' => 'due_date']],
            'tagihan' => ['label' => 'Tagihan', 'default' => true, 'align' => 'right', 'sort' => ['type' => 'invoice', 'column' => 'total_amount']],
            'berkas' => ['label' => 'Berkas', 'default' => true, 'align' => 'center', 'sort' => ['type' => 'column', 'column' => 'data_completeness_status']],
            'status' => ['label' => 'Status', 'default' => true, 'align' => 'center', 'sort' => ['type' => 'column', 'column' => 'status']],
            'email' => ['label' => 'Email', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'email']],
            'nik' => ['label' => 'NIK', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'identity_number']],
            'gender' => ['label' => 'Gender', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'gender']],
            'alamat' => ['label' => 'Alamat', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'address']],
            'kecamatan' => ['label' => 'Kecamatan', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'districts', 'column' => 'name', 'fk' => 'district_id']],
            'kota' => ['label' => 'Kota/Kabupaten', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'cities', 'column' => 'name', 'fk' => 'city_id']],
            'mini_pop' => ['label' => 'Mini POP', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'pops', 'column' => 'name', 'fk' => 'mini_pop_id'], 'with' => 'miniPop:id,name'],
            'kolektor' => ['label' => 'Kolektor', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'users', 'column' => 'name', 'fk' => 'collector_id']],
            'tgl_daftar' => ['label' => 'Tgl. Daftar', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'column', 'column' => 'registration_date']],
            'sales' => ['label' => 'Sales', 'default' => false, 'align' => 'left', 'sort' => ['type' => 'relation', 'table' => 'users', 'column' => 'name', 'fk' => 'sales_user_id'], 'with' => 'salesUser:id,name'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        return array_keys(array_filter(self::all(), fn (array $c) => $c['default']));
    }

    /**
     * @return list<string>
     */
    public static function sortableKeys(): array
    {
        return array_keys(array_filter(self::all(), fn (array $c) => $c['sort'] !== null));
    }
}
