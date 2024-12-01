<script setup lang="ts">
import {Head, Link, usePage} from '@inertiajs/vue3';

const user = usePage().props.auth.user
</script>

<template>
    <Head title="Dashboard"/>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <Link :href="route('babies.create')" class="p-6 text-gray-900">Add baby!</Link>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" v-if="user.babies?.length > 0">
            <Link :href="route('baby_actions.create')" class="p-6 text-gray-900">Add baby action</Link>
        </div>
</template>
