# Translation portal

A small PHP and MySQL web app that lets several volunteers work together on the translations of a website. Strings start as AI-generated text. Contributors review them, propose better versions, add notes and vote, and the tool exports the accepted translations back into the site's translations file.

It is **language-independent**: the code is shared, and everything specific to one language or project lives in a single folder under `languages/`. Adding a language means adding a folder, not changing code.

This repository is the Irish (Gaeilge) portal for [FindARescue.ie](https://findarescue.ie), running at **translate.findarescue.ie**. The main site's code is in a separate repository: [odhranroche/findarescue.ie](https://github.com/odhranroche/findarescue.ie).

![Screenshot of the translation tool at translate.findarescue.ie](FAR_translate_homepage.png)

## What it does

- **Reads the site's strings.** It loads the site's translations file from a location set in `config.php` and syncs the keys into its own database. New keys are added, and when a key's base-language text changes the old text is kept and affected translations are tagged "Older English". Keys removed from the file are hidden, but their history stays.
- **Shows AI translations as a starting point.** Where the file already has text in the target language, that is imported as an AI entry. Where it is still a placeholder, an AI suggestion comes from the language's `ai_seed.json`.
- **Lets anyone add translations and notes.** Each string can have several translations and discussion notes from different contributors.
- **Uses voting to pick the best one.** Each translator gets one vote per translation: Accept or Reject. The accepted translation is the one with the most accepts minus rejects, as long as that is above zero, and the newest wins a tie.
- **Has two views.** "To translate" shows strings without an accepted translation, and "All text" shows everything. There is also search and a section filter.
- **Exports the result.** It builds a ready-to-paste block (for example `ga: { ... }`) containing each key's accepted translation, which is then copied into the site's translations file.
- **Lets people undo mistakes.** Deleting is a soft delete with a short Undo window, and the database records who deleted what and when.

## How it is organised

| Path | Purpose |
|---|---|
| `index.html` | The page translators use. A generic shell with no language-specific wording |
| `assets/portal.css`, `assets/portal.js` | The page's styles and behaviour, shared by every language |
| `api.php` | JSON API entry point. It only routes requests |
| `core/` | The server code: settings (`portal.php`), HTTP and input checks (`http.php`), database (`db.php`), file parsing (`source.php`), sync (`sync.php`), API actions (`actions.php`) |
| **`languages/<id>/`** | **Everything specific to one language or project: `language.json` and an optional `ai_seed.json`** |
| `languages/ga/` | The Irish portal for FindARescue.ie |
| `languages/_template/` | A starting point for a new language, plus a sample translations file |
| `languages/README.md` | Reference for every setting in `language.json` |
| `config.php` | Database login, file location and which language to serve (**not in this repo**). `config.sample.php` shows the settings |
| `schema.sql` | Creates the tables. The same for every language |
| `.htaccess` | Blocks direct access to `config.php`, `schema.sql` and this README |

One deployment serves one language, with its own database. To run several languages, deploy the same code several times (for example `translate.example.com`, `translate-cy.example.com`), each with its own `config.php` and database.

## Add another language

1. Copy `languages/_template` to `languages/<name>` (for example `languages/cy`).
2. Edit `languages/<name>/language.json`: the project and language names, the block names used in your translations file, and optionally colours and wording. See `languages/README.md`.
3. Optional: put AI suggestions for untranslated keys in `languages/<name>/ai_seed.json` as `{ "key": "text" }`.
4. Create an empty database and import `schema.sql` into it.
5. Copy `config.sample.php` to `config.php`, set `PORTAL_LANGUAGE` to `<name>`, and fill in the database login and the location of the translations file.
6. Upload everything to the subdomain. Open `api.php?action=health` to check the setup, then open the site.

The portal expects the site's translations file to look like `languages/_template/translations.sample.js`: one block per language with one `key: "text",` entry per line.

## Install the Irish portal on Hostinger

1. **Create the tables.** In hPanel → Databases → phpMyAdmin, open `u472596395_translate`, go to the **Import** tab and upload `schema.sql`. You can run it again safely. (Select the database first: the script no longer names it.)
2. **Upload the files.** In File Manager, open the folder for the `translate.findarescue.ie` subdomain and upload `index.html`, `api.php`, `config.php`, `.htaccess` and the `assets/`, `core/` and `languages/` folders.
3. **Check `config.php`.** `PORTAL_LANGUAGE` should be `ga`, and `TRANSLATIONS_SOURCE` should be the real public URL of `translations.js` (for example `https://findarescue.ie/translations.js`) or its absolute server path.
4. Open `https://translate.findarescue.ie/`. The first load reads the file and fills the database.

### Upgrading an existing install

The database does not change, so there is nothing to import. Upload the new `index.html`, `api.php`, `.htaccess`, and the `assets/`, `core/` and `languages/` folders. `config.php` keeps working as it is. Without a `PORTAL_LANGUAGE` line it serves `ga`, though adding `define('PORTAL_LANGUAGE', 'ga');` is tidier. Everyone's saved name and view carry over.

## How it behaves

(The examples use Irish. For another language, read "Irish" and `ga` as that language.)

- **To translate.** A piece of text is "to translate" until one of its translations is accepted. Either the AI translation or a human one can be accepted. The page has two views: **To translate** and **All text**. There's also a search box and a section picker.
- **Accepting.** Each translator gets one vote per translation: **Accept** or **Reject**. Clicking your current vote again removes it. Names are matched case-insensitively.
  - The accepted translation is the one with the most accepts minus rejects, as long as that's above zero. The newest one wins a tie. It's shown in green with an "Accepted" badge.
  - A translation with more rejects than accepts is shown in red, with the text struck through.
  - Hover over the buttons to see who voted.
- **Sync.** The file is re-read at most every 5 minutes, and immediately when you press the refresh button in the top bar.
  - New keys are added.
  - When a key's English changes, the old English is kept, and any translation made against the old wording is tagged "Older English".
  - Keys removed from the file are hidden, but their history stays in the database.
  - If the file can't be read, nothing is changed.
- **AI translation.**
  - Where the file already has real Irish, that Irish is used as the AI entry. All AI entries are credited to the label set in `language.json` ("Claude (AI)" for Irish).
  - Where the file's Irish is still an English placeholder (marked `// TODO (ga)` in the file), the AI entry comes from the `ai_seed` table, which is filled from `ai_seed.json` on every sync. A new placeholder key has no AI entry until you add it to `ai_seed.json` (or a row to `ai_seed`).
- **Deleting.** Anyone can delete a translation (AI or human) or a note using the bin icon, and **Undo** appears for a few seconds afterwards. Deleted items are only hidden: the database records who deleted them and when, and they no longer count towards the accepted translation or appear in Export. A deleted AI translation doesn't come back on the next sync.
- **Database upgrades.** Older databases are upgraded automatically the first time `api.php` runs. There's no SQL to run by hand.
- **Saving.** Every action saves as soon as it happens, and a short message confirms it or explains what went wrong. Half-written translations and notes survive page refreshes while the tab stays open.
- **Export** builds a `ga: { … }` block with each key's accepted translation. Keys without one keep their current value, and English placeholders keep their `// TODO (ga)` marker. You can copy the block or download it. Inline `// unused` comments are not carried over.

## Precautions included

- Prepared statements everywhere, and all text is escaped on display.
- Length limits and control-character stripping on input.
- Writes require a custom request header, and a cross-origin `Origin` header is rejected. Together these block forged posts from other sites.
- Each IP address gets at most 60 writes a minute.
- Every write is a single short statement. The sync runs in one ordered transaction behind a MySQL named lock (named after the database, so two portals on one server don't block each other), and deadlocks or lock timeouts are retried.
- The page has `noindex` set. Anyone who has the link can still contribute.

## Limits

- Left-to-right languages only for now. Right-to-left scripts would need layout changes.
- The page's own interface (buttons, hints) is in English. A language can reword any of it under `"ui"` in `language.json`, but there is no separate interface-language switch.
- The translations file must be a JavaScript object with one-line `key: "text",` entries. Other formats (JSON, PO, YAML) would need a new parser in `core/source.php`.
- The `strings` table keeps its original column names (`en_text`, `file_ga`, …) for every language, so existing databases need no migration. They hold the base and target language respectively. See the note at the top of `schema.sql`.

## Working on this repo

- **`config.php` is deliberately not committed.** It contains database credentials, and `.gitignore` excludes it. Create it on the server from `config.sample.php`, and keep a copy somewhere safe, as the repo can't recover it.
- **The database is not in git.** Translations, votes and notes live in MySQL on Hostinger. Export a dump from phpMyAdmin to back them up.
- **Where this lives on disk.** Locally this repository sits inside the main site's folder, at `findarescue.ie/public_html/translate/`, to match the server layout. The main site's repository ignores it.
