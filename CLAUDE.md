# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mafia Ratings** is a web platform for managing Mafia game tournaments, leagues, events, and player ratings at https://www.mafiaratings.com.

Local development URL: `http://127.0.0.1/projects/mafiaratings`

## Development Environment

- **Server:** EasyPHP DevServer 14.1 VC11 (required for local dev)
- **Database:** MySQL — no single file holds the full current schema; it's the cumulative result of `db/alter1.sql` through the latest `db/alter*.sql`. `db/create_sample_db.sql` embeds a schema snapshot, but only for building the local sample dataset, not as a source of truth.
- **PHP 7+**, **Node.js/npm** (for Angular plugin), **Java 8+** (for JS compiler)

## Build Commands

### JavaScript (Main App)
```bash
js/src/compile.bat          # Production build via Google Closure Compiler
js/src/compile-debug.bat    # Debug build
```
Edit source files in `js/src/`, run compile.bat, outputs go to `js/`.

### Angular Plugin (OBS Overlay)
From `plugins/obs/PlayersOverlayPlugin/`:
```bash
npm start              # Dev server at :4200
npm run build          # Development build
npm run build-prod     # Production build (sets base href for deployment)
npm test               # Run Karma/Jasmine tests
```

## Architecture

### Backend (PHP)
- **Root-level `*.php` files** (~202 files) are the page controllers (e.g., `club_list.php`, `tournament_view.php`).
- **`include/`** contains all reusable domain modules: `game.php`, `event.php`, `tournament.php`, `scoring.php`, `user.php`, `session.php`, `security.php`, `rules.php`, `db.php`, etc.
- **`api/get/`** — read endpoints; **`api/ops/`** — write endpoints; **`api/control/`** — admin operations. Base class in `include/api.php`.
- Database access goes through `DbQuery` class (`include/db.php`) — always use parameterized queries.
- Server environment is auto-detected in `include/server.php` (localhost = testing, otherwise production).

### Permissions
Bit-flag based, defined in `include/constants.php`. Eight levels from player to superadmin. Permission checks via `include/security.php`.

### Frontend (JavaScript)
- Vanilla JS + jQuery, compiled with Google Closure Compiler.
- Source files in `js/src/`, compiled outputs in `js/`.
- Multilingual: each module has per-language files (e.g., `game_en.js`, `game_ru.js`, `game_ua.js`). Supported languages: English, Russian, Ukrainian.

### Angular Plugin
- Located in `plugins/obs/PlayersOverlayPlugin/` — Angular 13 app for OBS streaming overlays.
- Uses OBS WebSocket JS for real-time OBS Studio integration.
- i18n via ngx-translate.

### Database Migrations
Apply migration scripts sequentially: `db/alter1.sql` through the latest `db/alter*.sql`. New migrations go in the next `db/alterNNN.sql`, etc. There is no base `db/create_tables.sql` — the schema exists only as the cumulative result of applying every `alter*.sql` in order (or as already-applied state in a live database).

**Whenever a migration changes the database structure** (new/dropped table, new/dropped/renamed column, new index, etc.), also update in the same changeset:
- `db/drop_all.sql` — add/remove the corresponding `DROP TABLE IF EXISTS` line.
- `db/create_sample_db.sql` — this file embeds its own full copy of the schema (`CREATE TABLE`, keys/indexes, `AUTO_INCREMENT`, foreign keys) plus a small sample dataset; keep it in sync so it keeps loading cleanly and matching production shape.

## Coding Standards

- Always use curly braces after `if`, `else`, `foreach`, `while`, etc. — even when the body is a single line:
```php
if ($condition)
{
    statement;
}
```

## Working with Claude Code on this repo

- **Never commit or deploy without an explicit user request for that specific changeset.** Finishing a task or the user saying "yes"/"да" to a fix does not authorize `git commit`, `git push`, or any deploy step — stop after the code change and wait for an explicit instruction (e.g. "commit", "deploy", "коммит", "деплой"). Authorization for one task does not carry over to another task, even later in the same session.
- **Never deploy `CLAUDE.md` to production** — it contains development-only instructions for Claude Code and is not part of the application; exclude it from any deployment file list.
- **Never deploy `create_sample_db.bat`** — it's local dev tooling (builds the sample database) and is not part of the deployed application; exclude it from any deployment file list, same as `CLAUDE.md` and anything under `db/`.

## Localization

All user-visible strings must be wrapped in the `get_label` function. The string argument must be in English. Translations for each language are stored in `labels.php` in the corresponding directory (e.g., alongside the PHP file that uses them). Never hardcode translated strings directly — always use `get_label`.

## Key Domain Concepts

- **Ratings:** Players start at 1000 points. Red/black rating tracks are separate. Scoring logic in `include/scoring.php` and `include/evaluator.php`.
- **Game roles:** mafia, town, don, sheriff (defined in `include/constants.php`).
- **Hierarchy:** Leagues → Tournaments → Events → Games.
- **Rules engine:** `include/rules.php` allows per-tournament customization of game rules and scoring.
- **Branding/config:** `include/branding.php` (domain, email), `include/server.php` (environment URLs).
