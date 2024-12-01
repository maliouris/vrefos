<script setup lang="ts">
import {HTMLAttributes, ref} from 'vue'
import {useVModel} from '@vueuse/core'
import Input from "../input/Input.vue";
import {Eye, EyeOff} from 'lucide-vue-next';
import {Button} from "@/app/components/ui/button";

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes['class'],
  dontShowPassword?: boolean
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const showPassword = ref(false)
</script>

<template>
    <Input :type="showPassword ? 'text' : 'password'" v-model="modelValue" :class="props.class">
        <template #suffix v-if="!dontShowPassword">
            <Button type="button" size="xs" variant="ghost" @click="showPassword = !showPassword">
                <Eye v-if="showPassword"></Eye>
                <EyeOff v-else></EyeOff>
            </Button>
        </template>
    </Input>
</template>
