<script setup>
defineProps({
    seatNumber: String,
    taken: {
        type: Boolean,
        default: false,
    },
    selected: Boolean
})
</script>

<template>
    <div
        :class="{
            'seat-taken': taken,
            'seat-selected': selected && taken === false,
            'seat-available': selected === false && taken === false,
        }"
        class="seat-component"
    >
        {{ seatNumber }}
    </div>
</template>

<style scoped>
.seat-component {
    width: 36px;
    height: 36px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    border: 2px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
    user-select: none;
    
    /* Better touch targets for mobile */
    min-width: 44px;
    min-height: 44px;
    
    /* Prevent text selection on touch */
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.seat-available {
    background-color: #ffffff;
    border-color: #dcdee0;
    color: #323233;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.seat-available:hover {
    border-color: #1989fa;
    box-shadow: 0 2px 6px rgba(25, 137, 250, 0.2);
    transform: scale(1.05);
}

.seat-available:active {
    transform: scale(0.95);
}

.seat-selected {
    background-color: #07c160;
    border-color: #07c160;
    color: white;
    box-shadow: 0 2px 6px rgba(7, 193, 96, 0.3);
    transform: scale(1.1);
}

.seat-selected:hover {
    background-color: #06ad56;
    border-color: #06ad56;
}

.seat-selected:active {
    transform: scale(1.05);
}

.seat-taken {
    background: repeating-linear-gradient(
        45deg,
        #f2f3f5,
        #f2f3f5 4px,
        #e8e9eb 4px,
        #e8e9eb 8px
    );
    border-color: #c8c9cc;
    color: #969799;
    cursor: not-allowed;
    opacity: 0.6;
}

/* Mobile-specific touch improvements */
@media (hover: none) and (pointer: coarse) {
    .seat-component {
        min-width: 48px;
        min-height: 48px;
    }
    
    .seat-available:hover {
        transform: none;
        border-color: #dcdee0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .seat-available:active {
        background-color: #f7f8fa;
        transform: scale(0.95);
    }
    
    .seat-selected:hover {
        background-color: #07c160;
        border-color: #07c160;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    .seat-component {
        transition: none;
    }
    
    .seat-available:hover,
    .seat-selected {
        transform: none;
    }
}
</style>
