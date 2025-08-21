<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Calendar, Home, MapPin, Menu, X, ChevronRight } from 'lucide-vue-next'
import dayjs from 'dayjs'
import { Button } from '@/Components/ui/button'
import ToastProvider from '@/Components/ToastProvider.vue'
import { cn } from '@/lib/utils.ts'

const props = defineProps({
  title: {
    type: String,
    default: 'Admin Dashboard'
  },
  breadcrumbs: {
    type: Array,
    default: () => []
  }
})

const page = usePage()

const sidebarOpen = ref(false)

const upcomingEvents = computed(() => {
  const events = page.props.events || []
  const now = dayjs()
  return events
    .filter(event => dayjs(event.starts_at).isAfter(now))
    .sort((a, b) => dayjs(a.starts_at).diff(dayjs(b.starts_at)))
    .slice(0, 10)
})

const navigateTo = (route) => {
  router.visit(route)
  sidebarOpen.value = false
}

</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Mobile sidebar toggle -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
      <Button
        @click="sidebarOpen = !sidebarOpen"
        size="icon"
        variant="outline"
        class="bg-white"
      >
        <Menu v-if="!sidebarOpen" class="h-4 w-4" />
        <X v-else class="h-4 w-4" />
      </Button>
    </div>

    <!-- Sidebar -->
    <aside
      :class="cn(
        'fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 transform transition-transform duration-200 ease-in-out',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:translate-x-0'
      )"
    >
      <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="px-6 py-4 border-b border-gray-200">
          <h1 class="text-xl font-bold text-gray-900">Seating Reservation</h1>
          <p class="text-xs text-gray-500 mt-1">Admin</p>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
          <button
            @click="navigateTo('/admin')"
            class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md hover:bg-gray-100 text-gray-700"
          >
            <Home class="mr-3 h-4 w-4" />
            Dashboard
          </button>

          <button
            @click="navigateTo('/admin/events')"
            class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md hover:bg-gray-100 text-gray-700"
          >
            <Calendar class="mr-3 h-4 w-4" />
            Events
          </button>

          <button
            @click="navigateTo('/admin/rooms')"
            class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md hover:bg-gray-100 text-gray-700"
          >
            <MapPin class="mr-3 h-4 w-4" />
            Rooms
          </button>

          <!-- Upcoming Events Section -->
          <div class="pt-4">
            <h3 class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              Upcoming Events
            </h3>
            <div class="mt-2 space-y-1">
              <div v-if="upcomingEvents.length === 0" class="px-3 py-2 text-sm text-gray-500">
                No upcoming events
              </div>
              <button
                v-for="event in upcomingEvents"
                :key="event.id"
                @click="navigateTo(`/admin/events/${event.id}`)"
                class="w-full flex items-start px-3 py-2 text-sm rounded-md hover:bg-gray-100 text-gray-700"
              >
                <Calendar class="mr-3 h-4 w-4 mt-0.5 flex-shrink-0" />
                <div class="text-left">
                  <div class="font-medium">{{ event.name }}</div>
                  <div class="text-xs text-gray-500">
                    {{ dayjs(event.starts_at).format('MMM D, YYYY') }}
                  </div>
                </div>
              </button>
            </div>
          </div>
        </nav>
      </div>
    </aside>

    <!-- Main content -->
    <main
      :class="cn(
        'transition-all duration-200 ease-in-out',
        'lg:ml-64'
      )"
    >
      <!-- Header Bar with Title and Breadcrumbs -->
      <div class="bg-white border-b border-gray-200 px-4 lg:px-8 h-24 flex items-center justify-between">
        <div class="flex flex-col space-y-2">
          <!-- Breadcrumbs -->
          <nav v-if="breadcrumbs.length > 0" class="flex items-center space-x-2 text-sm text-gray-500">
            <a href="/admin" class="hover:text-gray-700">Admin</a>
            <template v-for="(breadcrumb, index) in breadcrumbs" :key="index">
              <ChevronRight class="h-4 w-4" />
              <a
                v-if="breadcrumb.url && index < breadcrumbs.length - 1"
                :href="breadcrumb.url"
                class="hover:text-gray-700"
              >
                {{ breadcrumb.title }}
              </a>
              <span v-else class="text-gray-900 font-medium">
                {{ breadcrumb.title }}
              </span>
            </template>
          </nav>

          <!-- Page Title -->
          <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
        </div>

        <!-- Slot for additional header content (like action buttons) -->
        <div class="mt-4">
          <slot name="header" />
        </div>
      </div>

      <!-- Main Content -->
      <div class="p-4 lg:p-8">
        <slot />
      </div>
    </main>

    <!-- Mobile sidebar overlay -->
    <div
      v-if="sidebarOpen"
      @click="sidebarOpen = false"
      class="fixed inset-0 bg-black/50 z-30 lg:hidden"
    />
    
    <!-- Toast Notifications -->
    <ToastProvider />
  </div>
</template>
