<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useNavigation } from '@/composables/useNavigation';
import { Link } from '@inertiajs/vue3';
import { Activity, ChartNoAxesColumn, Film, Rows2, SlidersHorizontal } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const { main } = useNavigation();
const route = useRoute();
const open = ref(false);
const heading = ref<InstanceType<typeof DialogTitle> | null>(null);
function focusTitle(event: Event) {
    event.preventDefault();
    const element = heading.value?.$el;
    if (element instanceof HTMLElement) element.focus();
}
const props = defineProps<{ visible?: boolean }>();
watch(
    () => props.visible,
    (visible) => {
        if (visible === false) open.value = false;
    },
);
const links = computed(() => {
    const items = [
        { title: 'Сериалы', description: 'Эпизоды и персонажи', icon: Film },
        { title: 'Генерации', description: 'Очередь и результаты', icon: Activity },
        { title: 'Каналы', description: 'Подключённые аккаунты', icon: ChartNoAxesColumn },
        { title: 'Источники', description: 'Импорт клипов', icon: Rows2 },
    ].flatMap((item) => {
        const link = main.value.find((link) => link.title === item.title);
        return link ? [{ ...item, href: link.href, active: link.isActive }] : [];
    });
    return [
        ...items,
        {
            title: 'Настройки',
            description: 'Профиль студии',
            icon: SlidersHorizontal,
            href: route('profile.edit'),
            active: route().current('profile.*') || route().current('password.edit') || route().current('appearance'),
        },
    ];
});
</script>
<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child><slot :open="open" /></DialogTrigger>
        <DialogContent class="account-panel studio-more-panel bg-card text-card-foreground" @open-auto-focus="focusTitle">
            <DialogTitle ref="heading" tabindex="-1" class="text-xl font-bold outline-none">Ещё</DialogTitle>
            <DialogDescription class="sr-only">Дополнительные разделы студии</DialogDescription>
            <nav aria-label="Дополнительные разделы">
                <Link
                    v-for="item in links"
                    :key="item.href"
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="more-link"
                    @start="open = false"
                >
                    <component :is="item.icon" aria-hidden="true" /><span
                        ><strong>{{ item.title }}</strong
                        ><small>{{ item.description }}</small></span
                    >
                </Link>
            </nav>
        </DialogContent>
    </Dialog>
</template>
<style scoped>
.more-link {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 7.4px 0;
    border-bottom: 1px solid var(--border);
}
.more-link:hover,
.more-link:focus-visible {
    background: var(--accent);
}
.more-link svg {
    width: 20px;
    height: 20px;
    margin-top: 3px;
    color: var(--sidebar-accent-foreground);
}
.more-link strong,
.more-link small {
    display: block;
}
.more-link small {
    margin-top: 2px;
    font-size: 13px;
    color: var(--muted-foreground);
}
</style>
