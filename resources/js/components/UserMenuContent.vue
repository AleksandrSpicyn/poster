<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Info, Menu } from '@lucide/vue';
import { ref } from 'vue';
import { useRoute } from 'ziggy-js';

defineProps<{ user: User }>();
const emit = defineEmits<{ navigate: [] }>();
const route = useRoute();
const open = ref(false);
const sections = [
    { label: 'Профиль', description: 'Почта и личные данные', href: route('profile.edit') },
    { label: 'Безопасность', description: 'Пароль и сеансы', href: route('password.edit') },
    { label: 'Оформление', description: 'Тема интерфейса', href: route('appearance') },
];

function close() {
    open.value = false;
    emit('navigate');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child><slot /></DialogTrigger>
        <DialogContent class="account-panel bg-card text-card-foreground" :show-close="false">
            <DialogTitle class="text-xl font-bold">Меню пользователя</DialogTitle>
            <div class="bg-muted flex items-start gap-3 rounded-md border-l-2 p-3">
                <Info class="text-muted-foreground mt-0.5 size-5 shrink-0" aria-hidden="true" />
                <div class="min-w-0">
                    <strong class="block break-words">{{ user.name }}</strong>
                    <DialogDescription class="text-muted-foreground mt-1 break-all">{{ user.email }}</DialogDescription>
                </div>
            </div>
            <nav aria-label="Настройки пользователя">
                <Link
                    v-for="section in sections"
                    :key="section.href"
                    :href="section.href"
                    class="hover:bg-accent flex items-start gap-3 border-b py-2"
                    @start="close"
                >
                    <Menu class="text-primary mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <span
                        ><strong class="block">{{ section.label }}</strong
                        ><small class="text-muted-foreground">{{ section.description }}</small></span
                    >
                </Link>
            </nav>
            <Button as-child variant="outline" class="mt-3 h-11 justify-self-start">
                <Link method="post" :href="route('logout')" as="button" @click="router.flushAll()" @start="close">Выйти</Link>
            </Button>
        </DialogContent>
    </Dialog>
</template>

<style>
.account-panel[data-slot='dialog-content'] {
    inset: 76px 40px auto auto;
    width: min(480px, calc(100% - 32px));
    max-width: none;
    max-height: calc(100dvh - 96px);
    overflow-y: auto;
    translate: none;
    gap: 0;
    animation: none;
    padding: 22px;
    border-radius: 12px;
}
.account-panel h2 {
    margin-bottom: 18px;
}
@media (min-width: 768px) and (max-width: 1023px) {
    .account-panel[data-slot='dialog-content'] {
        right: 24px;
    }
}
@media (max-width: 767px) {
    .account-panel[data-slot='dialog-content'] {
        inset: auto 16px 76px;
        width: auto;
        padding: 14px 16px;
    }
}
body:has(.account-panel[data-state='open']) [data-slot='dialog-overlay'] {
    background-color: rgb(8 10 12 / 58%);
}
@media (max-width: 767px) {
    body:has(.account-panel[data-state='open']) :is([data-slot='sheet-overlay'], [data-sidebar='sidebar'][data-mobile='true']) {
        visibility: hidden;
    }
}
</style>
