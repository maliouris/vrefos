<script setup lang="ts">
import { Input } from '@/components/ui/input';
import {
    getArrowByType,
    getDateByType,
    setDateByType
} from './timepicker-utils.ts';
import { cn } from '@/lib/utils';
import {computed, ref, watch, defineEmits} from "vue";
import {useVModel} from "@vueuse/core";

const props = withDefaults(defineProps<{
    picker: string
    modelValue?: Date,
    defaultValue?: Date
    period?: string,
    class?: string,
    type?: string,
    id?: string,
    name?: string,
}>(),{
    modelValue: null,
    defaultValue: new Date(new Date().setHours(0, 0, 0, 0)),
    type: 'tel'
})

const emits = defineEmits<{
    (e: 'update:modelValue', payload: Date | undefined): void,
    (e: 'rightFocus'): void,
    (e: 'leftFocus'): void,
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
})

const flag = ref(false);
const prevIntKey = ref('');

const inputClasses = computed(() =>
    cn('w-[48px] text-center font-mono text-base tabular-nums caret-transparent focus:bg-accent focus:text-accent-foreground [&::-webkit-inner-spin-button]:appearance-none', props.class)
);

const calculatedValue = computed(() => {
    return getDateByType(modelValue.value ? modelValue.value : props.defaultValue, props.picker)
});

watch(flag, (newFlag) => {
    if (newFlag) {
        const timer = setTimeout(() => {
            flag.value = false;
        }, 2000);
        return () => clearTimeout(timer);
    }
});

watch(() => props.period, (newPeriod) => {
    if (newPeriod) {
        const tempDate = new Date(modelValue.value);
        modelValue.value = setDateByType(tempDate, tempDate.getHours() % 12, props.picker, newPeriod);
    }
});

const calculateNewValue = (key) => {
    if (props.picker === '12hours') {
        if (flag.value && prevIntKey.value === '1' && ['0', '1', '2'].includes(key)) {
            const newValue = '1' + key;
            prevIntKey.value = '';
            return newValue;
        }
        if (flag.value) {
            prevIntKey.value = '';
            return prevIntKey.value + key;
        }
        prevIntKey.value = key;
        return '0' + key;
    }
    return !flag.value ? '0' + key : calculatedValue.value.slice(1, 2) + key;
};

const handleKeyDown = (e) => {
    if (e.key === 'Tab') return;

    e.preventDefault();

    if (e.key === 'ArrowRight') emits('rightFocus');
    if (e.key === 'ArrowLeft') emits('leftFocus');
    if (['ArrowUp', 'ArrowDown'].includes(e.key)) {
        const step = e.key === 'ArrowUp' ? 1 : -1;
        const newValue = getArrowByType(calculatedValue.value, step, props.picker);
        if (flag.value) flag.value = false;
        const tempDate = new Date(modelValue.value);
        modelValue.value = setDateByType(tempDate, newValue, props.picker, props.period);
    }
    if (e.key >= '0' && e.key <= '9') {
        const newValue = calculateNewValue(e.key);
        if (flag.value && (newValue === '10' || newValue === '11')) {
            emits('rightFocus');
        }
        flag.value = !flag.value;
        const tempDate = new Date(modelValue.value);
        modelValue.value = setDateByType(tempDate, newValue, props.picker, props.period);
    }
};
</script>
<template>
    <Input
        :id="picker"
        :name="picker"
        :class="inputClasses"
        :value="calculatedValue"
        :defaultValue="calculatedValue"
        :type="type"
        inputmode="decimal"
        @keydown="handleKeyDown"
    />
</template>
