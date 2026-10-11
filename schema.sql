-- ==========================================================================
-- FindARescue.ie translation tool: database schema
-- Run once in phpMyAdmin (Hostinger → Databases → phpMyAdmin → select
-- u472596395_translate → Import or SQL tab). It is safe to re-run:
-- every statement uses IF NOT EXISTS / INSERT IGNORE.
-- Engine: InnoDB, charset utf8mb4 (needed for fadas, emoji, "…", "€").
-- ==========================================================================

SET NAMES utf8mb4;
USE u472596395_translate;

-- One row per key in translations.js. Rows are never deleted. When a key
-- disappears from the file it is marked inactive, so its history survives.
CREATE TABLE IF NOT EXISTS strings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  str_key        VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  section        VARCHAR(160) NULL,
  sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
  en_text        TEXT NOT NULL,
  prev_en_text   TEXT NULL,                    -- English before the last change
  en_changed_at  DATETIME NULL,                -- when English last changed (UTC)
  file_ga        TEXT NULL,                    -- current "ga" value in the file
  file_ga_todo   TINYINT(1) NOT NULL DEFAULT 0,-- 1 = file's ga is an English placeholder
  active         TINYINT(1) NOT NULL DEFAULT 1,-- 0 = key removed from the file
  seen_sync      INT UNSIGNED NOT NULL DEFAULT 0,
  first_seen     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_strings_key (str_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI and human translations. At most one 'ai' row per string; the API
-- enforces this with INSERT … WHERE NOT EXISTS under the sync lock.
CREATE TABLE IF NOT EXISTS translations (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  string_id    INT UNSIGNED NOT NULL,
  source       ENUM('ai','human') NOT NULL,
  text         TEXT NOT NULL,
  translator   VARCHAR(80) NOT NULL,
  note         TEXT NULL,
  en_snapshot  TEXT NOT NULL,                  -- English at the time of translating
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at   DATETIME NULL,                  -- set when deleted (kept so it can be restored)
  deleted_by   VARCHAR(80) NULL,
  PRIMARY KEY (id),
  KEY ix_tr_string (string_id, source, created_at),
  CONSTRAINT fk_tr_string FOREIGN KEY (string_id) REFERENCES strings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One vote per translator per translation: +1 accept, -1 reject.
-- Clicking the same button again deletes the row.
CREATE TABLE IF NOT EXISTS votes (
  translation_id INT UNSIGNED NOT NULL,
  voter          VARCHAR(80) NOT NULL,         -- compared case-insensitively
  vote           TINYINT NOT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (translation_id, voter),
  CONSTRAINT fk_vote_tr FOREIGN KEY (translation_id) REFERENCES translations(id),
  CONSTRAINT ck_vote CHECK (vote IN (-1, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free-standing notes on a string (notes typed alongside a translation are
-- stored on the translation itself).
CREATE TABLE IF NOT EXISTS notes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  string_id   INT UNSIGNED NOT NULL,
  author      VARCHAR(80) NOT NULL,
  text        TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  deleted_by  VARCHAR(80) NULL,
  PRIMARY KEY (id),
  KEY ix_notes_string (string_id, created_at),
  CONSTRAINT fk_note_string FOREIGN KEY (string_id) REFERENCES strings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI translations for keys whose Irish in the file is still an English
-- placeholder. Sync copies a row from here into `translations` the first
-- time it sees that key. Add rows here to seed AI text for future TODO keys.
CREATE TABLE IF NOT EXISTS ai_seed (
  str_key  VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  text     TEXT NOT NULL,
  PRIMARY KEY (str_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Small key/value store (last sync time, sync counter).
CREATE TABLE IF NOT EXISTS meta (
  k  VARCHAR(40) NOT NULL,
  v  VARCHAR(255) NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-IP write throttle (fixed one-minute window).
CREATE TABLE IF NOT EXISTS rate_limits (
  ip            VARBINARY(16) NOT NULL,
  window_start  INT UNSIGNED NOT NULL,
  hits          INT UNSIGNED NOT NULL,
  PRIMARY KEY (ip)
) ENGINE=InnoDB;

INSERT IGNORE INTO ai_seed (str_key, text) VALUES
('locationClearAll',  'Glan gach rud'),
('locationDone',      'Déanta'),
('locationRemove',    'Bain'),
('linkUnavailable',   'níl sé ar fáil'),
('skipToResults',     'Léim chuig na torthaí'),
('skipToContent',     'Léim chuig an ábhar'),
('heroTitle',         'Aimsigh eagraíocht tarrthála ainmhithe in aice leat'),
('heroTrust',         'Ní dhéantar grinnfhiosrú ar na liostaí ná ní fhormhuinítear iad.'),
('searchClear',       'Glan an cuardach'),
('categoriesLabel',   'Cineál ainmhí'),
('resultsCount',      '{n} eagraíocht'),
('resultsCountOne',   '1 eagraíocht'),
('clearFilters',      'Glan na scagairí'),
('noResultsTitle',    'Níl aon tarrthálacha ag teacht le do chuardach'),
('noResultsHint',     'Bain triail as focal nó contae eile, nó glan na scagairí chun gach eagraíocht a fheiceáil.'),
('charityBadgeLabel', 'Carthanas cláraithe'),
('inactiveLabel',     'B''fhéidir nach bhfuil sé gníomhach'),
('closeLabel',        'Dún'),
('footerTagline',     'Eolaire saor in aisce d''eagraíochtaí tarrthála ainmhithe sna 32 contae, á reáchtáil ag oibrithe deonacha.'),
('footerValues',      'Gan fógraí · Gan urraíocht · Ní dhíoltar sonraí'),
('footerExplore',     'Taiscéal'),
('footerContact',     'Teagmháil'),
('mapTitle',          'Léarscáil tarrthála'),
('mapBrowseList',     'Brabhsáil mar liosta'),
('mapLoading',        'Léarscáil á lódáil…'),
('mapDismiss',        'Dún'),
('aboutBmcButton',    'Ceannaigh caife dom'),
('aboutRaised',       'bailithe');
