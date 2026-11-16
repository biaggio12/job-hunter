import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { api, type Stage } from '../lib/api'
import { CONTRACTS, dateTime, relative, salary, SENIORITIES, STAGE_ORDER, STAGES, STATUSES, WORK_MODES } from '../lib/format'
import { OfferActions } from '../components/OfferActions'
import { Empty, Spinner } from '../components/ui'
import { useToast } from '../components/Toast'

/** Szczegóły ogłoszenia (US-C3), oznaczenie jako przejrzane (US-C4), etapy (US-D3), notatka (US-D4). */
export function OfferPage() {
  const id = Number(useParams().id)
  const qc = useQueryClient()
  const toast = useToast()
  const { data: offer, isLoading, error } = useQuery({ queryKey: ['offer', id], queryFn: () => api.offer(id) })
  const [note, setNote] = useState<string | null>(null)

  // US-C4: otwarcie szczegółów = przejrzane
  useEffect(() => {
    if (offer && !offer.viewed) {
      api.view(id).then(() => {
        qc.invalidateQueries({ queryKey: ['facets'] })
        qc.invalidateQueries({ queryKey: ['offers'] })
      })
    }
  }, [offer, id, qc])

  const update = useMutation({
    mutationFn: (patch: Parameters<typeof api.updateOffer>[1]) => api.updateOffer(id, patch),
    onSuccess: (data) => {
      qc.setQueryData(['offer', id], data)
      qc.invalidateQueries({ queryKey: ['offers'] })
      qc.invalidateQueries({ queryKey: ['pipeline'] })
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })

  const blacklist = useMutation({
    mutationFn: () => api.addBlacklist(offer!.company),
    onSuccess: (r) => {
      toast(`Dodano „${r.name}” do czarnej listy, ukryto ogłoszeń: ${r.hiddenOffers}`)
      qc.invalidateQueries()
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })

  if (isLoading) return <Spinner />
  if (error || !offer) return <Empty title="Nie znaleziono ogłoszenia" />

  const pay = salary(offer)
  const noteValue = note ?? offer.note ?? ''
  const noteDirty = note !== null && note !== (offer.note ?? '')

  return (
    <div className="mx-auto max-w-3xl">
      <Link to=".." relative="path" onClick={(e) => { if (window.history.length > 1) { e.preventDefault(); window.history.back() } }} className="mb-4 inline-block text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
        ← Wróć do listy
      </Link>

      <div className="card p-5 sm:p-6">
        <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
          <span className="font-medium">{offer.portal.name}</span>
          <span>·</span>
          <span>opublikowano {relative(offer.publishedAt ?? offer.firstSeenAt)}</span>
          {offer.expired && <span className="chip bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">wygasłe — zniknęło z portalu</span>}
        </div>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight">{offer.title}</h1>
        <p className="mt-1 text-lg text-slate-700 dark:text-slate-300">{offer.company}</p>

        <div className="mt-4 flex flex-wrap gap-2">
          {pay && <span className="rounded-md bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{pay} / mies.</span>}
          {offer.workMode && <span className="chip px-2.5 py-1 text-sm">{WORK_MODES[offer.workMode]}</span>}
          {offer.seniority && <span className="chip px-2.5 py-1 text-sm">{SENIORITIES[offer.seniority] ?? offer.seniority}</span>}
          {offer.location && <span className="chip px-2.5 py-1 text-sm">📍 {offer.location}</span>}
        </div>

        {offer.tags.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-1.5">
            {offer.tags.map((t) => (
              <span key={t} className="chip">{t}</span>
            ))}
          </div>
        )}

        <div className="mt-6 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
          <a href={offer.url} target="_blank" rel="noopener noreferrer" className="btn-primary">
            Otwórz oryginał i aplikuj ↗
          </a>
          <OfferActions offer={offer} />
        </div>

        {offer.duplicates.count > 0 && (
          <div className={`mt-5 rounded-lg border p-3 text-sm ${offer.duplicates.applied ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40' : 'border-slate-200 dark:border-slate-800'}`}>
            <p className="font-medium">{offer.duplicates.applied ? '⚠️ Ta oferta jest też na innym portalu i już na nią aplikowałeś' : 'Możliwe duplikaty na innych portalach'}</p>
            <ul className="mt-2 space-y-1">
              {offer.duplicateOffers.map((d) => (
                <li key={d.id}>
                  <Link to={`/offers/${d.id}`} className="text-indigo-600 hover:underline dark:text-indigo-400">{d.title}</Link>
                  <span className="text-slate-500"> — {d.portal.name} · {STATUSES[d.status]}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      <div className="mt-4 grid gap-4 md:grid-cols-2">
        <section className="card p-5">
          <h2 className="font-semibold">Etap rekrutacji</h2>
          <div className="mt-3 flex flex-wrap gap-1.5">
            {STAGE_ORDER.map((s) => (
              <button
                key={s}
                disabled={update.isPending}
                onClick={() => update.mutate({ stage: s as Stage })}
                className={`rounded-lg border px-2.5 py-1 text-sm transition ${
                  offer.stage === s ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'
                }`}
              >
                {STAGES[s]}
              </button>
            ))}
          </div>
          {offer.appliedAt && <p className="mt-3 text-sm text-slate-500">Zaaplikowano: {dateTime(offer.appliedAt)}</p>}
          {offer.stageHistory.length > 0 && (
            <ol className="mt-4 space-y-2 border-l-2 border-slate-200 pl-4 text-sm dark:border-slate-800">
              {[...offer.stageHistory].reverse().map((h, i) => (
                <li key={i}>
                  <span className="font-medium">{h.to ? STAGES[h.to] : 'Cofnięto aplikację'}</span>
                  <span className="text-slate-500"> · {dateTime(h.changedAt)}</span>
                </li>
              ))}
            </ol>
          )}
        </section>

        <section className="card flex flex-col p-5">
          <h2 className="font-semibold">Notatka</h2>
          <textarea
            className="input mt-3 min-h-32 flex-1 resize-y"
            placeholder="Z kim rozmawiałem, jakie widełki podałem, terminy…"
            value={noteValue}
            onChange={(e) => setNote(e.target.value)}
          />
          <div className="mt-2 flex items-center justify-end gap-2">
            {noteDirty && <span className="text-xs text-amber-600">Niezapisane zmiany</span>}
            <button
              className="btn-primary"
              disabled={!noteDirty || update.isPending}
              onClick={() => update.mutate({ note: noteValue }, { onSuccess: () => { setNote(null); toast('Zapisano notatkę') } })}
            >
              Zapisz
            </button>
          </div>
        </section>
      </div>

      <section className="card mt-4 p-5 text-sm">
        <h2 className="font-semibold">Dane ogłoszenia</h2>
        <dl className="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
          <Row label="Umowa">{offer.contractType ? CONTRACTS[offer.contractType] : '—'}</Row>
          <Row label="ID w portalu"><span className="break-all">{offer.externalId}</span></Row>
          <Row label="Data publikacji">{dateTime(offer.publishedAt)}</Row>
          <Row label="Pierwsze wykrycie">{dateTime(offer.firstSeenAt)}</Row>
          <Row label="Ostatnie wykrycie">{dateTime(offer.lastSeenAt)}</Row>
          <Row label="Powiadomienie">{offer.notifiedAt ? dateTime(offer.notifiedAt) : '—'}</Row>
        </dl>
        <div className="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
          <button
            className="btn-danger text-xs"
            disabled={blacklist.isPending}
            onClick={() => confirm(`Dodać „${offer.company}” do czarnej listy? Jej ogłoszenia będą ukrywane i nie wygenerują powiadomień.`) && blacklist.mutate()}
          >
            Dodaj firmę do czarnej listy
          </button>
        </div>
      </section>
    </div>
  )
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-4 border-b border-slate-100 py-1.5 dark:border-slate-800 sm:block sm:border-0 sm:py-0">
      <dt className="text-slate-500">{label}</dt>
      <dd className="text-right font-medium sm:text-left">{children}</dd>
    </div>
  )
}
