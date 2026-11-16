import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type ReactNode } from 'react'
import { api, type Settings } from '../lib/api'
import { dateTime } from '../lib/format'
import { ChipInput, PageHeader, Spinner, Toggle } from '../components/ui'
import { useToast } from '../components/Toast'

interface Form {
  discordEnabled: boolean
  discordWebhookUrl: string
  telegramEnabled: boolean
  telegramBotToken: string
  telegramChatId: string
  notifyMinSalary: string
  notifyIncludeNoSalary: boolean
  notifyRemoteOnly: boolean
  notifyKeywords: string[]
  quietEnabled: boolean
  quietHoursStart: string
  quietHoursEnd: string
}

const toForm = (s: Settings): Form => ({
  discordEnabled: s.discordEnabled,
  discordWebhookUrl: '',
  telegramEnabled: s.telegramEnabled,
  telegramBotToken: '',
  telegramChatId: s.telegramChatId ?? '',
  notifyMinSalary: s.notifyMinSalary ? String(s.notifyMinSalary) : '',
  notifyIncludeNoSalary: s.notifyIncludeNoSalary,
  notifyRemoteOnly: s.notifyRemoteOnly,
  notifyKeywords: s.notifyKeywords,
  quietEnabled: !!(s.quietHoursStart && s.quietHoursEnd),
  quietHoursStart: s.quietHoursStart ?? '22:00',
  quietHoursEnd: s.quietHoursEnd ?? '07:00',
})

