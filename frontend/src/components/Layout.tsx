import { NavLink, Outlet } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { ScanButton } from './ScanButton'

const NAV = [
  { to: '/', label: 'Ogłoszenia', icon: 'M4 6h16M4 12h16M4 18h10', end: true },
  { to: '/pipeline', label: 'Rekrutacje', icon: 'M5 4v16M12 4v10M19 4v6' },
  { to: '/profiles', label: 'Wyszukiwanie', icon: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm9 3-4.3-4.3' },
  { to: '/portals', label: 'Portale', icon: 'M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Z' },
  { to: '/scans', label: 'Historia', icon: 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' },
  { to: '/settings', label: 'Ustawienia', icon: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z' },
]

function Icon({ d }: { d: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" className="size-5" aria-hidden>
      <path d={d} />
    </svg>
  )
}

export function Layout() {
  const { data: facets } = useQuery({ queryKey: ['facets'], queryFn: api.facets, refetchInterval: 60_000 })
  const unseen = facets?.unseen ?? 0

  return (
    <div className="min-h-dvh pb-20 sm:pb-0">
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <div className="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4">
          <NavLink to="/" className="flex items-center gap-2 font-semibold tracking-tight">
            <span className="grid size-8 place-items-center rounded-lg bg-indigo-600 text-white">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="size-4.5" aria-hidden>
                <path d="M4 9h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9Zm5 0V7a3 3 0 0 1 6 0v2" strokeLinecap="round" />
              </svg>
            </span>
            <span className="hidden sm:inline">Job Hunter</span>
          </NavLink>
          <nav className="hidden flex-1 items-center gap-1 sm:flex">
            {NAV.map((n) => (
              <NavLink
                key={n.to}
                to={n.to}
                end={n.end}
                className={({ isActive }) =>
                  `relative rounded-lg px-3 py-1.5 text-sm font-medium transition ${
                    isActive ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                  }`
                }
              >
                {n.label}
                {n.to === '/' && unseen > 0 && (
                  <span className="ml-1.5 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">{unseen}</span>
                )}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto sm:ml-0">
            <ScanButton />
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-7xl px-4 py-6">
        <Outlet />
      </main>

      {/* Dolna nawigacja na telefonie */}
      <nav className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-6 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-slate-800 dark:bg-slate-950 sm:hidden">
        {NAV.map((n) => (
          <NavLink
            key={n.to}
            to={n.to}
            end={n.end}
            className={({ isActive }) =>
              `relative flex flex-col items-center gap-0.5 py-2 text-[10px] font-medium ${isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500'}`
            }
          >
            <Icon d={n.icon} />
            {n.label}
            {n.to === '/' && unseen > 0 && (
              <span className="absolute right-3 top-1 rounded-full bg-indigo-600 px-1 text-[9px] font-semibold text-white">{unseen > 99 ? '99+' : unseen}</span>
            )}
          </NavLink>
        ))}
      </nav>
    </div>
  )
}
