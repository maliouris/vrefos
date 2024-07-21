<script setup lang="ts">
import {Label} from "@/components/ui/label";
import {TimepickerInput} from "@/components/ui/timepicker";
import {computed, ref} from "vue";
import {useVModel} from "@vueuse/core";

const props = withDefaults(
    defineProps<{
        modelValue?: Date,
        defaultValue?: Date,
        withSeconds?: boolean,
        withPeriod?: boolean,
        withLabels?: boolean
    }>(), {
        modelValue: null,
        defaultValue: new Date(new Date().setHours(0, 0, 0, 0)),
        withSeconds: false,
        withPeriod: false,
        withLabels: false
    }
)

const emits = defineEmits<{
    (e: 'update:modelValue', payload: Date | undefined): void,
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
})


const internalDate = computed({
    get: () => modelValue.value ? new Date(modelValue.value) : props.defaultValue,
    set: (value) => modelValue.value = value,
});

const period = ref("PM");
const hourRef = ref(null);
const minuteRef = ref(null);
const secondRef = ref(null);
const periodRef = ref(null);

const focusMinuteRef = () => minuteRef.value?.$el.focus();
const focusHourRef = () => hourRef.value?.$el.focus();
const focusSecondRef = () => secondRef.value?.$el.focus();
const focusPeriodRef = () => periodRef.value?.$el.focus();

const focusLeftConditional = () => {
    if (props.withSeconds) {
        focusSecondRef();
    } else {
        focusMinuteRef();
    }
};
const focusRightConditional = () => {
    if (props.withSeconds) {
        focusSecondRef();
    } else {
        focusPeriodRef();
    }
};
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="flex flex-col items-center gap-1">
            <Label v-if="withLabels" for="hours" class="text-xs">Hours</Label>
            <TimepickerInput
                :picker="withPeriod ? '12hours' : 'hours'"
                :period="period"
                v-model="internalDate"
                ref="hourRef"
                @rightFocus="focusMinuteRef"
            />
        </div>
        <div v-if="!withLabels">:</div>
        <div class="flex flex-col items-center gap-1">
            <Label v-if="withLabels" for="minutes" class="text-xs">Minutes</Label>
            <TimepickerInput
                picker="minutes"
                v-model="internalDate"
                ref="minuteRef"
                @leftFocus="focusHourRef"
                @rightFocus="focusRightConditional"
            />
        </div>
        <div v-if="!withLabels && withSeconds">:</div>
        <div v-if="withSeconds" class="flex flex-col items-center gap-1">
            <Label v-if="withLabels" for="seconds" class="text-xs">Seconds</Label>
            <TimepickerInput
                v-model="internalDate"
                picker="seconds"
                ref="secondRef"
                @leftFocus="focusMinuteRef"
                @rightFocus="focusPeriodRef"
            />
        </div>
        <Select v-if="withPeriod" class="w-20" v-model="period">
            <SelectTrigger @keydown.arrow-left="focusLeftConditional" ref="periodRef">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem value="PM">
                        PM
                    </SelectItem>
                    <SelectItem value="AM">
                        AM
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
</template>
