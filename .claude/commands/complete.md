Commit, push, and deploy the current changeset. Steps:

1. Run `git status` and `git diff` to see what will be committed.
2. Stage every modified/added file (skip files that clearly contain secrets, e.g. `.env`, and skip database dumps like `db/mafiawor_*.sql`). If new `db/alter*.sql` migrations exist, stage them but do NOT try to FTP them (SQL migrations are applied to the DB manually — remind the user at the end if any are staged).
3. Draft a commit message from the actual diff (not from earlier conversation summaries). Follow the repository's existing commit-message style — short imperative subject line, blank line, then a wrapped body listing the concrete changes.
4. Create the commit with the drafted message and a HEREDOC, then `git push origin master`.
5. Deploy every staged non-SQL file via FTP to `public_html/`, preserving the file's relative path. Use the FTP host/user/password stored in the auto-memory (project_ftp_deploy). Do NOT deploy `CLAUDE.md` (see project_deployment memory), do NOT try to deploy files under `db/`, and do NOT deploy `create_sample_db.bat` (local dev tooling only, not part of the deployed application).
6. Report: commit hash, files pushed, files deployed, plus any manual follow-up (SQL migrations to run) at the end.

Do not ask for confirmation — invoking this command IS the authorization for this changeset.
