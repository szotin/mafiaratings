# new_look: what to roll back before merging into master

While the `new_look` branch is current, production for it is `public_html/new_look`
(https://www.mafiaratings.com/new_look), beside the main site, which keeps running master
from `public_html/`. The two share the database and the uploaded files (`pics/`, `sounds/`).

Everything below exists only so that the branch can live in that directory. Every place in the
code is marked with a `NEW_LOOK:` comment, so `grep -rn "NEW_LOOK" --include=*.php .` finds them
all. Undo each item, then delete this file, in the last commit before the merge.

This file is development-only: never deploy it.

## Code

1. **include/branding.php** - remove `SITE_SUBDIR`; `PRODUCT_URL` and `PRODUCT_URL_HTTP` go back
   to `https://www.mafiaratings.com` and `http://www.mafiaratings.com`.
2. **include/server.php** - remove `get_site_subdir()` and `get_shared_files_prefix()`.
3. **include/constants.php** - remove the `require_once` of server.php and `SHARED_FILES_PREFIX`;
   `*_PICS_DIR`, `PHOTOS_DIR` and `SOUNDS_DIR` go back to plain `'pics/...'` and `'sounds/'`.
4. **include/session.php**
   - `initiate_session()`: remove the `$site_subdir` block (session folder `~/sessions_new_look`,
     session name `MRSESSID_new_look`, session cookie path).
   - remove `get_auth_cookie_path()`; `set_auth_key_cookie()` uses `get_cookie_path()` again.

Kept after the merge (not to roll back): the `mr_tab` cookie uses `get_cookie_path()` instead of
`/`, and album_upload.php builds the picture URL from `PHOTOS_DIR` instead of a literal `pics/`.

## Tooling

5. **.claude/commands/complete.md** - remove the new_look branch section (push to `new_look`,
   deploy to `public_html/new_look/`).
6. **CLAUDE.md** - remove the "new_look branch" section.

## On the server, after the merge is deployed to public_html

7. Delete `public_html/new_look/` (it holds its own `logs/` and `*.task.txt` files).
8. Delete `~/sessions_new_look/` (the session files of new_look, outside the web root).
9. Delete the `new_look` branch on origin once nothing is left on it.

## Things that crossed over to the main site while the branch lived

The database is shared, so every migration (`db/alter*.sql`) applied for this branch is already
in the main site's database. List each one here as it is applied, so that the merge knows which
migrations production already has:

- (none yet)

Changes made on `master` itself for the branch:

- **0611ebb1** "Link the main page to the new look of the site" - a dark bar at the top of the old
  `index.php` (marked `NEW_LOOK:`) leading to `new_look/index.php`, and its two labels
  ('Try the new look of the site', 'It is still being tested. Please tell us what is wrong with
  it.') in `include/languages/ru|ua/labels.php`. At the merge `index.php` conflicts: keep the
  new_look version, which has no bar, and let build_labels drop the two labels.
