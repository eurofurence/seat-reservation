<script setup>

import Seat from "@/Components/Seat.vue";
import {onMounted, ref} from "vue";

defineEmits({
    seatSelected: (seatId) => seatId,
});
const props = defineProps({
    blocks: Array,
    selectedSeats: Array,
    takenSeats: Array,
})
const overflowContainer = ref(null);
onMounted(() => {
    overflowContainer.value.scrollTo((overflowContainer.value.offsetWidth / 2), 0);
});

function getRowAlignment(align) {
    if (align === 'left') {
        return 'items-start';
    }
    if (align === 'right') {
        return 'items-end';
    }
    return 'items-center';
}

function getMinAndMaxRowNumber() {
    let min = 0;
    let max = 0;
    props.blocks.forEach((block) => {
        block.rows.forEach((row) => {
            if (row.name < min) {
                min = row.name;
            }
            if (row.name > max) {
                max = row.name;
            }
        });
    });
    // return an array with all the row numbers
    return Array.from({length: max - min + 1}, (_, i) => i + min);
}

function getBlocksByRowNumber(rowNumber) {
    return props.blocks.filter((block) => block.row === rowNumber);
}

function isVertical(block) {
    if (block.rotate === 90 || block.rotate === 270) {
        return true;
    }
    return false;
}

function getRowStyle(block) {
    if (isVertical(block)) {
        // only text
        return {
            writingMode: 'vertical-rl',
            textOrientation: 'mixed',
        };
    }
}

function getRowClasses(block, row) {
    // add getRowAlignment(row.align)
    let classes = 'flex';
    if (isVertical(block)) {
        if (block.rotate === 90) {
            classes += ' flex-row-reverse';
        } else {
            classes += ' flex-row';
        }
    } else {
        classes += ' flex-col';
    }
    // add getRowAlignment(row.align)
    return classes + ' ' + getRowAlignment(row.align);

}

</script>

<template>
    <div
        ref="overflowContainer"
        class="overflow-auto bg-gray-200 touch-auto"
    >
        <div class="flex flex-col gap-12 w-max py-6 px-6 mx-auto">
            <div class="text-center border border-black p-2 font-bold bg-white">Stage</div>
            <div class="flex flex-col gap-12">
                <div v-for="rowNumber in getMinAndMaxRowNumber()" :key="rowNumber" class="flex gap-9">
                    <!-- Block -->
                    <div v-for="block in getBlocksByRowNumber(rowNumber)" class="flex gap-2" :class="{
                        'flex-row': isVertical(block),
                        'flex-col': !isVertical(block),
                    }">
                        <!-- Row -->
                        <div v-for="row in block.rows" class="flex flex-row" :class="getRowClasses(block,row)">
                            <div
                                class="flex bg-white border-gray-700 border  divide-gray-700"
                                :class="{'flex-col divide-y': isVertical(block), 'flex-row divide-x': !isVertical(block)}"

                            >
                                <Seat v-for="seat in row.seats"
                                      @click="$emit('seatSelected', seat.id)"
                                      :selected="selectedSeats.find((item) => item == seat.id)"
                                      :taken="takenSeats.find((item) => item == seat.id)"
                                      :seat-number="seat.name"
                                >
                                </Seat>
                            </div>
                            <small
                                class="text-center block text-xs uppercase font-mono p-2" :style="getRowStyle(block)" >{{ block.name }} - Row {{
                                    row.name
                                }}</small>
                        </div>
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
