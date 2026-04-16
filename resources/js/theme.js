const THEME_KEY = 'phppgadmin.theme';

/**
 * @returns {'system'|'light'|'dark'}
 */
export function readStoredTheme() {
    try {
        const v = localStorage.getItem(THEME_KEY);
        if (v === 'light' || v === 'dark' || v === 'system') {
            return v;
        }
    } catch {
        //
    }

    return 'system';
}

/**
 * @param {'system'|'light'|'dark'} mode
 * @returns {'light'|'dark'}
 */
export function resolveEffective(mode) {
    if (mode === 'light') {
        return 'light';
    }
    if (mode === 'dark') {
        return 'dark';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * @param {'light'|'dark'} effective
 */
export function applyThemeClass(effective) {
    document.documentElement.classList.toggle('dark', effective === 'dark');
    document.documentElement.style.colorScheme = effective === 'dark' ? 'dark' : 'light';
}

export function createThemePicker() {
    return {
        menuOpen: false,
        /** @type {'system'|'light'|'dark'} */
        mode: 'system',
        /** @type {(() => void) | null} */
        _onPrefChange: null,
        init() {
            this.mode = readStoredTheme();
            applyThemeClass(resolveEffective(this.mode));
            this._onPrefChange = () => {
                if (this.mode === 'system') {
                    applyThemeClass(resolveEffective('system'));
                }
            };
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', this._onPrefChange);
        },
        setMode(mode) {
            if (mode !== 'light' && mode !== 'dark' && mode !== 'system') {
                return;
            }
            this.mode = mode;
            try {
                localStorage.setItem(THEME_KEY, mode);
            } catch {
                //
            }
            applyThemeClass(resolveEffective(mode));
            this.menuOpen = false;
        },
    };
}
