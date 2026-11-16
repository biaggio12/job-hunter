# Job Hunter — historyjki użytkownika

> Status: **szkic do rewizji** · Data: 2026-11-13

## 1. Cel aplikacji

Aplikacja agreguje ogłoszenia o pracę z kilku portali w jednym miejscu, zapisuje je w bazie, pozwala oznaczać,
na które ogłoszenia już zaaplikowałem, oraz cyklicznie (co kilka godzin) skanuje portale i wysyła powiadomienie
o nowych ogłoszeniach na Discorda (lub inny komunikator), żeby można było zaaplikować jak najszybciej.

## 2. Stack technologiczny

| Warstwa        | Technologia                                   |
|----------------|-----------------------------------------------|
| Backend / API  | Symfony (PHP)                                 |
| Frontend       | React + Tailwind CSS                          |
| Baza danych    | PostgreSQL                                    |
| Uruchamianie   | Docker / Docker Compose                       |
| Harmonogram    | Symfony Scheduler + Messenger (worker w osobnym kontenerze) — *do potwierdzenia* |

## 3. Aktorzy

- **Użytkownik** — ja; osoba szukająca pracy, jedyny użytkownik aplikacji (na start).
- **System (scheduler)** — proces działający w tle, który skanuje portale i wysyła powiadomienia.

## 4. Priorytety

- **MUST** — wymagane w MVP
- **SHOULD** — ważne, ale może wejść zaraz po MVP
- **COULD** — miłe do posiadania

---

## 5. Historyjki

### Epik A — Źródła ogłoszeń (portale)

#### US-A1 — Lista obsługiwanych portali · MUST
**Jako** użytkownik **chcę** widzieć listę portali, z których aplikacja pobiera ogłoszenia, **aby** wiedzieć, skąd pochodzą dane.

Kryteria akceptacji:
- Widzę nazwę portalu, status (włączony / wyłączony) i datę ostatniego udanego skanu.
- Każdy portal jest obsługiwany przez osobny „adapter” (scraper lub klient API), dodawany w kodzie.

#### US-A2 — Włączanie i wyłączanie portalu · SHOULD
**Jako** użytkownik **chcę** włączyć lub wyłączyć skanowanie wybranego portalu, **aby** nie dostawać ogłoszeń ze źródeł, które mnie nie interesują lub chwilowo nie działają.

Kryteria akceptacji:
- Wyłączony portal jest pomijany przy kolejnych skanach.
- Ogłoszenia już pobrane z wyłączonego portalu pozostają w bazie.

#### US-A3 — Kryteria wyszukiwania per portal · MUST
**Jako** użytkownik **chcę** zdefiniować, czego szukam (np. słowa kluczowe, technologie, lokalizacja / zdalnie, poziom doświadczenia), **aby** aplikacja pobierała tylko ogłoszenia pasujące do mnie.

Kryteria akceptacji:
- Mogę zdefiniować jeden lub więcej „profili wyszukiwania” (np. „PHP Senior zdalnie”, „Symfony Wrocław”).
- Profil jest tłumaczony na parametry wyszukiwania danego portalu (URL, filtry API).
- Zmiana profilu działa od następnego skanu.

---

### Epik B — Pobieranie i zapisywanie ogłoszeń

#### US-B1 — Automatyczne skanowanie co kilka godzin · MUST
**Jako** użytkownik **chcę**, aby system sam skanował wszystkie włączone portale w regularnych odstępach, **aby** nie musieć niczego uruchamiać ręcznie.

Kryteria akceptacji:
- Interwał jest konfigurowalny (domyślnie np. co 3 godziny).
- Skan każdego portalu działa niezależnie — awaria jednego nie zatrzymuje pozostałych.
- Skan działa w tle w kontenerze Dockera (worker), bez otwartej przeglądarki.

#### US-B2 — Ręczne uruchomienie skanu · SHOULD
**Jako** użytkownik **chcę** móc ręcznie uruchomić skan (wszystkich portali lub jednego), **aby** sprawdzić nowe oferty od razu.

