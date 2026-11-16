Commit, push, and deploy the current changeset. Steps:

0. Run `git branch --show-current`. The rest depends on it - see **Branch new_look** below. On any other branch than `master` or `new_look`, stop and ask where to push and deploy.
1. Run `git status` and `git diff` to see what will be committed.
2. Stage every modified/added file (skip files that clearly contain secrets, e.g. `.env`, and skip database dumps like `db/mafiawor_*.sql`). If new `db/alter*.sql` migrations exist, stage them but do NOT try to FTP them (SQL migrations are applied to the DB manually — remind the user at the end if any are staged).
3. Draft a commit message from the actual diff (not from earlier conversation summaries). Follow the repository's existing commit-message style — short imperative subject line, blank line, then a wrapped body listing the concrete changes.
4. Create the commit with the drafted message and a HEREDOC, then `git push origin <current branch>`.
5. Deploy every staged non-SQL file via FTP to `public_html/` (on `new_look`: to `public_html/new_look/`), preserving the file's relative path. Use the FTP host/user/password stored in the auto-memory (project_ftp_deploy). Do NOT deploy `CLAUDE.md` (see project_deployment memory), do NOT try to deploy files under `db/`, do NOT deploy `create_sample_db.bat` (local dev tooling only, not part of the deployed application), and do NOT deploy `_new_look_rollback.md`. If anything under `js/src/` changed, build it and deploy the built `js/<name>.js` too.
6. Report: commit hash, branch, files pushed, files deployed (and where), plus any manual follow-up (SQL migrations to run) at the end.

Do not ask for confirmation — invoking this command IS the authorization for this changeset.

## Branch new_look

<!-- NEW_LOOK: remove this section before merging new_look into master (see _new_look_rollback.md). -->

While `new_look` is the current branch, its production is `public_html/new_look/` (https://www.mafiaratings.com/new_look). The main site in `public_html/` runs `master` and must not be touched from this branch.

- Push to `origin new_look`, never to `master`.
- Deploy to `public_html/new_look/<relative path>` only. Never write anything to `public_html/` outside `new_look/`.
- The database is shared with the main site. A staged migration changes the main site's database too: say so in the report, check it does not break the master code still running there, and once the user has applied it, add it to the list at the end of `_new_look_rollback.md`.
- If the changeset adds anything that exists only so that the branch can live in `new_look` (a path, a cookie, a define), mark it with a `NEW_LOOK:` comment and add it to `_new_look_rollback.md` in the same commit.
- `_new_look_rollback.md` is committed with the rest whenever it changed, but never deployed.
- **First deploy** - if `public_html/new_look/` does not exist yet (or lacks `index.php`), deploy the whole site rather than the changeset:
  - every file `git ls-files` lists at HEAD, except `db/`, `standalone/`, `docs/`, `bak/`, `mockups/`, `.claude/`, `CLAUDE.md`, `create_sample_db.bat`, `_new_look_rollback.md`, `*.bat`;
  - the built scripts: run `js/src/compile.bat` first, then upload every `js/*.js` (they are gitignored);
  - copied from the main site, server to server (download from `public_html/...`, upload to `public_html/new_look/...`), because they are not in git: `include/api_keys.php`, `form/.user.ini`, and the font caches `include/pdf/font/unifont/*.mtx.php`, `*.cw127.php`, `*.cw.dat`;
  - create the empty directory `public_html/new_look/logs/`;
  - never copy `pics/` or `sounds/`: new_look uses the main site's (`SHARED_FILES_PREFIX`).
  Then check that https://www.mafiaratings.com/new_look/index.php answers 200, that a picture on it loads from `/pics/`, and that https://www.mafiaratings.com/ still answers as before.
