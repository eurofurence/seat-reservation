<script setup>
import {Link} from "@inertiajs/vue3";
defineProps({
    href: {
        type: String,
        required: false
    }
})
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        class="bg-blue-500 disabled:bg-gray-400 block text-center duration-150 cursor-pointer hover:bg-blue-700 text-white font-bold py-4 px-4 w-full">
        <slot></slot>
    </component>
</template>

<style scoped>

</style>
