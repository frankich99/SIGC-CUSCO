<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card-header"
    :class="cn('flex flex-col gap-1.5 px-6 [.border-b]:pb-6 min-w-0 max-w-full', props.class)"
  >
    <slot />
  </div>
</template>
