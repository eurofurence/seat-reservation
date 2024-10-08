<script setup>
import ListItem from "@/Components/List/ListItem.vue";

const props = defineProps({
    event: Object,
    seats: Array,
    takenSeats: Array,
    availableToUser: Number,
})
import BackButton from "@/Components/BackButton.vue";
import List from "@/Components/List/List.vue";
import dayjs from "dayjs";
import {computed, onMounted, ref} from "vue";
import ChevronRight from "@/Components/Icons/ChevronRight.vue";
import {Head, Link} from "@inertiajs/vue3";
import Seat from "@/Components/Seat.vue";
import LinkButton from "@/Components/LinkButton.vue";
import StageBox from "@/Components/StageBox.vue";
import FullWidthLayout from "@/Layouts/FullWidthLayout.vue";

defineOptions({layout: FullWidthLayout})

const selectedSeats = ref(props.seats);

function clickSeatHandler(seatId) {
    // Check if seat is already taken
    if (props.takenSeats.includes(seatId)) {
        return;
    }
    // Make sure user can only select the amount of seats that are available to them
    // only
    // Check if seat is already selected, if so remove it, if not add it
    if (selectedSeats.value.find(function (item) {
        return item == seatId;
    })) {
        selectedSeats.value.splice(selectedSeats.value.findIndex((item) => item == seatId), 1)
    } else {
        if (selectedSeats.value.length >= props.availableToUser) {
            return;
        }
        selectedSeats.value.push(seatId)
    }
}

onMounted(() => {
    // Remove any seats that are already taken from the selectedSeats array
    selectedSeats.value = selectedSeats.value.filter((seat) => !props.takenSeats.includes(seat));
});

</script>

<template>
    <div class="flex flex-col h-full justify-between">
        <Head title="Create Booking"/>
        <div>
            <div>
                <BackButton :href="route('events.index')">Back to Events</BackButton>
                <div class="mb-2 py-4 px-6">
                    <h1 class="text-2xl font-semibold">Select your Seat(s)</h1>
                    <p class="text-gray-700 text-sm">Please select up to {{ availableToUser }} Seats.</p>
                </div>
            </div>
            <div class="pb-2 px-6">
                <div class="text-center hidden lg:block">
                    Use the scrollbar below to scroll through the room.
                </div>
                <div class="block lg:hidden">
                    Use two fingers to scroll through the room. Confirm at the bottom of the page.
                </div>
                <!-- Alert text how many are still available -->
                <div class="text-center text-gray-700 mt-2 text-2xl" :class="{'text-red-600': availableToUser === selectedSeats.length}">
                    {{ selectedSeats.length }} / {{ availableToUser }} Seats selected
                </div>
            </div>
        </div>
        <div>
            <StageBox
                @seat-selected="(e) => clickSeatHandler(e)"
                :selected-seats="selectedSeats"
                :taken-seats="takenSeats"
                :blocks="event.room.blocks"
            ></StageBox>
            <!-- Confirm Bookings Button -->
            <LinkButton
                :href="route('bookings.create', {event: event.id})"
                :data="{seats: selectedSeats, verifyBooking: 1}" as="button" type="submit"
                :disabled="selectedSeats.length === 0"
            >
                Confirm Booking
            </LinkButton>
        </div>
    </div>
</template>

<style scoped>
.w-max {
    width: max-content;
}
</style>