/** Kanały (US-E1, E2), reguły (US-E3), godziny ciszy (US-E4), test (US-E5), czarna lista (US-D5). */
export function SettingsPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const { data } = useQuery({ queryKey: ['settings'], queryFn: api.settings })
  const [form, setForm] = useState<Form | null>(null)

  useEffect(() => {
    if (data && !form) setForm(toForm(data))
  }, [data, form])

  const save = useMutation({
    mutationFn: (f: Form) =>
      api.saveSettings({
        discordEnabled: f.discordEnabled,
        discordWebhookUrl: f.discordWebhookUrl || null,
        telegramEnabled: f.telegramEnabled,
        telegramBotToken: f.telegramBotToken || null,
        telegramChatId: f.telegramChatId || null,
        notifyMinSalary: f.notifyMinSalary ? Number(f.notifyMinSalary) : null,
        notifyIncludeNoSalary: f.notifyIncludeNoSalary,
        notifyRemoteOnly: f.notifyRemoteOnly,
        notifyKeywords: f.notifyKeywords,
        quietHoursStart: f.quietEnabled ? f.quietHoursStart : null,
        quietHoursEnd: f.quietEnabled ? f.quietHoursEnd : null,
      }),
    onSuccess: (s) => {
      qc.setQueryData(['settings'], s)
      setForm(toForm(s))
      toast('Zapisano ustawienia')
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })

  const clearSecret = useMutation({
    mutationFn: (field: 'discordWebhookUrl' | 'telegramBotToken') =>
      api.saveSettings({ ...serializeKeep(data!), clearSecrets: [field] }),
    onSuccess: (s) => { qc.setQueryData(['settings'], s); setForm(toForm(s)); toast('Usunięto zapisaną wartość') },
  })

  const test = useMutation({
    mutationFn: (channel: string) => api.testNotification(channel),
    onSuccess: () => toast('Wysłano wiadomość testową — sprawdź komunikator'),
    onError: (e) => toast((e as Error).message, 'error'),
  })

  if (!data || !form) return <Spinner />
  const set = <K extends keyof Form>(k: K, v: Form[K]) => setForm((f) => ({ ...f!, [k]: v }))

  return (
    <>
      <PageHeader title="Ustawienia" subtitle={`Skan co ${data.scanIntervalHours} h (SCAN_INTERVAL_HOURS w .env) · strefa czasowa ${data.timezone}`} />

      <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(form) }}>
        <Section title="Discord" description="Powiadomienia przez webhook kanału: Ustawienia kanału → Integracje → Webhooki → Kopiuj URL.">
          <Toggle checked={form.discordEnabled} onChange={(v) => set('discordEnabled', v)} label="Wysyłaj powiadomienia na Discorda" />
          <SecretField
            label="URL webhooka"
            placeholder="https://discord.com/api/webhooks/…"
            stored={data.discordWebhookUrl}
            fromEnv={data.env.discordWebhookUrl}
            value={form.discordWebhookUrl}
            onChange={(v) => set('discordWebhookUrl', v)}
            onClear={() => clearSecret.mutate('discordWebhookUrl')}
          />
          <ChannelFooter configured={data.channels.discord?.configured} onTest={() => test.mutate('discord')} testing={test.isPending} />
        </Section>

        <Section title="Telegram" description="Utwórz bota przez @BotFather, dodaj go do czatu i podaj ID czatu (np. z https://api.telegram.org/bot<TOKEN>/getUpdates).">
          <Toggle checked={form.telegramEnabled} onChange={(v) => set('telegramEnabled', v)} label="Wysyłaj powiadomienia na Telegram" />
          <div className="grid gap-3 md:grid-cols-2">
            <SecretField
              label="Token bota"
              placeholder="123456:ABC-…"
              stored={data.telegramBotToken}
              fromEnv={data.env.telegramBotToken}
              value={form.telegramBotToken}
              onChange={(v) => set('telegramBotToken', v)}
              onClear={() => clearSecret.mutate('telegramBotToken')}
            />
            <div>
              <label className="label" htmlFor="chat">ID czatu</label>
              <input id="chat" className="input" placeholder={data.env.telegramChatId ? 'ustawione w .env' : '-1001234567890'} value={form.telegramChatId} onChange={(e) => set('telegramChatId', e.target.value)} />
            </div>
          </div>
          <ChannelFooter configured={data.channels.telegram?.configured} onTest={() => test.mutate('telegram')} testing={test.isPending} />
        </Section>

        <Section title="Reguły powiadomień" description="Które nowe ogłoszenia mają generować powiadomienie. Pozostałe i tak trafiają na listę.">
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <label className="label" htmlFor="minsal">Minimalne widełki (górna granica, PLN / mies.)</label>
              <input id="minsal" type="number" min={0} step={1000} className="input" placeholder="bez limitu" value={form.notifyMinSalary} onChange={(e) => set('notifyMinSalary', e.target.value)} />
              <div className="mt-2">
                <Toggle checked={form.notifyIncludeNoSalary} onChange={(v) => set('notifyIncludeNoSalary', v)} label="Powiadamiaj też o ogłoszeniach bez widełek" disabled={!form.notifyMinSalary} />
              </div>
            </div>
            <div>
              <label className="label">Słowa kluczowe (tytuł lub technologie)</label>
              <ChipInput value={form.notifyKeywords} onChange={(v) => set('notifyKeywords', v)} placeholder="puste = wszystkie" />
            </div>
          </div>
          <Toggle checked={form.notifyRemoteOnly} onChange={(v) => set('notifyRemoteOnly', v)} label="Tylko oferty zdalne" />
        </Section>

        <Section title="Godziny ciszy" description="Powiadomienia z nocnych skanów zostaną wysłane zbiorczo po zakończeniu ciszy. Alerty o awariach są wysyłane zawsze.">
          <Toggle checked={form.quietEnabled} onChange={(v) => set('quietEnabled', v)} label="Włącz godziny ciszy" />
          {form.quietEnabled && (
            <div className="flex flex-wrap items-end gap-3">
              <div>
                <label className="label" htmlFor="qs">Od</label>
                <input id="qs" type="time" className="input w-32" value={form.quietHoursStart} onChange={(e) => set('quietHoursStart', e.target.value)} />
              </div>
              <div>
                <label className="label" htmlFor="qe">Do</label>
                <input id="qe" type="time" className="input w-32" value={form.quietHoursEnd} onChange={(e) => set('quietHoursEnd', e.target.value)} />
              </div>
              {data.quietNow && <span className="pb-2 text-sm text-amber-600">Teraz trwa cisza</span>}
            </div>
          )}
        </Section>

        <div className="card sticky bottom-20 z-10 flex items-center justify-end gap-3 p-3 shadow-lg sm:bottom-4">
          <span className="text-xs text-slate-500">Sekrety nie są wyświetlane — puste pole zostawia zapisaną wartość.</span>
          <button className="btn-primary px-6" disabled={save.isPending}>
            Zapisz ustawienia
          </button>
        </div>
      </form>

      <Blacklist />
    </>
  )
}

