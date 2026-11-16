// Klient REST API Symfony + typy odpowiedzi.

export type WorkMode = 'remote' | 'hybrid' | 'office'
export type Status = 'none' | 'applied' | 'rejected' | 'hidden'
export type Stage = 'applied' | 'interview' | 'task' | 'offer' | 'rejected'

export interface PortalRef { code: string; name: string }

export interface Offer {
  id: number
  portal: PortalRef
  externalId: string
  url: string
  title: string
  company: string
  location: string | null
  workMode: WorkMode | null
  salaryFrom: number | null
  salaryTo: number | null
  salaryCurrency: string | null
  contractType: string | null
  tags: string[]
  seniority: string | null
  publishedAt: string | null
  firstSeenAt: string
  lastSeenAt: string
  expired: boolean
  status: Status
  appliedAt: string | null
  stage: Stage | null
  viewed: boolean
  hasNote: boolean
  notifiedAt: string | null
  duplicates: { count: number; applied: boolean }
}

export interface OfferDetails extends Offer {
  note: string | null
  missedScans: number
  stageHistory: { from: Stage | null; to: Stage | null; changedAt: string }[]
  duplicateOffers: { id: number; title: string; company: string; portal: PortalRef; status: Status; url: string }[]
}

export interface OfferPage { items: Offer[]; total: number; page: number; perPage: number; pages: number }

export interface Facets { portals: PortalRef[]; tags: { name: string; count: number }[]; unseen: number }

export interface ScanRun {
  id: number
  portal: PortalRef
  trigger: 'scheduled' | 'manual' | 'console'
  status: 'queued' | 'running' | 'success' | 'failed'
  createdAt: string
  startedAt: string | null
  finishedAt: string | null
  foundCount: number
  newCount: number
  error: string | null
}

export interface Portal {
  id: number
  code: string
  name: string
  homepage: string
  enabled: boolean
  hasAdapter: boolean
  lastSuccessfulScanAt: string | null
  consecutiveFailures: number
  lastRun: ScanRun | null
}

export interface Profile {
  id: number
  name: string
  keywords: string[]
  workModes: WorkMode[]
  seniorities: string[]
  locations: string[]
  active: boolean
  createdAt: string
}
export type ProfileInput = Omit<Profile, 'id' | 'createdAt'>

export interface Settings {
  discordEnabled: boolean
  discordWebhookUrl: string | null
  telegramEnabled: boolean
  telegramBotToken: string | null
  telegramChatId: string | null
  notifyMinSalary: number | null
  notifyIncludeNoSalary: boolean
  notifyRemoteOnly: boolean
  notifyKeywords: string[]
  quietHoursStart: string | null
  quietHoursEnd: string | null
  env: Record<'discordWebhookUrl' | 'telegramBotToken' | 'telegramChatId', boolean>
  channels: Record<string, { name: string; configured: boolean; enabled: boolean }>
  scanIntervalHours: number
  timezone: string
  quietNow: boolean
}

export interface BlacklistEntry { id: number; name: string; createdAt: string }

export class ApiError extends Error {
  constructor(message: string, public status: number, public details?: unknown) {
    super(message)
  }
}

async function request<T>(method: string, url: string, body?: unknown): Promise<T> {
  const res = await fetch(url, {
    method,
    headers: body === undefined ? { Accept: 'application/json' } : { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  if (res.status === 204) return undefined as T
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    // Błędy walidacji Symfony (422) mają listę naruszeń.
    const violations = (data?.violations ?? []) as { propertyPath: string; title: string }[]
    const msg = violations.length
      ? violations.map((v) => v.title).join(' ')
      : (data?.error ?? data?.detail ?? data?.title ?? `Błąd ${res.status}`)
    throw new ApiError(msg, res.status, data)
  }
  return data as T
}

export const api = {
  offers: (params: URLSearchParams) => request<OfferPage>('GET', `/api/offers?${params}`),
  facets: () => request<Facets>('GET', '/api/offers/facets'),
  offer: (id: number) => request<OfferDetails>('GET', `/api/offers/${id}`),
  view: (id: number) => request<Offer>('POST', `/api/offers/${id}/view`),
  apply: (id: number) => request<OfferDetails>('POST', `/api/offers/${id}/apply`),
  unapply: (id: number) => request<OfferDetails>('DELETE', `/api/offers/${id}/apply`),
  updateOffer: (id: number, patch: Partial<{ status: Status; stage: Stage | null; note: string | null }>) =>
    request<OfferDetails>('PATCH', `/api/offers/${id}`, patch),
  markAllViewed: () => request<{ updated: number }>('POST', '/api/offers/mark-all-viewed'),
  pipeline: () => request<{ columns: Record<Stage, Offer[]> }>('GET', '/api/offers/pipeline'),

  portals: () => request<Portal[]>('GET', '/api/portals'),
  updatePortal: (id: number, patch: { enabled: boolean }) => request<Portal>('PATCH', `/api/portals/${id}`, patch),

  scans: (portal?: string) => request<{ items: ScanRun[]; active: number }>('GET', `/api/scans?limit=150${portal ? `&portal=${portal}` : ''}`),
  startScan: (portal?: string) => request<{ items: ScanRun[] }>('POST', '/api/scans', portal ? { portal } : {}),
  scanStatus: (ids: number[]) => request<{ items: ScanRun[]; finished: boolean; newCount: number }>('GET', `/api/scans/status?ids=${ids.join(',')}`),

  profiles: () => request<Profile[]>('GET', '/api/profiles'),
  createProfile: (p: ProfileInput) => request<Profile>('POST', '/api/profiles', p),
  updateProfile: (id: number, p: ProfileInput) => request<Profile>('PUT', `/api/profiles/${id}`, p),
  deleteProfile: (id: number) => request<void>('DELETE', `/api/profiles/${id}`),

  settings: () => request<Settings>('GET', '/api/settings'),
  saveSettings: (s: Record<string, unknown>) => request<Settings>('PUT', '/api/settings', s),
  testNotification: (channel: string) => request<{ ok: boolean }>('POST', '/api/settings/test-notification', { channel }),

  blacklist: () => request<BlacklistEntry[]>('GET', '/api/blacklist'),
  addBlacklist: (name: string) => request<BlacklistEntry & { hiddenOffers: number }>('POST', '/api/blacklist', { name }),
  removeBlacklist: (id: number) => request<void>('DELETE', `/api/blacklist/${id}`),
}
