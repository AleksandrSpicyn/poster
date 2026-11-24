import { useFormApi } from '@/lib/api/useFormApi';
import { useForm, type InertiaForm } from '@inertiajs/vue3';

export function useProductionRunActions() {
    const submitForm = useFormApi();

    const transition = useForm({});

    function start(projectId: number, form: Pick<InertiaForm<{ request_key: string }>, 'submit' | 'processing' | 'request_key'>) {
        submitForm(form, ['production_runs.store', projectId], {
            preserveScroll: true,
            onSuccess: () => {
                form.request_key = crypto.randomUUID();
            },
        });
    }

    function changeStatus(runId: number, action: 'stop' | 'resume') {
        submitForm(transition, ['production_runs.' + action, runId], { preserveScroll: true });
    }

    return { start, changeStatus, transition };
}
