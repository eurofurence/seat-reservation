<script setup>

import {Link} from "@inertiajs/vue3";
import ChevronRight from "@/Components/Icons/ChevronRight.vue";
const props = defineProps({
    clickable: {
        type: Boolean,
        default: true
    }
})
</script>

<template>
    <component :is="clickable ? Link : 'div'" as="div" :class="{
        'cursor-pointer hover:bg-gray-100 duration-200': clickable,
        'cursor-default': !clickable
    }" class="py-4 px-6">
        <slot></slot>
    </component>
</template>

<style scoped>

</style>
