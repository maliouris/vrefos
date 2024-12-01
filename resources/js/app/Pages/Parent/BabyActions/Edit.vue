<script setup lang="ts">
import InputError from '@/app/Components/InputError.vue';
import InputLabel from '@/app/Components/InputLabel.vue';
import {Head, useForm} from '@inertiajs/vue3';
import {Baby, BabyAction, BabyActionType} from "@/app/types/models";
import {Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue} from "@/app/components/ui/select";
import {Datetimepicker} from "@/app/components/ui/datepicker";
import {Button} from "@/app/components/ui/button";


const props = defineProps<{
    babies: Baby[],
    babyAction: BabyAction
    babyActionTypes: BabyActionType[]
}>()

const form = useForm({
    baby_id: props.babyAction.baby_id,
    baby_action_type_id: props.babyAction.baby_action_type_id,
    started_at: new Date(props.babyAction.started_at),
    finished_at: props.babyAction.finished_at ? new Date(props.babyAction.finished_at) : undefined,
});

const submit = () => {
    form.patch(route('baby_actions.update', props.babyAction));
};

</script>

<template>
    <Head title="Add baby action" />


    <form @submit.prevent="submit">

        <div class="mt-4">
            <InputLabel for="baby_id" value="Baby" />

            <Select
                id="baby_id"
                v-model="form.baby_id"
                required
            >
                <SelectTrigger>
                    <SelectValue placeholder="Select a baby"/>
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem v-for="baby in babies" :value="baby.id.toString()">
                            {{baby.name}}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>

            <InputError class="mt-2" :message="form.errors.baby_id" />
        </div>

        <div class="mt-4">
            <InputLabel for="type" value="Type" />

            <Select
                id="type"
                v-model="form.baby_action_type_id"
                required
            >
                <SelectTrigger>
                    <SelectValue placeholder="Select a type"/>
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem v-for="babyActionType in babyActionTypes" :value="babyActionType.id.toString()">
                            {{babyActionType.name}}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>

            <InputError class="mt-2" :message="form.errors.baby_action_type_id" />
        </div>

        <div class="mt-4">
            <InputLabel for="started_at" value="Started at" />

            <Datetimepicker
                id="started_at"
                class="mt-1 block w-full"
                v-model="form.started_at"
                required
            />

            <InputError class="mt-2" :message="form.errors.started_at" />
        </div>

        <div class="mt-4">
            <InputLabel for="finished_at" value="Finished at" />

            <Datetimepicker
                id="finished_at"
                class="mt-1 block w-full"
                v-model="form.finished_at"
                required
            />

            <InputError class="mt-2" :message="form.errors.finished_at" />
        </div>

        <div class="flex items-center gap-4 mt-4">
            <Button :disabled="form.processing">Update baby action</Button>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Baby action updated</p>
            </Transition>
        </div>
    </form>
</template>
