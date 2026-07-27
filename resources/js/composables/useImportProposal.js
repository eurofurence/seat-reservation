import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import { useToast } from '@/Components/ui/toast'

// All the review-screen logic for the import preview: the editable guest/seat proposal,
// quota tracking, click-to-auto-fill seat picking, and the "leaving discards a staged global
// import" navigation guard. Kept out of the page component so the .vue file stays presentational.
export function useImportProposal(props) {
    const toast = useToast()

    // original_guest_name is the untouched name the server pinned each guest's requested_seats
    // quota to; it's echoed back unchanged on Confirm so the quota alignment guard still holds
    // even though guest_name itself is now editable on the review screen. original_comment is
    // tracked the same way purely for the UI to detect an edit (not sent/validated server-side).
    const cloneProposal = (proposal) => proposal.map(g => ({ ...g, original_guest_name: g.guest_name, original_comment: g.comment, seat_ids: [...g.seat_ids], skipped: false }))

    // Local editable copy of the proposal - only sent to the server on Confirm.
    const guests = ref(cloneProposal(props.proposal))

    // Single pass over the room layout (blocks/rows/seats already arrive pre-sorted in
    // canonical physical order - see RoomLayoutLoader/SeatAssigner::orderedRoomSeatIds), building:
    //   - seatLabelById: seat id -> "Block Row Label", since guests only carry seat ids and the
    //     guest list needs to render a label for whatever is currently selected
    //   - orderedSeatIds: flat seat id list in that same physical order, used to auto-fill a
    //     guest's remaining shortfall forward from wherever they click next
    const roomLayout = computed(() => {
        const seatLabelById = {}
        const orderedSeatIds = []
        for (const block of props.blocks || []) {
            for (const row of block.rows || []) {
                for (const seat of row.seats || []) {
                    seatLabelById[seat.id] = `${block.name} ${row.name} ${seat.label}`
                    orderedSeatIds.push(seat.id)
                }
            }
        }
        return { seatLabelById, orderedSeatIds }
    })
    const seatLabelById = computed(() => roomLayout.value.seatLabelById)
    const orderedSeatIds = computed(() => roomLayout.value.orderedSeatIds)

    const isUnderQuota = (guest) => guest.seat_ids.length < (guest.requested_seats ?? 1)
    const isOverQuota = (guest) => guest.seat_ids.length > (guest.requested_seats ?? 1)

    // Starting from `startSeatId` (included), walks forward through the room's physical seat
    // order collecting up to `count` seats, skipping any id in `unavailable`. Shared by the
    // real auto-fill-on-click (onSeatsChanged) and the hover preview (previewSeatIds) below,
    // so the preview always shows exactly what a click would actually place.
    const fillForward = (startSeatId, count, unavailable) => {
        const filled = [startSeatId]
        const startIndex = orderedSeatIds.value.indexOf(startSeatId)
        if (startIndex !== -1) {
            for (let i = startIndex + 1; i < orderedSeatIds.value.length && filled.length < count; i++) {
                const seatId = orderedSeatIds.value[i]
                if (!unavailable.has(seatId)) {
                    filled.push(seatId)
                }
            }
        }
        return filled
    }

    // Guests below their requested seat count - either never resolved (typo/mismatch/collision)
    // or the admin removed seats on the map below what the CSV asked for. Jump straight to the
    // first one so it's obvious a pick is needed. If everyone is already resolved, prefer the
    // first guest that still needs a look (i.e. not already-booked) over already_booked guests,
    // which are usually collapsed and need no attention - landing on one of those by default
    // is confusing rather than helpful.
    const pickDefaultActiveIndex = (guestsArr) => {
        if (!guestsArr.length) return null
        const firstUnderQuota = guestsArr.findIndex(isUnderQuota)
        if (firstUnderQuota !== -1) return firstUnderQuota
        const firstNotAlreadyBooked = guestsArr.findIndex(g => !g.already_booked)
        return firstNotAlreadyBooked !== -1 ? firstNotAlreadyBooked : 0
    }

    const activeIndex = ref(pickDefaultActiveIndex(guests.value))

    // In a global (cross-event) import, Inertia navigates Preview -> Preview between events and
    // reuses this component instance, so setup() (and the guests ref above) never re-runs for the
    // next event. Reset the local editable copy whenever the event changes, otherwise we'd post
    // the previous event's guests to the new event's confirm endpoint and trip its
    // "no longer matches the pending preview" guard. Also watch props.proposal directly: after a
    // concurrency reassignment ConfirmImportController redirects back to the SAME event with a
    // revised session proposal, and without this the UI would keep the stale conflicting seats.
    watch([() => props.event.id, () => props.proposal], ([, proposal]) => {
        guests.value = cloneProposal(proposal)
        activeIndex.value = pickDefaultActiveIndex(guests.value)
    })

    // The import must never book fewer seats than requested - Confirm stays disabled until
    // every guest has at least their requested_seats count. Skipped guests aren't being
    // booked this run at all, so they don't count as blockers. The server enforces both.
    const underQuotaCount = computed(() => guests.value.filter(g => isUnderQuota(g) && !g.skipped).length)

    // Any guest can be skipped, not just ones blocking Confirm - shown alongside the guest
    // count so it's clear some of them won't actually be booked this run.
    const skippedCount = computed(() => guests.value.filter(g => g.skipped).length)

    const activeSeatIds = computed(() => {
        if (activeIndex.value === null) return []
        return guests.value[activeIndex.value].seat_ids
    })

    // Seats assigned to every guest OTHER than the active one are shown as "reserved" (blue,
    // not clickable) - distinct from real bookedSeats (red) so the two aren't confused. Skipped
    // guests aren't being booked this run, so their seats shouldn't read as reserved either -
    // otherwise skipping a guest leaves their seats visually locked for everyone else.
    const otherGuestsSeats = computed(() => {
        return guests.value
            .filter((g, index) => index !== activeIndex.value && !g.skipped)
            .flatMap(g => g.seat_ids)
    })

    // Every already-booked guest's original seats (existing_bookings) are real DB rows and
    // would otherwise permanently read as "booked" on the map - excluded here, for EVERY
    // already-booked guest (not just the active one), from every unavailable-seat calculation
    // below (map rendering, click auto-fill, hover preview, auto-place). A seat still held by a
    // guest's current seat_ids is instead correctly shown via otherGuestsSeats (reserved, blue)
    // or activeSeatIds (selected, blue) below; a seat a guest gave up falls through to plain
    // "available" (green).
    const alreadyBookedOriginalSeatIds = computed(() => {
        return guests.value.flatMap(g => (g.existing_bookings ?? []).map(b => b.seat_id))
    })

    const mapBookedSeats = computed(() => props.bookedSeats.filter(id => !alreadyBookedOriginalSeatIds.value.includes(id)))

    const onSeatsChanged = (seatIds) => {
        if (activeIndex.value === null) return

        const guest = guests.value[activeIndex.value]

        const oldSeatIds = guest.seat_ids
        const addedSeatId = seatIds.find(id => !oldSeatIds.includes(id))
        const isSingleAdd = seatIds.length === oldSeatIds.length + 1 && addedSeatId !== undefined
        const shortfallBeforeClick = (guest.requested_seats ?? 1) - oldSeatIds.length

        // Clicking one seat while still short more than one seat auto-fills the rest of the
        // shortfall forward from the clicked seat (physical room order), skipping seats that are
        // booked or already claimed by another guest in this batch - so "2 short" picks 2 and
        // "9 short" (everything removed) picks 9, instead of requiring a click per seat.
        if (isSingleAdd && shortfallBeforeClick > 1) {
            const unavailable = new Set([...mapBookedSeats.value, ...otherGuestsSeats.value, ...oldSeatIds])
            const filled = fillForward(addedSeatId, shortfallBeforeClick, unavailable)
            guest.seat_ids = [...oldSeatIds, ...filled]
            return
        }

        guest.seat_ids = seatIds
    }

    // Wipe every seat currently placed for the active guest, so the admin can re-pick them
    // all from scratch (clicking a seat afterwards auto-fills the full shortfall again).
    const clearActiveSeats = () => {
        if (activeIndex.value === null) return
        guests.value[activeIndex.value].seat_ids = []
    }

    // Currently hovered seat id on the map (admin import review only) - drives previewSeatIds
    // below so the admin can see the whole group before committing to a single click.
    const hoveredSeatId = ref(null)

    const onSeatHover = (seat) => {
        hoveredSeatId.value = seat.id
    }

    const onSeatLeave = () => {
        hoveredSeatId.value = null
    }

    // Seats that would be placed if the currently hovered seat were clicked right now - mirrors
    // onSeatsChanged's auto-fill exactly (same fillForward helper). Only meaningful when the
    // active guest is short more than one seat; a single-seat pick already highlights on :hover
    // via getSeatStatus, so there's nothing extra to preview.
    const previewSeatIds = computed(() => {
        if (activeIndex.value === null || hoveredSeatId.value === null) return []

        const guest = guests.value[activeIndex.value]
        const shortfall = (guest.requested_seats ?? 1) - guest.seat_ids.length
        if (shortfall <= 1) return []

        const unavailable = new Set([...mapBookedSeats.value, ...otherGuestsSeats.value, ...guest.seat_ids])
        if (unavailable.has(hoveredSeatId.value)) return []

        return fillForward(hoveredSeatId.value, shortfall, unavailable)
    })

    // Re-run the backend's own import auto-assignment for the active guest (the exact same
    // stage-aware, preference-tiered logic used when the CSV was first parsed - see
    // ImportProposalBuilder::assignGroup), rather than a separate client-side heuristic. Sends
    // the guest's original preference plus the live set of unavailable seats (booked + held by
    // other guests in this batch); the server returns a fresh contiguous run.
    const isAutoPlacing = ref(false)

    const autoPlaceActiveSeats = async () => {
        if (activeIndex.value === null || isAutoPlacing.value) return

        const guest = guests.value[activeIndex.value]

        isAutoPlacing.value = true

        try {
            const { data } = await axios.post(
                route('admin.events.import-bookings.autoplace', props.event.id),
                {
                    quantity: guest.requested_seats ?? 1,
                    strategy: guest.assignment_strategy ?? 'none',
                    // A row-only CSV preference (no Block) has a null preferred_block_name for
                    // display (see ImportProposalBuilder), but still defaults to the "center"
                    // block internally - fall back to that here too, since assignment_strategy
                    // 'row_preferred' requires a block name server-side.
                    preferred_block: guest.preferred_block_name ?? 'center',
                    preferred_row: guest.preferred_row_name ?? null,
                    unavailable: [...mapBookedSeats.value, ...otherGuestsSeats.value],
                },
            )
            guest.seat_ids = data.seat_ids
            guest.fallback_level_used = data.fallback_level
        } catch (e) {
            toast.error('Could not auto-place seats', e.response?.data?.error ?? 'Please try again.')
        } finally {
            isAutoPlacing.value = false
        }
    }

    const form = useForm({ guests: [] })

    // In a global (cross-event) import nothing is written to the database until the final event
    // is confirmed - everything is staged server-side. So leaving the workflow midway discards
    // every staged assignment. `isConfirming` marks the one navigation the guard must allow.
    let isConfirming = false
    let removeBeforeListener = null

    const confirmImport = () => {
        if (underQuotaCount.value > 0) return

        form.guests = guests.value.map(g => ({
            guest_name: g.guest_name,
            original_guest_name: g.original_guest_name,
            comment: g.comment,
            seat_ids: g.seat_ids,
            skipped: g.skipped,
        }))

        // Confirming (and the server's follow-up redirect to the next event / completion) is the
        // one navigation the leave-guard below must let through untouched.
        isConfirming = true
        form.post(route('admin.events.import-bookings.confirm', props.event.id), {
            onError: () => { isConfirming = false },
        })
    }

    const beforeUnloadHandler = (e) => {
        e.preventDefault()
        e.returnValue = ''
    }

    onMounted(() => {
        if (!props.progress) return

        window.addEventListener('beforeunload', beforeUnloadHandler)

        // Inertia reuses this component instance across the queue's events, so this listener is
        // registered once and guards every in-workflow navigation (sidebar links, Cancel, back).
        removeBeforeListener = router.on('before', () => {
            if (isConfirming) {
                isConfirming = false
                return
            }
            if (!window.confirm('Leaving now discards the entire import — nothing has been booked yet. Leave and discard all staged bookings?')) {
                return false
            }
        })
    })

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnloadHandler)
        if (removeBeforeListener) removeBeforeListener()
    })

    return {
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
    }
}