/** Zachowuje bieżące wartości przy czyszczeniu sekretu. */
function serializeKeep(s: Settings) {
  return {
    discordEnabled: s.discordEnabled,
    telegramEnabled: s.telegramEnabled,
    telegramChatId: s.telegramChatId,
    notifyMinSalary: s.notifyMinSalary,
    notifyIncludeNoSalary: s.notifyIncludeNoSalary,
    notifyRemoteOnly: s.notifyRemoteOnly,
    notifyKeywords: s.notifyKeywords,
    quietHoursStart: s.quietHoursStart,
    quietHoursEnd: s.quietHoursEnd,
  }
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <section className="card space-y-4 p-5">
      <div>
        <h2 className="font-semibold">{title}</h2>
        {description && <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{description}</p>}
      </div>
      {children}
    </section>
  )
}

function SecretField(props: { label: string; placeholder: string; stored: string | null; fromEnv: boolean; value: string; onChange: (v: string) => void; onClear: () => void }) {
  const hint = props.stored ? `zapisano (${props.stored}) — wpisz nowy, aby zmienić` : props.fromEnv ? 'ustawione w .env — wpisz, aby nadpisać' : props.placeholder
  return (
    <div>
      <label className="label">{props.label}</label>
      <div className="flex gap-2">
        <input type="password" autoComplete="off" className="input" placeholder={hint} value={props.value} onChange={(e) => props.onChange(e.target.value)} />
        {props.stored && (
          <button type="button" className="btn-ghost shrink-0 text-xs" onClick={() => confirm('Usunąć zapisaną wartość?') && props.onClear()}>
            Usuń
          </button>
        )}
      </div>
    </div>
  )
}

function ChannelFooter({ configured, onTest, testing }: { configured?: boolean; onTest: () => void; testing: boolean }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
      <span className={`text-sm ${configured ? 'text-emerald-600' : 'text-slate-500'}`}>{configured ? '● Skonfigurowany' : '○ Nieskonfigurowany'}</span>
      <button type="button" className="btn-secondary text-xs" onClick={onTest} disabled={!configured || testing} title={configured ? undefined : 'Najpierw zapisz konfigurację'}>
        Wyślij test
      </button>
    </div>
  )
}

function Blacklist() {
  const qc = useQueryClient()
  const toast = useToast()
  const [name, setName] = useState('')
  const { data } = useQuery({ queryKey: ['blacklist'], queryFn: api.blacklist })
  const add = useMutation({
    mutationFn: () => api.addBlacklist(name.trim()),
    onSuccess: (r) => {
      setName('')
      qc.invalidateQueries()
      toast(`Dodano „${r.name}”, ukryto ogłoszeń: ${r.hiddenOffers}`)
    },
    onError: (e) => toast((e as Error).message, 'error'),
  })
  const remove = useMutation({
    mutationFn: (id: number) => api.removeBlacklist(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['blacklist'] }),
  })

  return (
    <section className="card mt-4 space-y-4 p-5">
      <div>
        <h2 className="font-semibold">Czarna lista firm</h2>
        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Ogłoszenia tych firm są automatycznie ukrywane i nie generują powiadomień. Porównanie ignoruje wielkość liter i formy prawne (sp. z o.o., S.A.).</p>
      </div>
      <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); if (name.trim()) add.mutate() }}>
        <input className="input" placeholder="Nazwa firmy" value={name} onChange={(e) => setName(e.target.value)} maxLength={255} />
        <button className="btn-secondary shrink-0" disabled={!name.trim() || add.isPending}>Dodaj</button>
      </form>
      {data && data.length > 0 && (
        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
          {data.map((b) => (
            <li key={b.id} className="flex items-center justify-between gap-2 py-2 text-sm">
              <span>
                {b.name} <span className="text-xs text-slate-400">· {dateTime(b.createdAt)}</span>
              </span>
              <button className="btn-ghost px-2 py-1 text-xs" onClick={() => remove.mutate(b.id)}>Usuń</button>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
