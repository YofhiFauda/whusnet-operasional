<!-- Global Dialog Container -->
<div id="global-dialog-container" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <!-- Dialog Box -->
    <div id="global-dialog-box" class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 transform transition-all scale-95 opacity-0 flex flex-col max-h-[90vh]">
        
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <!-- Optional Icon Slot -->
                <div id="global-dialog-icon-container" class="hidden flex-shrink-0 p-1.5 rounded-full">
                    <!-- Icon SVG injected here -->
                </div>
                <h3 id="global-dialog-title" class="text-lg font-bold text-slate-800">Dialog Title</h3>
            </div>
            <button type="button" onclick="window.Dialog.close()" class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none rounded-md hover:bg-slate-100 p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <!-- Body (Scrollable) -->
        <div id="global-dialog-body" class="px-6 py-5 overflow-y-auto text-sm text-slate-600 whitespace-pre-line">
            <!-- Message or Custom Form HTML gets injected here -->
        </div>
        
        <!-- Footer (Actions) -->
        <div id="global-dialog-footer" class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0 bg-slate-50 rounded-b-xl">
            <!-- Buttons get injected here -->
        </div>
        
    </div>
</div>

<script>
    window.Dialog = {
        isOpen: false,
        onCloseCallback: null,
        get container() { return document.getElementById('global-dialog-container'); },
        get box() { return document.getElementById('global-dialog-box'); },
        get titleEl() { return document.getElementById('global-dialog-title'); },
        get bodyEl() { return document.getElementById('global-dialog-body'); },
        get footerEl() { return document.getElementById('global-dialog-footer'); },
        get iconContainer() { return document.getElementById('global-dialog-icon-container'); },
        
        show(options) {
            const container = this.container;
            const box = this.box;
            const titleEl = this.titleEl;
            const bodyEl = this.bodyEl;
            const footerEl = this.footerEl;
            const iconContainer = this.iconContainer;

            if (!container || this.isOpen) return;
            
            // Set Title
            titleEl.innerText = options.title || 'Dialog';
            
            // Set Icon (if any)
            iconContainer.className = 'hidden flex-shrink-0 p-1.5 rounded-full';
            iconContainer.innerHTML = '';
            if (options.icon) {
                iconContainer.classList.remove('hidden');
                if (options.icon === 'warning') {
                    iconContainer.classList.add('text-amber-500', 'bg-amber-50');
                    iconContainer.innerHTML = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
                } else if (options.icon === 'error') {
                    iconContainer.classList.add('text-rose-500', 'bg-rose-50');
                    iconContainer.innerHTML = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
                } else if (options.icon === 'success') {
                    iconContainer.classList.add('text-emerald-500', 'bg-emerald-50');
                    iconContainer.innerHTML = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>`;
                } else if (options.icon === 'info') {
                    iconContainer.classList.add('text-sky-500', 'bg-sky-50');
                    iconContainer.innerHTML = `<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`;
                }
            }
            
            // Set Body
            if (options.contentHtml) {
                bodyEl.innerHTML = options.contentHtml;
            } else if (options.message) {
                bodyEl.innerHTML = `<p>${options.message}</p>`;
            } else {
                bodyEl.innerHTML = '';
            }
            
            // Set Footer Buttons
            footerEl.innerHTML = '';
            if (options.buttons && options.buttons.length > 0) {
                options.buttons.forEach(btn => {
                    const btnEl = document.createElement('button');
                    
                    // Professional design button classes
                    let baseClasses = 'px-4 py-2 text-sm font-semibold rounded-lg shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 cursor-pointer ';
                    
                    if (btn.type === 'primary' || btn.type === 'submit') {
                        baseClasses += 'bg-sky-600 hover:bg-sky-700 text-white focus:ring-sky-500 active:scale-95';
                    } else if (btn.type === 'danger') {
                        baseClasses += 'bg-rose-600 hover:bg-rose-700 text-white focus:ring-rose-500 active:scale-95';
                    } else {
                        // Secondary
                        baseClasses += 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-500 active:scale-95';
                    }
                    
                    btnEl.className = baseClasses;
                    btnEl.innerText = btn.text;
                    
                    if (btn.type === 'submit') {
                        btnEl.type = 'submit';
                        if (btn.formId) {
                            btnEl.setAttribute('form', btn.formId);
                        }
                    } else {
                        btnEl.type = 'button';
                    }
                    
                    if (typeof btn.onClick === 'function') {
                        btnEl.addEventListener('click', (e) => {
                            if (btnEl.disabled) return;
                            btnEl.disabled = true;
                            btnEl.classList.add('opacity-50', 'cursor-not-allowed');
                            btn.onClick(e, this);
                        });
                    } else if (!btn.type || btn.type === 'secondary') {
                        // default close behavior for secondary if no onClick
                        btnEl.addEventListener('click', () => this.close());
                    }
                    
                    footerEl.appendChild(btnEl);
                });
            } else {
                // Default Close Button
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'px-4 py-2 text-sm font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg shadow-sm transition-all focus:outline-none active:scale-95 cursor-pointer';
                closeBtn.innerText = 'Tutup';
                closeBtn.addEventListener('click', () => this.close());
                footerEl.appendChild(closeBtn);
            }
            
            this.onCloseCallback = options.onClose;
            
            // Show Dialog
            container.classList.remove('hidden');
            // Force reflow
            void container.offsetWidth;
            container.classList.remove('opacity-0');
            box.classList.remove('scale-95', 'opacity-0');
            
            this.isOpen = true;
            document.body.classList.add('overflow-hidden');
        },
        
        close() {
            const container = this.container;
            const box = this.box;
            if (!this.isOpen || !container || !box) return;
            
            container.classList.add('opacity-0');
            box.classList.add('scale-95', 'opacity-0');
            document.body.classList.remove('overflow-hidden');
            
            setTimeout(() => {
                container.classList.add('hidden');
                if (typeof this.onCloseCallback === 'function') {
                    this.onCloseCallback();
                }
                this.isOpen = false;
            }, 300);
        }
    };
</script>
