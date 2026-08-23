import { ref, computed } from 'vue';

const THEME_STORAGE_KEY = 'afyanova-theme';

// Global singleton reactive state so all components stay in perfect sync
const theme = ref(getInitialTheme());

function getInitialTheme() {
    if (typeof window === 'undefined') return 'system';
    try {
        return localStorage.getItem(THEME_STORAGE_KEY) || 'system';
    } catch {
        return 'system';
    }
}

function applyThemeToDocument(targetTheme) {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const isDark =
        targetTheme === 'dark' ||
        (targetTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    if (isDark) {
        root.classList.add('dark');
        root.classList.remove('light');
    } else {
        root.classList.remove('dark');
        root.classList.add('light');
    }
}

// Immediately apply on initial script evaluation
if (typeof window !== 'undefined') {
    applyThemeToDocument(theme.value);
}

export function useTheme() {
    const resolvedTheme = computed(() => {
        if (theme.value === 'system') {
            if (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
            return 'light';
        }
        return theme.value;
    });

    const isDark = computed(() => resolvedTheme.value === 'dark');

    const setTheme = (newTheme) => {
        if (!['light', 'dark', 'system'].includes(newTheme)) return;
        theme.value = newTheme;
        try {
            localStorage.setItem(THEME_STORAGE_KEY, newTheme);
        } catch {}
        applyThemeToDocument(newTheme);
    };

    const toggleTheme = () => {
        if (theme.value === 'light') {
            setTheme('dark');
        } else if (theme.value === 'dark') {
            setTheme('system');
        } else {
            setTheme('light');
        }
    };

    // Listen to system changes if theme === 'system'
    if (typeof window !== 'undefined') {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        const handleSystemChange = () => {
            if (theme.value === 'system') {
                applyThemeToDocument('system');
            }
        };

        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleSystemChange);
        } else if (mediaQuery.addListener) {
            mediaQuery.addListener(handleSystemChange);
        }
    }

    return {
        theme,
        resolvedTheme,
        isDark,
        setTheme,
        toggleTheme,
    };
}
