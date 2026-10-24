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

`kropek.html` polls `vad.php` every 100 ms (same data as `voice.html`, see [voice.md](voice.md)) and keeps a `mood`:

- `wantCalm = clamp((VAD − 0.4) / 0.5)` — a voice calms it
- `wantNerve = clamp((peak dB + 50) / 25) × (1 − wantCalm)` — loud non-speech (noise, bangs) makes it nervous
- smoothed per frame in `updateMood()`: nerve rises fast (startle) and settles slowly; calm builds gently and lingers

| | Nervous (`mood.nerve`) | Calm (`mood.calm`) |
|---|---|---|
| Movement | twitchy steering, sudden darts, shorter pauses, more lunges | slower, longer pauses, far fewer leaps |
| Body | stronger lobes, shivering, more outline wobble, more jitter | rounder, slow breathing, less wobble/jitter |
| Tendrils | flick out on their own, whip harder | stay in |
| Heartbeat | up to ~2.2× faster | ~35 % slower |

The fps box also shows `nerve` / `calm` live. Without mic data both stay 0 (original behaviour).
Tune thresholds in `pollMood()`: the quiet room peaks around −47 dB, so nerve idles at ~0.1–0.2.
