<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { ArrowLeft } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  room: Object,
  title: String,
  breadcrumbs: Array,
})

const form = useForm({
  name: props.room.name,
})

const submit = () => {
  form.put(route('admin.rooms.update', props.room.id), {
    onSuccess: () => {
      router.visit('/admin/rooms')
    },
  })
}
</script>

<template>
  <Head :title="title" />
  
  <div class="max-w-2xl">
    <div class="flex items-center mb-6">
      <Button variant="ghost" size="sm" @click="router.visit('/admin/rooms')" class="mr-4">
        <ArrowLeft class="h-4 w-4" />
      </Button>
    </div>
    
    <Card>
      <CardHeader>
        <CardTitle>Room Details</CardTitle>
      </CardHeader>
      <CardContent>
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-2">Room Name</label>
            <Input
              v-model="form.name"
              placeholder="Enter room name"
              :disabled="form.processing"
            />
            <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
              {{ form.errors.name }}
            </div>
          </div>
          
          <div class="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              @click="router.visit('/admin/rooms')"
              :disabled="form.processing"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              :disabled="form.processing"
            >
              Save Changes
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>