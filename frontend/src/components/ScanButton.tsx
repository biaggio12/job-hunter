import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useToast } from './Toast'

/** „Skanuj teraz” (US-B2): kolejkuje skan i odpytuje status aż do zakończenia. */
export function ScanButton({ portal, label = 'Skanuj teraz', className = 'btn-primary' }: { portal?: string; label?: string; className?: string }) {
  const [ids, setIds] = useState<number[] | null>(null)
  const [progress, setProgress] = useState('')
  const toast = useToast()
  const qc = useQueryClient()

  useEffect(() => {
    if (!ids) return
    let stop = false
    const tick = async () => {
      try {
        const s = await api.scanStatus(ids)
        const done = s.items.filter((i) => i.status === 'success' || i.status === 'failed').length
        setProgress(`${done}/${s.items.length}`)
        if (s.finished) {
          const failed = s.items.filter((i) => i.status === 'failed')
          toast(
            `Skan zakończony — nowych ogłoszeń: ${s.newCount}` + (failed.length ? ` · błędy: ${failed.map((f) => f.portal.name).join(', ')}` : ''),
            failed.length && failed.length === s.items.length ? 'error' : 'success',
          )
          setIds(null)
          qc.invalidateQueries()
          return
        }
      } catch {
        /* chwilowy błąd sieci — spróbuj ponownie */
      }
      if (!stop) setTimeout(tick, 2500)
    }
    const t = setTimeout(tick, 1500)
    return () => {
      stop = true
      clearTimeout(t)
    }
  }, [ids, qc, toast])

  const start = async () => {
    try {
      const res = await api.startScan(portal)
      if (!res.items.length) {
        toast('Brak włączonych portali do skanowania.', 'info')
        return
      }
      setProgress(`0/${res.items.length}`)
      setIds(res.items.map((i) => i.id))
      qc.invalidateQueries({ queryKey: ['scans'] })
    } catch (e) {
      toast((e as Error).message, 'error')
    }
  }

  return (
    <button className={className} onClick={start} disabled={ids !== null} title={ids ? 'Skan trwa — skanuje worker w tle' : undefined}>
      {ids ? (
        <>
          <span className="size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent" />
          Skanuję… {progress}
        </>
      ) : (
        <>
          <svg viewBox="0 0 20 20" fill="currentColor" className="size-4" aria-hidden>
            <path fillRule="evenodd" d="M15.3 5.3A7 7 0 0 0 3.1 9H1.5l2.5 3 2.5-3H5.2a5 5 0 0 1 8.7-2.3l1.4-1.4ZM16 8l-2.5 3h1.3a5 5 0 0 1-8.7 2.3l-1.4 1.4A7 7 0 0 0 16.9 11h1.6L16 8Z" clipRule="evenodd" />
          </svg>
          {label}
        </>
      )}
    </button>
  )
}
