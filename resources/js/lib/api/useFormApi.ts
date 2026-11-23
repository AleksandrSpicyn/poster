import { useRoute } from '@/composables/useRoute';
import type { SharedData } from '@/types';
import type { UseFormSubmitOptions } from '@inertiajs/core';
import { usePage, type InertiaFormProps } from '@inertiajs/vue3';

const methods = { POST: 'post', PATCH: 'patch', PUT: 'put', DELETE: 'delete' } as const;
type Endpoint = string | [name: string, params: number | number[] | Record<string, string | number>];

export function useFormApi() {
    const route = useRoute();
    const page = usePage<SharedData>();

    return function submitForm(form: Pick<InertiaFormProps<object>, 'submit'>, endpoint: Endpoint, options?: UseFormSubmitOptions) {
        const [name, params] = typeof endpoint === 'string' ? [endpoint, undefined] : endpoint;
        const verb = page.props.ziggy.routes[name]?.methods.find((method): method is keyof typeof methods => method in methods);
        if (!verb) throw new Error(`No form submission method for route "${name}".`);
        const url = route(name, typeof params === 'number' ? [params] : params);
        form.submit(methods[verb], url, options);
    };
}
