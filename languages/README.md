# Language folders

Each folder here (except `_template`) is one language/project that the portal can serve. `PORTAL_LANGUAGE` in `config.php` picks which one a deployment uses.

```
languages/
  ga/                      the Irish portal for FindARescue.ie
    language.json          settings (required)
    ai_seed.json           AI suggestions for untranslated keys (optional)
  _template/               copy this to start a new language
    language.json
    ai_seed.json
    translations.sample.js the shape of file the portal reads
```

To add a language, copy `_template` to a new folder name (letters, digits, `-` or `_`, not starting with `_`), edit `language.json`, and set `PORTAL_LANGUAGE` to that name.

## `language.json`

Only `project.name`, `language.code` and `language.name` are required. Everything else has a default, shown in the last column.

| Setting | What it does | Default |
|---|---|---|
| `project.name` | The website being translated. Shown in the title and heading | required |
| `project.url` | The website's address (kept for reference) | empty |
| `language.code` | The target language code, e.g. `ga`, `cy`, `pt-BR` | required |
| `language.name` | The language's name in English, e.g. `Irish`. Used in headings and help text | required |
| `language.native_name` | The language's own name, e.g. `Gaeilge` | `language.name` |
| `language.logo` | The 1–3 letters shown in the logo and tab icon | `language.code` |
| `base_language.code` / `.name` | The language being translated from | `en` / `English` |
| `source.file_name` | The translations file's name, shown in help text | `translations.js` |
| `source.base_block` | The name of the base-language block in the file | `base_language.code` |
| `source.target_block` | The name of the target-language block in the file. Quote it in the file if it isn't a plain word (`"pt-BR": {`) | `language.code` |
| `source.todo_marker` | The comment that marks a placeholder not yet translated | `TODO (<code>)` |
| `export.file_name` | The name of the downloaded export | `translations-<code>.js` |
| `ai.label` | The name credited on AI translations | `AI` |
| `ui_locale` | Locale for dates, e.g. `en-IE`, `cy` | `en` |
| `storage_prefix` | Prefix for what the browser remembers (name, view). Change it only if you accept that people have to re-enter their name | `tp_<id>_` |
| `theme.color` | Browser theme colour and tab icon colour | `#0b6e4f` |
| `theme.light` / `theme.dark` | CSS custom properties to override in light / dark mode (see below) | none |
| `section_strip` | Regular expressions removed from section names for display (e.g. trailing `(…)` notes) | one that removes a trailing `( … )` |
| `ui` | Overrides for the portal's own wording (see below) | none |

### Colours

`theme.light` and `theme.dark` take any of the portal's CSS custom properties, e.g.

```json
"theme": {
  "color": "#c2410c",
  "light": { "--primary": "#c2410c", "--primary-hover": "#9a3412", "--primary-soft": "#ffedd5",
             "--logo-1": "#ea580c", "--logo-2": "#c2410c", "--logo-3": "#7c2d12", "--bar-hi": "#ea580c" },
  "dark":  { "--primary": "#fb923c", "--primary-soft": "#3a2210" }
}
```

The main ones are `--primary`, `--primary-hover`, `--primary-ink`, `--primary-soft`, `--primary-line`, the logo gradient (`--logo-1`, `--logo-2`, `--logo-3`), the progress bar's start colour (`--bar-hi`), `--todo`, `--ai`, `--bg` and `--surface`. The full list is at the top of `assets/portal.css`.

### Wording (`ui`)

The portal's interface is in English. Every line can be replaced under `"ui"`. These placeholders are filled in: `{project}`, `{language}`, `{native}`, `{base}`, `{source}`, `{code}`, `{block}`. Wrap text in backticks to show it as code.

The Irish portal overrides four lines:

```json
"ui": {
  "welcomeTitleFirst": "Fáilte! Welcome",
  "namePlaceholder": "e.g. Aoife Ní Bhriain",
  "composerPlaceholder": "Scríobh an t-aistriúchán anseo…",
  "allDoneText": "Every piece of text has an accepted {language} translation. Go raibh míle maith agat!"
}
```

Available keys (defaults are in `assets/portal.js`, `DEFAULT_UI`): `pageTitle`, `brandAria`, `brandLong`, `brandShort`, `brandSub`, `resyncTitle`, `heroTitle`, `heroText`, `searchPlaceholder`, `welcomeTitleFirst`, `welcomeTitle`, `welcomeIntro`, `welcomeStep1Title`…`welcomeStep3Text`, `namePlaceholder`, `exportTitle`, `exportIntro`, `exportAria`, `bannerFetchFailed`, `bannerParseFailed`, `resyncOk`, `resyncFail`, `allDoneText`, `noTextYet`, `olderBase`, `baseChanged` (uses `{ago}`), `copyBase`, `copyBaseAria`, `baseCopied`, `composerLabel`, `composerPlaceholder`, `composerEmpty`.

## `ai_seed.json`

```json
{ "homeTitle": "Welcome in the target language", "homeIntro": "…" }
```

AI suggestions for keys whose target text in the translations file is still a placeholder. They are loaded into the database on every sync; rows already there are never overwritten. Keys without a suggestion simply have no AI entry until someone translates them. The file is optional.

## The translations file

See `_template/translations.sample.js`. The portal reads the base-language block for what needs translating (comments inside it become the section names) and the target block for what is already done.
