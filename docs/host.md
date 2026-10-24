# Host

| | |
|---|---|
| Address | `192.168.1.6/24` on `enp1s0` (DHCP — may change) |
| Hostname / user | `kropek` / `kropek` |
| Access | SSH with key auth (works non-interactively from kamil's machine); `ssh` service enabled |
| Root | `sudo` requires a password; `su -` is also used (see `.bash_history`) |
| OS | Debian 13 (trixie), kernel 6.12.x amd64 |
| CPU | AMD G-T44R (low-power embedded APU) |
| GPU | AMD Radeon HD 6250 (Wrestler), `/dev/dri/card0`, `renderD128` |
| RAM | 1.5 GiB |
| Disk | `/dev/sda1` 108 GB (~2 GB used) |
| Display | DVI-I-1 connected, 1024x768; DP-1 unused |

## Installed kiosk packages

| Package | Version |
|---------|---------|
| cage | 0.2.0-2 |
| cog | 0.18.4-1 |
| libwpewebkit-2.0-1 | 2.48.3-1 |
| libwpe-1.0-1 | 1.16.2-1 |
| radeontop | 1.4-2 (GPU load monitor: `radeontop`) |
| php-cli | 8.4 (Debian candidate; must be installed by root) |

## Constraints

The machine is weak: the WebKit web process sits around ~80% CPU while rendering the animation.
Anything shown on the kiosk must be cheap to render (see [page.md](page.md)).
