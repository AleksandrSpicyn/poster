import type { Appearance, AppearanceValues } from '@/types';
import { inject, readonly, ref, type App, type InjectionKey, type Ref } from 'vue';

interface ThemePreference {
    appearance: Readonly<Ref<Appearance>>;
    updateAppearance: (value: Appearance) => void;
}

const themePreference: InjectionKey<ThemePreference> = Symbol('themePreference');

function storedPreference(values: AppearanceValues): Appearance {
    try {
        const stored = localStorage.getItem('appearance');
        if (stored === values.DARK || stored === values.LIGHT) return stored;
    } catch {
        // Storage can be unavailable in a restricted browsing context.
    }
    return values.SYSTEM;
}

export function installAppearance(app: App, values: AppearanceValues) {
    const browser = typeof window !== 'undefined';
    const appearance = ref<Appearance>(browser ? storedPreference(values) : values.SYSTEM);
    const system = browser ? window.matchMedia('(prefers-color-scheme: dark)') : undefined;
    function apply() {
        if (system)
            document.documentElement.classList.toggle(
                'dark',
                appearance.value === values.DARK || (appearance.value === values.SYSTEM && system.matches),
            );
    }

    apply();
    function syncPreference(event: StorageEvent) {
        if (event.key !== 'appearance' && event.key !== null) return;
        appearance.value = storedPreference(values);
        apply();
    }
    if (browser) {
        system?.addEventListener('change', apply);
        window.addEventListener('storage', syncPreference);
        app.onUnmount(() => {
            system?.removeEventListener('change', apply);
            window.removeEventListener('storage', syncPreference);
        });
    }

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        apply();
        if (!browser) return;
        try {
            localStorage.setItem('appearance', value);
        } catch {
            // The cookie still supplies the preference for the next server render.
        }
        document.cookie = `appearance=${value};path=/;max-age=31536000;SameSite=Lax`;
    }
    app.provide(themePreference, { appearance: readonly(appearance), updateAppearance });
}

export function useAppearance() {
    const preference = inject(themePreference);
    if (!preference) throw new Error('Theme preference must be installed on the current application.');
    return preference;
}
