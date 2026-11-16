import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'

type Kind = 'success' | 'error' | 'info'
interface Toast { id: number; kind: Kind; text: string }

const ToastContext = createContext<(text: string, kind?: Kind) => void>(() => {})

export function useToast() {
  return useContext(ToastContext)
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])

  const push = useCallback((text: string, kind: Kind = 'success') => {
    const id = Date.now() + Math.random()
    setToasts((t) => [...t, { id, kind, text }])
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), kind === 'error' ? 7000 : 4000)
  }, [])

  const colors: Record<Kind, string> = {
    success: 'bg-emerald-600',
    error: 'bg-rose-600',
    info: 'bg-slate-800',
  }

  return (
    <ToastContext.Provider value={push}>
      {children}
      <div className="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 sm:bottom-6" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`${colors[t.kind]} pointer-events-auto max-w-md rounded-lg px-4 py-2.5 text-sm text-white shadow-lg`}>
            {t.text}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}