Kryteria akceptacji:
- Przycisk „Skanuj teraz” w UI oraz komenda konsolowa (`bin/console app:scan [portal]`).
- Widzę informację, że skan trwa, a po zakończeniu — ile nowych ogłoszeń znaleziono.

#### US-B3 — Zapisywanie ogłoszeń · MUST
**Jako** użytkownik **chcę**, aby każde pobrane ogłoszenie było zapisane w bazie, **aby** mieć do niego dostęp nawet po zniknięciu z portalu.

Kryteria akceptacji — zapisywane pola (o ile portal je udostępnia):
- tytuł stanowiska, firma, lokalizacja, tryb pracy (zdalna / hybrydowa / stacjonarna),
- widełki wynagrodzenia (od, do, waluta, typ umowy: B2B / UoP),
- technologie / tagi, poziom (junior / mid / senior),
- link do oryginalnego ogłoszenia, portal źródłowy, zewnętrzne ID ogłoszenia,
- data publikacji, data pierwszego i ostatniego wykrycia przez system,
- opis (pełny lub skrócony — *do ustalenia*).

#### US-B4 — Deduplikacja ogłoszeń · MUST
**Jako** użytkownik **chcę**, aby to samo ogłoszenie nie pojawiało się wielokrotnie, **aby** lista była czytelna, a powiadomienia nie były powtarzane.

Kryteria akceptacji:
- Ogłoszenie z tym samym portalem i zewnętrznym ID (lub URL) jest aktualizowane, a nie dodawane ponownie.
- Ponowne wykrycie ogłoszenia aktualizuje „datę ostatniego wykrycia”, ale nie wywołuje powiadomienia.

#### US-B5 — Wykrywanie duplikatów między portalami · COULD
**Jako** użytkownik **chcę**, aby system rozpoznawał, że ta sama oferta (ta sama firma i stanowisko) jest na kilku portalach, **aby** nie aplikować dwa razy.

Kryteria akceptacji:
- Podobne ogłoszenia są grupowane (np. ta sama firma + podobny tytuł) i oznaczone jako „możliwy duplikat”.
- Oznaczenie jednego jako „zaaplikowano” pokazuje ostrzeżenie przy pozostałych z grupy.

#### US-B6 — Oznaczanie wygasłych ogłoszeń · SHOULD
**Jako** użytkownik **chcę** wiedzieć, że ogłoszenie zniknęło z portalu, **aby** nie tracić czasu na nieaktualne oferty.

Kryteria akceptacji:
- Ogłoszenie niewykryte przez N kolejnych skanów otrzymuje status „wygasłe”.
- Wygasłe ogłoszenia są domyślnie ukryte na liście, ale można je pokazać filtrem.

---

### Epik C — Przeglądanie ogłoszeń

#### US-C1 — Lista ogłoszeń · MUST
**Jako** użytkownik **chcę** widzieć listę wszystkich zapisanych ogłoszeń, **aby** przejrzeć oferty w jednym miejscu.

Kryteria akceptacji:
- Domyślnie posortowane od najnowszych.
- Każda pozycja pokazuje: tytuł, firmę, lokalizację / tryb pracy, widełki, portal, datę, status aplikacji.
- Nowe (jeszcze nieprzejrzane) ogłoszenia są wyróżnione.
- Paginacja lub nieskończone przewijanie.

#### US-C2 — Filtrowanie i wyszukiwanie · MUST
**Jako** użytkownik **chcę** filtrować ogłoszenia, **aby** szybko znaleźć te, które mnie interesują.

Kryteria akceptacji — filtry:
- status aplikacji (nowe / zaaplikowano / odrzucone przeze mnie / ukryte),
- portal, tryb pracy, lokalizacja, minimalne wynagrodzenie, technologie,
- wyszukiwanie tekstowe po tytule i firmie.

#### US-C3 — Szczegóły ogłoszenia · MUST
**Jako** użytkownik **chcę** zobaczyć szczegóły ogłoszenia i przejść do oryginału jednym kliknięciem, **aby** szybko zaaplikować.

