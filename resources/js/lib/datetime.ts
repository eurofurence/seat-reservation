import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'

dayjs.extend(utc)
dayjs.extend(timezone)

export const APP_TIMEZONE = 'Europe/Berlin' // ef timezone

export function fmt(value: string | null | undefined, template: string): string {
  if (!value) return ''
  return dayjs(value).tz(APP_TIMEZONE).format(template)
}

export default dayjs
