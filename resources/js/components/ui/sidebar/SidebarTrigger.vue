<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { PanelLeft } from '@lucide/vue'
import { useSidebar } from './utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const { open, toggleSidebar } = useSidebar()
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    :aria-expanded="open"
    @click="toggleSidebar"
  >
    <PanelLeft />
    <span class="sr-only">{{ open ? 'Свернуть меню' : 'Развернуть меню' }}</span>
  </Button>
</template>
