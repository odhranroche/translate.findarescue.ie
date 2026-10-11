# FindARescue.ie translation tool: setup

## Files

| File | Purpose |
|---|---|
| `index.html` | The page translators use |
| `api.php` | JSON API. It reads `translations.js`, syncs it into MySQL and saves every change |
| `config.php` | Database login and the location of `translations.js` |
| `schema.sql` | Creates the tables and seeds AI translations for the 28 strings that are still English placeholders |
| `.htaccess` | Blocks direct access to `config.php`, `schema.sql` and this README |

## Install on Hostinger

1. **Create the tables.** In hPanel → Databases → phpMyAdmin, open `u472596395_translate`, go to the **Import** tab and upload `schema.sql`. You can run it again safely.
2. **Upload the files.** In File Manager, create a folder such as `public_html/translate/` and upload `index.html`, `api.php`, `config.php` and `.htaccess` into it.
3. **Check `TRANSLATIONS_SOURCE` in `config.php`.** Set it to the real public URL of `translations.js` (for example `https://findarescue.ie/js/translations.js`), or to its absolute server path.
4. Open `https://findarescue.ie/translate/`. The first load reads the file and fills the database.

## How it behaves

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
  - Where the file already has real Irish, that Irish is used as the AI entry. All AI entries are credited to "Claude (AI)".
  - Where the file's Irish is still an English placeholder (marked `// TODO (ga)` in the file), the AI entry comes from the `ai_seed` table. A new placeholder key has no AI entry until you add a row to `ai_seed`.
- **Deleting.** Anyone can delete a translation (AI or human) or a note using the bin icon, and **Undo** appears for a few seconds afterwards. Deleted items are only hidden: the database records who deleted them and when, and they no longer count towards the accepted translation or appear in Export. A deleted AI translation doesn't come back on the next sync.
- **Database upgrades.** Older databases are upgraded automatically the first time `api.php` runs. It adds the columns deletion needs and relabels the imported Irish as "Claude (AI)". There's no SQL to run by hand.
- **Saving.** Every action saves as soon as it happens, and a short message confirms it or explains what went wrong. Half-written translations and notes survive page refreshes while the tab stays open.
- **Export** builds a `ga: { … }` block with each key's accepted translation. Keys without one keep their current value, and English placeholders keep their `// TODO (ga)` marker. You can copy the block or download it. Inline `// unused` comments are not carried over.

## Precautions included

- Prepared statements everywhere, and all text is escaped on display.
- Length limits and control-character stripping on input.
- Writes require a custom request header, and a cross-origin `Origin` header is rejected. Together these block forged posts from other sites.
- Each IP address gets at most 60 writes a minute.
- Every write is a single short statement. The sync runs in one ordered transaction behind a MySQL named lock, and deadlocks or lock timeouts are retried.
- The page has `noindex` set. Anyone who has the link can still contribute.
