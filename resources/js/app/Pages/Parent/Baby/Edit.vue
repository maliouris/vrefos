<script setup lang="ts">
import InputError from '@/app/Components/InputError.vue';
import InputLabel from '@/app/Components/InputLabel.vue';
import {Head, useForm} from '@inertiajs/vue3';
import {Baby} from "@/app/types/models";
import {Datepicker} from "@/app/components/ui/datepicker";
import {Input} from "@/app/components/ui/input";
import {Button} from "@/app/components/ui/button";


const submit = () => {
    form.transform((data) => {
        const birthDate = data.birth_date
        const birthDateFormatted = `${birthDate.getFullYear()}-${birthDate.getMonth() + 1}-${birthDate.getDate()}`

        return {
            ...data,
            birth_date: birthDateFormatted
        }
    }).patch(route('babies.update', {
        id: props.baby.id
    }));
};

const props = defineProps<{
    baby: Baby
}>()

const form = useForm({
    name: props.baby.name,
    birth_date: new Date(props.baby.birth_date),
});
</script>

<template>
    <Head title="Edit baby" />
            <form @submit.prevent="submit">

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />

                    <Input
                        id="name"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                        autocomplete="name"
                    />

                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="birth_date" value="Birth date" />

                    <Datepicker
                        type="advanced"
                        class="mt-1 block w-full"
                        id="birth_date"
                        v-model="form.birth_date"
                        placeholder="Pick a Date"
                    />

                    <InputError class="mt-2" :message="form.errors.birth_date" />
                </div>

                <div class="flex items-center gap-4 mt-4">
                    <Button :disabled="form.processing">Update baby details</Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Baby details saved</p>
                    </Transition>
                </div>
            </form>

</template>
