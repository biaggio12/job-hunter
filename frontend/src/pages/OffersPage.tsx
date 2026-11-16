import { useSearchParams } from 'react-router'
import { useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { STATUSES, WORK_MODES } from '../lib/format'
import { OfferCard } from '../components/OfferCard'
import { Empty, PageHeader, Spinner } from '../components/ui'
import { useToast } from '../components/Toast'

/** Lista ogłoszeń z filtrami (US-C1, US-C2). Filtry są w URL, więc da się je zapisać w zakładce. */
export function OffersPage() {
  const [params, setParams] = useSearchParams()
  const [q, setQ] = useState(params.get('q') ?? '')
  const [filtersOpen, setFiltersOpen] = useState(false)
  const qc = useQueryClient()
  const toast = useToast()

  const set = (key: string, value: string | null) => {
    const next = new URLSearchParams(params)
    if (value === null || value === '' || value === 'false') next.delete(key)
    else next.set(key, value)
    if (key !== 'page') next.delete('page')
    setParams(next, { replace: true })
  }

  // Wyszukiwanie z opóźnieniem, żeby nie strzelać zapytaniem na każdy znak.
  useEffect(() => {
    const t = setTimeout(() => {
      if ((params.get('q') ?? '') !== q) set('q', q)
    }, 300)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  const { data: facets } = useQuery({ queryKey: ['facets'], queryFn: api.facets })
  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['offers', params.toString()],
    queryFn: () => api.offers(params),
    placeholderData: keepPreviousData,
  })

  const page = Number(params.get('page') ?? 1)
  const activeFilters = [...params.keys()].filter((k) => k !== 'page').length

  const markAll = async () => {
    const r = await api.markAllViewed()
    toast(`Oznaczono jako przejrzane: ${r.updated}`)
    qc.invalidateQueries()
  }

  return (
    <>
      <PageHeader
        title="Ogłoszenia"
        subtitle={data ? `${data.total} ${data.total === 1 ? 'ogłoszenie' : 'ogłoszeń'}${isFetching ? ' · odświeżam…' : ''}` : undefined}
        actions={
          (facets?.unseen ?? 0) > 0 && (
            <button className="btn-ghost text-xs" onClick={markAll}>
              Oznacz wszystkie jako przejrzane
            </button>
          )
        }
      />

      {/* Na telefonie: wyszukiwarka + przycisk rozwijający pozostałe filtry */}
      <div className="mb-3 flex gap-2 sm:hidden">
        <input className="input" placeholder="Szukaj stanowiska lub firmy…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Szukaj" />
        <button className="btn-secondary shrink-0" onClick={() => setFiltersOpen((v) => !v)} aria-expanded={filtersOpen}>
          Filtry{activeFilters > 0 ? ` (${activeFilters})` : ''}
        </button>
      </div>

      <div className={`card mb-4 grid-cols-2 gap-3 p-4 sm:grid md:grid-cols-4 lg:grid-cols-8 ${filtersOpen ? 'grid' : 'hidden'}`}>
        <div className="col-span-2 hidden sm:block lg:col-span-2">
          <label className="label" htmlFor="q">Szukaj</label>
          <input id="q" className="input" placeholder="Stanowisko lub firma…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="status">Status</label>
          <select id="status" className="input" value={params.get('status') ?? ''} onChange={(e) => set('status', e.target.value)}>
            <option value="">Aktywne</option>
            {Object.entries(STATUSES).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
            <option value="all">Wszystkie</option>
          </select>
        </div>
        <div>
          <label className="label" htmlFor="portal">Portal</label>
          <select id="portal" className="input" value={params.get('portal') ?? ''} onChange={(e) => set('portal', e.target.value)}>
            <option value="">Wszystkie</option>
            {facets?.portals.map((p) => (
              <option key={p.code} value={p.code}>{p.name}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label" htmlFor="workMode">Tryb pracy</label>
          <select id="workMode" className="input" value={params.get('workMode') ?? ''} onChange={(e) => set('workMode', e.target.value)}>
            <option value="">Dowolny</option>
            {Object.entries(WORK_MODES).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label" htmlFor="tech">Technologia</label>
          <select id="tech" className="input" value={params.get('tech') ?? ''} onChange={(e) => set('tech', e.target.value)}>
            <option value="">Dowolna</option>
            {facets?.tags.map((t) => (
              <option key={t.name} value={t.name}>{t.name} ({t.count})</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label" htmlFor="location">Lokalizacja</label>
          <input id="location" className="input" placeholder="np. Wrocław" defaultValue={params.get('location') ?? ''} onBlur={(e) => set('location', e.target.value.trim())} onKeyDown={(e) => e.key === 'Enter' && set('location', e.currentTarget.value.trim())} />
        </div>
        <div>
          <label className="label" htmlFor="minSalary">Min. widełki</label>
          <input id="minSalary" type="number" inputMode="numeric" step={1000} min={0} className="input" placeholder="PLN / mies." defaultValue={params.get('minSalary') ?? ''} onBlur={(e) => set('minSalary', e.target.value)} onKeyDown={(e) => e.key === 'Enter' && set('minSalary', e.currentTarget.value)} />
        </div>
        <div className="col-span-2 flex flex-wrap items-center gap-4 md:col-span-4 lg:col-span-8">
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 accent-indigo-600" checked={params.get('unseen') === 'true'} onChange={(e) => set('unseen', String(e.target.checked))} />
            Tylko nieprzejrzane
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 accent-indigo-600" checked={params.get('showExpired') === 'true'} onChange={(e) => set('showExpired', String(e.target.checked))} />
            Pokaż wygasłe
          </label>
          {activeFilters > 0 && (
            <button className="ml-auto text-sm text-indigo-600 hover:underline dark:text-indigo-400" onClick={() => { setQ(''); setParams({}, { replace: true }) }}>
              Wyczyść filtry
            </button>
          )}
        </div>
      </div>

      {isLoading ? (
        <Spinner />
      ) : !data?.items.length ? (
        <Empty title="Brak ogłoszeń">
          {activeFilters > 0 ? 'Żadne ogłoszenie nie pasuje do filtrów.' : 'Kliknij „Skanuj teraz”, aby pobrać ogłoszenia z portali, albo poczekaj na automatyczny skan.'}
        </Empty>
      ) : (
        <div className="flex flex-col gap-3">
          {data.items.map((o) => (
            <OfferCard key={o.id} offer={o} />
          ))}
        </div>
      )}

      {data && data.pages > 1 && (
        <div className="mt-6 flex items-center justify-center gap-2">
          <button className="btn-secondary" disabled={page <= 1} onClick={() => { set('page', String(page - 1)); window.scrollTo(0, 0) }}>
            ← Poprzednia
          </button>
          <span className="px-2 text-sm text-slate-500">
            {page} / {data.pages}
          </span>
          <button className="btn-secondary" disabled={page >= data.pages} onClick={() => { set('page', String(page + 1)); window.scrollTo(0, 0) }}>
            Następna →
          </button>
        </div>
      )}
    </>
  )
}
