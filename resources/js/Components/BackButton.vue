<script setup>
import {useAttrs} from "vue";

defineOptions({
    inheritAttrs: false
})
const attrs = useAttrs();
import {Link} from "@inertiajs/vue3";
</script>

<template>
    <div class="py-4 px-6">
        <Link v-bind="attrs"
              as="button"
              class="flex items-center gap-2 text-gray-500 hover:text-gray-700 cursor-pointer">
            <svg class="w-6 h-6" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <div class="text-gray-700 text-sm">
                <slot></slot>
            </div>
        </Link>
    </div>
</template>

<style scoped>

</style>
