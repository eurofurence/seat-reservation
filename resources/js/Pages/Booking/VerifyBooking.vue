<script setup>

import {Head, Link, useForm, usePage} from "@inertiajs/vue3";
import BackButton from "@/Components/BackButton.vue";
import List from "@/Components/List/List.vue";
import ListItem from "@/Components/List/ListItem.vue";
import dayjs from "dayjs";
import {reactive} from "vue";
import LinkButton from "@/Components/LinkButton.vue";
import Layout from "@/Layouts/Layout.vue";
defineOptions({layout: Layout})
const props = defineProps({
    event: Object,
    seats: Array,
    seatsFalltrough: Array
})

// Create Form Model with seat id's as index
const formModel = reactive(
    Object.fromEntries(
        props.seats.map((seat) => [seat.id, {name: usePage().props.auth.user.name, comment: '', seat_id: seat.id}]
        )
    ))

function copyNameToAllSeats(text) {
    for (const [key, value] of Object.entries(formModel)) {
        value.name = text;
    }
}

function copyCommentToAllSeats(text) {
    for (const [key, value] of Object.entries(formModel)) {
        value.comment = text;
    }
}

const form = useForm({
    'seats': formModel
});

function confirmBooking() {
    form.seats = formModel;
    form.post(route('bookings.store', {event: props.event.id}));
}
</script>

<template>
    <Head title="Confirm Booking" />
    <form @submit.prevent="confirmBooking">
    <div class="flex flex-col justify-between min-h-screen">
            <div>
                <BackButton
                    :href="route('bookings.create',{event: event.id})"
                    :data="{seats: seatsFalltrough,verifyBooking: 0}"
                >Return to Seat Selection
                </BackButton>
                <div class="mb-4 py-4 px-6">
                    <h1 class="text-2xl font-semibold">{{ event.name }}</h1>
                    <p class="text-gray-700 text-sm">{{ dayjs(event.starts_at).format('DD MMMM YYYY HH:mm') }}</p>
                    <p class="text-gray-700 text-sm">{{ event.room.name }}</p>
                </div>
                <div class="px-6 py-4 font-semibold bg-red-200" v-if="form.errors.seats">
                    {{ form.errors.seats }}
                </div>
                <div>
                    <List>
                        <ListItem :clickable="false" v-for="seat in seats">
                            <div>
                                <div class="flex justify-between items-start w-full font-semibold">
                                    <div class="w-1/3">{{ seat.row.block.name }}</div>
                                    <div class="w-1/3 text-end">Row {{ seat.row.name }}</div>
                                    <div class="w-1/3 text-end">Seat {{ seat.name }}</div>
                                </div>
                                <!-- Reserver Name and Additional info field -->
                                <div class="w-full mt-2">
                                    <label for="name" class="text-sm">Name on the Reservation</label>
                                    <input id="name" name="name" class="form form-input w-full rounded"
                                           v-model="formModel[seat.id].name">
                                    <a @click="copyNameToAllSeats(formModel[seat.id].name)"
                                            class="font-semibold text-xs text-blue-600 mt-0 pt-0">Copy name to all seats
                                    </a>
                                </div>

                                <div class="w-full mt-2">
                                    <label for="name" class="text-sm">Additional Comment</label>
                                    <textarea id="name" name="name" class="form form-textarea w-full rounded"
                                              v-model="formModel[seat.id].comment"></textarea>
                                    <a @click="copyCommentToAllSeats(formModel[seat.id].comment)"
                                            class="font-semibold text-xs text-blue-600 mt-0 pt-0">Copy comment to all seats
                                    </a>
                                </div>

                            </div>
                        </ListItem>
                    </List>
                </div>
            </div>
            <!-- Tailwind UI Login Button -->
            <div>
                <LinkButton as="button"
                            type="submit">
                    Confirm Booking
                </LinkButton>
            </div>
    </div>
    </form>
</template>

<style scoped>

</style>
