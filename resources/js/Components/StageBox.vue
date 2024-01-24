<script setup>

import Seat from "@/Components/Seat.vue";
import {onMounted, ref} from "vue";

defineEmits({
    seatSelected: (seatId) => seatId,
});
defineProps({
    blocks: Array,
    selectedSeats: Array,
    takenSeats: Array,
})
const overflowContainer = ref(null);
onMounted(() => {
    overflowContainer.value.scrollTo((overflowContainer.value.offsetWidth / 2), 0);
});
</script>

<template>
    <div
        ref="overflowContainer"
        class="overflow-auto bg-gray-200 touch-auto"
    >
        <div class="flex flex-col gap-12 w-max py-6 px-6 mx-auto">
            <div class="text-center border border-black p-2 font-bold bg-white">Stage</div>
            <!-- Block Container -->
            <div class="flex gap-12">
                <!-- Block -->
                <div v-for="block in blocks" class="flex flex-col gap-3">
                    <!-- Row -->
                    <div v-for="row in block.rows">
                        <div class="flex bg-white border-gray-700 border divide-x divide-gray-700">
                            <Seat v-for="seat in row.seats"
                                  @click="$emit('seatSelected', seat.id)"
                                  :selected="selectedSeats.find((item) => item == seat.id)"
                                  :taken="takenSeats.find((item) => item == seat.id)"
                                  :seat-number="seat.name"
                            >
                            </Seat>
                        </div>
                        <small
                            class="text-center block text-xs uppercase font-mono">{{ block.name }} - Row {{ row.name }}</small>
                    </div>
                </div>
            </div>
            <!-- Explanation -->
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center justify-start gap-2">
                    <Seat class="border border-gray-700" seat-number="1"></Seat>
                    <div>Free</div>
                </div>
                <div class="flex items-center justify-start gap-2">
                    <Seat class="border border-gray-700" seat-number="1" selected></Seat>
                    <div>Selected</div>
                </div>
                <div class="flex items-center justify-start gap-2">
                    <Seat class="border border-gray-700" seat-number="1" taken></Seat>
                    <div>Taken</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
