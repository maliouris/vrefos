<script setup lang="ts">
import {HTMLAttributes, ref} from 'vue'
import {cn} from '@/lib/utils'
import {Link, router} from '@inertiajs/vue3';
import {RouteList, RouteParams} from "ziggy-js";
import {InertiaLinkProps} from "@inertiajs/vue3/types/link";

export interface MenuItemsProps {
    routeName: keyof RouteList
    routeParams?: RouteParams<keyof RouteList>,
  class?: HTMLAttributes['class'],
    onSuccess?: InertiaLinkProps['onSuccess']
}

// const props = withDefaults(defineProps<Props>(), {
//     routeParams: {}
// })
const props = withDefaults(defineProps<MenuItemsProps>(), {
    onSuccess: () => {}
})

const isCurrentRoute = ref(route().current(props.routeName, props.routeParams))

router.on("navigate", (event) => {
    isCurrentRoute.value = route().current(props.routeName, props.routeParams)
})
</script>

<template>
  <Link
      :href="route(routeName, routeParams)"
    :class="cn('flex items-center gap-3 rounded-lg px-3 py-2 transition-all hover:text-primary',isCurrentRoute ? 'bg-muted text-primary' : 'text-muted-foreground', props.class)"
      :onSuccess="onSuccess"
  >
    <slot />
  </Link>
</template>
