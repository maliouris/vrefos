<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import {Head, router, useForm} from '@inertiajs/vue3';
import {Input} from "@/components/ui/input";
import {Datepicker} from "@/components/ui/datepicker";
import {Button} from "@/components/ui/button";

const form = useForm<{
    name: string,
    birth_date: Date | null
}>({
    name: '',
    birth_date: null,
});

const submit = () => {
    form.transform((data) => {
        const birthDate = data.birth_date
        const birthDateFormatted = `${birthDate.getFullYear()}-${birthDate.getMonth() + 1}-${birthDate.getDate()}`

        return {
            ...data,
            birth_date: birthDateFormatted
        }
    })
        .post(route('babies.store'), {
            onSuccess: () => {
                router.visit(route('babies.show'))
            },
        });
};

</script>

<template>
    <Head title="Add baby"/>

    <!--        <template >-->
    <!--            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add baby</h2>-->
    <!--        </template>-->
    <form @submit.prevent="submit">

        <div class="mt-4">
            <InputLabel for="name" value="Name"/>

            <Input
                id="name"
                class="mt-1 block w-full"
                v-model="form.name"
                required
                autocomplete="name"
            />

            <InputError class="mt-2" :message="form.errors.name"/>
        </div>

        <div>
            <InputLabel for="birth_date" value="Birth date"/>

            <Datepicker
                type="advanced"
                id="birth_date"
                class="mt-1 block w-full"
                v-model="form.birth_date"
                required
            />

            <InputError class="mt-2" :message="form.errors.birth_date"/>
        </div>

        <div class="flex items-center gap-4 mt-4">
            <Button :disabled="form.processing">Add baby</Button>
        </div>
    </form>

</template>
