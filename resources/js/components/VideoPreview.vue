<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { PromptSummary } from '@/types/content';
import { Play } from '@lucide/vue';

defineProps<{ prompt: PromptSummary }>();
</script>

<template>
    <Dialog v-if="prompt.url">
        <DialogTrigger as-child
            ><Button variant="outline" size="sm" class="h-11 sm:h-9"
                ><Play class="size-3.5" aria-hidden="true" /> Preview<span class="sr-only"> generation {{ prompt.id }}</span></Button
            ></DialogTrigger
        >
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader
                ><DialogTitle>Video preview</DialogTitle><DialogDescription class="break-words">{{ prompt.title }}</DialogDescription></DialogHeader
            >
            <video controls preload="metadata" class="mx-auto max-h-[60svh] w-full rounded-lg bg-black">
                <source :src="prompt.url" type="video/mp4" />
                Your browser does not support video playback.
            </video>
        </DialogContent>
    </Dialog>
    <span v-else class="text-muted-foreground text-xs">Not ready yet</span>
</template>
