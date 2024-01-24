<script setup>

import {Head, Link, useForm} from "@inertiajs/vue3";
import ChevronRight from "@/Components/Icons/ChevronRight.vue";
import BackButton from "@/Components/BackButton.vue";
import dayjs from "dayjs";

const props = defineProps({
    booking: Object,
    event: Object
})

const form = useForm({
    'comment': props.booking.comment,
    'name': props.booking.name
});

function cancelRegistration() {
    // Ask for confirmation
    if (!confirm('Are you sure you want to cancel your reservation?')) {
        return;
    }
    form.delete(route('bookings.destroy', {booking: props.booking.id, event: props.event.id}));
}
</script>

<template>
    <Head title="Manage Booking" />
    <div class="flex flex-col justify-between min-h-screen">
        <div>
            <!-- Back Button -->
            <BackButton :href="route('dashboard')">Back to Bookings</BackButton>
            <div class="mb-4 py-4 px-6">
                <h1 class="text-2xl font-semibold">Your reservations</h1>
                <p class="text-gray-700 text-sm mb-2">Here you can cancel your existing reservations or make a new seat reservation, please note that changing your reservation can be done by canceling and booking a new reservation.</p>
                <p class="text-gray-700 text-sm">Reservations can be made and be cancelled up to the seating submission deadline of a sigular event.</p>
            </div>
            <div class="py-4 px-6">
                <div class="flex justify-between items-center">
                    <div class="flex justify-between items-start w-full">
                        <div>
                            <div class="font-semibold">{{ booking.event.name }}</div>
                            <div
                                class="text-sm">{{ dayjs(booking.event.starts_at).format('DD.MM HH:mm') }} @ {{ booking.event.room.name }}
                            </div>
                        </div>
                        <div class="text-sm text-right">
                            <div>Block {{ booking.seat.row.block.name }}</div>
                            <div>Row {{ booking.seat.row.name }}</div>
                            <div>Seat {{ booking.seat.name }}</div>
                        </div>
                    </div>
                </div>
                <form @submit.prevent="form.patch(route('bookings.update',{booking: booking.id, event: event.id}))">
                    <div>
                        <div class="w-full mt-2">
                            <label for="name" class="text-sm">Name on the Reservation</label>
                            <input max="255" required id="name" name="name" class="form form-input w-full rounded"
                                   v-model="form.name">
                        </div>

                        <div class="w-full mt-2">
                            <label for="name" class="text-sm">Additional Comment</label>
                            <textarea max="255" id="name" name="name" class="form form-textarea w-full rounded"
                                      v-model="form.comment"></textarea>
                        </div>
                    </div>
                    <!-- Update Info Button -->
                    <div class="flex gap-3 mt-3" v-if="dayjs().isBefore(dayjs(event.reservation_ends_at))">
                        <div class="w-full">
                            <button type="submit"
                                    class="bg-blue-500 block text-sm text-center hover:bg-blue-700 text-white font-bold py-2 px-4 w-full">
                                Save
                            </button>
                        </div>
                        <!-- Cancel Button -->
                        <div class="w-full">
                            <button type="button"
                                    @click="cancelRegistration"
                                    class="bg-red-500 block text-sm text-center hover:bg-red-700 text-white font-bold py-2 px-4 w-full">
                                Cancel Reservation
                            </button>
                        </div>
                    </div>
                    <div v-else>
                        <div class="text-gray-700 text-sm font-semibold">You can no longer change your reservation.</div>
                    </div>
                    <!-- Change Deadline -->
                    <div class="text-gray-700 text-sm font-semibold mt-3">
                        You can change your reservation up until {{ dayjs(event.reservation_ends_at).format('DD.MM.YYYY HH:mm') }}.
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
