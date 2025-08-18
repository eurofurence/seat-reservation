<script setup>
import { Link, Head } from '@inertiajs/vue3'
import Layout from "@/Layouts/Layout.vue"
import Button from "@/Components/ui/Button.vue"
import Card from "@/Components/ui/Card.vue"
import Alert from "@/Components/ui/Alert.vue"
import { User, Info, LogIn } from 'lucide-vue-next'

defineOptions({layout: Layout})
</script>

<template>
  <Head title="Login" />
  
  <div class="min-h-screen bg-linear-to-br from-gray-50 to-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md space-y-8">
      <!-- Brand Section -->
      <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Seat Reservation</h1>
        <p class="text-gray-600">Reserve your seats for Eurofurence Events</p>
      </div>
      
      <!-- Login Card -->
      <Card class="p-6 space-y-6">
        <!-- Welcome Message -->
        <div class="flex items-start space-x-3 p-4 bg-blue-50 rounded-lg border border-blue-200">
          <User class="h-5 w-5 text-blue-600 mt-0.5" />
          <div>
            <h3 class="font-medium text-blue-900">Welcome</h3>
            <p class="text-sm text-blue-700">Tap below to sign in securely</p>
          </div>
        </div>
        
        <!-- Login Button -->
        <Link 
          as="button" 
          method="post" 
          :href="route('auth.login.redirect')"
          class="w-full"
        >
          <Button size="lg" class="w-full">
            <LogIn class="mr-2 h-4 w-4" />
            Sign In
          </Button>
        </Link>
        
        <!-- Info Notice -->
        <Alert class="border-blue-200 bg-blue-50">
          <Info class="h-4 w-4" />
          <div class="text-blue-800">
            You'll be redirected to a secure login page
          </div>
        </Alert>
      </Card>
    </div>
  </div>
</template>

<style scoped>
/* Additional styles can be added here if needed */
</style>