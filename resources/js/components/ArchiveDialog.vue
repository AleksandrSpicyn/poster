<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

defineProps<{ title: string; description: string; action: string; processing: boolean; error?: string }>();
defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child><Button variant="outline" class="h-11">В архив</Button></DialogTrigger>
        <DialogContent class="max-h-[calc(100dvh-2rem)] overflow-y-auto">
            <DialogHeader
                ><DialogTitle>{{ title }}</DialogTitle
                ><DialogDescription>{{ description }}</DialogDescription></DialogHeader
            >
            <dl class="divide-y"><slot /></dl>
            <InputError :message="error" />
            <DialogFooter>
                <DialogClose as-child><Button type="button" variant="outline" :disabled="processing">Отмена</Button></DialogClose>
                <Button type="button" :disabled="processing" @click="$emit('confirm')">{{ action }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
