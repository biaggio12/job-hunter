import type { Offer, Stage, Status, WorkMode } from './api'

export const WORK_MODES: Record<WorkMode, string> = { remote: 'Zdalnie', hybrid: 'Hybrydowo', office: 'Stacjonarnie' }
export const SENIORITIES: Record<string, string> = { junior: 'Junior', mid: 'Mid', senior: 'Senior', lead: 'Lead' }
export const STATUSES: Record<Status, string> = { none: 'Nowe', applied: 'Zaaplikowano', rejected: 'Odrzucone', hidden: 'Ukryte' }
export const STAGES: Record<Stage, string> = {
  applied: 'Zaaplikowano',
  interview: 'Rozmowa',
  task: 'Zadanie',
  offer: 'Oferta',
  rejected: 'Odrzucenie',
}
export const STAGE_ORDER: Stage[] = ['applied', 'interview', 'task', 'offer', 'rejected']
export const CONTRACTS: Record<string, string> = { b2b: 'B2B', uop: 'UoP', uz: 'UZ', other: 'inna' }

const nf = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 })

export function salary(o: Pick<Offer, 'salaryFrom' | 'salaryTo' | 'salaryCurrency' | 'contractType'>): string | null {
  if (o.salaryFrom === null && o.salaryTo === null) return null
  const cur = o.salaryCurrency ?? 'PLN'
  const range =
    o.salaryFrom !== null && o.salaryTo !== null && o.salaryFrom !== o.salaryTo
      ? `${nf.format(o.salaryFrom)} – ${nf.format(o.salaryTo)}`
      : nf.format((o.salaryTo ?? o.salaryFrom)!)
  const contract = o.contractType ? ` · ${CONTRACTS[o.contractType] ?? o.contractType}` : ''
  return `${range} ${cur}${contract}`
}

const rtf = new Intl.RelativeTimeFormat('pl', { numeric: 'auto' })

export function relative(iso: string | null): string {
  if (!iso) return '—'
  const diff = (new Date(iso).getTime() - Date.now()) / 1000
  const abs = Math.abs(diff)
  if (abs < 60) return 'przed chwilą'
  if (abs < 3600) return rtf.format(Math.round(diff / 60), 'minute')
  if (abs < 86400) return rtf.format(Math.round(diff / 3600), 'hour')
  if (abs < 86400 * 30) return rtf.format(Math.round(diff / 86400), 'day')
  return dateTime(iso)
}

export function dateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('pl-PL', { dateStyle: 'medium', timeStyle: 'short' })
}

export function duration(from: string | null, to: string | null): string {
  if (!from || !to) return '—'
  const s = Math.max(0, Math.round((new Date(to).getTime() - new Date(from).getTime()) / 1000))
  return s < 60 ? `${s} s` : `${Math.floor(s / 60)} min ${s % 60} s`
}
