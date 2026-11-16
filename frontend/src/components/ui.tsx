import { useState, type ReactNode, type KeyboardEvent } from 'react'

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

export function Toggle({ checked, onChange, label, disabled }: { checked: boolean; onChange: (v: boolean) => void; label?: string; disabled?: boolean }) {
  return (
    <label className={`inline-flex items-center gap-2 text-sm ${disabled ? 'opacity-50' : 'cursor-pointer'}`}>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={`relative h-5 w-9 shrink-0 rounded-full transition ${checked ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'}`}
      >
        <span className={`absolute top-0.5 size-4 rounded-full bg-white shadow transition-all ${checked ? 'left-4.5' : 'left-0.5'}`} />
      </button>
      {label}
    </label>
  )
}

/** Pole do wpisywania listy wartości (Enter / przecinek dodaje). */
export function ChipInput({ value, onChange, placeholder }: { value: string[]; onChange: (v: string[]) => void; placeholder?: string }) {
  const [draft, setDraft] = useState('')
  const add = () => {
    const parts = draft.split(',').map((s) => s.trim()).filter(Boolean)
    if (parts.length) onChange([...value, ...parts.filter((p) => !value.includes(p))])
    setDraft('')
  }
  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault()
      add()
    } else if (e.key === 'Backspace' && !draft && value.length) {
      onChange(value.slice(0, -1))
    }
  }
  return (
    <div className="input flex min-h-10 flex-wrap items-center gap-1.5 py-1.5">
      {value.map((v) => (
        <span key={v} className="chip bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
          {v}
          <button type="button" onClick={() => onChange(value.filter((x) => x !== v))} className="text-indigo-400 hover:text-indigo-700" aria-label={`Usuń ${v}`}>
            ×
          </button>
        </span>
      ))}
      <input
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        onKeyDown={onKey}
        onBlur={add}
        placeholder={value.length ? '' : placeholder}
        className="min-w-24 flex-1 bg-transparent text-sm outline-none placeholder:text-slate-400"
      />
    </div>
  )
}

/** Grupa przycisków wielokrotnego wyboru. */
export function MultiToggle<T extends string>({ options, value, onChange }: { options: Record<T, string>; value: T[]; onChange: (v: T[]) => void }) {
  return (
    <div className="flex flex-wrap gap-1.5">
      {(Object.keys(options) as T[]).map((k) => {
        const on = value.includes(k)
        return (
          <button
            key={k}
            type="button"
            onClick={() => onChange(on ? value.filter((x) => x !== k) : [...value, k])}
            className={`rounded-lg border px-3 py-1.5 text-sm transition ${
              on ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'
            }`}
          >
            {options[k]}
          </button>
        )
      })}
    </div>
  )
}

export function Empty({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="card flex flex-col items-center gap-2 px-6 py-14 text-center">
      <p className="font-medium">{title}</p>
      {children && <div className="max-w-md text-sm text-slate-500 dark:text-slate-400">{children}</div>}
    </div>
  )
}

export function Spinner() {
  return (
    <div className="flex justify-center py-16">
      <span className="size-6 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent" />
    </div>
  )
}

export function StatusBadge({ status }: { status: 'queued' | 'running' | 'success' | 'failed' }) {
  const map = {
    queued: ['W kolejce', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
    running: ['Trwa', 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300'],
    success: ['OK', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'],
    failed: ['Błąd', 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'],
  } as const
  const [label, cls] = map[status]
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cls}`}>{label}</span>
}
