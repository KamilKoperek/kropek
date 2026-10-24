# Displayed page: "Symbiote"

`/home/kropek/kropek.html` (~380 lines, single self-contained HTML file, no external assets):
a full-screen `<canvas>` animation of a black liquid "symbiote" blob on a white background.

## Behaviour (from function names)

- Body + tendrils: `updateBody`, `bodyPoint`, `updateTendrils`, `resetTendril`, `tendrilPoint`
- Movement: `wander`, `startCrouch` → `leap`, `splat` on landing
- Blobs split and re-merge: `makeBlob`, `splitBlobs`, `mergeClose`, `computeTargets`
- Loop: `step` (physics) → `draw` → `requestAnimationFrame(loop)`; `resize` on window resize
- Cursor is `crosshair` (canvas reacts to pointer)

## Rendering trick and performance

Gooey/metaball look: the canvas is drawn **small and blurred**, the browser upscales it,
and CSS `filter: contrast(40)` snaps soft edges into a crisp outline.
Contrast is a cheap per-pixel op compared with a full-screen CSS blur.

`index.html` (older, ~550 lines) is an alternative variant that avoids CSS filters
entirely and renders at reduced resolution.

Given the hardware (AMD G-T44R + Radeon HD 6250, see [host.md](host.md)),
keep the canvas resolution low and avoid full-screen CSS filters/blur;
check impact with `top` (WPEWebProcess CPU) and `radeontop`.

## Measured on the kiosk (2026-10-24)

Steady state (~25–35 blobs after merging), per frame:

| Variant | fps | step | draw |
|---------|-----|------|------|
| `RES = 1` | 9.6 | 10 ms | 80 ms |
| `RES = 0.5` | 12.1 | 9 ms | 60 ms |
| **`RES = 0.25`** (current) | 21.6 | 7 ms | 28 ms |
| `RES = 0.25`, no CSS contrast | 22.1 | 9 ms | 27 ms |

The canvas `blur()` in `draw()` is the main cost; the CSS `contrast(40)` is nearly free, so it stays.
The first ~3 s run at ~3 fps while the 420 single-unit blobs merge (`mergeClose` is O(n²)).
Method: a temporary copy reported timings via `fetch('/fps-beacon?...')`, read from `journalctl --user -u kiosk-php`.

## Reacts to the microphone

`kropek.html` polls `vad.php` every 100 ms (same data as `voice.html`, see [voice.md](voice.md)) and averages the
**speech %** of the new frames: **≥ 40 % → calm, < 40 % → nervous** (`setSpeech()`).
`updateMood()` smooths it per frame: nerve rises fast and settles slowly; calm builds over ~1–2 s and lingers.

| | Nervous (`mood.nerve`) | Calm (`mood.calm`) |
|---|---|---|
| Movement | restless (never rests), darts, frequent leaps: spontaneous jumps, short wind-up, quicker flight, bounces on landing; a sudden rise in nerve makes it bolt | sits in one place, no leaps |
| Body | 11 sharp rotating spikes, whole-body trembling (±4 px) | perfectly round, slow breathing |
| Tendrils | occasionally flick out, whip harder | pulled in |
| Heartbeat | up to 2.5× faster | 40 % slower |

Per-particle jitter is kept low on purpose; strong jitter made the body look like it was boiling.
Without mic data both stay 0 (original behaviour).

### Test override

The box under the fps counter has a **test speech** checkbox + slider (0–100 %) that replaces the mic.
On the kiosk use the URL: `./kiosk-restart 'http://127.0.0.1/kropek.html?speech=80'` (calm) or `?speech=10`
(nervous); `./kiosk-restart http://127.0.0.1/kropek.html` returns to the mic. `kiosk-url` keeps the override across reboots.

## Performance (single-core AMD G-T44R @ 1.2 GHz; `vad` takes ~35 % of it)

Blob outlines use 10–40 vertices scaled to buffer pixels (`r * RES * 4`) instead of up to 128: the low-res
buffer and blur hide finer detail. Measured steady state: ~37 fps nervous (~75 blobs), ~42–48 fps calm (~25 blobs).
The next cost to cut would be `mergeClose()` (O(n²) in blobs).
