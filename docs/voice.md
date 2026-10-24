# Live voice activity (mic → RNNoise → web)

```
mic jack ─ arecord (48 kHz mono S16) ─ ~/vad (RNNoise, 10 ms frames) ─ /dev/shm/kropek-vad.json
                                                                           │ rewritten every 50 ms
                         voice.html ── polls every 50 ms ─── vad.php ──────┘
```

## On the box (not in git)

| Piece | What it does |
|-------|--------------|
| `~/vad.c` → `~/vad` | `./vad` prints one text line per frame (original behaviour); `./vad FILE` keeps the last 50 frames (0.5 s) in FILE as JSON, atomically rewritten every 5 frames. `~/vad.c.orig` = your original |
| `kiosk-vad` user service | `arecord -q -D plughw:CARD=SB,DEV=0 -f S16_LE -r 48000 -c 1 -t raw -F 10000 -B 50000 \| ~/vad /dev/shm/kropek-vad.json`, `Restart=always` |
| `/dev/shm/kropek-vad.json` | `{"seq":N,"frames":[[vad,speech%,level_dBFS],...]}` — tmpfs, so no disk writes |

Build: `gcc -O2 -Wall vad.c -o vad -lrnnoise -lm` (rnnoise is in `/usr/local`), then
`systemctl --user restart kiosk-vad`.

## In the repo

- `vad.php` — returns the JSON plus `age` (seconds since last write, 1 s resolution); empty frames if the service is down.
- `voice.html` — state (SPEECH/silence), VAD / speech % / level meters, 10 s chart, list of speech segments.
  Segments use hysteresis: start at VAD ≥ 0.6, end after 300 ms below 0.3.
  Rendering: polled frames go into a queue and are fed at their real 10 ms rate on every
  `requestAnimationFrame` (smooth scrolling, ~100 ms behind live; if >250 ms queues up it skips ahead).
  The chart canvas is drawn at half resolution (`SCALE`) with one point per pixel column (max of its frames)
  and upscaled by CSS; DOM text/bars are touched only when their value changes.

Polling rather than a held-open stream (SSE) on purpose: `php -S` has 3 workers and every open stream would
hold one forever, starving the kiosk.

## Consumers

- `voice.html` (meters, chart, segments) and `kropek.html` (creature mood, see [page.md](page.md)).

## Notes

- The service holds the capture device; stop it to use `arecord` by hand: `systemctl --user stop kiosk-vad`.
- RNNoise costs ~35 % of one CPU core on this box.
- Hearing the mic in the headphones is the analog `Loopback Mixing` path, independent of this
  (persist mixer settings with `su -c "alsactl store"`).