Kryteria akceptacji:
- Widok szczegółów z wszystkimi zapisanymi danymi.
- Link do oryginalnego ogłoszenia otwiera się w nowej karcie.

#### US-C4 — Oznaczanie jako przejrzane · SHOULD
**Jako** użytkownik **chcę**, aby ogłoszenie po otwarciu było oznaczane jako przejrzane, **aby** odróżniać nowe od już widzianych.

---

### Epik D — Śledzenie aplikacji

#### US-D1 — Oznaczenie „zaaplikowano” · MUST
**Jako** użytkownik **chcę** oznaczyć ogłoszenie jako „zaaplikowano”, **aby** wiedzieć, gdzie już wysłałem CV.

Kryteria akceptacji:
- Jedno kliknięcie na liście i w widoku szczegółów.
- Zapisuje się data aplikowania.
- Można cofnąć oznaczenie.

#### US-D2 — Ukrywanie / odrzucanie ogłoszeń · MUST
**Jako** użytkownik **chcę** oznaczyć ogłoszenie jako „nie interesuje mnie”, **aby** zniknęło z listy domyślnej.

#### US-D3 — Etapy procesu rekrutacji · COULD
**Jako** użytkownik **chcę** śledzić etap rekrutacji (zaaplikowano → rozmowa → zadanie → oferta → odrzucenie), **aby** mieć pełny obraz moich procesów.

Kryteria akceptacji:
- Status można zmieniać, a historia zmian jest zapisywana z datami.
- Widok tablicy (kanban) lub listy z filtrem po etapie.

#### US-D4 — Notatki do ogłoszenia · SHOULD
**Jako** użytkownik **chcę** dodać notatkę do ogłoszenia (np. z kim rozmawiałem, jakie widełki podałem), **aby** mieć te informacje pod ręką.

#### US-D5 — Czarna lista firm · COULD
**Jako** użytkownik **chcę** dodać firmę do czarnej listy, **aby** jej ogłoszenia były automatycznie ukrywane i nie generowały powiadomień.

---

### Epik E — Powiadomienia

#### US-E1 — Powiadomienie na Discordzie o nowych ogłoszeniach · MUST
**Jako** użytkownik **chcę** dostać wiadomość na Discordzie, gdy pojawi się nowe ogłoszenie pasujące do moich kryteriów, **aby** zaaplikować jak najszybciej.

Kryteria akceptacji:
- Integracja przez Discord Webhook (URL konfigurowany w ustawieniach / zmiennej środowiskowej).
- Wiadomość zawiera: tytuł, firmę, widełki, tryb pracy, portal i link do ogłoszenia.
- Powiadomienie wysyłane tylko raz dla danego ogłoszenia.
- Przy wielu nowych ogłoszeniach w jednym skanie są grupowane (np. jedna wiadomość z listą lub kilka embedów), aby nie przekroczyć limitów Discorda.

#### US-E2 — Inne kanały powiadomień · SHOULD
**Jako** użytkownik **chcę** móc wybrać inny komunikator (np. Telegram, Slack, e-mail), **aby** dostawać powiadomienia tam, gdzie je najszybciej zobaczę.

Kryteria akceptacji:
- Kanały są implementowane jako wymienne adaptery (np. przez Symfony Notifier).
- Można włączyć kilka kanałów jednocześnie.

#### US-E3 — Reguły powiadomień · SHOULD
**Jako** użytkownik **chcę** określić, które ogłoszenia mają generować powiadomienie (np. tylko z widełkami ≥ X, tylko zdalne, tylko z danym słowem kluczowym), **aby** nie być zasypywanym mało istotnymi ofertami.

#### US-E4 — Wyciszenie powiadomień w nocy · COULD
**Jako** użytkownik **chcę** ustawić godziny ciszy, **aby** powiadomienia z nocnego skanu przyszły zbiorczo rano.

