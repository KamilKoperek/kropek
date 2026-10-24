# Kiosk: cog in cage

The kiosk is started by **getty autologin + `~/.profile`**. The page is served by a PHP
built-in server running as a **systemd user service** (`kiosk-php`, see below), reachable from the LAN.

## Boot chain

1. `getty@tty1` autologins user `kropek`
   (drop-in `/etc/systemd/system/getty@tty1.service.d/autologin.conf`, created by `~/kiosk-root-setup.sh`):
   ```ini
   [Service]
   ExecStart=
   ExecStart=-/sbin/agetty --autologin kropek --noclear %I $TERM
   ```
2. Login shell reads `~/.profile`, whose tail is:
   ```sh
   if [ "$(tty)" = /dev/tty1 ] && [ -z "$WAYLAND_DISPLAY" ]; then
       exec cage -- cog "$(cat ~/kiosk-url 2>/dev/null || echo about:blank)" >~/kiosk.log 2>&1
   fi
   ```
   Only tty1 starts the kiosk; SSH logins are unaffected. If `kiosk-url` points at
   `http://127.0.0.1/…`, `.profile` first waits up to 10 s for the PHP server to listen.
3. `cage` runs `cog` fullscreen. When cog exits, cage exits, the login shell ends,
   and getty autologins again → kiosk restarts automatically.

Resulting processes: `cage` → `cog` → `WPENetworkProcess` + `WPEWebProcess` (sandboxed with `bwrap`).

## PHP server (`kiosk-php` user service)

`~/.config/systemd/user/kiosk-php.service` runs
`php -S 0.0.0.0:80 -t ~/php` with `PHP_CLI_SERVER_WORKERS=3` (`Restart=always`, enabled for `default.target`).
It listens on all interfaces: `http://192.168.1.6/` from the LAN; cog uses `http://127.0.0.1/`.
Port 80 as a normal user works because of `/etc/sysctl.d/80-kiosk-http.conf`
(`net.ipv4.ip_unprivileged_port_start=80`, root-owned).
Document root is `~/php`; `index.php` there is the default page.

```sh
systemctl --user status kiosk-php
journalctl --user -u kiosk-php -f      # request log / PHP errors
systemctl --user restart kiosk-php
```

Needs root once: `apt install php-cli git`, `loginctl enable-linger kropek` and the sysctl file above. Linger keeps the
user manager (and PHP) running while cog restarts; without it, PHP restarts with each kiosk login.

## Files in `/home/kropek`

| File | Purpose |
|------|---------|
| `kiosk-url` | URL shown by cog (currently `http://127.0.0.1/kropek.html`) |
| `php/` | PHP document root, replaced on every deploy (`index.php` = default page) |
| `repo/` | Clone of GitHub `main` used by deploys |
| `bin/deploy` | Deploy script (see [deploy.md](deploy.md)) |
| `kiosk-restart` | Kills cog and waits up to 15 s for it to come back; optional arg sets a new URL first |
| `kiosk-root-setup.sh` | One-time root setup of the tty1 autologin drop-in |
| `kiosk.log` | stdout/stderr of cage+cog (overwritten on each start) |
| `kropek.html` | Previous default page (see [page.md](page.md)); `kropek.html.bak` = older version |
| `index.html` | Alternative/older version of the same "Symbiote" page |

## Operations

```sh
./kiosk-restart                 # reload after editing the page
./kiosk-restart <url>           # change URL and reload
cat ~/kiosk.log                 # cog load messages / errors
pgrep -a cog                    # is it running?
radeontop                       # GPU load
```

Deploys come from **GitHub `main`** (`git@github.com:KamilKoperek/kropek.git`), never from a local working copy:
push first, then trigger. See [deploy.md](deploy.md).

```sh
./deploy.sh                          # from the repo root, via SSH
# or click the button on http://192.168.1.6/deploy.php
```

`php -S` serves `/` only if the repo has `index.php` or `index.html`; otherwise it returns 404, so point
`kiosk-url` at a real file (`./kiosk-restart http://127.0.0.1/kropek.html`).

Go back to the static page: `./kiosk-restart file:///home/kropek/kropek.html`.

## Troubleshooting

- `kiosk-restart` says cog did not come back → tty1 is probably not the active VT,
  or getty/autologin broke. Check `systemctl status getty@tty1` and `~/kiosk.log`.
- cog shows a connection error → `systemctl --user status kiosk-php` (is `php-cli` installed?).
- Black screen with `about:blank` → `~/kiosk-url` missing or empty.
- Changing autologin/getty needs root (`su -` or `sudo` with password).
