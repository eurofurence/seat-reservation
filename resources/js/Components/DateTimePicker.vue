<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import { Calendar } from '@/Components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { TimeFieldInput, TimeFieldRoot } from 'reka-ui'
import { CalendarIcon, X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { parseDate, Time } from '@internationalized/date'
import dayjs, { APP_TIMEZONE, fmt } from '@/lib/datetime'

interface Props {
  modelValue: string
  label: string
  error?: string
  hint?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const parseDateTime = (value: string): any => {
  try {
    return value ? parseDate(fmt(value, 'YYYY-MM-DD')) : undefined
  } catch {
    return undefined
  }
}

const parseTime = (value: string) => {
  try {
    return value ? fmt(value, 'HH:mm') : ''
  } catch {
    return ''
  }
}

const combineDateTime = (date: any, time: string) => {
  if (!date || !time) return ''
  try {
    const dateStr = date.toString() // YYYY-MM-DD format
    // Entered value is app-timezone wall-clock; emit UTC so it stores unshifted.
    return dayjs.tz(`${dateStr}T${time}:00`, APP_TIMEZONE).utc().format()
  } catch {
    return ''
  }
}

const date = ref<any>(parseDateTime(props.modelValue))
const time = ref(parseTime(props.modelValue))

const timeValue = computed({
  get: () => {
    if (!time.value) return undefined
    const [h, m] = time.value.split(':').map(Number)
    return new Time(h, m)
  },
  set: (value) => {
    time.value = value ? `${value}`.slice(0, 5) : ''
  },
})

watch([date, time], ([newDate, newTime]) => {
  emit('update:modelValue', combineDateTime(newDate, newTime))
})

const clear = () => {
  date.value = undefined
  time.value = ''
}
</script>

<template>
  <div>
    <label class="block text-sm font-medium mb-2">{{ label }}</label>
    <div class="flex gap-2">
      <div class="flex-1">
        <Popover>
          <PopoverTrigger as-child>
            <Button
              variant="outline"
              :class="cn(
                'w-full justify-start text-left font-normal',
                !date && 'text-muted-foreground',
                error && 'border-red-500'
              )"
            >
              <CalendarIcon class="mr-2 h-4 w-4" />
              {{ date ? dayjs(date.toString()).format('MMM D, YYYY') : 'Pick a date' }}
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-auto p-0">
            <Calendar v-model="date" initial-focus />
          </PopoverContent>
        </Popover>
      </div>
      <div class="w-32">
        <TimeFieldRoot
          v-slot="{ segments }"
          v-model="timeValue"
          :hour-cycle="24"
          granularity="minute"
          :class="cn(
            'border-input dark:bg-input/30 flex h-9 w-full min-w-0 items-center rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm',
            'focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]',
            error && 'border-destructive ring-destructive/20 dark:ring-destructive/40'
          )"
        >
          <TimeFieldInput
            v-for="item in segments"
            :key="item.part"
            :part="item.part"
            :class="item.part === 'literal'
              ? 'text-muted-foreground'
              : 'focus:bg-[Highlight] focus:text-[HighlightText] rounded-sm px-0.5 tabular-nums outline-none'"
          >
            {{ item.value }}
          </TimeFieldInput>
        </TimeFieldRoot>
      </div>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        aria-label="Clear"
        @click="clear"
      >
        <X class="h-4 w-4" />
      </Button>
    </div>
    <span v-if="error" class="text-sm text-red-500">{{ error }}</span>
    <p v-if="hint" class="text-sm text-muted-foreground mt-1">{{ hint }}</p>
  </div>
</template>
