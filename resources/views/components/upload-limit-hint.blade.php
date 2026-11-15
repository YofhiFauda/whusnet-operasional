@props(['format' => null, 'size' => 'sm'])
{{-- Hint batas upload foto. Angkanya dari App\Support\UploadLimit — jangan
     tulis "Maks. N MB" manual di form, nanti menyimpang dari rule server.
     `size="xs"` untuk kartu upload yang padat (teks 10px, jarak atas rapat). --}}
<span {{ $attributes->class([
    'block text-slate-500 dark:text-slate-400',
    'text-[11px] mt-1' => $size !== 'xs',
    'text-[10px] mt-0.5' => $size === 'xs',
]) }}>
    @if($format){{ $format }} · @endif<span class="font-semibold">Maks. {{ \App\Support\UploadLimit::label() }}</span> per file
</span>
