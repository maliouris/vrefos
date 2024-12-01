<script setup lang="ts">
import {createColumnHelper,} from '@tanstack/vue-table'

import {h} from 'vue'
import {Button} from '@/app/components/ui/button'
import {DataTable} from '@/app/components/ui/data-table'
import {DateFormatter} from "@internationalized/date";
import {Link} from '@inertiajs/vue3';
import {BabyAction} from "@/app/types/models";

const props = defineProps<{
    babyActions: BabyAction[]
}>()

const columnHelper = createColumnHelper<BabyAction>()

const datetimeFormat = new DateFormatter('el-GR',{
    dateStyle: 'short',
    timeStyle: 'short',
    hour12: false
})

const columns = [
    columnHelper.accessor('baby', {
        header: 'Baby name',
        cell: ({ row }) => h('div', { }, row.original.baby.name),
    }),
    columnHelper.accessor('baby_action_type.name', {
        enablePinning: true,
        header: 'Action type',
        cell: ({ row }) => h('div', {}, row.original.baby_action_type.name),
    }),
    columnHelper.accessor('started_at', {
        enablePinning: true,
        header: 'Started at',
        cell: ({ row }) => h('div', {}, datetimeFormat.format(new Date(row.original.started_at)))
    }),
    columnHelper.accessor('finished_at', {
        enablePinning: true,
        header: 'Finished at',
        cell: ({ row }) => h('div', {}, row.original.finished_at ? datetimeFormat.format(new Date(row.original.finished_at)) : undefined),
    }),
    columnHelper.display({
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            return h('div', {
                class: 'flex justify-end'
            }, h(Button, {
                size: 'xs',
                as: h(Link, {
                    href: route('baby_actions.edit', {id: row.original.id}),
                })
            }, () => 'Edit'))
        },
    }),
]


</script>

<template>
    <div class="basis-full">
        <div class="mb-2 flex items-start">
        <Button as-child>
            <Link :href="route('baby_actions.create')">Add baby action</Link>
        </Button>
        </div>
        <DataTable :columns="columns" :data="babyActions" />
    </div>
</template>
