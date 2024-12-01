<script setup lang="ts">
import {createColumnHelper,} from '@tanstack/vue-table'

import {h} from 'vue'
import {Button} from '@/app/components/ui/button'
import {DataTable} from '@/app/components/ui/data-table'

import {Link} from '@inertiajs/vue3';
import {Baby} from "@/app/types/models";

const props = defineProps<{
    babies: Baby[]
}>()

const columnHelper = createColumnHelper<Baby>()

const columns = [
    columnHelper.accessor('name', {
        header: 'Name',
        cell: ({ row }) => h('div', { }, row.original.name),
    }),
    columnHelper.accessor('birth_date', {
        enablePinning: true,
        //     header: ({ column }) => {
        //         return h(Button, {
        //             variant: 'ghost',
        //             onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
        //         }, () => ['Birthday', h(ArrowUpDown)])
        //     },
        header: 'Birthday',
        cell: ({ row }) => h('div', {}, new Date(row.original.birth_date).toLocaleDateString('el-GR')),
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
                    href: route('babies.edit', {id: row.original.id}),
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
            <Link :href="route('babies.create')">Add baby</Link>
        </Button>
        </div>
        <DataTable :columns="columns" :data="babies" />
    </div>
</template>
