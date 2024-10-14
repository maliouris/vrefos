<script setup lang="ts">
import type {HTMLAttributes} from 'vue'
import {useVModel} from '@vueuse/core'
import {cn} from '@/lib/utils'

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes['class'],
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

defineOptions({
    inheritAttrs: false
})
</script>

<template>
    <label :class="cn('flex items-center h-10 w-full px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background overflow-hidden file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50', props.class)">
        <input class="w-full h-full focus-visible:outline-none bg-transparent" v-model="modelValue" v-bind="$attrs">
        <slot name="suffix" />
    </label>
</template>
