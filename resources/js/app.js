import './workspace-editors';
import { createThemePicker } from './theme';

document.addEventListener('alpine:init', () => {
    Alpine.data('themePicker', () => createThemePicker());

    Alpine.data('sidebarShell', () => ({
        collapsed: false,
        widthPx: 288,
        init() {
            try {
                const c = localStorage.getItem('phppgadmin.sidebarCollapsed');
                if (c !== null) {
                    this.collapsed = c === '1';
                }
                const w = localStorage.getItem('phppgadmin.sidebarWidth');
                if (w !== null) {
                    const n = parseInt(w, 10);
                    if (!Number.isNaN(n)) {
                        this.widthPx = Math.min(520, Math.max(200, n));
                    }
                }
            } catch {
                //
            }
        },
        toggle() {
            this.collapsed = !this.collapsed;
            try {
                localStorage.setItem('phppgadmin.sidebarCollapsed', this.collapsed ? '1' : '0');
            } catch {
                //
            }
        },
        startResize(event) {
            if (this.collapsed) {
                return;
            }
            const startX = event.clientX;
            const startW = this.widthPx;
            const onMove = (ev) => {
                const delta = ev.clientX - startX;
                this.widthPx = Math.min(520, Math.max(200, startW + delta));
            };
            const onUp = () => {
                try {
                    localStorage.setItem('phppgadmin.sidebarWidth', String(this.widthPx));
                } catch {
                    //
                }
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
                document.body.style.removeProperty('cursor');
                document.body.style.removeProperty('user-select');
            };
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        },
    }));
});
