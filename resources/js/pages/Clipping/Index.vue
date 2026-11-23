<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

defineProps<{
    connections: {
        id: number;
        name: string;
        driver: string;
        import_projects: boolean;
        catalog_checked_at: string | null;
        catalog_error: string | null;
    }[];
    drivers: Record<string, string>;
}>();
const route = useRoute();
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Источники', href: route('clipping.index') }]">
        <Head title="Источники" />
        <div class="workspace-page">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <Heading title="Источники" description="Poster импортирует проекты и клипы при подключении площадки и обновляет их каждый час." />
                <div class="flex flex-wrap gap-2">
                    <Button v-if="connections.length" as-child variant="outline"
                        ><Link :href="route('clipping.history')">История импорта</Link></Button
                    ><Button as-child><Link :href="route('clipping.create')">Подключить площадку</Link></Button>
                </div>
            </header>
            <p v-if="!connections.length" class="text-muted-foreground">Пока нет подключённых площадок.</p>
            <ul v-else class="divide-y">
                <li v-for="connection in connections" :key="connection.id">
                    <Link :href="route('clipping.edit', connection.id)" class="flex flex-wrap items-center justify-between gap-2 py-4">
                        <span
                            ><strong class="block">{{ connection.name }}</strong
                            ><small v-if="connection.name !== drivers[connection.driver]" class="text-muted-foreground">{{
                                drivers[connection.driver]
                            }}</small></span
                        >
                        <span class="text-sm" :class="connection.catalog_error ? 'text-destructive' : 'text-muted-foreground'">{{
                            !connection.import_projects
                                ? 'Импорт выключен'
                                : connection.catalog_error
                                  ? 'Ошибка подключения'
                                  : connection.catalog_checked_at
                                    ? 'Автообновление включено'
                                    : 'Ожидает проверки'
                        }}</span>
                    </Link>
                </li>
            </ul>
        </div></AppLayout
    >
</template>
