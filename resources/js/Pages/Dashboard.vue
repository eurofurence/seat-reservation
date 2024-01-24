<script setup>

import {Link} from "@inertiajs/vue3";

const seats = [
    {
        'name': 'Meeting of the Furries!',
        'time': '17:00',
        'location': 'CCH Hall 1',
        'seat': 'Right Wing, Row 3, Seat 4'
    },
    {
        'name': 'Meeting of the Furries!',
        'time': '17:00',
        'location': 'CCH Hall 1',
        'seat': 'Right Wing, Row 3, Seat 4'
    },
    {
        'name': 'Meeting of the Furries!',
        'time': '17:00',
        'location': 'CCH Hall 1',
        'seat': 'Right Wing, Row 3, Seat 4'
    },
    {
        'name': 'Meeting of the Furries!',
        'time': '17:00',
        'location': 'CCH Hall 1',
        'seat': 'Right Wing, Row 3, Seat 4'
    }
];
</script>

<template>
    <div class="flex flex-col justify-between min-h-screen py-4 px-6">
        <div>
            <div class="mb-4">
                <h1 class="text-2xl font-semibold">Your reservations</h1>
                <p class="text-gray-700 text-sm mb-2">Here you can cancel your existing reservations or make a new seat reservation, please note that changing your reservation can be done by canceling and booking a new reservation.</p>
                <p class="text-gray-700 text-sm">Reservations can be made and be cancelled up to the seating submission deadline of a sigular event.</p>
            </div>
            <div class="divide-solid divide-y divide-gray-400 mb-8">
                <div v-for="seat in seats" class="py-3">
                    <div class="flex justify-between items-start py-2">
                        <div>
                            <div class="font-semibold">{{ seat.name }}</div>
                            <div class="text-sm">{{ seat.time }} @ {{ seat.location }}</div>
                        </div>
                        <div>
                            <div>{{ seat.seat }}</div>
                        </div>
                    </div>
                    <!-- Cancel Booking button full width and sdecondary -->
                    <div>
                        <button class="bg-white duration-200 block text-center hover:bg-gray-200 drop-shadow text-gray-900 border border-gray-300 font-bold py-1 px-4 rounded w-full">
                            Cancel Booking
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tailwind UI Login Button -->
        <div>
            <Link as="button" method="post" :href="route('auth.login.redirect')"
                  class="bg-blue-500 block text-center hover:bg-blue-700 text-white font-bold py-2 px-4 rounded w-full">
                Make a new Booking
            </Link>
        </div>
    </div>
</template>

<style scoped>

</style>
