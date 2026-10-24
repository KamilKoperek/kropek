# kropek — project knowledge

Kiosk box at `kropek@192.168.1.6` that boots straight into a fullscreen web page
served by a local PHP server from `~/php` (port 80, `http://192.168.1.6/`), using **cog** (WPE WebKit browser) inside **cage** (Wayland kiosk compositor).

| File | What's in it |
|------|--------------|
| [host.md](host.md) | Hardware, OS, network, access |
| [kiosk.md](kiosk.md) | How cog-in-cage starts, files involved, day-to-day operations |
| [deploy.md](deploy.md) | Deploying from GitHub (`./deploy.sh` or the button on `/deploy.php`) |
| [page.md](page.md) | The displayed page (`kropek.html`) and performance notes |

Quick start:

```sh
ssh kropek@192.168.1.6
./kiosk-restart                      # reload current page
./kiosk-restart file:///home/kropek/index.html   # switch URL and reload
```

State captured 2026-10-24.
