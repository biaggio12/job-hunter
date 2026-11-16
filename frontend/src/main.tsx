import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import './index.css'
import { Layout } from './components/Layout'
import { ToastProvider } from './components/Toast'
import { OffersPage } from './pages/OffersPage'
import { OfferPage } from './pages/OfferPage'
import { PipelinePage } from './pages/PipelinePage'
import { ProfilesPage } from './pages/ProfilesPage'
import { PortalsPage } from './pages/PortalsPage'
import { ScansPage } from './pages/ScansPage'
import { SettingsPage } from './pages/SettingsPage'
import { Empty } from './components/ui'

const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 10_000, refetchOnWindowFocus: true, retry: 1 } },
})

const router = createBrowserRouter([
  {
    element: <Layout />,
    children: [
      { index: true, element: <OffersPage /> },
      { path: 'offers/:id', element: <OfferPage /> },
      { path: 'pipeline', element: <PipelinePage /> },
      { path: 'profiles', element: <ProfilesPage /> },
      { path: 'portals', element: <PortalsPage /> },
      { path: 'scans', element: <ScansPage /> },
      { path: 'settings', element: <SettingsPage /> },
      { path: '*', element: <Empty title="Nie ma takiej strony" /> },
    ],
  },
])

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <RouterProvider router={router} />
      </ToastProvider>
    </QueryClientProvider>
  </StrictMode>,
)
