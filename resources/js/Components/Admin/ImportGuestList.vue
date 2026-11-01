<script setup lang="ts">
import { ref } from 'vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { GUEST_NAME_MAX, ADMIN_COMMENT_MAX } from '@/lib/validation'

// One entry of the review screen's editable import proposal (see useImportProposal.js's
// cloneProposal - guest_name/comment/seat_ids/skipped are always present, everything else
// mirrors whatever ImportProposalBuilder put on that guest, so it's optional here).
export interface ImportGuest {
    guest_name: string
    original_guest_name: string
    comment: string | null
    original_comment: string | null
    seat_ids: number[]
    requested_seats?: number
    skipped: boolean
    already_booked?: boolean
    seat_count_mismatch?: boolean
    csv_requested_seats?: number
    existing_seat_count?: number
    user_display_name?: string | null
    preferred_block_name?: string | null
    preferred_row_name?: string | null
    submission_timestamp?: string | null
    assignment_strategy?: string
    fallback_level_used?: string
    unresolved?: boolean
    unresolved_reason?: string
}

const props = defineProps<{
    guests: ImportGuest[]
    seatLabelById: Record<number, string>
    isUnderQuota: (guest: ImportGuest) => boolean
    isOverQuota: (guest: ImportGuest) => boolean
    clearActiveSeats: () => void
}>()

const activeIndex = defineModel<number | null>('activeIndex')

// Show a "fallback" hint only when the assigned area is coarser than the strategy asked
// for - e.g. a row-preferred guest whose row was full but got seats elsewhere in the same
// block, or a block-preferred guest who spilled out to the room. No hint when everything
// landed where requested (row-preferred -> row, block-preferred -> block), and none for
// the "no preference" strategy (nothing was asked for, nothing to fall back from).
const fallbackHint = (guest: ImportGuest) => {
    const strategy = guest.assignment_strategy
    const level = guest.fallback_level_used
    if (!strategy || !level || strategy === 'none') return null
    if (strategy === 'row_preferred' && level === 'block') return 'Requested row was full - seated elsewhere in the block'
    if (strategy === 'row_preferred' && level === 'room') return 'Requested row was full - seated elsewhere in the room'
    if (strategy === 'block_preferred' && level === 'room') return 'Requested block was full - seated elsewhere in the room'
    return null
}

// Editing the name here changes what actually gets booked - a later re-imported CSV that still
// uses the original name won't recognize this guest as already booked and may book them again.
// Not shown for already-booked or skipped guests (skipped guests aren't booked/renamed at all -
// see renamePending below for the already-booked case).
const nameChanged = (guest: ImportGuest) => !guest.already_booked && !guest.skipped
    && guest.guest_name.trim().toLowerCase() !== guest.original_guest_name.trim().toLowerCase()

// An emptied name field is almost never intentional (there's no such thing as booking a blank
// name) - fall back to the CSV's original value on blur instead of leaving it empty.
const restoreIfEmpty = (guest: ImportGuest) => {
    if (guest.guest_name.trim() === '') {
        guest.guest_name = guest.original_guest_name
    }
}

// For an already-booked guest, editing name/comment here doesn't create a new booking - it
// bulk-renames their existing one(s) on confirm instead, so it's worth surfacing as an
// intentional action rather than silently discarding the edit. Not shown while skipped - a
// skipped guest is left completely untouched on confirm, edits included.
const renamePending = (guest: ImportGuest) => guest.already_booked && !guest.skipped && (
    guest.guest_name.trim() !== guest.original_guest_name.trim()
    || (guest.comment || '') !== (guest.original_comment || '')
)

// Already-booked guests usually need no attention at all (that's the common case on a
// re-imported CSV). Collapse those down to a one-line summary so the list isn't dominated by
// entries nobody needs to look at - unless there's actually something to review (a seat-count
// mismatch or a pending rename). Collapse state is tracked separately per guest and toggled by
// clicking the row, independent of activeIndex (which defaults to guest 0 for seat-map purposes)
// so default selection alone doesn't force the first already-booked guest open.
const expandedIndices = ref<Set<number>>(new Set())

const isCollapsible = (guest: ImportGuest) => !!guest.already_booked && !guest.seat_count_mismatch && !renamePending(guest)

const isCollapsedAlreadyBooked = (guest: ImportGuest, index: number) => isCollapsible(guest) && !expandedIndices.value.has(index)

