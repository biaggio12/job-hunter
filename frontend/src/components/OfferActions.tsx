import { useMutation, useQueryClient } from '@tanstack/react-query'
import { api, type Offer } from '../lib/api'
import { useToast } from './Toast'

/** Szybkie akcje: zaaplikowano (US-D1), nie interesuje mnie / ukryj (US-D2). */
export function OfferActions({ offer, compact = false }: { offer: Offer; compact?: boolean }) {
  const qc = useQueryClient()
  const toast = useToast()

  const mutation = useMutation({
    mutationFn: async (action: 'apply' | 'unapply' | 'reject' | 'hide' | 'restore') => {
      switch (action) {
        case 'apply': return api.apply(offer.id)
        case 'unapply': return api.unapply(offer.id)
        case 'reject': return api.updateOffer(offer.id, { status: 'rejected' })
        case 'hide': return api.updateOffer(offer.id, { status: 'hidden' })
        case 'restore': return api.updateOffer(offer.id, { status: 'none' })
      }
    },
    onSuccess: (_d, action) => {
      qc.invalidateQueries()
      const msg = { apply: 'Oznaczono jako zaaplikowane', unapply: 'Cofnięto oznaczenie', reject: 'Oznaczono: nie interesuje mnie', hide: 'Ukryto ogłoszenie', restore: 'Przywrócono ogłoszenie' }[action]
      toast(msg)
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })

  const busy = mutation.isPending
  const size = compact ? 'px-2.5 py-1.5 text-xs' : ''

  if (offer.status === 'rejected' || offer.status === 'hidden') {
    return (
      <button className={`btn-secondary ${size}`} disabled={busy} onClick={() => mutation.mutate('restore')}>
        Przywróć
      </button>
    )
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {offer.status === 'applied' ? (
        <button className={`btn-success ${size}`} disabled={busy} onClick={() => mutation.mutate('unapply')} title="Kliknij, aby cofnąć">
          ✓ Zaaplikowano
        </button>
      ) : (
        <button className={`btn-primary ${size}`} disabled={busy} onClick={() => mutation.mutate('apply')}>
          Zaaplikowałem
        </button>
      )}
      {offer.status !== 'applied' && (
        <>
          <button className={`btn-secondary ${size}`} disabled={busy} onClick={() => mutation.mutate('reject')}>
            Nie interesuje mnie
          </button>
          {!compact && (
            <button className={`btn-ghost ${size}`} disabled={busy} onClick={() => mutation.mutate('hide')}>
              Ukryj
            </button>
          )}
        </>
      )}
    </div>
  )
}
