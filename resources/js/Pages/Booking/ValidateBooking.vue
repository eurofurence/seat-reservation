<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import FullWidthLayout from "@/Layouts/FullWidthLayout.vue"
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Alert } from '@/Components/ui/alert'
import { ArrowLeft, Calendar, MapPin, Clock, AlertCircle, Check } from 'lucide-vue-next'
import dayjs from "dayjs"

defineOptions({layout: FullWidthLayout})

const props = defineProps({
    event: Object,
    seats: Array,
    seatIds: Array
})

// Create form with seat data
const form = useForm({
    seats: props.seats.map(seat => ({
        seat_id: seat.id,
        name: '',
        comment: ''
    }))
})

const submitting = ref(false)

function submitBooking() {
    if (submitting.value) return
    
    // Validate that all names are filled
    const allNamesProvided = form.seats.every(seat => seat.name && seat.name.trim())
    
    if (!allNamesProvided) {
        alert('Please provide names for all seats')
        return
    }
    
    submitting.value = true
    
    form.post(route('bookings.store', { event: props.event.id }), {
        onSuccess: () => {
            submitting.value = false
        },
        onError: () => {
            submitting.value = false
        }
    })
}

function goBack() {
    // Navigate back to seat selection with the seat IDs preserved
    const params = {
        seats: props.seatIds
    }
    
    router.get(route('bookings.create', { event: props.event.id }), params)
}
</script>

<template>
  <Head title="Confirm Booking Details" />
  
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center">
            <button
              @click="goBack"
              class="mr-4 p-2 hover:bg-gray-100 rounded-md transition-colors"
            >
              <ArrowLeft class="h-5 w-5" />
            </button>
            <h1 class="text-xl font-semibold">Booking Details</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- Event Summary -->
      <Card class="mb-6">
        <div class="p-4 space-y-3">
          <div class="flex items-center text-sm">
            <Calendar class="h-4 w-4 mr-2 text-gray-500" />
            <span class="font-medium mr-2">Event:</span>
            <span>{{ event.name }}</span>
          </div>
          <div class="flex items-center text-sm">
            <MapPin class="h-4 w-4 mr-2 text-gray-500" />
            <span class="font-medium mr-2">Room:</span>
            <span>{{ event.room?.name || 'No room assigned' }}</span>
          </div>
          <div class="flex items-center text-sm">
            <Clock class="h-4 w-4 mr-2 text-gray-500" />
            <span class="font-medium mr-2">Date & Time:</span>
            <span>{{ dayjs(event.starts_at).format('MMM DD, YYYY - HH:mm') }}</span>
          </div>
          <div class="flex items-center text-sm">
            <AlertCircle class="h-4 w-4 mr-2 text-gray-500" />
            <span class="font-medium mr-2">Seats Selected:</span>
            <span>{{ seats.length }} seat(s)</span>
          </div>
        </div>
      </Card>

      <!-- Seat Details Form -->
      <div class="space-y-4">
        <h2 class="text-lg font-semibold">Enter Details for Each Seat</h2>
        
        <Card v-for="(seat, index) in seats" :key="seat.id" class="p-4">
          <div class="space-y-4">
            <div class="font-medium text-sm text-gray-600">
              Seat: {{ seat.row.block.name }} - {{ seat.row.name }} - {{ seat.label }}
            </div>
            
            <div class="space-y-2">
              <Label :for="`name-${index}`">
                Name <span class="text-red-500">*</span>
              </Label>
              <Input
                :id="`name-${index}`"
                v-model="form.seats[index].name"
                placeholder="Enter attendee name"
                :class="{ 'border-red-500': form.errors[`seats.${index}.name`] }"
              />
              <p v-if="form.errors[`seats.${index}.name`]" class="text-sm text-red-500">
                {{ form.errors[`seats.${index}.name`] }}
              </p>
            </div>
            
            <div class="space-y-2">
              <Label :for="`comment-${index}`">
                Comment (Optional)
              </Label>
              <Textarea
                :id="`comment-${index}`"
                v-model="form.seats[index].comment"
                placeholder="Optional comment"
                :rows="2"
              />
            </div>
          </div>
        </Card>
      </div>

      <!-- Error Display -->
      <Alert v-if="form.errors && Object.keys(form.errors).length > 0" variant="destructive" class="mt-6">
        <AlertCircle class="h-4 w-4" />
        <div>
          {{ Object.values(form.errors)[0] }}
        </div>
      </Alert>

      <!-- Action Buttons -->
      <div class="mt-8 flex gap-4">
        <Button
          variant="outline"
          size="lg"
          @click="goBack"
          :disabled="submitting"
          class="flex-1"
        >
          <ArrowLeft class="mr-2 h-4 w-4" />
          Back to Seat Selection
        </Button>
        
        <Button
          size="lg"
          @click="submitBooking"
          :loading="submitting"
          :disabled="submitting"
          class="flex-1"
        >
          <Check class="mr-2 h-4 w-4" />
          Confirm Booking
        </Button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Add any additional styles if needed */
</style>