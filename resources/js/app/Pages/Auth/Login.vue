<script setup lang="ts">
import GuestLayout from '@/app/Layouts/GuestLayout.vue';
import InputError from '@/app/Components/InputError.vue';
import InputLabel from '@/app/Components/InputLabel.vue';
import {Head, useForm, usePage} from '@inertiajs/vue3';
import {Input} from "@/app/components/ui/input";
import {Button} from "@/app/components/ui/button";
import {Checkbox} from "@/app/components/ui/checkbox";
import {Password} from "@/app/components/ui/password";
import {inject} from "vue";
import {PUSH_NOTIFICATIONS} from "@/app/injection-keys";

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const pushNotificationService = inject(PUSH_NOTIFICATIONS)!

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onSuccess: () => {
          pushNotificationService.connect(usePage().props.auth.user)
        },
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <template #before-content>
            <h1>
                Login in to {{$page.props.appName}} alpha version
            </h1>
        </template>

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <Password
                    id="password"
                    class="mt-1 w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
<!--                <Link-->
<!--                    v-if="canResetPassword"-->
<!--                    :href="route('password.request')"-->
<!--                    class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"-->
<!--                >-->
<!--                    Forgot your password?-->
<!--                </Link>-->

                <Button class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Log in
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
