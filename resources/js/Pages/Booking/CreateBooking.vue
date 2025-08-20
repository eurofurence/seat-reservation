<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import FullWidthLayout from "@/Layouts/FullWidthLayout.vue"
import SeatLayout from "@/Components/SeatLayout.vue"
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Alert } from '@/Components/ui/alert'
import { ArrowLeft, Info, AlertCircle, Calendar, MapPin, Clock, ArrowRight } from 'lucide-vue-next'
import dayjs from "dayjs"

defineOptions({layout: FullWidthLayout})

const props = defineProps({
    event: Object,
    room: Object,
    blocks: Array,
    bookedSeats: Array,
    maxSeatsPerUser: Number,
    userBookedCount: Number
})

const selectedSeats = ref([])
const showInstructions = ref(false)

const availableSeats = computed(() => {
    return props.maxSeatsPerUser - props.userBookedCount
})

const canProceed = computed(() => {
    return selectedSeats.value.length > 0 && selectedSeats.value.length <= availableSeats.value
})

function handleSeatsChanged(seats) {
    selectedSeats.value = seats
}

function proceedToValidation() {
    if (canProceed.value) {
        const form = useForm({
            seats: selectedSeats.value,
            validate: true
        })

        form.get(route('bookings.create', { event: props.event.id }))
    }
}

function goBack() {
    window.location.href = route('events.index')
}
</script>

<template>
  <Head title="Select Seats" />

  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">
          <div class="flex items-center">
            <button
              @click="goBack"
              class="mr-4 p-2 hover:bg-gray-100 rounded-md transition-colors lg:hidden"
            >
              <ArrowLeft class="h-5 w-5" />
            </button>
            <h1 class="text-xl lg:text-2xl font-semibold">{{ event.name }}</h1>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Desktop: Back button -->
            <Button variant="outline" @click="goBack" class="hidden lg:flex">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Back to Events
            </Button>
            <button
              @click="showInstructions = !showInstructions"
              class="p-2 hover:bg-gray-100 rounded-md transition-colors"
            >
              <Info class="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
      <div class="lg:grid lg:grid-cols-12 lg:gap-8">
        <!-- Left Sidebar: Event Info & Instructions (Desktop) -->
        <div class="lg:col-span-4 xl:col-span-3 space-y-6 mb-6 lg:mb-0">
          <!-- Selection Status Alert -->
          <Alert
            :variant="selectedSeats.length === availableSeats ? 'destructive' : 'default'"
          >
            <AlertCircle class="h-4 w-4" />
            <div class="font-medium">
              {{ selectedSeats.length }} / {{ availableSeats }} seats selected
              <span v-if="userBookedCount > 0" class="text-sm text-muted-foreground block lg:inline">
                ({{ userBookedCount }} already booked)
              </span>
            </div>
          </Alert>

          <!-- Event Info -->
          <Card>
            <div class="p-4 lg:p-6 space-y-3">
              <h3 class="font-semibold text-lg mb-4 hidden lg:block">Event Details</h3>
              <div class="flex items-center text-sm lg:text-base">
                <Calendar class="h-4 w-4 mr-2 text-gray-500 flex-shrink-0" />
                <span class="font-medium mr-2">Event:</span>
                <span class="break-words">{{ event.name }}</span>
              </div>
              <div class="flex items-center text-sm lg:text-base">
                <MapPin class="h-4 w-4 mr-2 text-gray-500 flex-shrink-0" />
                <span class="font-medium mr-2">Room:</span>
                <span>{{ room.name }}</span>
              </div>
              <div class="flex items-center text-sm lg:text-base">
                <Clock class="h-4 w-4 mr-2 text-gray-500 flex-shrink-0" />
                <span class="font-medium mr-2">Date & Time:</span>
                <span>{{ dayjs(event.starts_at).format('MMM DD, YYYY - HH:mm') }}</span>
              </div>
            </div>
          </Card>

          <!-- Instructions -->
          <Card class="hidden lg:block">
            <div class="p-4 lg:p-6">
              <h3 class="font-semibold mb-3 lg:text-lg">How to Select Seats</h3>
              <ul class="space-y-2 text-sm lg:text-base text-gray-600">
                <li class="flex items-start">
                  <span class="mr-2">•</span>
                  Click on available seats (green) to select them
                </li>
                <li class="flex items-start">
                  <span class="mr-2">•</span>
                  Click again to deselect
                </li>
                <li class="flex items-start">
                  <span class="mr-2">•</span>
                  You can select up to {{ availableSeats }} seats
                </li>
                <li class="flex items-start">
                  <span class="mr-2">•</span>
                  Red seats are already booked
                </li>
              </ul>
            </div>
          </Card>

          <!-- Desktop: Continue Button -->
          <div class="hidden lg:block">
            <Button
              size="lg"
              :disabled="!canProceed"
              @click="proceedToValidation"
              class="w-full"
            >
              Continue to Details
              <ArrowRight class="ml-2 h-4 w-4" />
            </Button>
          </div>
        </div>

        <!-- Mobile Instructions (when shown) -->
        <Card v-if="showInstructions" class="mb-6 p-4 lg:hidden">
          <h3 class="font-semibold mb-3">How to Select Seats</h3>
          <ul class="space-y-2 text-sm text-gray-600">
            <li class="flex items-start">
              <span class="mr-2">•</span>
              Click on available seats (green) to select them
            </li>
            <li class="flex items-start">
              <span class="mr-2">•</span>
              Click again to deselect
            </li>
            <li class="flex items-start">
              <span class="mr-2">•</span>
              You can select up to {{ availableSeats }} seats
            </li>
            <li class="flex items-start">
              <span class="mr-2">•</span>
              Red seats are already booked
            </li>
          </ul>
        </Card>

        <!-- Right Content: Seat Layout -->
        <div class="lg:col-span-8 xl:col-span-9">
          <Card class="mb-20 lg:mb-0">
            <div class="p-4 lg:p-6">
              <h3 class="font-semibold text-lg mb-4 hidden lg:block">Select Your Seats</h3>
              <SeatLayout
                :event="event"
                :room="room"
                :blocks="blocks"
                :selected-seats="selectedSeats"
                :booked-seats="bookedSeats"
                @seats-changed="handleSeatsChanged"
              />
            </div>
          </Card>
        </div>
      </div>
    </div>

    <!-- Fixed Bottom Action Bar (Mobile Only) -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg lg:hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-center justify-between">
          <div class="text-sm">
            <span class="font-semibold">{{ selectedSeats.length }} seat(s) selected</span>
            <span class="text-gray-500 ml-2">Max: {{ availableSeats }}</span>
          </div>
          <Button
            size="lg"
            :disabled="!canProceed"
            @click="proceedToValidation"
            class="min-w-[200px]"
          >
            Continue to Details
            <ArrowRight class="ml-2 h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Add any additional styles if needed */
</style>
