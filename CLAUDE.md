# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Mafia Ratings** is a web platform for managing Mafia game tournaments, leagues, events, and player ratings at https://www.mafiaratings.com.

Local development URL: `http://127.0.0.1/projects/mafiaratings`

## Development Environment

- **Server:** EasyPHP DevServer 14.1 VC11 (required for local dev)
- **Database:** MySQL — schema in `db/create_tables.sql`, 180+ incremental migrations in `db/alter*.sql`
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
Apply migration scripts sequentially: `db/alter1.sql` through `db/alter180.sql`. New migrations go in `db/alter181.sql`, etc.

## Localization

All user-visible strings must be wrapped in the `get_label` function. The string argument must be in English. Translations for each language are stored in `labels.php` in the corresponding directory (e.g., alongside the PHP file that uses them). Never hardcode translated strings directly — always use `get_label`.

## Key Domain Concepts

- **Ratings:** Players start at 1000 points. Red/black rating tracks are separate. Scoring logic in `include/scoring.php` and `include/evaluator.php`.
- **Game roles:** mafia, town, don, sheriff (defined in `include/constants.php`).
- **Hierarchy:** Leagues → Tournaments → Events → Games.
- **Rules engine:** `include/rules.php` allows per-tournament customization of game rules and scoring.
- **Branding/config:** `include/branding.php` (domain, email), `include/server.php` (environment URLs).
