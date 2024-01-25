<script setup>
import ListItem from "@/Components/List/ListItem.vue";
defineOptions({layout: Layout})
defineProps({
    events: Array
})
import BackButton from "@/Components/BackButton.vue";
import List from "@/Components/List/List.vue";
import dayjs from "dayjs";
import {computed} from "vue";
import ChevronRight from "@/Components/Icons/ChevronRight.vue";
import {Head} from "@inertiajs/vue3";
import Layout from "@/Layouts/Layout.vue";

const seatColor = (seatCount) => {
    return seatCount < 10 ? 'text-red-500' : 'text-green-500';
}
</script>

<template>
    <Head title="Events" />
    <div>
        <BackButton :href="route('dashboard')">Back to Dashboard</BackButton>
        <div class="mb-4 py-4 px-6">
            <h1 class="text-2xl font-semibold">Select Event</h1>
            <p class="text-gray-700 text-sm">Please select the event you would like to book a seat for.</p>
        </div>
    </div>
    <List v-if="events.length">
        <ListItem v-for="event in events" :href="route('bookings.create',{event: event.id})">
            <div class="flex justify-between items-center">
                <div class="flex justify-between items-start w-full">
                    <div>
                        <div>{{ event.name }}</div>
                        <div :class="seatColor(event.seats_left)">{{ event.seats_left }} Seat left</div>
                    </div>
                    <div>
                        <div>{{ event.room.name }}</div>
                        <div>{{ dayjs(event.starts_at).format("DD.MM HH:MM") }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-end pl-8">
                    <ChevronRight class="w-2"></ChevronRight>
                </div>
            </div>
            <div class="text-xs">Reservation Deadline {{ dayjs(event.reservation_ends_at).format('DD.MM.YYYY - HH:mm') }}</div>
        </ListItem>
    </List>
    <div v-else class="py-4 px-6">
        <div class="text-gray-700 text-sm">There are currently no events available for booking.</div>
    </div>
</template>

<style scoped>

</style>
