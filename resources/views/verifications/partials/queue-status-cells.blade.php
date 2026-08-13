{{-- Tiga <td> ini (STATUS, WAKTU LIVE, ACTION) sengaja dipisah dari
     queue.blade.php biar bisa di-refetch per baris lewat verifications.row
     saat App\Events\CustomerVerificationStatusChanged masuk (Echo) — tanpa
     itu, logic banyak cabang tombol per status/permission bakal keduplikasi
     di JS. Dipakai DUA cara: render awal (@include di queue.blade.php) dan
     fragment mentah lewat endpoint (lihat CustomerVerificationController::row()).

     Isinya cuma pembungkus <td>; badge/timer/tombol ada di partial terpisah
     karena kartu mobile (queue-card.blade.php) memakai tiga blok yang sama
     persis dalam bungkus <div>. Fragment dari endpoint ini juga yang dipakai
     buat menyegarkan kartu mobile — JS menyalin innerHTML tiap sel. --}}
@php
    $installation = $installation ?? $customer->latestInstallation;
@endphp
<td class="px-4 py-3.5 text-center" id="customer-status-cell-{{ $customer->id }}">
    @include('verifications.partials.queue-status-badge', ['customer' => $customer])
</td>
<td class="px-4 py-3.5" id="customer-live-cell-{{ $customer->id }}">
    @include('verifications.partials.queue-timer', ['customer' => $customer, 'installation' => $installation, 'idPrefix' => ''])
</td>
<td class="px-4 py-3.5 text-right whitespace-nowrap" id="customer-action-cell-{{ $customer->id }}">
    @include('verifications.partials.queue-actions', ['customer' => $customer, 'layout' => 'row'])
</td>
