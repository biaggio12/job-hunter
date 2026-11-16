import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api, type Offer, type Stage } from '../lib/api'
import { relative, salary, STAGE_ORDER, STAGES } from '../lib/format'
import { Empty, PageHeader, Spinner } from '../components/ui'
import { useToast } from '../components/Toast'

const COLORS: Record<Stage, string> = {
  applied: 'bg-sky-500',
  interview: 'bg-violet-500',
  task: 'bg-amber-500',
  offer: 'bg-emerald-500',
  rejected: 'bg-slate-400',
}

/** Tablica kanban procesów rekrutacyjnych (US-D3). Przeciąganie na desktopie, lista wyboru na telefonie. */
export function PipelinePage() {
  const qc = useQueryClient()
  const toast = useToast()
  const { data, isLoading } = useQuery({ queryKey: ['pipeline'], queryFn: api.pipeline })
  const [dragOver, setDragOver] = useState<Stage | null>(null)

  const move = useMutation({
    mutationFn: ({ id, stage }: { id: number; stage: Stage | null }) => api.updateOffer(id, { stage }),
    onSuccess: (_d, v) => {
      qc.invalidateQueries()
      toast(v.stage ? `Przeniesiono do: ${STAGES[v.stage]}` : 'Usunięto z procesu')
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })

  if (isLoading || !data) return <Spinner />
  const total = STAGE_ORDER.reduce((n, s) => n + data.columns[s].length, 0)

  return (
    <>
      <PageHeader title="Rekrutacje" subtitle={`Procesy w toku: ${total}. Przeciągnij kartę, aby zmienić etap.`} />
      {total === 0 ? (
        <Empty title="Brak procesów rekrutacyjnych">Oznacz ogłoszenie jako „Zaaplikowałem”, a pojawi się tutaj.</Empty>
      ) : (
        <div className="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-4 lg:mx-0 lg:grid lg:grid-cols-5 lg:overflow-visible lg:px-0">
          {STAGE_ORDER.map((stage) => (
            <section
              key={stage}
              onDragOver={(e) => { e.preventDefault(); setDragOver(stage) }}
              onDragLeave={() => setDragOver(null)}
              onDrop={(e) => {
                e.preventDefault()
                setDragOver(null)
                const id = Number(e.dataTransfer.getData('text/plain'))
                if (id) move.mutate({ id, stage })
              }}
              className={`flex w-72 shrink-0 snap-start flex-col rounded-xl bg-slate-100 p-2 transition dark:bg-slate-900/60 lg:w-auto ${dragOver === stage ? 'ring-2 ring-indigo-500' : ''}`}
            >
              <h2 className="flex items-center gap-2 px-2 py-1.5 text-sm font-semibold">
                <span className={`size-2 rounded-full ${COLORS[stage]}`} />
                {STAGES[stage]}
                <span className="ml-auto text-xs font-normal text-slate-500">{data.columns[stage].length}</span>
              </h2>
              <div className="flex min-h-24 flex-col gap-2">
                {data.columns[stage].map((o) => (
                  <Card key={o.id} offer={o} onMove={(s) => move.mutate({ id: o.id, stage: s })} />
                ))}
              </div>
            </section>
          ))}
        </div>
      )}
    </>
  )
}

function Card({ offer, onMove }: { offer: Offer; onMove: (s: Stage | null) => void }) {
  const pay = salary(offer)
  return (
    <div
      draggable
      onDragStart={(e) => e.dataTransfer.setData('text/plain', String(offer.id))}
      className="card cursor-grab p-3 text-sm active:cursor-grabbing"
    >
      <Link to={`/offers/${offer.id}`} className="font-medium leading-snug hover:text-indigo-600 dark:hover:text-indigo-400">
        {offer.title}
      </Link>
      <p className="mt-0.5 text-slate-600 dark:text-slate-400">{offer.company}</p>
      {pay && <p className="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">{pay}</p>}
      <div className="mt-2 flex items-center justify-between gap-2 text-xs text-slate-500">
        <span>{offer.portal.name} · {relative(offer.appliedAt)}</span>
        {offer.hasNote && <span title="Ma notatkę">📝</span>}
      </div>
      <select
        aria-label="Zmień etap"
        className="input mt-2 py-1 text-xs lg:hidden"
        value={offer.stage ?? ''}
        onChange={(e) => onMove((e.target.value || null) as Stage | null)}
      >
        {STAGE_ORDER.map((s) => (
          <option key={s} value={s}>{STAGES[s]}</option>
        ))}
        <option value="">— usuń z procesu</option>
      </select>
    </div>
  )
}
