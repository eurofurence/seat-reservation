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
  
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4 lg:p-8">
    <!-- Desktop: Two-column layout -->
    <div class="w-full max-w-6xl lg:grid lg:grid-cols-2 lg:gap-12 lg:items-center">
      <!-- Left side: Brand/Welcome (hidden on mobile) -->
      <div class="hidden lg:block space-y-8">
        <div class="space-y-6">
          <div class="space-y-4">
            <h1 class="text-5xl font-bold text-gray-900 leading-tight">
              Welcome to<br />
              <span class="text-blue-600">Seat Reservation</span>
            </h1>
            <p class="text-xl text-gray-600 leading-relaxed">
              Reserve your seats for Eurofurence Events with our easy-to-use platform
            </p>
          </div>
          
          <div class="space-y-4">
            <div class="flex items-center space-x-3 text-gray-700">
              <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <User class="h-4 w-4 text-blue-600" />
              </div>
              <span>Secure authentication</span>
            </div>
            <div class="flex items-center space-x-3 text-gray-700">
              <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <Info class="h-4 w-4 text-blue-600" />
              </div>
              <span>Easy seat selection</span>
            </div>
            <div class="flex items-center space-x-3 text-gray-700">
              <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <LogIn class="h-4 w-4 text-blue-600" />
              </div>
              <span>Quick booking process</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right side: Login form -->
      <div class="w-full max-w-md mx-auto lg:max-w-none space-y-8">
        <!-- Mobile Brand Section -->
        <div class="text-center lg:hidden">
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Seat Reservation</h1>
          <p class="text-gray-600">Reserve your seats for Eurofurence Events</p>
        </div>
        
        <!-- Login Card -->
        <Card class="p-6 lg:p-8 space-y-6">
          <!-- Welcome Message -->
          <div class="flex items-start space-x-3 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <User class="h-5 w-5 text-blue-600 mt-0.5" />
            <div>
              <h3 class="font-medium text-blue-900">Welcome</h3>
              <p class="text-sm text-blue-700">Click below to sign in securely</p>
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
  </div>
</template>

<style scoped>
/* Additional styles can be added here if needed */
</style>