# Deploying from GitHub

```
GitHub main ──(deploy key, read-only)──> ~/repo ──git archive──> ~/php ──> cog reload
     ▲ triggered by ./deploy.sh (SSH) or the button on http://192.168.1.6/deploy.php
```

`deploy.php` is part of the repo, so it is deployed with the site (and redeploys itself).

## Pieces

| Piece | Where | What it does |
|-------|-------|--------------|
| `deploy.php` | repo → `~/php` | GET: page with one **Deploy main** button. POST (no auth) runs `~/bin/deploy` |
| `deploy.sh` | repo (not deployed) | `ssh kropek@192.168.1.6 bin/deploy` — same deploy, authenticated by your SSH key |
| `~/bin/deploy` | box | `flock` (one at a time) → clone/fetch `main` into `~/repo` → `reset --hard origin/main` → `git archive` into `~/php.new` → drop `docs/`, `deploy.sh` → replace contents of `~/php` → `~/kiosk-restart`. Exits before touching `~/php` if anything fails |
| `~/.ssh/github_deploy` | box | Read-only GitHub deploy key, used only for github.com (`~/.ssh/config`) |
| `~/.ssh/known_hosts` | box | github.com ED25519 `SHA256:+DiY3wvvV6TuJJhbpZisF/zLDA0zPMSvHdkr4UvCOqU` (matches GitHub's published key) |

## No auth (by choice)

Anyone who can reach the box can press the button. The only thing it can do is deploy what is already on
GitHub `main` and reload the kiosk, so the worst case is someone spamming reloads. It is POST-only, so
links, crawlers and browser prefetch do not trigger it; `flock` stops overlapping deploys.

curl: `curl -X POST http://192.168.1.6/deploy.php`

## Notes / limits

- Only what is pushed to `main` is deployed; `~/php` mirrors it (removed files disappear).
- The PHP server listens on all interfaces (`0.0.0.0:80`), so every file in the repo is publicly readable
  over HTTP — do not commit secrets.
- Do not port-forward port 80 to the internet as is: no HTTPS and an open deploy button.
- `PHP_CLI_SERVER_WORKERS=3`, so the kiosk can load pages while a deploy request is running.