const selectGuest = (guest: ImportGuest, index: number) => {
    activeIndex.value = index
    if (!isCollapsible(guest)) return
    if (expandedIndices.value.has(index)) {
        expandedIndices.value.delete(index)
    } else {
        expandedIndices.value.add(index)
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Guests ({{ guests.length }})</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 max-h-[70vh] overflow-y-auto">
            <div
                v-for="(guest, index) in guests"
                :key="index"
                role="button"
                tabindex="0"
                class="w-full text-left p-3 rounded-md border transition-colors cursor-pointer"
                :class="[
                    activeIndex === index ? 'border-primary bg-primary/5' : 'border-border hover:bg-gray-50',
                    guest.skipped ? 'opacity-60' : '',
                ]"
                @click="selectGuest(guest, index)"
                @keydown.enter.self="selectGuest(guest, index)"
                @keydown.space.self.prevent="selectGuest(guest, index)"
            >
                <div v-if="isCollapsedAlreadyBooked(guest, index)" class="flex items-center gap-2">
                    <span class="text-sm font-medium truncate">{{ guest.guest_name }}</span>
                    <Badge variant="secondary" class="border-emerald-500 text-emerald-700 bg-emerald-50 shrink-0">Already booked</Badge>
                </div>
                <template v-else>
                <div class="flex items-center justify-between gap-2">
                    <Input
                        v-model="guest.guest_name"
                        placeholder="Guest name"
                        :maxlength="GUEST_NAME_MAX"
                        class="h-7 font-medium"
                        @click.stop
                        @blur="restoreIfEmpty(guest)"
                    />
                    <div class="flex items-center gap-1 shrink-0">
                        <Badge v-if="guest.already_booked" variant="secondary" class="border-emerald-500 text-emerald-700 bg-emerald-50">Already booked</Badge>
                        <Badge v-if="guest.skipped" variant="secondary">Skipped</Badge>
                        <Badge v-else-if="isUnderQuota(guest)" variant="destructive">{{ guest.seat_ids.length }}/{{ guest.requested_seats ?? 1 }} seats</Badge>
                        <Badge v-else-if="isOverQuota(guest)" variant="outline" class="border-amber-500 text-amber-600">{{ guest.seat_ids.length }}/{{ guest.requested_seats ?? 1 }} seats</Badge>
                        <Badge v-else-if="!guest.already_booked" variant="secondary">{{ guest.seat_ids.length }} seat{{ guest.seat_ids.length === 1 ? '' : 's' }}</Badge>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 px-2"
                            @click.stop="guest.skipped = !guest.skipped"
                        >
                            {{ guest.skipped ? 'Un-skip' : 'Skip' }}
                        </Button>
                    </div>
                </div>
                <Input
                    :model-value="guest.comment ?? ''"
                    placeholder="Comment (optional)"
                    :maxlength="ADMIN_COMMENT_MAX"
                    class="mt-1.5 h-7 text-xs"
                    @click.stop
                    @update:model-value="(value) => guest.comment = value === '' ? null : String(value)"
                />
                <div v-if="guest.user_display_name" class="text-xs text-muted-foreground mt-1">
                    Entered by: {{ guest.user_display_name }}
                </div>
                <div
                    v-if="guest.preferred_block_name || guest.preferred_row_name"
                    class="text-xs text-muted-foreground mt-1"
                >
                    Prefers:
                    <span v-if="guest.preferred_block_name && guest.preferred_row_name">Block {{ guest.preferred_block_name }}, Row {{ guest.preferred_row_name }}</span>
                    <span v-else-if="guest.preferred_row_name">Row {{ guest.preferred_row_name }}</span>
                    <span v-else>Block {{ guest.preferred_block_name }}</span>
                </div>
                <div
                    v-if="fallbackHint(guest)"
                    class="text-xs text-amber-600 mt-1"
                >
                    {{ fallbackHint(guest) }}
                </div>
                <div v-if="guest.submission_timestamp" class="text-xs text-muted-foreground mt-1">
                    Submitted: {{ guest.submission_timestamp }}
                </div>
                <div v-if="nameChanged(guest)" class="text-xs text-amber-600 mt-1">
                    Renaming means a re-imported CSV using the old name won't recognize this
                    guest as already booked, and may book them again.
                </div>
                <div v-if="guest.already_booked" class="text-xs text-emerald-600 mt-1">
                    Already booked previously.
                </div>
                <div v-if="guest.already_booked && guest.seat_count_mismatch" class="text-xs text-amber-600 mt-1">
                    This CSV now requests {{ guest.csv_requested_seats }} seat{{ guest.csv_requested_seats === 1 ? '' : 's' }} for them
                    (they currently have {{ guest.existing_seat_count }} booked) — add or remove seats on the map to match, if needed.
                </div>
                <div v-if="renamePending(guest)" class="text-xs text-blue-600 mt-1">
                    Their existing booking will be renamed to "{{ guest.guest_name.trim() }}" on confirm.
                </div>
                <div v-if="guest.skipped" class="text-xs text-muted-foreground mt-1">
                    Won't be booked this run — will be picked up on the next import upload.
                </div>
                <div v-else-if="isUnderQuota(guest) && guest.unresolved_reason" class="text-xs text-red-600 mt-1">{{ guest.unresolved_reason }}</div>
                <div v-else-if="isUnderQuota(guest)" class="text-xs text-red-600 mt-1">
                    Needs {{ (guest.requested_seats ?? 1) - guest.seat_ids.length }} more seat{{ (guest.requested_seats ?? 1) - guest.seat_ids.length === 1 ? '' : 's' }} picked.
                </div>
                <div v-else-if="isOverQuota(guest)" class="text-xs text-amber-600 mt-1">
                    {{ guest.seat_ids.length - (guest.requested_seats ?? 1) }} more than requested.
                </div>
                <div v-else class="text-xs text-muted-foreground mt-1">
                    {{ guest.seat_ids.map(id => seatLabelById[id] || '?').join(' | ') }}
                </div>
                <Button
                    v-if="activeIndex === index && guest.seat_ids.length > 0"
                    variant="outline"
                    size="sm"
                    class="mt-2"
                    @click.stop="clearActiveSeats"
                >
                    Clear seats
                </Button>
                </template>
            </div>
        </CardContent>
    </Card>
</template>
