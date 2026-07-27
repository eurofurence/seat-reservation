<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import SeatLayout from '@/Components/SeatLayout.vue'
import ImportGuestList from '@/Components/Admin/ImportGuestList.vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { useImportProposal } from '@/composables/useImportProposal'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    event: Object,
    room: Object,
    blocks: Array,
    stageBlocks: Array,
    bookedSeats: Array,
    proposal: Array,
    progress: Object,
    title: String,
    breadcrumbs: Array,
})

const {
    guests,
    activeIndex,
    seatLabelById,
    isUnderQuota,
    isOverQuota,
    underQuotaCount,
    skippedCount,
    activeSeatIds,
    otherGuestsSeats,
    mapBookedSeats,
    onSeatsChanged,
    clearActiveSeats,
    autoPlaceActiveSeats,
    isAutoPlacing,
    confirmImport,
    form,
    previewSeatIds,
    onSeatHover,
    onSeatLeave,
} = useImportProposal(props)
</script>

<template>
    <Head :title="title"/>

    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Import Preview</h1>
                <p class="text-sm text-muted-foreground">
                    {{ progress ? 'Nothing is booked until the entire import is confirmed.' : 'Nothing is booked until you confirm.' }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <div class="flex gap-2">
                    <Link :href="route('admin.events.show', event.id)">
                        <Button variant="outline">Cancel</Button>
                    </Link>
                    <Button @click="confirmImport" :disabled="form.processing || guests.length === 0 || underQuotaCount > 0">
                        {{ form.processing ? 'Importing...' : `Confirm Import (${guests.length - skippedCount} guest${guests.length - skippedCount === 1 ? '' : 's'}${skippedCount > 0 ? `, ${skippedCount} skipped` : ''})` }}
                    </Button>
                </div>
                <p v-if="underQuotaCount > 0" class="text-xs text-red-600">
                    {{ underQuotaCount }} guest{{ underQuotaCount === 1 ? '' : 's' }} still need{{ underQuotaCount === 1 ? 's' : '' }} more seats picked.
                </p>
            </div>
        </div>

        <div v-if="progress" class="mb-6">
            <div class="mb-1 flex items-center justify-between text-sm">
                <span class="font-medium">Global import progress</span>
                <span class="text-muted-foreground">Event {{ progress.done }} of {{ progress.total }}</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    class="h-full rounded-full bg-primary transition-all"
                    :style="{ width: `${Math.round(((progress.done - 1) / progress.total) * 100)}%` }"
                ></div>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                Nothing is booked until the last event is confirmed — leaving now discards the whole import.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-2">
                <ImportGuestList
                    v-model:active-index="activeIndex"
                    :guests="guests"
                    :seat-label-by-id="seatLabelById"
                    :is-under-quota="isUnderQuota"
                    :is-over-quota="isOverQuota"
                    :clear-active-seats="clearActiveSeats"
                />
                <p class="text-xs text-muted-foreground">
                    Select a guest, then click the map to change their seats. For {{ event.name }}.
                </p>
            </div>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle>
                            Seat Map
                            <span v-if="activeIndex !== null" class="text-sm font-normal text-muted-foreground">
                                — editing {{ guests[activeIndex].guest_name }}
                            </span>
                        </CardTitle>
                        <Button
                            v-if="activeIndex !== null"
                            variant="outline"
                            size="sm"
                            :disabled="isAutoPlacing"
                            @click="autoPlaceActiveSeats"
                        >
                            {{ isAutoPlacing ? 'Placing...' : 'Auto-place seats' }}
                        </Button>
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs text-muted-foreground mt-1">
                        <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-emerald-500"></span> Available</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-blue-500"></span> Reserved (this import)</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-blue-300"></span> Hover preview</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-red-500"></span> Booked</span>
                    </div>
                </CardHeader>
                <CardContent>
                    <SeatLayout
                        v-if="activeIndex !== null"
                        :event="event"
                        :room="room"
                        :blocks="blocks"
                        :stage-blocks="stageBlocks"
                        :selected-seats="activeSeatIds"
                        :booked-seats="mapBookedSeats"
                        :reserved-seats="otherGuestsSeats"
                        :preview-seats="previewSeatIds"
                        :admin-mode="true"
                        @seats-changed="onSeatsChanged"
                        @seat-hover="onSeatHover"
                        @seat-leave="onSeatLeave"
                    />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
