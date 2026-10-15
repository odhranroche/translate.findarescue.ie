-- ==========================================================================
-- Translation portal: database schema (the same for every language)
-- Run once in phpMyAdmin: select the portal's (empty) database first, then
-- open the Import or SQL tab. It is safe to re-run: every statement uses
-- IF NOT EXISTS.
-- Engine: InnoDB, charset utf8mb4 (needed for accents, emoji, "…", "€").
--
-- One database per portal. It holds one language's translations, so a second
-- language needs its own database (and its own deployment).
-- ==========================================================================

SET NAMES utf8mb4;

-- One row per key in the translations file. Rows are never deleted. When a key
-- disappears from the file it is marked inactive, so its history survives.
-- Naming note: str_key / en_text / prev_en_text / en_changed_at hold the BASE
-- language (English in the shipped examples) and file_ga / file_ga_todo hold
-- the TARGET language, whichever language the portal serves. The names are
-- historical and kept so existing databases need no migration.
CREATE TABLE IF NOT EXISTS strings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  str_key        VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  section        VARCHAR(160) NULL,
  sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
  en_text        TEXT NOT NULL,
  prev_en_text   TEXT NULL,                    -- English before the last change
  en_changed_at  DATETIME NULL,                -- when English last changed (UTC)
  file_ga        TEXT NULL,                    -- current target-language value in the file
  file_ga_todo   TINYINT(1) NOT NULL DEFAULT 0,-- 1 = file's target value is a base-language placeholder
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

-- AI translations for keys whose target text in the file is still a
-- placeholder. Sync copies a row from here into `translations` the first
-- time it sees that key. It is filled from languages/<id>/ai_seed.json on
-- every sync (existing rows are never overwritten), and you can add rows by hand.
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
