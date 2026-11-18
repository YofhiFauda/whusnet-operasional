{{--
    Satu percobaan penarikan alat (laporan DEAC gagal) sebagai item timeline
    — dipakai List (baris yang di-expand) dan Detail per pelanggan.

    Design.md: item ini berada DI DALAM satu panel (table panel / detail
    panel), jadi sengaja datar — tanpa border+radius+background sendiri
    (larangan double card §1.5) dan tanpa latar berwarna per status (warna
    status hanya di badge/teks, §1.3 aturan 4). Pembatas antar-percobaan =
    garis timeline + divider 1px.

    Aksi Approve/Tolak/Komentar inline toggle; semua POST ke endpoint yang
    sama, tujuan redirect dipilih server dari token `return_to`. Saat gagal
    validasi, `old('retrieval_id')` + `old('review_form')` membuka lagi form
    yang gagal supaya pesan error tetap terlihat (lihat docblock
    DeviceRetrievalReviewController).

    Param: $attempt, $number, $isLast, $canReview, $canComment, $returnTo ('list'|'detail'), $tab
--}}
@php
    $status = $attempt->review_status;
    $isPending = $status === \App\Enums\DeviceRetrievalReviewStatus::MENUNGGU;
    $withinTolerance = \App\Services\DeviceRetrievalReviewService::isWithinTolerance($number);
    $isThisRetrievalOld = (int) old('retrieval_id') === $attempt->id;
    $openForm = $isThisRetrievalOld ? old('review_form') : null;
    $photoUrl = $attempt->photoUrl();
    $technicians = $attempt->task?->teamMembers->map(fn ($m) => $m->user?->name)->filter()->implode(', ');
    [$badgeClass, $dotClass] = match ($status) {
        \App\Enums\DeviceRetrievalReviewStatus::MENUNGGU => ['badge badge-warning', 'bg-amber-50 text-amber-600 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800/60'],
        \App\Enums\DeviceRetrievalReviewStatus::DITOLAK => ['badge badge-error', 'bg-red-50 text-red-600 ring-red-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-800/60'],
        \App\Enums\DeviceRetrievalReviewStatus::DITUTUP => ['badge badge-neutral', 'bg-slate-100 text-slate-500 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700'],
        default => ['badge text-violet-700 bg-violet-50 border-violet-200 dark:text-violet-300 dark:bg-violet-950/50 dark:border-violet-800/60', 'bg-violet-50 text-violet-600 ring-violet-200 dark:bg-violet-950/60 dark:text-violet-300 dark:ring-violet-800/60'],
    };
    $labelClass = 'block text-[10px] font-semibold uppercase tracking-[0.07em] text-slate-500 dark:text-slate-400';
    $inputClass = 'w-full rounded-lg text-[13px] font-ui bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 outline-none placeholder-slate-400 dark:placeholder-slate-500 resize-y';
    $toggleClass = 'inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border text-xs font-semibold transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40';
@endphp

