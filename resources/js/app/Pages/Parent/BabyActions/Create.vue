<script setup lang="ts">
import InputError from '@/app/Components/InputError.vue';
import InputLabel from '@/app/Components/InputLabel.vue';
import {Head, router, useForm} from '@inertiajs/vue3';
import {Baby, BabyActionType} from "@/app/types/models";
import {Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue} from "@/app/components/ui/select";
import {Datetimepicker} from "@/app/components/ui/datepicker";
import {Button} from "@/app/components/ui/button";


const form = useForm({
    baby_id: '',
    baby_action_type_id: '',
    started_at: '',
    finished_at: '',
});

const submit = () => {
    form.post(route('baby_actions.store'), {
        onSuccess: () => {
            router.visit(route('baby_actions.show'))
        },
    });
};

defineProps<{
    babies: Baby[],
    babyActionTypes: BabyActionType[]
}>()

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
                            <SelectItem v-for="babyActionType in babyActionTypes" :value="babyActionType.id">
                                {{babyActionType.name}}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>

                <InputError class="mt-2" :message="form.errors.baby_action_type_id" />
            </div>

            <div>
                <InputLabel for="started_at" value="Started at" />

                <Datetimepicker
                    id="started_at"
                    class="mt-1 block w-full"
                    v-model="form.started_at"
                    required
                />

                <InputError class="mt-2" :message="form.errors.started_at" />
            </div>

            <div>
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
                <Button :disabled="form.processing">Add baby action</Button>
            </div>
        </form>
</template>