#### US-E5 — Test powiadomienia · SHOULD
**Jako** użytkownik **chcę** wysłać testową wiadomość z ustawień, **aby** sprawdzić, czy kanał jest poprawnie skonfigurowany.

---

### Epik F — Monitoring i niezawodność

#### US-F1 — Historia skanów · SHOULD
**Jako** użytkownik **chcę** widzieć historię skanów (kiedy, który portal, ile ogłoszeń, ile nowych, czy był błąd), **aby** wiedzieć, że system działa.

#### US-F2 — Alert o awarii scrapera · SHOULD
**Jako** użytkownik **chcę** dostać powiadomienie, gdy skan portalu kilka razy z rzędu się nie powiedzie lub zwróci 0 ogłoszeń, **aby** wiedzieć, że portal zmienił strukturę i adapter wymaga poprawki.

#### US-F3 — Kulturalne pobieranie danych · MUST
**Jako** właściciel systemu **chcę**, aby scrapery ograniczały liczbę zapytań (opóźnienia, limit stron), **aby** nie zostać zablokowanym przez portale.

---

### Epik G — Infrastruktura i uruchamianie

#### US-G1 — Uruchomienie jedną komendą · MUST
**Jako** użytkownik **chcę** uruchomić całą aplikację poleceniem `docker compose up`, **aby** łatwo postawić ją lokalnie lub na serwerze.

Kryteria akceptacji — kontenery:
- `php` (Symfony API), `worker` (Messenger + Scheduler), `frontend` (React; w produkcji zbudowane pliki statyczne), `db` (PostgreSQL), `nginx` / reverse proxy.
- Konfiguracja przez plik `.env` (sekrety, webhook Discorda, interwał skanu).
- Dane PostgreSQL trzymane na wolumenie.

#### US-G2 — Dostęp chroniony hasłem · SHOULD
**Jako** użytkownik **chcę**, aby aplikacja wymagała logowania, **aby** po wystawieniu jej na serwer nikt obcy nie miał dostępu do moich danych.

---

## 6. Wymagania niefunkcjonalne

- Czas od pojawienia się ogłoszenia na portalu do powiadomienia ≤ interwał skanu + kilka minut.
- Awaria jednego adaptera nie wpływa na pozostałe.
- Dodanie nowego portalu wymaga tylko napisania nowego adaptera (wspólny interfejs), bez zmian w reszcie systemu.
- Logi błędów dostępne z poziomu `docker compose logs`.
- UI responsywny — wygodny także na telefonie (szybkie oznaczanie „zaaplikowano” po kliknięciu w powiadomienie).

## 7. Poza zakresem (na razie)

- Automatyczne aplikowanie / wysyłanie CV.
- Wielu użytkowników i role.
- Aplikacja mobilna (natywna).

## 8. Otwarte pytania do rewizji

1. **Które portale na start?** Propozycja: justjoin.it, nofluffjobs.com, theprotocol.it, pracuj.pl, bulldogjob.pl.
   LinkedIn i Indeed aktywnie blokują scraping i mają restrykcyjne regulaminy — sugeruję pominąć je w MVP.
2. **Jakie są Twoje kryteria wyszukiwania?** (stanowisko, technologie, lokalizacja, tryb pracy, minimalne widełki) — od tego zależy, jak budujemy profile wyszukiwania.
3. **Jaki domyślny interwał skanowania?** Co 1, 2, 3 czy 6 godzin?
4. **Gdzie będzie działać aplikacja?** Tylko lokalnie (WSL / Docker Desktop) czy na serwerze / VPS? Jeśli lokalnie — skan działa tylko przy włączonym komputerze.
5. **Czy potrzebne jest logowanie** (US-G2), jeśli aplikacja będzie tylko lokalna?
6. **Czy zapisywać pełny opis ogłoszenia**, czy wystarczą metadane + link?
7. **Drugi komunikator po Discordzie** — Telegram, Slack, e-mail, coś innego?
8. **Czy potrzebujesz pełnego śledzenia etapów rekrutacji** (US-D3), czy wystarczy prosty status „zaaplikowano / nie”?
