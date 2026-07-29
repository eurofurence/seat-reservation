export function coerceInputValue(
  value: string,
  options: { trim?: boolean, number?: boolean },
): string | number {
  let result: string | number = value
  if (options.trim) result = result.trim()
  if (options.number) {
    const parsed = parseFloat(result)
    result = isNaN(parsed) ? result : parsed
  }
  return result
}
