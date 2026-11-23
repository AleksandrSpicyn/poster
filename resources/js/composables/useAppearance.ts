import type { Appearance, AppearanceValues, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

function updateTheme(value: Appearance, values: AppearanceValues) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === values.SYSTEM) {
        const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        const systemTheme = mediaQueryList.matches ? values.DARK : values.LIGHT;

        document.documentElement.classList.toggle('dark', systemTheme === values.DARK);
    } else {
        document.documentElement.classList.toggle('dark', value === values.DARK);
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const handleSystemThemeChange = (values: AppearanceValues) => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || values.SYSTEM, values);
};

/** Значения темы приходят из PHP enum через shared props, до первого mount Vue. */
export function initializeTheme(values: AppearanceValues) {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || values.SYSTEM, values);

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', () => handleSystemThemeChange(values));
}

/** Синхронизирует настройку темы между Vue, localStorage и cookie корневого Blade-шаблона. */
export function useAppearance() {
    const values = usePage<SharedData>().props.appearanceValues;
    const appearance = ref<Appearance>(values.SYSTEM);

    onMounted(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // localStorage восстанавливает выбор после клиентского запуска, cookie доступна Laravel до него.
        localStorage.setItem('appearance', value);

        setCookie('appearance', value);

        updateTheme(value, values);
    }

    return {
        appearanceValues: values,
        appearance,
        updateAppearance,
    };
}
