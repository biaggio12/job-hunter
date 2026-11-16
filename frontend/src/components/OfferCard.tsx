import { Link } from 'react-router'
import type { Offer } from '../lib/api'
import { relative, salary, SENIORITIES, STAGES, WORK_MODES } from '../lib/format'
import { OfferActions } from './OfferActions'

export function OfferCard({ offer }: { offer: Offer }) {
  const pay = salary(offer)
  const isNew = !offer.viewed && offer.status === 'none'

  return (
    <article
      className={`card relative flex flex-col gap-3 p-4 transition hover:border-slate-300 dark:hover:border-slate-700 sm:flex-row sm:items-center ${
        isNew ? 'border-l-4 border-l-indigo-500' : ''
      } ${offer.expired ? 'opacity-60' : ''}`}
    >
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          {isNew && <span className="rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Nowe</span>}
          <Link to={`/offers/${offer.id}`} className="truncate font-semibold hover:text-indigo-600 dark:hover:text-indigo-400">
            {offer.title}
          </Link>
          {offer.expired && <span className="chip bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">wygasłe</span>}
          {offer.stage && <span className="chip bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{STAGES[offer.stage]}</span>}
          {offer.status === 'rejected' && <span className="chip">odrzucone</span>}
          {offer.status === 'hidden' && <span className="chip">ukryte</span>}
          {offer.hasNote && <span title="Ma notatkę" className="text-xs">📝</span>}
        </div>
        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600 dark:text-slate-400">
          <span className="font-medium text-slate-800 dark:text-slate-200">{offer.company}</span>
          {offer.workMode && <span>{WORK_MODES[offer.workMode]}</span>}
          {offer.location && <span className="max-w-64 truncate">{offer.location}</span>}
          {offer.seniority && <span>{SENIORITIES[offer.seniority] ?? offer.seniority}</span>}
        </div>
        <div className="mt-2 flex flex-wrap items-center gap-1.5">
          {pay ? (
            <span className="rounded-md bg-emerald-50 px-2 py-0.5 text-sm font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{pay}</span>
          ) : (
            <span className="text-xs text-slate-400">brak widełek</span>
          )}
          {offer.tags.slice(0, 6).map((t) => (
            <span key={t} className="chip">{t}</span>
          ))}
        </div>
        {offer.duplicates.count > 0 && (
          <p className={`mt-2 text-xs ${offer.duplicates.applied ? 'font-medium text-amber-700 dark:text-amber-400' : 'text-slate-500'}`}>
            {offer.duplicates.applied
              ? '⚠️ Już aplikowałeś na tę ofertę przez inny portal'
              : offer.duplicates.count === 1
                ? 'Możliwy duplikat — podobne ogłoszenie tej firmy jest na innym portalu'
                : `Możliwy duplikat — podobne ogłoszenia tej firmy na innych portalach: ${offer.duplicates.count}`}
          </p>
        )}
      </div>

      <div className="flex shrink-0 flex-col items-start gap-2 sm:items-end">
        <div className="text-xs text-slate-500">
          <span className="font-medium">{offer.portal.name}</span> · {relative(offer.publishedAt ?? offer.firstSeenAt)}
        </div>
        <div className="flex flex-wrap items-center gap-1.5">
          <a href={offer.url} target="_blank" rel="noopener noreferrer" className="btn-ghost px-2.5 py-1.5 text-xs">
            Oryginał ↗
          </a>
          <OfferActions offer={offer} compact />
        </div>
      </div>
    </article>
  )
}
