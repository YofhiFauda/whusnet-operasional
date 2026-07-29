<?php

namespace App\Http\Controllers;

use App\Models\Customer;

/**
 * Halaman Perangkat & Pemasangan — pecahan dari customers.show (Detail
 * Pelanggan) supaya teknisi bisa tetap kerja lapangan (isi/lihat data
 * perangkat & pemasangan) TANPA perlu permission customers.detail.view
 * (yang sengaja diblok buat teknisi — mereka gak boleh buka Detail
 * Pelanggan umum: identitas/alamat/paket/billing/dokumen). Reuse partial
 * yang sama persis dengan yang dipakai di customers.show
 * (customers.tabs._device / customers.tabs._installation) — cuma
 * relasi yang dimuat dipersempit ke yang benar-benar dipakai kedua partial
 * itu, bukan 17 relasi customers.show yang jauh lebih berat.
 */
class CustomerFieldworkController extends Controller
{
    public function show(Customer $customer)
    {
        $customer->load(['customerDevice', 'customerTechnicalDetail', 'installations.technician']);

        return view('customers.fieldwork', compact('customer'));
    }
}
