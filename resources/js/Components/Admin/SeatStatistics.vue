<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'

const props = defineProps<{
    stats: Record<string, any>
}>()

// Guard against maxTickets == 0 (e.g. an event with no seats configured yet, since
// EventShow.vue falls back to max_tickets || totalSeats). Without this the percentage
// text renders as NaN% and the progress bar width as Infinity%, garbling the UI.
const bookedPct = computed(() =>
    props.stats.maxTickets > 0 ? Math.min(100, (props.stats.booked / props.stats.maxTickets) * 100) : 0,
)
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Ticket Statistics</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ stats.maxTickets }}</div>
                    <div class="text-sm text-muted-foreground">Available Tickets</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ stats.booked }}</div>
                    <div class="text-sm text-muted-foreground">Tickets Requested</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold" :class="stats.ticketsRemaining > 0 ? 'text-emerald-600' : 'text-red-600'">{{ stats.ticketsRemaining }}</div>
                    <div class="text-sm text-muted-foreground">Tickets Remaining</div>
                </div>
            </div>

            <!-- Progress bar for remaining tickets -->
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium">Remaining Tickets</span>
                    <span class="text-sm text-muted-foreground">{{ Math.round(bookedPct) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div
                        class="h-2 rounded-full transition-all duration-300"
                        :class="stats.isOverLimit ? 'bg-red-500' : stats.ticketsRemaining === 0 ? 'bg-yellow-500' : 'bg-emerald-500'"
                        :style="{ width: bookedPct + '%' }"
                    ></div>
                </div>
                <div v-if="stats.isOverLimit" class="mt-2 text-sm text-red-600 font-medium">
                    ⚠️ {{ stats.booked - stats.maxTickets }} tickets over limit
                </div>
                <div v-else-if="stats.ticketsRemaining === 0" class="mt-2 text-sm text-yellow-600 font-medium">
                    🎟️ Event is sold out
                </div>
            </div>
        </CardContent>
    </Card>
</template>
