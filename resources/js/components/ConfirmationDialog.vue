<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';

defineProps<{
    heading: string;
    title: string;
    description: string;
    cancel?: string;
    destructive?: boolean;
    action: string;
    processing?: boolean;
    error?: string;
}>();
defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child
            ><slot name="trigger"><Button variant="outline" class="h-11">В архив</Button></slot></DialogTrigger
        >
        <DialogContent class="studio-confirm" :show-close="false">
            <DialogTitle class="studio-confirm-title">{{ heading }}</DialogTitle>
            <div class="studio-confirm-notice">
                <CircleAlert aria-hidden="true" />
                <strong>{{ title }}</strong>
                <DialogDescription>{{ description }}</DialogDescription>
            </div>
            <dl><slot /></dl>
            <InputError :message="error" />
            <DialogFooter class="studio-confirm-actions">
                <Button type="button" :variant="destructive ? 'destructive' : 'default'" :disabled="processing" @click="$emit('confirm')">{{
                    action
                }}</Button>
                <DialogClose as-child
                    ><Button type="button" variant="outline" :disabled="processing">{{ cancel ?? 'Отмена' }}</Button></DialogClose
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
