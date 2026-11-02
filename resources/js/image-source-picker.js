const originalInputState = new WeakMap();
let activeInput = null;
let bypassSourcePicker = false;
let sourceDialog = null;
let returnFocusTo = null;

function usesMobileSourcePicker() {
    if (/Android|iPhone|iPad|iPod|Tablet|Mobile/i.test(navigator.userAgent)) {
        return true;
    }

    if (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) {
        return true;
    }

    return window.innerWidth <= 1024 && window.matchMedia('(pointer: coarse) and (hover: none)').matches;
}

function isImageUpload(input) {
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || input.disabled) {
        return false;
    }

    const accept = originalInputState.get(input)?.accept ?? input.accept;

    return accept.toLowerCase().split(',').some((type) => type.trim().startsWith('image/'));
}

function closeSourcePicker() {
    sourceDialog?.remove();
    sourceDialog = null;
    activeInput = null;
    returnFocusTo?.focus?.();
    returnFocusTo = null;
}

function openNativePicker(source) {
    const input = activeInput;
    if (!input) {
        return;
    }

    const original = originalInputState.get(input);
    closeSourcePicker();

    input.accept = source === 'camera' ? 'image/*' : original.accept;
    input.multiple = source === 'camera' ? false : original.multiple;

    if (source === 'camera') {
        input.setAttribute('capture', 'environment');
    } else {
        input.removeAttribute('capture');
    }

    const restoreInput = () => {
        input.accept = original.accept;
        input.multiple = original.multiple;

        if (original.capture === null) {
            input.removeAttribute('capture');
        } else {
            input.setAttribute('capture', original.capture);
        }

        input.removeEventListener('change', restoreInput);
        input.removeEventListener('cancel', restoreInput);
    };

    input.addEventListener('change', restoreInput);
    input.addEventListener('cancel', restoreInput);

    bypassSourcePicker = true;
    try {
        input.click();
    } finally {
        bypassSourcePicker = false;
    }
}

function showSourcePicker(input) {
    if (!originalInputState.has(input)) {
        originalInputState.set(input, {
            accept: input.accept,
            multiple: input.multiple,
            capture: input.getAttribute('capture'),
        });
    }

    if (sourceDialog) {
        sourceDialog.remove();
    }

    activeInput = input;
    returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[9999] flex items-end justify-center bg-slate-950/55 p-3 sm:items-center';
    overlay.innerHTML = `
        <div role="dialog" aria-modal="true" aria-label="Pilih sumber foto"
             class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-slate-900 dark:text-white">Pilih sumber foto</p>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Gunakan kamera HP atau foto yang sudah tersimpan.</p>
                </div>
                <button type="button" data-close aria-label="Tutup pilihan foto"
                        class="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-sky-600 dark:text-slate-300 dark:hover:bg-slate-800">✕</button>
            </div>
            <div class="grid gap-2">
                <button type="button" data-camera
                        class="flex min-h-12 w-full items-center gap-3 rounded-xl border border-sky-600 bg-sky-600 px-4 text-left text-sm font-semibold text-white hover:bg-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h3l2-3h8l2 3h3v13H3V7Z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    Ambil Foto
                </button>
                <button type="button" data-gallery
                        class="flex min-h-12 w-full items-center gap-3 rounded-xl border border-slate-300 px-4 text-left text-sm font-semibold text-slate-800 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800">
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 16-5-5L5 21"/></svg>
                    Pilih dari Galeri
                </button>
            </div>
        </div>`;

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeSourcePicker();
        }
    });
    overlay.querySelector('[data-close]').addEventListener('click', closeSourcePicker);
    overlay.querySelector('[data-camera]').addEventListener('click', () => openNativePicker('camera'));
    overlay.querySelector('[data-gallery]').addEventListener('click', () => openNativePicker('gallery'));

    document.body.append(overlay);
    sourceDialog = overlay;
    overlay.querySelector('[data-camera]').focus();
}

document.addEventListener('click', (event) => {
    if (bypassSourcePicker || !usesMobileSourcePicker() || !isImageUpload(event.target)) {
        return;
    }

    event.preventDefault();
    showSourcePicker(event.target);
}, true);

document.addEventListener('keydown', (event) => {
    if (!sourceDialog) {
        return;
    }

    if (event.key === 'Escape') {
        closeSourcePicker();
        return;
    }

    if (event.key === 'Tab') {
        const buttons = sourceDialog.querySelectorAll('button');
        const first = buttons[0];
        const last = buttons[buttons.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
});