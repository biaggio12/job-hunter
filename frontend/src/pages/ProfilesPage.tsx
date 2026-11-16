import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api, type Profile, type ProfileInput, type WorkMode } from '../lib/api'
import { SENIORITIES, WORK_MODES } from '../lib/format'
import { ChipInput, Empty, MultiToggle, PageHeader, Spinner, Toggle } from '../components/ui'
import { useToast } from '../components/Toast'

const EMPTY: ProfileInput = { name: '', keywords: [], workModes: [], seniorities: [], locations: [], active: true }

/** Profile wyszukiwania (US-A3). */
export function ProfilesPage() {
  const { data, isLoading } = useQuery({ queryKey: ['profiles'], queryFn: api.profiles })
  const [editing, setEditing] = useState<Profile | 'new' | null>(null)

  return (
    <>
      <PageHeader
        title="Profile wyszukiwania"
        subtitle="Czego szukasz. Każdy profil jest tłumaczony na filtry każdego portalu; zmiany działają od następnego skanu."
        actions={<button className="btn-primary" onClick={() => setEditing('new')}>+ Nowy profil</button>}
      />

      {editing && <ProfileForm profile={editing === 'new' ? null : editing} onDone={() => setEditing(null)} />}

      {isLoading ? (
        <Spinner />
      ) : !data?.length ? (
        <Empty title="Brak profili">Bez aktywnego profilu skany niczego nie pobiorą.</Empty>
      ) : (
        <div className="grid gap-3 md:grid-cols-2">
          {data.map((p) => (
            <ProfileCard key={p.id} profile={p} onEdit={() => setEditing(p)} />
          ))}
        </div>
      )}
    </>
  )
}

function ProfileCard({ profile: p, onEdit }: { profile: Profile; onEdit: () => void }) {
  const qc = useQueryClient()
  const toast = useToast()
  const toggle = useMutation({
    mutationFn: (active: boolean) => api.updateProfile(p.id, { ...p, active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['profiles'] }),
    onError: (e) => toast((e as Error).message, 'error'),
  })
  const remove = useMutation({
    mutationFn: () => api.deleteProfile(p.id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['profiles'] }); toast('Usunięto profil') },
  })

  const Line = ({ label, values }: { label: string; values: string[] }) => (
    <div className="flex gap-2 text-sm">
      <span className="w-28 shrink-0 text-slate-500">{label}</span>
      <span>{values.length ? values.join(', ') : <span className="text-slate-400">dowolne</span>}</span>
    </div>
  )

  return (
    <div className={`card p-4 ${p.active ? '' : 'opacity-60'}`}>
      <div className="mb-3 flex items-start justify-between gap-2">
        <h3 className="font-semibold">{p.name}</h3>
        <Toggle checked={p.active} onChange={(v) => toggle.mutate(v)} label={p.active ? 'Aktywny' : 'Wyłączony'} />
      </div>
      <div className="space-y-1">
        <Line label="Słowa kluczowe" values={p.keywords} />
        <Line label="Tryb pracy" values={p.workModes.map((m) => WORK_MODES[m])} />
        <Line label="Poziom" values={p.seniorities.map((s) => SENIORITIES[s] ?? s)} />
        <Line label="Lokalizacje" values={p.locations} />
      </div>
      <div className="mt-3 flex gap-2">
        <button className="btn-secondary px-2.5 py-1.5 text-xs" onClick={onEdit}>Edytuj</button>
        <button className="btn-ghost px-2.5 py-1.5 text-xs text-rose-600" onClick={() => confirm(`Usunąć profil „${p.name}”?`) && remove.mutate()}>
          Usuń
        </button>
      </div>
    </div>
  )
}

function ProfileForm({ profile, onDone }: { profile: Profile | null; onDone: () => void }) {
  const qc = useQueryClient()
  const toast = useToast()
  const [form, setForm] = useState<ProfileInput>(profile ? { ...profile } : EMPTY)
  const set = <K extends keyof ProfileInput>(k: K, v: ProfileInput[K]) => setForm((f) => ({ ...f, [k]: v }))

  const save = useMutation({
    mutationFn: () => (profile ? api.updateProfile(profile.id, form) : api.createProfile(form)),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['profiles'] })
      toast(profile ? 'Zapisano profil' : 'Dodano profil')
      onDone()
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })

  return (
    <form className="card mb-5 space-y-4 p-5" onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
      <h2 className="font-semibold">{profile ? 'Edytuj profil' : 'Nowy profil'}</h2>
      <div>
        <label className="label" htmlFor="pname">Nazwa</label>
        <input id="pname" className="input" required maxLength={120} placeholder="np. Symfony Wrocław" value={form.name} onChange={(e) => set('name', e.target.value)} />
      </div>
      <div>
        <label className="label">Słowa kluczowe / technologie</label>
        <ChipInput value={form.keywords} onChange={(v) => set('keywords', v)} placeholder="symfony, php… (Enter dodaje)" />
        <p className="mt-1 text-xs text-slate-500">Każde słowo to osobne zapytanie do portalu — wyniki są łączone.</p>
      </div>
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <label className="label">Tryb pracy</label>
          <MultiToggle<WorkMode> options={WORK_MODES} value={form.workModes} onChange={(v) => set('workModes', v)} />
        </div>
        <div>
          <label className="label">Poziom doświadczenia</label>
          <MultiToggle<string> options={SENIORITIES} value={form.seniorities} onChange={(v) => set('seniorities', v)} />
        </div>
      </div>
      <div>
        <label className="label">Lokalizacje</label>
        <ChipInput value={form.locations} onChange={(v) => set('locations', v)} placeholder="np. Wrocław (puste = cała Polska)" />
        <p className="mt-1 text-xs text-slate-500">Ogłoszenia zdalne przechodzą niezależnie od lokalizacji.</p>
      </div>
      <div className="flex items-center justify-between gap-2">
        <Toggle checked={form.active} onChange={(v) => set('active', v)} label="Aktywny" />
        <div className="flex gap-2">
          <button type="button" className="btn-ghost" onClick={onDone}>Anuluj</button>
          <button className="btn-primary" disabled={save.isPending}>Zapisz</button>
        </div>
      </div>
    </form>
  )
}
