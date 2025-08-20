<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Table } from '@/Components/ui/table'
import { Dialog } from '@/Components/ui/dialog'
import { DialogContent } from '@/Components/ui/dialog'
import { DialogHeader } from '@/Components/ui/dialog'
import { DialogTitle } from '@/Components/ui/dialog'
import { DialogTrigger } from '@/Components/ui/dialog'
import { Plus, Edit, Trash2, MapPin } from 'lucide-vue-next'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  rooms: Array,
  title: String,
  breadcrumbs: Array,
})

const showCreateModal = ref(false)
const showDeleteDialog = ref(false)
const roomToDelete = ref(null)

// Create room form
const createForm = useForm({
  name: ''
})

const createRoom = () => {
  createForm.post(route('admin.rooms.store'), {
    onSuccess: () => {
      showCreateModal.value = false
      createForm.reset()
    }
  })
}

// Delete room form
const deleteForm = useForm({})

const openDeleteDialog = (room) => {
  roomToDelete.value = room
  showDeleteDialog.value = true
}

const confirmDelete = () => {
  if (roomToDelete.value) {
    deleteForm.delete(route('admin.rooms.destroy', roomToDelete.value.id), {
      onSuccess: () => {
        showDeleteDialog.value = false
        roomToDelete.value = null
      }
    })
  }
}

const cancelDelete = () => {
  showDeleteDialog.value = false
  roomToDelete.value = null
}

const openFloorPlanner = (room) => {
  window.open(`/admin/rooms/${room.id}/layout`, '_blank')
}
</script>

<template>
  <Head :title="title" />
  
  <div>
    <div class="flex justify-end items-center mb-6">
      <Button @click="showCreateModal = true">
        <Plus class="mr-2 h-4 w-4" />
        Create Room
      </Button>
    </div>
    
    <!-- Rooms Table -->
    <Card>
      <CardContent class="p-0">
        <Table>
          <thead>
            <tr class="border-b">
              <th class="text-left p-4">Name</th>
              <th class="text-left p-4">Blocks</th>
              <th class="text-left p-4">Total Seats</th>
              <th class="text-left p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="room in rooms" :key="room.id" class="border-b">
              <td class="p-4 font-medium">{{ room.name }}</td>
              <td class="p-4">{{ room.blocks_count || 0 }}</td>
              <td class="p-4">{{ room.total_seats || 0 }}</td>
              <td class="p-4">
                <div class="flex gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    @click="openFloorPlanner(room)"
                  >
                    <MapPin class="h-4 w-4 mr-1" />
                    Floor Plan
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    @click="router.visit(`/admin/rooms/${room.id}/edit`)"
                  >
                    <Edit class="h-4 w-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="destructive"
                    @click="openDeleteDialog(room)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </td>
            </tr>
            <tr v-if="rooms.length === 0">
              <td colspan="4" class="text-center p-8 text-muted-foreground">
                No rooms created yet
              </td>
            </tr>
          </tbody>
        </Table>
      </CardContent>
    </Card>
    
    <!-- Create Room Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <Card class="w-full max-w-md">
        <CardHeader>
          <CardTitle>Create New Room</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Room Name</label>
              <Input
                v-model="createForm.name"
                placeholder="Enter room name"
                @keyup.enter="createRoom"
              />
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" @click="showCreateModal = false">
                Cancel
              </Button>
              <Button @click="createRoom" :disabled="createForm.processing">
                Create Room
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
    
    <!-- Delete Room Dialog -->
    <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Delete Room</DialogTitle>
        </DialogHeader>
        <div class="space-y-4">
          <p class="text-sm text-gray-600">
            Are you sure you want to delete the room <strong>"{{ roomToDelete?.name }}"</strong>? 
            This action cannot be undone and will also delete all blocks, rows, and seats in this room.
          </p>
          <div class="flex justify-end gap-2">
            <Button variant="outline" @click="cancelDelete">
              Cancel
            </Button>
            <Button variant="destructive" @click="confirmDelete" :disabled="deleteForm.processing">
              Delete Room
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>