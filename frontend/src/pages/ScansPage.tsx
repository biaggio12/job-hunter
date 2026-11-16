import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../lib/api'
import { dateTime, duration } from '../lib/format'
import { Empty, PageHeader, Spinner, StatusBadge } from '../components/ui'

const TRIGGERS = { scheduled: 'automatyczny', manual: 'ręczny', console: 'konsola' } as const

/** Historia skanów (US-F1). */
export function ScansPage() {
  const [portal, setPortal] = useState('')
  const { data: portals } = useQuery({ queryKey: ['portals'], queryFn: api.portals })
  const { data, isLoading } = useQuery({
    queryKey: ['scans', portal],
    queryFn: () => api.scans(portal || undefined),
    refetchInterval: (q) => ((q.state.data?.active ?? 0) > 0 ? 3000 : 30_000),
  })

  return (
    <>
      <PageHeader
        title="Historia skanów"
        subtitle={data?.active ? `Trwające / oczekujące: ${data.active}` : 'Kiedy, który portal, ile ogłoszeń i czy wystąpił błąd.'}
        actions={
          <select className="input w-auto" value={portal} onChange={(e) => setPortal(e.target.value)} aria-label="Portal">
            <option value="">Wszystkie portale</option>
            {portals?.map((p) => (
              <option key={p.code} value={p.code}>{p.name}</option>
            ))}
          </select>
        }
      />
      {isLoading ? (
        <Spinner />
      ) : !data?.items.length ? (
        <Empty title="Brak skanów">Pierwszy automatyczny skan wykona się po starcie workera, możesz też kliknąć „Skanuj teraz”.</Empty>
      ) : (
        <div className="card overflow-x-auto">
          <table className="w-full min-w-[720px] text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800">
              <tr>
                <th className="px-4 py-3 font-medium">Kiedy</th>
                <th className="px-4 py-3 font-medium">Portal</th>
                <th className="px-4 py-3 font-medium">Tryb</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 text-right font-medium">Znalezione</th>
                <th className="px-4 py-3 text-right font-medium">Nowe</th>
                <th className="px-4 py-3 font-medium">Czas</th>
                <th className="px-4 py-3 font-medium">Uwagi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {data.items.map((s) => (
                <tr key={s.id} className="align-top">
                  <td className="whitespace-nowrap px-4 py-2.5">{dateTime(s.createdAt)}</td>
                  <td className="px-4 py-2.5 font-medium">{s.portal.name}</td>
                  <td className="px-4 py-2.5 text-slate-500">{TRIGGERS[s.trigger]}</td>
                  <td className="px-4 py-2.5"><StatusBadge status={s.status} /></td>
                  <td className="px-4 py-2.5 text-right tabular-nums">{s.foundCount}</td>
                  <td className="px-4 py-2.5 text-right font-semibold tabular-nums">{s.newCount}</td>
                  <td className="whitespace-nowrap px-4 py-2.5 text-slate-500">{duration(s.startedAt, s.finishedAt)}</td>
                  <td className={`max-w-md px-4 py-2.5 text-xs ${s.status === 'failed' ? 'text-rose-700 dark:text-rose-400' : 'text-slate-500'}`}>
                    <span className="line-clamp-3 whitespace-pre-line" title={s.error ?? undefined}>{s.error}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
