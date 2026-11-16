# Job Hunter

Agregator ogłoszeń o pracę: co kilka godzin skanuje portale, zapisuje oferty w PostgreSQL,
pozwala śledzić aplikacje (etapy, notatki, kanban) i wysyła powiadomienia o nowych ofertach
na Discorda i/lub Telegram. Wymagania: [`user-stories.md`](user-stories.md).

**Stack:** Symfony 8.1 (PHP 8.4) · Messenger + Scheduler · PostgreSQL 17 · React 19 + Tailwind 4 · Docker Compose

## Uruchomienie

```bash
cp .env.example .env        # ustaw APP_SECRET (np. `openssl rand -hex 16`)
docker compose up -d --build
```

Aplikacja: **http://localhost:8088**. Przy pierwszym starcie kontener `php` wykonuje migracje,
rejestruje portale i tworzy domyślny profil „PHP / Symfony — zdalnie, mid/senior”.

Aplikacja nie ma logowania (praca lokalna), dlatego domyślnie nasłuchuje tylko na `127.0.0.1`
(`HTTP_BIND`). Jeśli wystawisz ją do sieci, postaw przed nią uwierzytelnianie.

| Kontener | Rola |
|---|---|
| `nginx` | reverse proxy + zbudowany frontend (pliki statyczne) |
| `php` | API Symfony (php-fpm), migracje przy starcie |
| `worker` | `messenger:consume async scheduler_default`: skany w tle, harmonogram, wysyłka powiadomień |
| `db` | PostgreSQL (dane na wolumenie `jobhunter_db_data`) |
| `frontend` | tylko tryb dev (`--profile dev`), Vite z hot-reload na porcie 5173 |

Wszystkie polecenia dotyczą wyłącznie projektu `jobhunter` (stała nazwa w `docker-compose.yml`).

## Codzienne użycie

- **Powiadomienia:** w UI → Ustawienia wklej URL webhooka Discorda (lub token bota i ID czatu Telegrama), kliknij „Wyślij test”.
- **Skan ręczny:** przycisk „Skanuj teraz” (wszystkie portale) albo na karcie portalu; z konsoli:
  ```bash
  docker compose exec php bin/console app:scan             # wszystkie, synchronicznie
  docker compose exec php bin/console app:scan justjoin    # jeden portal
  docker compose exec php bin/console app:scan --async     # przez workera
  docker compose exec php bin/console app:notify test discord
  ```
- **Logi:** `docker compose logs -f worker php`
- **Zmiana interwału:** `SCAN_INTERVAL_HOURS` w `.env`, potem `docker compose up -d worker`.
- **Testy backendu:** `docker compose --profile test run --rm tests` (osobna baza `jobhunter_test`).

## Portale

| Portal | Metoda | Stan (15.11.2026) |
|---|---|---|
| justjoin.it | publiczne API JSON | ✅ działa |
| nofluffjobs.com | API wyszukiwania (JSON) | ✅ działa |
| bulldogjob.pl | dane Next.js ze strony listy | ✅ działa |
| it.pracuj.pl | dane Next.js ze strony listy | ✅ działa |
| theprotocol.it | dane Next.js ze strony listy | ⛔ portal odrzuca klientów niebędących przeglądarką (Cloudflare) |

Adaptery celowo **nie obchodzą** zabezpieczeń anty-botowych. Gdy portal blokuje, skan kończy się
czytelnym błędem w historii, a po 3 nieudanych skanach z rzędu przychodzi alert. theprotocol.it
możesz wyłączyć w zakładce Portale.

Pierwszy skan portalu to import bez powiadomień, żeby nie zalać kanału setkami istniejących ofert.
Powiadomienia dotyczą tylko ofert, które pojawią się później.

### Dodanie nowego portalu
Utwórz klasę w `backend/src/Portal/Adapter/` implementującą `PortalAdapterInterface`
(najprościej: rozszerz `AbstractPortalAdapter`). Rejestracja w kontenerze i wpis w tabeli portali
dzieją się automatycznie przy starcie. Nie trzeba niczego zmieniać w reszcie systemu.

## Realizacja historyjek

| Historyjka | Gdzie |
|---|---|
| A1 lista portali, A2 włącz/wyłącz | UI „Portale”, `PortalController`, `PortalSynchronizer` |
| A3 profile wyszukiwania | UI „Wyszukiwanie”, `SearchProfile`, tłumaczenie w każdym adapterze + `OfferMatcher` |
| B1 skan cykliczny | `Schedule.php` (co `SCAN_INTERVAL_HOURS`), osobna wiadomość na portal → izolacja awarii |
| B2 skan ręczny | przycisk + odpytywanie statusu, `app:scan [portal]` |
| B3 zapis, B4 deduplikacja | `ScanService::store()`: unikalne (portal, external_id), aktualizacja „ostatniego wykrycia” |
| B5 duplikaty między portalami | `Text::duplicateKey()` (firma + tytuł bez poziomu/płci), ostrzeżenie „już aplikowałeś” |
| B6 wygasłe | po `EXPIRE_AFTER_MISSED_SCANS` kompletnych skanach bez oferty; ukryte domyślnie, filtr „Pokaż wygasłe” |
| C1–C4 lista, filtry, szczegóły, przejrzane | UI „Ogłoszenia” (filtry w URL), szczegóły oznaczają jako przejrzane |
| D1 zaaplikowano, D2 odrzucanie/ukrywanie | szybkie akcje na liście i w szczegółach, z cofaniem |
| D3 etapy + kanban | UI „Rekrutacje” (przeciąganie; lista wyboru na telefonie), historia zmian z datami |
| D4 notatki, D5 czarna lista | szczegóły ogłoszenia; Ustawienia → Czarna lista |
| E1 Discord, E2 Telegram | `NotificationChannelInterface` (wymienne adaptery); Discord grupuje po 10 embedów, obsługuje 429 |
| E3 reguły, E4 godziny ciszy, E5 test | Ustawienia; zaległe powiadomienia wysyła harmonogram co 10 min |
| F1 historia, F2 alert, F3 kulturalne pobieranie | UI „Historia”; alert po `SCAN_FAILURE_ALERT_THRESHOLD` porażkach; `PoliteHttpClient` (odstęp, limit stron, 429) |
| G1 docker compose | patrz wyżej |
| G2 logowanie | pominięte zgodnie z decyzją (praca lokalna); bind na 127.0.0.1 |

## Znane ograniczenia

- **Wygasanie:** skan pobiera maks. `SCRAPER_MAX_PAGES` stron na słowo kluczowe. Przy bardzo szerokich profilach starsze, wciąż aktywne oferty mogą wypaść poza limit i zostać oznaczone jako wygasłe.
- **Duplikaty między portalami:** klucz pomija poziom (Mid/Senior), więc dwa różne stanowiska tej samej firmy o tym samym tytule mogą trafić do jednej grupy „możliwych duplikatów”.
- **Widełki:** normalizowane do kwoty miesięcznej (stawki godzinowe × 168 h). Próg w regułach powiadomień dotyczy tylko PLN.
- Adaptery scrapujące strony (bulldogjob, pracuj, theprotocol) zależą od struktury stron. Testy w `backend/tests/Adapter` działają na nagranych odpowiedziach, a po zmianie portalu trzeba nagrać nowy fixture.
