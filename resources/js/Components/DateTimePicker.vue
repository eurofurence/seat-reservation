<script setup lang="ts">
import { ref, watch } from 'vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Calendar } from '@/Components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { CalendarIcon, X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { parseDate } from '@internationalized/date'
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

const parseDateTime = (dateTimeString: string): any => {
  if (!dateTimeString) return undefined
  try {
    return parseDate(fmt(dateTimeString, 'YYYY-MM-DD'))
  } catch {
    return undefined
  }
}

const parseTime = (dateTimeString: string) => {
  if (!dateTimeString) return ''
  try {
    return fmt(dateTimeString, 'HH:mm')
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
        <Input
          v-model="time"
          type="time"
          placeholder="HH:MM"
          :class="{ 'border-red-500': error }"
        />
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
