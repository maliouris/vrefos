<script setup lang="ts">
import {HTMLAttributes} from "vue";
import {useVModel} from "@vueuse/core";
import {DateFormatter, type DateValue} from "@internationalized/date";
import {Datepicker} from "@/components/ui/datepicker";
import {Timepicker} from "@/components/ui/timepicker";


const props = withDefaults(defineProps<{
    defaultValue?: Date
    modelValue?: Date
    class?: HTMLAttributes['class']
    type?: 'default' | 'advanced'
}>(), {
    defaultValue: null,
    modelValue: null,
    type: 'default'
})

const emits = defineEmits<{
    (e: 'update:modelValue', payload: Date | undefined): void
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
})

const calendarChange = (dateValue?: DateValue) => {
    console.log('change')
    if (!dateValue) {
        return modelValue.value = null
    }
    console.log(dateValue)
    const date = new Date(dateValue)
    // date.setFullYear(dateValue.getFullYear())
    // date.setMonth(dateValue.getMonth())
    // date.setDate(dateValue.getDate())

    modelValue.value = new Date(date)
}

// const hoursChange = (hours: number) => {
//     const date = new Date(modelValue.value).setHours(hours)
//     modelValue.value = new Date(date)
// }
// const minutesChange = (minutes: number) => {
//     const date = new Date(modelValue.value).setMinutes(minutes)
//     modelValue.value = new Date(date)
// }

const df = new DateFormatter('el-GR', {
    dateStyle: 'short',
    timeStyle: 'short',
    hour12: false,

})

</script>

<template>
    <Datepicker :date-formatter="df" :type="type" :model-value="modelValue" @update:model-value="calendarChange($event)">
        <div class="flex items-center justify-center py-3">
            <Timepicker v-model="modelValue" />
        </div>
    </Datepicker>
</template>
