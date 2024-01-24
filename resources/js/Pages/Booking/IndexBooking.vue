<script setup>

import {Head, Link} from "@inertiajs/vue3";
import ChevronRight from "@/Components/Icons/ChevronRight.vue";
import List from "@/Components/List/List.vue";
import ListItem from "@/Components/List/ListItem.vue";
import dayjs from "dayjs";
import BackButton from "@/Components/BackButton.vue";

defineProps({
    bookings: Array
})
</script>

<template>
    <Head title="My Bookings" />
    <div class="flex flex-col justify-between min-h-screen">
        <div>
            <BackButton method="post" :href="route('auth.logout')">Logout</BackButton>
            <div class="mb-4 py-4 px-6">
                <h1 class="text-2xl font-semibold">Your reservations</h1>
                <p class="text-gray-700 text-sm mb-2">Here you can cancel your existing reservations or make a new seat reservation, please note that changing your reservation can be done by canceling and booking a new reservation.</p>
                <p class="text-gray-700 text-sm">Reservations can be made and be cancelled up to the seating submission deadline of a sigular event.</p>
            </div>
            <div v-if="$page.props.flash.message" class="px-6 py-4 bg-blue-300 font-semibold mb-4">
                {{ $page.props.flash.message }}
            </div>
            <List v-if="bookings.length">
                <ListItem as="div" :href="route('bookings.show',{booking: booking.id,event: booking.event.id})" v-for="booking in bookings" :key="booking.id">
                    <div class="flex justify-between items-center">
                        <div class="flex justify-between items-start w-full">
                            <div>
                                <div class="font-semibold">{{ booking.event.name }}</div>
                                <div class="text-sm">{{ dayjs(booking.event.starts_at).format('DD.MM HH:mm') }} @ {{ booking.event.room.name }}</div>
                                <div class="text-sm">{{ booking.name }}</div>
                            </div>
                            <div class="text-sm text-right">
                                <div>Block {{ booking.seat.row.block.name }}</div>
                                <div>Row {{ booking.seat.row.name }}</div>
                                <div>Seat {{ booking.seat.name }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end pl-8">
                            <ChevronRight class="w-2"></ChevronRight>
                        </div>
                    </div>
                </ListItem>
            </List>
            <div v-else class="py-4 px-6 bg-blue-200">
                <div class="text-gray-700 text-sm font-semibold">You have no reservations yet.</div>
            </div>
        </div>
        <!-- Tailwind UI Login Button -->
        <div>
            <Link :href="route('events.index')"
                  class="bg-blue-500 block text-center hover:bg-blue-700 text-white font-bold py-2 px-4 w-full">
                Make a new Booking
            </Link>
        </div>
    </div>
</template>

<style scoped>

</style>
