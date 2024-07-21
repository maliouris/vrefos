<script setup lang="ts">
import {Calendar} from "@/components/ui/calendar";
import {computed, HTMLAttributes} from "vue";
import {useVModel} from "@vueuse/core";
import {Popover, PopoverContent, PopoverTrigger} from "@/components/ui/popover";
import {CalendarDateTime, DateFormatter, type DateValue, getLocalTimeZone} from "@internationalized/date";
import {cn} from "@/lib/utils";
import {CalendarIcon} from 'lucide-vue-next'
import {Button} from "@/components/ui/button";


const props = withDefaults(defineProps<{
    defaultValue?: Date
    modelValue?: Date
    class?: HTMLAttributes['class']
    type?: 'default' | 'advanced',
    dateFormatter?: DateFormatter
}>(), {
    defaultValue: null,
    modelValue: null,
    type: 'default',
    dateFormatter: new DateFormatter('el-GR', {
        dateStyle: 'short',
    })
})

const emits = defineEmits<{
    (e: 'update:modelValue', payload: Date | undefined): void
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
})

const value = computed(() => {
    if (props.modelValue) {
        const date = new Date(props.modelValue)

        return new CalendarDateTime(date.getFullYear(), date.getMonth() + 1, date.getDate(), date.getHours(), date.getMinutes(), date.getSeconds())
    }

    return undefined
})

const calendarChange = (date?: DateValue) => {
    const newDate = date ? new Date(date.toString()) : null
    if (newDate) {
        newDate.setHours(value.value?.hour ?? 0)
        newDate.setMinutes(value.value?.minute ?? 0)
        newDate.setSeconds(value.value?.second ?? 0)
    }

    modelValue.value = newDate
}

</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                :class="cn(
          'w-[280px] justify-start text-left font-normal',
          !value && 'text-muted-foreground',
        )"
            >
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ value ? dateFormatter.format(value.toDate(getLocalTimeZone())) : "Pick a date" }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0">
            <Calendar :type="type" :model-value="value" @update:model-value="calendarChange($event)" initial-focus />
            <slot></slot>
        </PopoverContent>
    </Popover>
</template>