<li class="relative flex gap-3 sm:gap-4 py-4 first:pt-1" x-data="{ form: @js($openForm) }">
    {{-- Garis penghubung timeline (Design.md §13.5) --}}
    @unless($isLast)
    <span class="absolute left-[15px] top-12 bottom-0 w-px bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
    @endunless

    <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1 font-mono text-xs font-semibold {{ $dotClass }}"
         aria-hidden="true">{{ $number }}</div>

    <div class="min-w-0 flex-1 space-y-3">
        {{-- Judul percobaan --}}
        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1.5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Percobaan #{{ $number }}</h3>
                    <span class="{{ $badgeClass }}">{{ $status->label() }}</span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ $attempt->outcome->label() }}
                    <span class="text-slate-300 dark:text-slate-600">·</span>
                    Teknisi: <span class="text-slate-700 dark:text-slate-300">{{ $technicians ?: '-' }}</span>
                </p>
            </div>
            <div class="text-right">
                <p class="font-mono text-xs text-slate-500 dark:text-slate-400 tabular-nums">{{ $attempt->created_at->translatedFormat('d M Y, H:i') }}</p>
                @if($attempt->task)
                <a href="{{ route('tasks.show', $attempt->task) }}" class="font-mono text-xs font-medium text-sky-700 dark:text-sky-400 hover:underline">{{ $attempt->task->task_number }}</a>
                @endif
            </div>
        </div>

        {{-- Bukti: foto + alasan + keputusan --}}
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
            <div class="shrink-0">
                @if($photoUrl)
                <button type="button" @click="$dispatch('open-image-preview', { url: @js($photoUrl), label: @js('Foto percobaan #'.$number) })"
                        class="group relative block h-24 w-24 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 cursor-zoom-in focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                        aria-label="Perbesar foto percobaan #{{ $number }}">
                    <img src="{{ $photoUrl }}" alt="Foto percobaan #{{ $number }}" class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105" loading="lazy">
                </button>
                @else
                <div class="flex h-24 w-24 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 p-2 text-center text-[10px] leading-tight text-slate-400 dark:text-slate-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                    {{ $attempt->photo_purged_at ? 'Foto dihapus (retensi 30 hari)' : 'Tanpa foto' }}
                </div>
                @endif
            </div>

            <dl class="min-w-0 flex-1 space-y-3">
                <div>
                    <dt class="{{ $labelClass }}">Alasan teknisi</dt>
                    <dd class="mt-1 text-[13px] leading-relaxed text-slate-800 dark:text-slate-200 whitespace-pre-line break-words [word-break:break-word]">{{ $attempt->notes ?: '-' }}</dd>
                </div>
                @if($attempt->reviewed_at)
                <div>
                    <dt class="{{ $labelClass }}">{{ $status === \App\Enums\DeviceRetrievalReviewStatus::DITUTUP ? 'Ditutup otomatis' : 'Keputusan FOP' }}</dt>
                    <dd class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ $attempt->reviewer?->name ?? 'Sistem' }}
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <span class="font-mono tabular-nums">{{ $attempt->reviewed_at->translatedFormat('d M Y, H:i') }}</span>
                    </dd>
                    @if($attempt->review_note)
                    <dd class="mt-1 text-[13px] leading-relaxed text-slate-800 dark:text-slate-200 whitespace-pre-line break-words [word-break:break-word]">{{ $attempt->review_note }}</dd>
                    @endif
                </div>
                @endif
            </dl>
        </div>

        {{-- Komentar (FOP/CS/BD) --}}
        @if($attempt->comments->isNotEmpty())
        <div>
            <p class="{{ $labelClass }}">Komentar ({{ $attempt->comments->count() }})</p>
            <ul class="mt-2 space-y-2.5 border-l-2 border-slate-200 dark:border-slate-700 pl-3">
                @foreach($attempt->comments as $comment)
                <li class="text-[13px]">
                    <p class="text-xs">
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $comment->user?->name ?? 'Pengguna dihapus' }}</span>
                        <span class="font-mono text-slate-400 dark:text-slate-500 tabular-nums">· {{ $comment->created_at->translatedFormat('d M Y, H:i') }}</span>
                    </p>
                    <p class="mt-0.5 leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line break-words [word-break:break-word]">{{ $comment->body }}</p>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Aksi --}}
        @if(($isPending && $canReview) || $canComment)
        <div class="flex flex-wrap items-center gap-2">
            @if($isPending && $canReview)
            <button type="button" @click="form = form === 'approve' ? null : 'approve'" :aria-expanded="(form === 'approve').toString()"
                    class="{{ $toggleClass }}"
                    :class="form === 'approve' ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-violet-700 dark:text-violet-300 hover:bg-slate-50 dark:hover:bg-slate-700'">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                Approve (Bebaskan)
            </button>
            <button type="button" @click="form = form === 'reject' ? null : 'reject'" :aria-expanded="(form === 'reject').toString()"
                    class="{{ $toggleClass }}"
                    :class="form === 'reject' ? 'border-red-600 bg-red-600 text-white' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-red-700 dark:text-red-300 hover:bg-slate-50 dark:hover:bg-slate-700'">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                Tolak (Ulangi Deact)
            </button>
            @endif
            @if($canComment)
            <button type="button" @click="form = form === 'comment' ? null : 'comment'" :aria-expanded="(form === 'comment').toString()"
                    class="{{ $toggleClass }}"
                    :class="form === 'comment' ? 'border-sky-700 bg-sky-700 text-white' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700'">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                Komentar
            </button>
            @endif
        </div>

        @if($isPending && $canReview)
        <form x-show="form === 'approve'" x-cloak x-collapse method="POST" action="{{ route('device-retrieval-reviews.approve', $attempt) }}"
              class="space-y-2.5 border-t border-dashed border-slate-200 dark:border-slate-700 pt-3">
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="retrieval_id" value="{{ $attempt->id }}">
            <input type="hidden" name="review_form" value="approve">
            <label for="approve-note-{{ $attempt->id }}" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                @if($withinTolerance)
                Catatan <span class="font-normal text-slate-500 dark:text-slate-400">(opsional — sudah {{ $number }} percobaan, alasan boleh ditoleransi)</span>
                @else
                Catatan <span class="text-red-600 dark:text-red-400">*</span> <span class="font-normal text-slate-500 dark:text-slate-400">— wajib: jelaskan kenapa dibebaskan sebelum {{ \App\Services\DeviceRetrievalReviewService::TOLERANCE_ATTEMPTS }} percobaan</span>
                @endif
            </label>
            <textarea id="approve-note-{{ $attempt->id }}" name="review_note" rows="2" maxlength="1000" @if(! $withinTolerance) required @endif class="{{ $inputClass }}">{{ $isThisRetrievalOld && $openForm === 'approve' ? old('review_note') : '' }}</textarea>
            @if($isThisRetrievalOld && $openForm === 'approve')
            @error('review_note')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            @endif
            <p class="text-xs text-slate-500 dark:text-slate-400">Pelanggan ditandai <span class="font-semibold text-violet-700 dark:text-violet-300">Dibebaskan</span>: kasus ditutup tanpa modem kembali, foto percobaan ini disimpan permanen.</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="inline-flex items-center h-9 px-4 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-[13px] font-semibold transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500/40">Konfirmasi Approve</button>
                <button type="button" @click="form = null" class="btn-secondary !min-h-9 !py-1.5">Batal</button>
            </div>
        </form>

        <form x-show="form === 'reject'" x-cloak x-collapse method="POST" action="{{ route('device-retrieval-reviews.reject', $attempt) }}"
              class="space-y-2.5 border-t border-dashed border-slate-200 dark:border-slate-700 pt-3">
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="retrieval_id" value="{{ $attempt->id }}">
            <input type="hidden" name="review_form" value="reject">
            <label for="reject-note-{{ $attempt->id }}" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Alasan penolakan <span class="text-red-600 dark:text-red-400">*</span></label>
            <textarea id="reject-note-{{ $attempt->id }}" name="review_note" rows="2" maxlength="1000" required placeholder="Mis. belum ada bukti rumah kosong, datangi lagi sore hari." class="{{ $inputClass }}">{{ $isThisRetrievalOld && $openForm === 'reject' ? old('review_note') : '' }}</textarea>
            @if($isThisRetrievalOld && $openForm === 'reject')
            @error('review_note')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            @endif
            <label class="flex items-start gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer">
                <input type="checkbox" name="schedule_now" value="1" @checked($isThisRetrievalOld && old('schedule_now'))
                       class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 accent-sky-600">
                <span>Langsung jadwalkan <span class="text-slate-500 dark:text-slate-400">— buka form tugaskan teknisi di Task FOP setelah disimpan</span></span>
            </label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Task pengambilan alat baru dibuat (Draft) sebagai percobaan berikutnya. Teknisi percobaan ini: {{ $technicians ?: '-' }}.</p>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="inline-flex items-center h-9 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white text-[13px] font-semibold transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40">Konfirmasi Tolak</button>
                <button type="button" @click="form = null" class="btn-secondary !min-h-9 !py-1.5">Batal</button>
            </div>
        </form>
        @endif

        @if($canComment)
        <form x-show="form === 'comment'" x-cloak x-collapse method="POST" action="{{ route('device-retrieval-reviews.comment', $attempt) }}"
              class="space-y-2.5 border-t border-dashed border-slate-200 dark:border-slate-700 pt-3">
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="retrieval_id" value="{{ $attempt->id }}">
            <input type="hidden" name="review_form" value="comment">
            <label for="comment-body-{{ $attempt->id }}" class="sr-only">Komentar</label>
            <textarea id="comment-body-{{ $attempt->id }}" name="body" rows="2" maxlength="1000" required placeholder="Tulis komentar untuk tim..." class="{{ $inputClass }}">{{ $isThisRetrievalOld && $openForm === 'comment' ? old('body') : '' }}</textarea>
            @if($isThisRetrievalOld && $openForm === 'comment')
            @error('body')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            @endif
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn-primary !min-h-9 !py-1.5">Kirim Komentar</button>
                <button type="button" @click="form = null" class="btn-secondary !min-h-9 !py-1.5">Batal</button>
            </div>
        </form>
        @endif
        @endif
    </div>
</li>
