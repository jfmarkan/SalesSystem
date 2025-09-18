// Minimal date utils for the monthly calendar

/** Zero-pad */
const z = n => String(n).padStart(2, '0')

/** ISO yyyy-mm-dd */
export const iso = d => `${d.getFullYear()}-${z(d.getMonth()+1)}-${z(d.getDate())}`

/** Simple formatter with a few tokens */
export function f(date, pattern = 'MMMM yyyy', locale = 'en-US') {
  const d = date instanceof Date ? date : new Date(date)
  const map = {
    YYYY: String(d.getFullYear()),
    MM: z(d.getMonth() + 1),
    DD: z(d.getDate()),
    MMM: new Intl.DateTimeFormat(locale, { month: 'short' }).format(d),
    MMMM: new Intl.DateTimeFormat(locale, { month: 'long' }).format(d),
  }
  return pattern
    .replace('YYYY', map.YYYY)
    .replace('MMMM', map.MMMM)
    .replace('MMM', map.MMM)
    .replace('MM', map.MM)
    .replace('DD', map.DD)
}

/**
 * Build month grid weeks for view. weekStartsOn: 0=Sun, 1=Mon
 * Returns array of weeks, each week is array of { date, inMonth, iso, isToday }
 */
export function monthGrid(year, monthIndex, { weekStartsOn = 1 } = {}) {
  const first = new Date(year, monthIndex, 1)
  const last  = new Date(year, monthIndex + 1, 0)

  const startOffset = (first.getDay() - weekStartsOn + 7) % 7
  const gridStart = new Date(first)
  gridStart.setDate(first.getDate() - startOffset)

  const totalDays = startOffset + last.getDate()
  const rows = Math.ceil(totalDays / 7)
  const todayIso = iso(new Date())

  const weeks = []
  let cursor = new Date(gridStart)
  for (let r = 0; r < rows; r++) {
    const week = []
    for (let c = 0; c < 7; c++) {
      const cell = {
        date: new Date(cursor),
        inMonth: cursor.getMonth() === monthIndex,
        iso: iso(cursor),
        isToday: iso(cursor) === todayIso
      }
      week.push(cell)
      cursor.setDate(cursor.getDate() + 1)
    }
    weeks.push(week)
  }
  return weeks
}