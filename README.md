# Bogo Extended

Rozszerzenie (add-on) do wtyczki **[Bogo](https://wordpress.org/plugins/bogo/)** – oryginalnej, lekkiej wtyczki wielojęzycznej od Rock Lobster Inc. (autor Contact Form 7).

To **nie jest fork**. Bogo zostaje nietknięte i aktualizuje się normalnie z WordPress.org. Bogo Extended dokłada brakujące funkcje wyłącznie przez hooki i API Bogo, WordPressa, ACF i Contact Form 7.

| | |
|---|---|
| Wersja | 1.4.1 |
| Wymaga | WordPress 6.7+, PHP 7.4+, Bogo 3.9+ |
| Opcjonalnie | Contact Form 7 6.x, ACF / ACF PRO 6.x, The SEO Framework |
| Licencja | GPL v2 lub nowsza |
| Text domain | `bogo-extended` (w zestawie tłumaczenie `pl_PL`) |

---

## Instalacja

1. Zainstaluj i włącz **Bogo** (z repozytorium WordPress.org).
2. Wgraj folder `bogo-extended` do `wp-content/plugins/` i włącz wtyczkę.
3. Wybierz języki w **Języki → Language Packs** (to ekran Bogo).
4. Moduły włączasz i wyłączasz w **Języki → Bogo Extended**.

Od WordPressa 6.5 nagłówek `Requires Plugins: bogo` sprawia, że WordPress nie pozwoli włączyć rozszerzenia bez Bogo.

### Pierwsza konfiguracja

W **Języki → Bogo Extended**:

1. **Tłumaczone typy treści.** Wpisy i strony są tłumaczone zawsze. Zaznacz własne typy treści (CPT), które mają dostać tłumaczenia.
2. **Moduły.** Domyślnie włączone jest wszystko poza synchronizacją kategorii do tłumaczeń. Wyłącz to, czego nie potrzebujesz.
3. **Tłumaczone bazy adresów** (opcjonalnie). Wpisz bazy adresów typów treści i taksonomii w innych językach, np. `projekte` zamiast `realizacje`.

## Tłumaczone typy treści

Bogo domyślnie tłumaczy tylko wpisy i strony. Ekran ustawień pokazuje wszystkie zarejestrowane typy treści z panelem w kokpicie. Zaznaczenie typu daje mu wszystko, co mają wpisy i strony:

- kolumnę tłumaczeń i zakładki języków na liście,
- tworzenie tłumaczeń z listy i z panelu w edytorze (w klasycznym edytorze przez okno „Language” z Bogo),
- prefiks języka w adresach (`/de/…`) oraz filtrowanie treści na froncie według języka,
- możliwość przetłumaczenia bazy adresu (moduł „Tłumaczone bazy adresów”).

Typy treści dodane do tłumaczenia kodem (filtrem `bogo_localizable_post_types` w motywie lub innej wtyczce) są pokazane jako zaznaczone i zablokowane. Po zmianie listy reguły adresów przebudowują się same.

Istniejące wpisy wybranego typu traktowane są jako treści w języku domyślnym. Tłumaczenia dodajesz od razu po zaznaczeniu.

---

## Moduły

Każdy moduł działa niezależnie i każdy można wyłączyć w ustawieniach.

### 1. Kolumna „Tłumaczenia” na listach wpisów

Na listach wpisów, stron i innych tłumaczonych typów treści każdy wiersz dostaje plakietki języków:

| Plakietka | Znaczenie | Kliknięcie |
|---|---|---|
| wypełniona | opublikowane tłumaczenie | edycja tłumaczenia |
| jasna z obramowaniem | szkic, oczekujący, zaplanowany lub prywatny | edycja tłumaczenia |
| przerywana | brak tłumaczenia | tworzy kopię roboczą i otwiera ją w nowej karcie |
| z czarną obwódką | język bieżącego wiersza | – |

Tłumaczenie powstaje przez **oficjalny endpoint REST Bogo**, więc obowiązują jego uprawnienia: rola musi móc tworzyć treści danego typu, a użytkownik musi mieć dostęp do danego języka.

Wszystkie grupy tłumaczeń widoczne na stronie listy wczytywane są **dwoma zapytaniami SQL**, a nie jednym zapytaniem na wiersz.

### 2. Widok „Tylko oryginały” i zakładki języków

Po wejściu na listę widać tylko treści w języku domyślnym, więc tłumaczenia nie zaśmiecają listy. Nad tabelą, obok zakładek statusów, są zakładki:

**Tylko oryginały | Wszystkie języki | Polski (12) | Deutsch (8) | English (3)**

- *Tylko oryginały* to widok domyślny.
- *Wszystkie języki* pokazuje każdą wersję językową.
- Zakładka języka pokazuje tylko treści w tym języku. Liczba w nawiasie liczy wszystkie statusy widoczne w „Wszystkie” (bez kosza).

Wybrany język listy nie zmienia języka panelu administracyjnego. Filtr języków z Bogo (lista rozwijana obok „Filtruj”) działa jak dotąd. Gdy widok „Tylko oryginały” jest wyłączony w ustawieniach, zostają zakładki *Wszystkie języki* i języki.

Widok nie gubi treści: wpisy, które istnieją **tylko w innym języku** (bez wersji domyślnej), są nadal pokazywane. Wyszukiwanie i Kosz zawsze pokazują wszystko.

### 3. Panel „Tłumaczenia” w edytorze blokowym

W nagłówku edytora pojawia się przypinana ikona tłumaczeń. Otwiera panel ze wszystkimi językami, w którym widać:

- status i tytuł każdego tłumaczenia,
- przyciski **Edytuj** oraz **Zobacz** albo **Podgląd**,
- przycisk **Utwórz** dla brakujących języków (kopia otwiera się w nowej karcie).

Panel korzysta z oficjalnego API `PluginSidebar`. Natywny panel „Language” z Bogo nadal działa obok.

### 4. Ukrycie menu języka w pasku administracyjnym

Bogo dodaje w górnym pasku, obok „Witaj, …”, menu z nazwą języka. Ono **nie przełącza języka witryny**, tylko język interfejsu panelu dla zalogowanego użytkownika, przez co łatwo je pomylić. Moduł usuwa to menu (domyślnie włączony). Język panelu nadal ustawisz w **Użytkownicy → Profil → Język**.

### 5. Tłumaczenia menu

**Menu klasyczne** (Wygląd → Menu). Każda pozycja ma rozwijaną sekcję *Tłumaczenia* z polami dla każdego języka pobocznego:

- **Etykieta**. Puste pole oznacza ustawienie automatyczne: tytuł przetłumaczonej strony albo przetłumaczona nazwa kategorii.
- **URL**, tylko dla własnych linków. Puste pole oznacza automatyczną lokalizację linku do własnej treści.
- **Opis**.

Na froncie w języku pobocznym:

- pozycja wskazująca stronę lub wpis prowadzi do **opublikowanego tłumaczenia** i bierze jego tytuł; działa też podświetlenie `current-menu-item`,
- własne linki do treści serwisu są lokalizowane, a linki zewnętrzne zostają bez zmian,
- pozycje kategorii używają przetłumaczonej nazwy.

W panelu „Dodaj pozycje menu” widać tylko oryginały (tłumaczenia są podstawiane automatycznie).

Mechanizm Bogo „Wyświetlaj na stronach w…” (pokazywanie lub ukrywanie pozycji per język) działa bez zmian i można go łączyć z tymi polami.

**Motywy blokowe** (blok Nawigacja). Linki do stron, wpisów i kategorii oraz własne URL-e są przepisywane na wersję w bieżącym języku. Etykieta jest tłumaczona, jeśli była równa oryginalnemu tytułowi lub nazwie.

### 6. Tłumaczenia kategorii, tagów i innych taksonomii

Na ekranie edycji terminu pojawiają się pola **Nazwa**, **Slug** i **Opis** dla każdego języka pobocznego. Taksonomie wybierasz w ustawieniach. Domyślnie są to kategorie i tagi, a dostępne są np. `product_cat` z WooCommerce.

- Puste pole oznacza, że użyty zostanie oryginał albo tłumaczenie z ekranu Bogo *Terms Translation*. Oba mechanizmy współistnieją.
- Tłumaczenie obowiązuje wszędzie, gdzie WordPress pobiera termin: tytuły archiwów, `the_category()`, listy kategorii, menu, breadcrumbs.
- Opis jest filtrowany jak natywny opis terminu. Użytkownicy bez `unfiltered_html` mogą używać tylko podstawowych tagów.

#### Tłumaczone slugi terminów

W Bogo termin jest jeden dla wszystkich języków, więc bez tego pola adres miałby polski slug z prefiksem języka (`/de/category/aktualnosci/`). Pole **Slug** w sekcji *Tłumaczenia* (Wpisy → Kategorie → edycja kategorii) ustawia slug dla danego języka:

| Język | Bez tłumaczenia sluga | Ze slugiem `neuigkeiten` | + baza z modułu 16 |
|---|---|---|---|
| PL (domyślny) | `/category/aktualnosci/` | `/category/aktualnosci/` | `/category/aktualnosci/` |
| DE | `/de/category/aktualnosci/` | `/de/category/neuigkeiten/` | `/de/kategorie/neuigkeiten/` |

- Placeholder pola pokazuje oryginalny slug. Puste pole albo wartość równa oryginałowi oznacza oryginał.
- Wpisaną wartość normalizuje `sanitize_title()` (np. `Construction Sites` → `construction-sites`), tak jak natywny slug.
- **Kategorie zagnieżdżone** dostają przetłumaczony slug na każdym poziomie: `/de/kategorie/neuigkeiten/baustellen/`. Rodzic bez tłumaczenia zostaje z oryginalnym slugiem.
- Linki z `get_term_link()` (menu, `the_category()`, widżety, sitemapy, breadcrumbs) dostają slug języka, w którym jest link. Przełącznik języków i hreflang (z `x-default`) na archiwum terminu podają adres z właściwym slugiem dla każdego języka.
- **Przekierowanie 301** ze sluga oryginalnego w danym języku: `/de/category/aktualnosci/` → `/de/category/neuigkeiten/`, z zachowaniem paginacji i parametrów. Feed (`/de/category/neuigkeiten/feed/`) działa.
- Slug przetłumaczony działa tylko w swoim języku. `/category/neuigkeiten/` zwraca 404, a w języku bez tłumaczenia obowiązuje oryginał.
- **Walidacja przy zapisie** odrzuca slug używany w tej samej taksonomii w tym języku przez inny termin, zarówno jego tłumaczenie, jak i oryginalny slug innego terminu bez tłumaczenia. Pole wraca wtedy do poprzedniej wartości, nad formularzem pojawia się komunikat, a reszta tłumaczeń się zapisuje.
- Działa dla każdej taksonomii z listy w ustawieniach. Wymaga „ładnych” bezpośrednich odnośników. Przy `?cat=12` slug nie występuje w adresie.

### 7. Synchronizacja kategorii i tagów (domyślnie wyłączona)

Gdy zmienisz kategorie lub tagi wpisu w języku domyślnym, te same terminy trafiają do jego tłumaczeń. Listę taksonomii można zawęzić filtrem `bogoext_sync_taxonomies`.

### 8. Automatyczna lokalizacja linków w treści

Na stronach w języku pobocznym linki w treści (`the_content`, widgety tekstowe i blokowe) prowadzące do wpisów lub stron **w języku domyślnym** są zamieniane na adres opublikowanego tłumaczenia. Parametry `?query` i `#kotwica` są zachowywane.

Nie są zmieniane:

- linki zewnętrzne, `mailto:`, `tel:`, kotwice `#…`, pliki i adresy `wp-admin`, `wp-json` oraz `feed`,
- linki, które **celowo** wskazują inny język, np. „Przeczytaj po niemiecku”,
- linki z `class="no-localize"`, `data-bogo-keep` albo `hreflang`,
- linki do treści, która nie ma opublikowanego tłumaczenia.

Link do strony głównej prowadzi do strony głównej w bieżącym języku. W oknie wstawiania linku widać tylko oryginały.

Parsowanie odbywa się przez `WP_HTML_Tag_Processor` (parser HTML WordPressa), a nie przez wyrażenia regularne. Wyniki są cache'owane w obrębie żądania i w object cache.

### 9. Integracja z ACF

Na stronach w języku pobocznym:

| Typ pola | Zachowanie |
|---|---|
| Post Object (pojedyncze i wielokrotne, zwracające obiekt lub ID) | zwraca tłumaczenie wybranego wpisu |
| Relationship | jak wyżej, dla każdej pozycji |
| Page Link | zwraca URL tłumaczenia |
| Link | URL zlokalizowany. Tytuł zamieniany na przetłumaczony tylko wtedy, gdy był równy tytułowi wpisu |

W panelu selektory wpisów ACF pokazują tylko oryginały, bo tłumaczenie jest podstawiane automatycznie. Można to wyłączyć filtrem `bogoext_acf_hide_translations`.

### 10. Tłumaczone wzorce synchronizowane (reusable blocks)

Wzorce (`wp_block`) stają się tłumaczalne jak strony: mają przycisk „Dodaj tłumaczenie”, plakietki i panel w edytorze. W treści nadal wstawiasz wzorzec w języku domyślnym, a na stronie w innym języku renderuje się jego **opublikowane tłumaczenie**. W inserterze edytora widać tylko wzorce w języku domyślnym.

### 11. Przełącznik języków `[bogo-dropdown]`

```text
[bogo-dropdown]                          rozwijane menu (hover / klik)
[bogo-dropdown style="toggle"]           rozwija się w miejscu (menu mobilne)
[bogo-dropdown style="list"]             zwykła lista <ul> z linkami, bez stylów i skryptów
[bogo-dropdown compact="1"]              krótkie kody: PL, DE, EN
[bogo-dropdown flags="1"]                flagi (emoji Bogo) przed nazwą
[bogo-dropdown hide_missing="0"]         pokazuje też języki bez tłumaczenia (jako tekst)
[bogo-dropdown class="moja-klasa"]       dodatkowa klasa CSS
```

W szablonie PHP:

```php
<?php echo do_shortcode( '[bogo-dropdown style="toggle" compact="1"]' ); ?>
```

Szczegóły działania:

- Pokazywane są tylko języki, do których jest dokąd przejść: opublikowane tłumaczenie albo archiwum. Przełącznik nie pojawia się, jeśli jest mniej niż 2 języki.
- Przycisk jest prawdziwym `<button>` z `aria-expanded`. Działa z klawiatury, zamyka się klawiszem Esc i kliknięciem poza menu.
- Przy 5+ i 9+ językach lista układa się w 2 lub 3 kolumny.
- Wygląd zmienisz zmiennymi CSS: `--bogoext-bg`, `--bogoext-fg`, `--bogoext-border`, `--bogoext-hover`, `--bogoext-radius`.
- CSS i JS ładują się tylko na stronach, na których jest shortcode.

#### Prosta lista (`style="list"`)

Wariant bez jakiegokolwiek stylowania: wtyczka nie ładuje swojego CSS ani JS, nie ma przycisku ani kontenera `<div>`, tylko czysty HTML:

```html
<ul class="bogoext-list">
  <li class="is-current"><a href="…" hreflang="pl" lang="pl" aria-current="page">Polski</a></li>
  <li><a href="…" hreflang="en" lang="en">English</a></li>
</ul>
```

Działają opcje `compact`, `flags`, `hide_missing` i `class`. Klasa `bogoext-list` i `is-current` są tylko zaczepami dla motywu – wtyczka nie przypisuje do nich żadnych reguł.

Natywny blok i widget przełącznika z Bogo działają bez zmian.

### 12. Tłumaczenia formularzy Contact Form 7

Każdy język ma **własny, pełny formularz CF7**: pola, etykiety, komunikaty walidacji, treść e-maili i ustawienia dodatkowe. Formularze są połączone w grupę, a na stronie wstawiasz **jeden shortcode lub blok**. Odwiedzający zobaczy wersję w swoim języku.

Jak przetłumaczyć formularz:

1. Otwórz formularz w **Kontakt → Formularze**. W polu *Status* (z prawej) jest sekcja **Język formularza / Tłumaczenia**.
2. Ustaw *Język formularza*, jeśli różni się od domyślnego.
3. Kliknij przerywaną plakietkę języka, np. **DE**. Powstanie kopia formularza „Tytuł [DE]” w tym języku i od razu otworzy się do edycji.
4. Przetłumacz pola, komunikaty i treść maili, a potem zapisz.
5. W treści strony zostaw shortcode oryginału, np. `[contact-form-7 id="7dc7d64" title="Formularz PL"]`. Na `/de/...` wyświetli się formularz DE.

Dodatkowo:

- Istniejący formularz możesz dołączyć do grupy ręcznie w polu **Tłumaczenie formularza** (gdy formularze tworzyłeś wcześniej osobno).
- Na liście formularzy jest kolumna **Języki** z plakietkami całej grupy.
- Wysyłka działa normalnie: CF7 obsługuje przetłumaczony formularz z jego własnymi mailami i komunikatami, a jego wbudowane komunikaty przełączają się na język formularza.
- Brak tłumaczenia oznacza, że wyświetla się oryginał.
- Usunięcie oryginału nie rozbija grupy: najstarsze tłumaczenie zostaje nowym oryginałem.
- Podmiana działa w shortcode, w bloku CF7, w page builderach i w `do_shortcode()`, bo wpięta jest w `shortcode_atts_wpcf7`.

### 13. Bezpieczne „Dodaj tłumaczenie”

Bogo przy kopiowaniu wpisu traci **wszystkie ukośniki wsteczne** (`\`). Atrybuty bloków typu `"\u003c"` zamieniają się w `"u003c"`, a `\"` w `"`, co psuje JSON bloku. Uszkadzane są też dane JSON w polach meta (Elementor, bloki ACF, page buildery). Moduł naprawia to przy każdym kopiowaniu, także przy kopiowaniu przez Bogo, i nie przenosi na tłumaczenie meta `_edit_lock`, `_edit_last`, `_wp_old_slug` i podobnych. Listę rozszerzysz filtrem `bogoext_duplicate_excluded_meta`.

> Sprawdzone na żywym WordPressie 7.1.3 z Bogo 3.9.3. Bez modułu kopia bloku `{"placeholder":"A\u003cB \"q\""}` staje się `{"placeholder":"Au003cB "q""}` (blok uszkodzony). Z modułem kopia jest identyczna z oryginałem.

### 14. Poprawki hreflang

- W `<link rel="alternate" hreflang>` są tylko **opublikowane** tłumaczenia. Bogo wypisuje też szkice i treści prywatne, co ujawnia ich adresy.
- Dodaje `hreflang="x-default"` wskazujący wersję w języku domyślnym.
- Pomija hreflang na stronach 404, wyszukiwania i podglądu oraz przy treści istniejącej tylko w jednym języku.
- Na stronach 404 i wyszukiwania przełącznik języków prowadzi na stronę główną danego języka.

### 15. The SEO Framework – przetłumaczona strona główna

Na przetłumaczonej statycznej stronie głównej TSF bierze tytuł i opis z ustawień globalnych, czyli w języku domyślnym. Moduł podstawia tytuł i opis SEO ustawione na przetłumaczonej stronie. Działa tylko przy aktywnym TSF.

### 16. Tłumaczone bazy adresów (typy treści i taksonomie)

Bogo tłumaczy slugi pojedynczych wpisów, bo każde tłumaczenie jest osobnym wpisem z własnym slugiem. **Bazę** adresu zostawia jednak w języku domyślnym. Ten moduł pozwala ją przetłumaczyć:

| Język | Bez modułu | Z modułem |
|---|---|---|
| PL (domyślny) | `/realizacje/projekt-x/` | `/realizacje/projekt-x/` |
| DE | `/de/realizacje/projekt-x/` | `/de/projekte/projekt-x/` |
| DE, archiwum | `/de/realizacje/` | `/de/projekte/` |
| DE, kategoria | `/de/category/aktualnosci/` | `/de/kategorie/aktualnosci/` (slug terminu: moduł 6) |

**Konfiguracja.** W **Języki → Bogo Extended → Tłumaczone bazy adresów** jest tabela z wierszem dla każdego tłumaczonego typu treści i taksonomii oraz kolumną dla każdego języka. Puste pole oznacza oryginalną bazę. Gdy archiwum typu treści ma inny slug niż wpisy (`has_archive => 'nasza-oferta'`), dostaje osobny wiersz.

**Co obejmuje:**
- wpisy, archiwa (z paginacją i feedem) i archiwa taksonomii pod nowym adresem,
- linki z `get_permalink()`, `get_post_type_archive_link()` i `get_term_link()`, a więc także menu, przełącznik języków, hreflang, sitemapy i linki w motywie,
- **przekierowanie 301** ze starego adresu (`/de/realizacje/x/` → `/de/projekte/x/`), z zachowaniem parametrów `?utm=…`,
- na stronach archiwów przełącznik i hreflang podają bazę właściwą dla każdego języka (Bogo zostawiłoby bazę bieżącego języka).

**Walidacja przy zapisie** odrzuca słowa zarezerwowane (`page`, `feed`, `wp-json`, kody języków…), bazę zajętą przez stronę w danym języku oraz bazę użytą dwa razy w tym samym języku. Błędne pole wraca do poprzedniej wartości, a reszta się zapisuje.

**Wydajność** (zmierzone na testowej instalacji: 2 typy treści, 3 taksonomie, 3 języki):
- **Reguły adresów.** Moduł dodał 75 reguł (było 281, jest 356). Dopasowanie adresu strony trwa dłużej o około 0,03–0,04 ms na żądanie.
- **Linki.** Podmiana bazy kosztuje około 1,4 µs na link i nie wykonuje żadnych zapytań do bazy. 100 linków na stronie to około 0,15 ms.
- **Przebudowa reguł** odbywa się tylko po zmianie baz lub włączeniu albo wyłączeniu modułu. WordPress buduje je wtedy na nowo przy następnym wejściu, a nigdy przy zwykłym ruchu.

Moduł nie działa przy „prostych” bezpośrednich odnośnikach (`?p=123`).

### 17. Tłumaczenie fraz motywu (Języki → Tłumaczenie fraz)

Do tłumaczenia tekstów zapisanych w kodzie motywu: przycisków, etykiet, stopki, komunikatów, czyli wszystkiego, co motyw wypisuje przez `__()`, `_e()`, `esc_html__()`, `_x()`, `_n()` itd. Ekran jest w menu **Języki → Tłumaczenie fraz**.

**Skanowanie.** Przy pierwszym wejściu na ekran moduł skanuje pliki PHP motywu głównego i potomnego. Wynik zapisuje w bazie, więc kolejne wejścia nie skanują. Skan powtarza się sam po zmianie motywu lub jego wersji (np. po aktualizacji), a ręcznie uruchomisz go przyciskiem **Skanuj motyw ponownie**.
- Skaner używa tokenizera PHP, a nie wyrażeń regularnych. Poprawnie czyta frazy z przecinkami, cudzysłowami i łączone kropką (`'a' . 'b'`), a pomija wywołania metod (`$obj->__()`).
- Zapisuje kontekst (`_x`), liczbę mnogą (`_n`), komentarze dla tłumaczy (`/* translators: … */`) i miejsca użycia (`plik.php:linia`).
- Pomija katalogi `vendor`, `node_modules`, `tests` oraz pliki większe niż 1 MB.
- Testowy motyw (twentytwentyone, 197 wywołań) skanuje się w około 0,4 s.

**Tabela.** Jest stronicowana (liczbę wierszy ustawisz w „Opcjach ekranu”) i ma:
- wybór **języka** (wszystkie języki Bogo, także domyślny, gdy motyw jest napisany po angielsku, a strona jest po polsku) i **domeny tekstowej** (domyślnie domena motywu; widać też inne domeny użyte w plikach motywu),
- wyszukiwarkę oraz zakładki **Wszystkie / Nieprzetłumaczone / Przetłumaczone** z licznikami,
- pola formy liczby mnogiej zgodne z regułami języka (dla polskiego 3 formy, dla niemieckiego 2),
- jako podpowiedź w pustym polu obecne tłumaczenie z motywu lub paczki językowej. Puste pole zostawia to tłumaczenie.

**Zabezpieczenie przed błędem PHP.** Tłumaczenie, które wymaga więcej argumentów (`%s`, `%d`, `%2$s`) niż oryginał, jest odrzucane z komunikatem. W PHP 8 taki tekst w `printf()` powoduje fatal error na stronie.

**Pliki.** Po zapisaniu powstają standardowe pliki gettext:

```text
wp-content/languages/bogo-extended/{domena}-{locale}.po         do edycji np. w Poedit
wp-content/languages/bogo-extended/{domena}-{locale}.mo
wp-content/languages/bogo-extended/{domena}-{locale}.l10n.php   szybki format PHP (WordPress 6.5+)
```

**Jak WordPress ich używa:**
- Na froncie nie ma żadnych filtrów `gettext`. WordPress wczytuje te pliki tak samo jak każde inne tłumaczenie, przez `load_textdomain()`, i woli `.l10n.php`, który jest cache'owany przez OPcache.
- Rozszerzenie dba tylko o **kolejność**: plik z frazami ładuje się **przed** plikiem motywu lub paczki językowej. WordPress bierze tłumaczenie z pierwszego pliku, który je zawiera. Twoje frazy wygrywają, a pozostałe teksty nadal pochodzą z oryginalnych, aktualnych tłumaczeń motywu.
- Działa to także dla motywów, które nie mają żadnych plików tłumaczeń ani nie wywołują `load_theme_textdomain()`.
- Osobny folder `bogo-extended` (zamiast `languages/themes`) jest celowy, bo aktualizacje paczek językowych nadpisują pliki w `languages/themes`.
- Źródłem prawdy jest baza danych. Gdyby pliki zniknęły (np. po wdrożeniu), wystarczy zapisać dowolną stronę tabeli, a pliki powstaną od nowa.

**Ograniczenia:**
- Skanowane są pliki PHP motywu. Frazy z JavaScriptu (`wp.i18n`) i z `theme.json` wymagają innego formatu (JSON) i nie są obsługiwane.
- Frazy budowane dynamicznie, np. `__( $zmienna )`, nie dają się wykryć statycznie.
- Do tłumaczenia **treści** (wpisów, stron, menu) służą pozostałe moduły. Ten służy tylko do tekstów z kodu.

---

## Uprawnienia

| Akcja | Wymagane uprawnienie |
|---|---|
| Tworzenie tłumaczenia wpisu | endpoint Bogo: `create_posts` dla typu, `edit_post` (lub opublikowany oryginał) oraz dostęp do języka (`bogo_access_locale`) |
| Panel w edytorze (odczyt) | `edit_post` dla edytowanego wpisu |
| Tłumaczenia terminów | `edit_term` + nonce |
| Tłumaczenia menu | `edit_theme_options` + nonce ekranu menu |
| Tłumaczenia CF7 | `wpcf7_edit_contact_form` + nonce |
| Ustawienia | `manage_options` + nonce |
| Tłumaczenie fraz | `manage_options` (filtr `bogoext_strings_capability`) + nonce |

Wszystkie dane z formularzy są sanityzowane, a wszystkie dane wyjściowe escapowane.

---

## Dane w bazie

| Klucz | Gdzie | Zawartość |
|---|---|---|
| `_locale`, `_original_post` | postmeta | dane Bogo (rozszerzenie ich nie zmienia) |
| `_bogoext_i18n` | termmeta | `[ locale => [ name, description ] ]` |
| `_bogoext_slug_{locale}` | termmeta | przetłumaczony slug terminu (np. `_bogoext_slug_de_DE`) |
| `_bogoext_i18n` | postmeta pozycji menu | `[ locale => [ title, description, url ] ]` |
| `_bogoext_cf7_source` | postmeta formularza CF7 | ID formularza źródłowego grupy |
| `_locale` | postmeta formularza CF7 | język formularza (natywne pole CF7) |
| `bogoext_settings` | options | ustawienia modułów, lista tłumaczonych typów treści (`post_types`), taksonomie z polami tłumaczeń |
| `bogoext_slugs` | options | przetłumaczone bazy adresów: `[ pt / tax => [ nazwa => [ locale => [ base, archive ] ] ] ]` |
| `bogoext_st_index` | options (bez autoload) | wynik skanowania motywu: frazy, domeny, miejsca użycia |
| `bogoext_st_tr_{locale}` | options (bez autoload) | tłumaczenia fraz danego języka |
| `bogoext_st_compiled` | options | lista wygenerowanych plików (domena → języki) |
| `wp-content/languages/bogo-extended/` | pliki | wygenerowane `.po`, `.mo`, `.l10n.php` |

Klucze z podkreślnikiem są ukryte w polach własnych. Odinstalowanie usuwa tylko ustawienia, a tłumaczenia (treść, frazy i ich pliki) zostają.

---

## Dla programistów

### Funkcje

```php
// ID tłumaczenia wpisu (0 gdy brak); domyślnie tylko opublikowane
bogoext_get_translation_id( $post_id, 'de_DE', $published_only = true );

// Cała grupa: [ locale => [ id, status, type ] ] (z cache żądania)
BogoExt_Translations::get_group( $post_id );
BogoExt_Translations::prime( $post_ids ); // wczytanie wielu grup naraz

// Adres w bieżącym (lub podanym) języku
bogoext_localize_url( 'https://example.com/kontakt/', 'de_DE' );

// Tłumaczenie terminu (pusty string gdy brak)
bogoext_term_field( $term_id, 'de_DE', 'name' );

// Przetłumaczony slug terminu ('' gdy brak) i termin po przetłumaczonym slugu
bogoext_term_slug( $term_id, 'de_DE' );                              // 'neuigkeiten'
bogoext_term_by_translated_slug( 'category', 'de_DE', 'neuigkeiten' ); // WP_Term|null

// Formularze CF7: [ locale => form_id ]
bogoext_cf7_group( $form_id );

// Przetłumaczona baza adresu ('' gdy brak) i poprawienie bazy w dowolnym URL-u
bogoext_slugs_get( 'pt', 'realizacje', 'de_DE' );          // 'projekte'
bogoext_slugs_normalize_url( 'https://example.com/de/realizacje/x/' ); // …/de/projekte/x/
```

Funkcje Bogo, które warto znać: `bogo_get_post_translations()`, `bogo_get_post_translation()`, `bogo_get_post_locale()`, `bogo_language_switcher_links()`, `bogo_url()`.

### Filtry

| Filtr | Opis |
|---|---|
| `bogoext_module_enabled( $enabled, $key )` | wymuszenie włączenia lub wyłączenia modułu z kodu |
| `bogoext_settings( $settings )` | modyfikacja ustawień |
| `bogoext_localizable_taxonomies( $list )` | taksonomie z polami tłumaczeń |
| `bogoext_sync_taxonomies( $list, $post )` | taksonomie synchronizowane do tłumaczeń |
| `bogoext_duplicate_excluded_meta( $keys, $post, $locale )` | meta niekopiowane przy tworzeniu tłumaczenia |
| `bogoext_hide_translations_in_link_picker( true )` | `false` = okno linku pokazuje też tłumaczenia |
| `bogoext_acf_hide_translations( true, $field )` | `false` = selektory ACF pokazują też tłumaczenia |
| `bogoext_hreflang( $languages )` | końcowa lista hreflang |
| `bogoext_switcher_links( $links )` | dane przełącznika `[bogo-dropdown]` |
| `bogoext_dropdown_html( $html, $links, $atts )` | HTML przełącznika |
| `bogoext_cf7_locale( $locale )` | język używany do wyboru formularza CF7 |
| `bogoext_strings_capability( 'manage_options' )` | uprawnienie do ekranu „Tłumaczenie fraz” |

Akcja `bogoext_loaded` jest wywoływana po załadowaniu modułów.

Typy treści można też włączyć kodem, filtrem Bogo. Tak włączone typy są widoczne w ustawieniach jako zablokowane:

```php
add_filter( 'bogo_localizable_post_types', function ( $types ) {
	$types[] = 'product';
	return $types;
} );
```

---

## Ograniczenia i uwagi

- Bogo trzyma tłumaczenia jako osobne wpisy (`_locale`, `_original_post`), a nie w osobnych tabelach. Rozszerzenie zachowuje ten model, więc po wyłączeniu wszystko nadal działa w czystym Bogo (bez dodatkowych funkcji).
- Automatyczna lokalizacja linków obejmuje wpisy, strony i stronę główną. Linki do archiwów kategorii w treści nie są przepisywane (w menu są).
- Termin jest w Bogo jeden dla wszystkich języków, więc przetłumaczony slug jest aliasem w termmeta, a nie osobnym terminem. Natywne pole „Slug” WordPressa zawsze pokazuje slug oryginalny.
- Linki do kategorii wpisane ręcznie w treść (`/de/category/aktualnosci/`) nie są przepisywane, ale przekierowują 301 na właściwy adres.
- Wspólny przedrostek struktury bezpośrednich odnośników (np. `/blog/%postname%/`) nie jest tłumaczony.
- Po zmianie bazy na działającej stronie wyczyść cache strony (LiteSpeed, WP Rocket itp.). Stare adresy przekierowują same.
- Formularz CF7 ma jeden język. Tłumaczenie to osobny formularz, więc zmiany pól trzeba nanieść w każdej wersji.
- Moduły ACF, CF7 i TSF są bezczynne, gdy dana wtyczka nie jest aktywna.

---

## Struktura plików

```text
bogo-extended/
├── bogo-extended.php            bootstrap, sprawdzenie obecności Bogo
├── uninstall.php
├── includes/
│   ├── settings.php             ustawienia (ładowane najwcześniej)
│   ├── functions.php            funkcje pomocnicze
│   ├── class-translations.php   grupy tłumaczeń (batch SQL + cache żądania)
│   ├── class-url-resolver.php   URL → wpis → URL tłumaczenia
│   ├── slugs-data.php           dane i walidacja tłumaczonych baz adresów
│   ├── admin/                   ekran ustawień
│   ├── strings/                 tłumaczenie fraz: skaner, magazyn i kompilacja plików, ekran
│   └── modules/                 jeden plik = jeden moduł
├── assets/                      css, js (bez kroku budowania)
└── languages/                   .pot + pl_PL
```

Nie ma kroku budowania (webpack, npm). JS to czysty ES2017 korzystający z globalnych pakietów `wp.*` WordPressa.

---

## Historia zmian

**1.4.1**
- `[bogo-dropdown style="list"]` – prosta lista linków bez stylów i skryptów wtyczki.

**1.4.0**
- Tłumaczone slugi kategorii, tagów i terminów innych taksonomii: pole „Slug” dla każdego języka, linki, przełącznik i hreflang ze slugiem języka, 301 ze sluga oryginalnego, obsługa kategorii zagnieżdżonych, walidacja kolizji.

**1.3.0**
- Nowy moduł „Tłumaczenie fraz”: skan motywu głównego i potomnego, stronicowana tabela tłumaczeń, zapis do natywnych plików `.po` / `.mo` / `.l10n.php` ładowanych przed tłumaczeniami motywu, walidacja symboli zastępczych.

**1.2.0**
- Wybór tłumaczonych typów treści (CPT) w ustawieniach, bez pisania kodu.
- Zakładki języków z licznikami na listach wpisów, obok „Tylko oryginały” i „Wszystkie języki”.
- Nowy moduł: ukrycie menu języka Bogo w pasku administracyjnym (domyślnie włączony).

**1.1.0**
- Nowy moduł: tłumaczone bazy adresów typów treści i taksonomii, z przekierowaniami 301, walidacją kolizji i poprawnym przełącznikiem oraz hreflang na archiwach.
- Ekran ustawień ładuje własny arkusz stylów.

**1.0.0**
- Pierwsze wydanie.
