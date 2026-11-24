import type { TiktokPublicationProps } from '@/types/tiktokPublication';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

export function useTiktokPublicationForm(props: TiktokPublicationProps) {
    const consentChanged = ref(false);
    const form = useForm({
        media_id: null as number | null,
        account_id: props.accountId,
        mode: props.inbox ? 'inbox' : 'direct',
        title: '',
        privacy: '',
        consent: false,
        comment: false,
        duet: false,
        stitch: false,
        disclose: false,
        own_brand: false,
        partner: false,
        ai: false,
    });
    watch(
        () => [props.accountId, props.inbox, props.creator] as const,
        ([account, inbox, creator]) => {
            if (form.account_id !== account) {
                form.account_id = account;
                form.privacy = '';
                form.own_brand = false;
                form.partner = false;
                form.disclose = false;
            }
            if (!inbox && creator) form.mode = 'direct';
            if (!creator && inbox) form.mode = 'inbox';
            if (!creator?.privacy.includes(form.privacy)) form.privacy = '';
            if (!creator?.comment) form.comment = false;
            if (!creator?.duet) form.duet = false;
            if (!creator?.stitch) form.stitch = false;
            form.consent = false;
        },
    );
    watch(
        () => [
            form.media_id,
            form.mode,
            form.title,
            form.privacy,
            form.disclose,
            form.comment,
            form.duet,
            form.stitch,
            form.own_brand,
            form.partner,
            form.ai,
        ],
        () => {
            consentChanged.value = form.consent || consentChanged.value;
            form.consent = false;
        },
    );
    watch(
        () => form.disclose,
        (disclose) => {
            if (!disclose) {
                form.partner = false;
                form.own_brand = false;
            }
        },
    );
    return { form, consentChanged };
}
