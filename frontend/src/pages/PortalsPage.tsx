import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { dateTime, relative } from '../lib/format'
import { PageHeader, Spinner, StatusBadge, Toggle } from '../components/ui'
import { ScanButton } from '../components/ScanButton'
import { useToast } from '../components/Toast'

/** Lista portali (US-A1) z włączaniem/wyłączaniem (US-A2) i skanem pojedynczego portalu (US-B2). */
export function PortalsPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const { data, isLoading } = useQuery({ queryKey: ['portals'], queryFn: api.portals, refetchInterval: 15_000 })
  const toggle = useMutation({
    mutationFn: ({ id, enabled }: { id: number; enabled: boolean }) => api.updatePortal(id, { enabled }),
    onSuccess: (p) => {
      qc.invalidateQueries({ queryKey: ['portals'] })
      toast(p.enabled ? `Włączono ${p.name}` : `Wyłączono ${p.name} — będzie pomijany przy skanach`)
    },
  })

  return (
    <>
      <PageHeader title="Portale" subtitle="Źródła ogłoszeń. Każdy portal obsługuje osobny adapter w kodzie; wyłączony portal jest pomijany, a pobrane ogłoszenia zostają." />
      {isLoading || !data ? (
        <Spinner />
      ) : (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {data.map((p) => {
            const last = p.lastRun
            return (
              <div key={p.id} className={`card flex flex-col gap-3 p-4 ${p.enabled ? '' : 'opacity-70'}`}>
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <a href={p.homepage} target="_blank" rel="noopener noreferrer" className="font-semibold hover:text-indigo-600">
                      {p.name}
                    </a>
                    <p className="text-xs text-slate-500">{p.code}</p>
                  </div>
                  <Toggle checked={p.enabled} disabled={!p.hasAdapter} onChange={(enabled) => toggle.mutate({ id: p.id, enabled })} label={p.enabled ? 'Włączony' : 'Wyłączony'} />
                </div>
                <dl className="space-y-1 text-sm">
                  <div className="flex justify-between gap-2">
                    <dt className="text-slate-500">Ostatni udany skan</dt>
                    <dd title={dateTime(p.lastSuccessfulScanAt)}>{relative(p.lastSuccessfulScanAt)}</dd>
                  </div>
                  {last && (
                    <div className="flex justify-between gap-2">
                      <dt className="text-slate-500">Ostatni skan</dt>
                      <dd className="flex items-center gap-2">
                        <StatusBadge status={last.status} />
                        {last.status === 'success' && <span>{last.foundCount} znalezionych, {last.newCount} nowych</span>}
                      </dd>
                    </div>
                  )}
                </dl>
                {p.consecutiveFailures > 0 && (
                  <p className="rounded-lg bg-rose-50 p-2 text-xs text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                    {p.consecutiveFailures} nieudanych skanów z rzędu.{last?.error ? ` ${last.error}` : ''}
                  </p>
                )}
                {!p.hasAdapter && <p className="text-xs text-amber-700">Brak adaptera w kodzie — portal nieaktywny.</p>}
                <div className="mt-auto">
                  <ScanButton portal={p.code} label="Skanuj ten portal" className="btn-secondary w-full" />
                </div>
              </div>
            )
          })}
        </div>
      )}
    </>
  )
}
