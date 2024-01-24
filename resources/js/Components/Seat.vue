<script setup>
defineProps({
    seatNumber: String,
    taken: {
        type: Boolean,
        default: false,
    },
    selected: Boolean
})
</script>

<template>
    <div
        :class="{
            'taken': taken,
            'bg-green-400': selected && taken === false,
            'bg-white': selected === false && taken === false,
        }"
        class="duration-200 text-center h-8 w-8 flex justify-center items-center">
        {{ seatNumber }}
    </div>
</template>

<style scoped>
.taken {
    background: repeating-linear-gradient(
        45deg,
        #afb7de,
        #afb7de 5px,
        #999ec0 5px,
        #999ec0 10px
    );
}
</style>
