<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import type { ManagedChannel } from '@/types/channels';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{ channels: ManagedChannel[]; disabled: boolean; errors: Record<string, string | undefined> }>();
const selected = defineModel<number[] | null>({ required: true });
const route = useRoute();
const itemErrors = computed(() => Object.entries(props.errors).filter(([key]) => key.startsWith('channel_ids.')));

function projectLabel(channel: ManagedChannel) {
    if (!channel.projects?.length) return 'Доступен для подключения';
    return 'Выбран в проектах ' + channel.projects.map((project) => '«' + project.name + '»').join(', ');
}

function connectionLabel(channel: ManagedChannel) {
    if (!channel.is_active) return 'Приостановлен';
    return channel.connected ? (channel.account_name ?? 'Подключён') : 'Нужно подключение';
}
</script>

<template>
    <fieldset id="publishing-channels" tabindex="-1" class="min-w-0 space-y-3 rounded-lg border p-4 sm:p-5">
        <legend class="sr-only">Каналы публикации</legend>

        <div v-if="channels.length" class="grid grid-cols-1 gap-2 divide-y">
            <label v-for="channel in channels" :key="channel.id" class="hover:bg-muted/50 flex min-h-14 cursor-pointer items-start gap-3 py-3">
                <input v-model="selected" type="checkbox" :value="channel.id" :disabled="disabled" class="accent-primary mt-1 size-5 shrink-0" />
                <span class="min-w-0 flex-1 text-sm"
                    ><span class="block font-medium">{{ channel.name }} · {{ channel.type_label }}</span
                    ><span class="text-muted-foreground block text-xs">{{ projectLabel(channel) }} · {{ connectionLabel(channel) }}</span></span
                >
            </label>
        </div>
        <p v-else class="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">Подключите канал, чтобы выбрать его для проекта.</p>
        <InputError :message="errors.channel_ids" />
        <InputError v-for="[key, message] in itemErrors" :key="key" :message="message" />
        <Link
            v-if="!channels.length"
            :href="route('channels.index')"
            class="text-primary inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4"
            >Управление каналами</Link
        >
    </fieldset>
</template>
