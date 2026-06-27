<!-- Global Toast Container -->
<div id="toast-container" class="fixed top-6 right-6 z-50 w-[calc(100%-3rem)] sm:w-[350px] h-0 pointer-events-none"></div>

<script>
    window.Toast = {
        toasts: [],
        isHovered: false,
        get container() { return document.getElementById('toast-container'); },
        updateToasts() {
            let currentY = 0;
            this.toasts.forEach((t, i) => {
                if (t.isRemoving) return;

                if (this.isHovered) {
                    if (i < 3) {
                        t.style.transform = `translateY(${currentY}px) scale(1)`;
                        t.style.opacity = '1';
                        currentY += t.offsetHeight + 12;
                    } else {
                        t.style.transform = `translateY(${currentY}px) scale(0.9)`;
                        t.style.opacity = '0';
                    }
                } else {
                    if (i < 3) {
                        t.style.transform = `translateY(${i * 14}px) scale(${1 - i * 0.05})`;
                        t.style.opacity = '1';
                    } else {
                        t.style.transform = `translateY(${2 * 14}px) scale(${1 - 3 * 0.05})`;
                        t.style.opacity = '0';
                    }
                }
                t.style.zIndex = 50 - i;
                t.style.pointerEvents = (i < 3) ? 'auto' : 'none';
            });
        },
        show(type, title, description = '', duration = 5000) {
            const container = this.container;
            if (!container) return;

            // Create toast wrapper
            const toast = document.createElement('div');
            toast.className = 'absolute top-0 right-0 w-full pointer-events-auto transition-all duration-400 ease-out flex items-start gap-3 p-4 rounded-xl shadow-lg border bg-white overflow-hidden';
            
            // Initial state for entrance animation (slides in from slightly above and scaled down)
            toast.style.transform = 'translateY(-20px) scale(0.9)';
            toast.style.opacity = '0';
            
            let iconHtml = '';
            let borderClass = '';
            let iconClass = '';
            let progressClass = '';

            switch (type) {
                case 'success':
                    borderClass = 'border-emerald-100';
                    iconClass = 'text-emerald-500 bg-emerald-50';
                    progressClass = 'bg-emerald-500';
                    iconHtml = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`;
                    break;
                case 'error':
                    borderClass = 'border-rose-100';
                    iconClass = 'text-rose-500 bg-rose-50';
                    progressClass = 'bg-rose-500';
                    iconHtml = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>`;
                    break;
                case 'warning':
                    borderClass = 'border-amber-100';
                    iconClass = 'text-amber-500 bg-amber-50';
                    progressClass = 'bg-amber-500';
                    iconHtml = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
                    break;
                case 'info':
                default:
                    borderClass = 'border-sky-100';
                    iconClass = 'text-sky-500 bg-sky-50';
                    progressClass = 'bg-sky-500';
                    iconHtml = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
                    break;
            }

            toast.classList.add(borderClass);

            toast.innerHTML = `
                <div class="flex-shrink-0 p-1 rounded-lg ${iconClass}">
                    ${iconHtml}
                </div>
                <div class="flex-1 pt-0.5 min-w-0">
                    <h4 class="text-sm font-semibold text-slate-800 leading-tight">${title}</h4>
                    ${description ? `<div class="mt-1 text-xs text-slate-500 break-words leading-relaxed">${description}</div>` : ''}
                </div>
                <button type="button" class="flex-shrink-0 ml-4 inline-flex text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <span class="sr-only">Close</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                ${duration > 0 ? `<div class="progress-bar absolute bottom-0 left-0 h-1 ${progressClass}" style="width: 100%;"></div>` : ''}
            `;

            container.appendChild(toast);
            this.toasts.unshift(toast); // Add new toast to the front of the array

            const removeToast = () => {
                if (toast.isRemoving) return;
                toast.isRemoving = true;
                
                this.toasts = this.toasts.filter(t => t !== toast);
                toast.style.transform = 'translateX(100%) scale(1)';
                toast.style.opacity = '0';
                
                this.updateToasts(); // Update others immediately
                
                toast.addEventListener('transitionend', () => {
                    toast.remove();
                }, { once: true });
            };
            
            toast.removeToastFn = removeToast;

            // Animate entrance and handle progress
            requestAnimationFrame(() => {
                this.updateToasts();
                
                if (duration > 0) {
                    const progressBar = toast.querySelector('.progress-bar');
                    if (progressBar) {
                        // Force reflow to start transition
                        void progressBar.offsetWidth;
                        progressBar.style.transition = `width ${duration}ms linear`;
                        progressBar.style.width = '0%';
                    }
                    setTimeout(removeToast, duration);
                }
            });

            const closeBtn = toast.querySelector('button');
            closeBtn.addEventListener('click', removeToast);
        },
        success(title, description, duration) { this.show('success', title, description, duration); },
        error(title, description, duration) { this.show('error', title, description, duration); },
        warning(title, description, duration) { this.show('warning', title, description, duration); },
        info(title, description, duration) { this.show('info', title, description, duration); }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const container = window.Toast.container;
        if (container) {
            container.addEventListener('mouseenter', () => {
                window.Toast.isHovered = true;
                window.Toast.updateToasts();
            });
            container.addEventListener('mouseleave', () => {
                window.Toast.isHovered = false;
                window.Toast.updateToasts();
            });
        }

        // Handle Laravel Session Flashes automatically
        @if(session('success'))
            Toast.success('Berhasil', @json(session('success')));
        @endif

        @if(session('error'))
            Toast.error('Gagal', @json(session('error')));
        @endif

        @if(session('warning'))
            Toast.warning('Peringatan', @json(session('warning')));
        @endif

        @if(session('info'))
            Toast.info('Informasi', @json(session('info')));
        @endif

        @if($errors->any())
            let errorHtml = `
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            `;
            Toast.error('Terdapat Kesalahan', errorHtml, 6000);
        @endif
    });
</script>
