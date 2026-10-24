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
