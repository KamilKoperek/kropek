<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kropek — Klaudia Michalak &amp; Kamil Koperek</title>
<meta name="description" content="Kropek — instalacja artystyczna o konsekwencjach zanieczyszczenia hałasem. Klaudia Michalak &amp; Kamil Koperek, 2026.">
<style>
  :root { --ink: #000; --paper: #fff; }
  * { box-sizing: border-box; }
  html, body { margin: 0; background: var(--paper); color: var(--ink); }
  body { font: 18px/1.6 "Helvetica Neue", Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }

  /* Same gooey trick as kropek.html: blobs drawn small and blurred, contrast snaps them into one liquid body. */
  canvas { position: fixed; inset: 0; width: 100%; height: 100%; display: block; filter: contrast(40); z-index: 0; }

  /* Text sits above the creature and inverts where it crosses it. */
  main { position: relative; z-index: 1; mix-blend-mode: difference; color: #fff; pointer-events: none; }
  main a, main button { pointer-events: auto; }

  .hero { min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column; justify-content: space-between; padding: 24px; }
  .top, .bottom { display: flex; justify-content: space-between; gap: 16px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; }
  h1 { margin: 0; font-size: clamp(72px, 22vw, 340px); line-height: .85; font-weight: 900; letter-spacing: -.05em; }
  .hint { font-size: 13px; letter-spacing: .12em; text-transform: uppercase; }

  section { padding: 15vh 24px; max-width: 1100px; margin: 0 auto; }
  .about p { font-size: clamp(22px, 3.2vw, 38px); line-height: 1.3; margin: 0 0 1em; font-weight: 500; letter-spacing: -.01em; }
  .about p:last-child { margin: 0; }

  dl { display: grid; grid-template-columns: 180px 1fr; margin: 0; border-top: 1px solid #fff; }
  dt, dd { margin: 0; padding: 18px 0; border-bottom: 1px solid #fff; }
  dt { font-size: 13px; letter-spacing: .12em; text-transform: uppercase; padding-top: 22px; }
  dd { font-size: clamp(18px, 2vw, 24px); }

  footer { padding: 24px; display: flex; justify-content: space-between; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; }

  @media (max-width: 640px) {
    .top, .bottom, footer { flex-direction: column; gap: 4px; }
    dl { grid-template-columns: 1fr; }
    dt { padding: 18px 0 0; border-bottom: 0; }
    dd { padding-top: 4px; }
  }
</style>
</head>
<body>
<canvas id="c" aria-hidden="true"></canvas>
<main>
  <header class="hero">
    <div class="top"><span>Klaudia Michalak &amp; Kamil Koperek</span><span>2026</span></div>
    <div>
      <h1>Kropek</h1>
    </div>
    <div class="bottom"><span>Instalacja artystyczna</span><span class="hint">Porusz kursorem — hałas go stresuje</span></div>
  </header>

  <section class="about">
    <p>Kropek jest instalacją artystyczną, która ma przedstawiać bezpośrednie konsekwencje zanieczyszczenia hałasem.</p>
    <p>Szumy i szeroko pojęty hałas „stresują” Kropka, co objawia się widocznie wzmożonym ruchem obiektu samego w sobie zarówno jak i w obrębie instalacji.</p>
    <p>Sama jego nazwa ma wywołać w odbiorcy empatię. Jego duża reaktywność na otoczenie ma zachęcić odbiorcę do mówienia do niego tak, aby się „uspokoił”, co objawia się płynnym i widocznie wolniejszym poruszaniem się.</p>
  </section>

  <section>
    <dl>
      <dt>Autorzy</dt><dd>Klaudia Michalak &amp; Kamil Koperek</dd>
      <dt>Tytuł</dt><dd>Kropek</dd>
      <dt>Rok</dt><dd>2026</dd>
      <dt>Technika</dt><dd>Matryca umieszczona na płycie plexi podłączona do zasilania, komputera sterującego oraz mikrofonu, oświetlona od tyłu.</dd>
      <dt>Własność</dt><dd>Własność autorów</dd>
    </dl>
  </section>

  <footer><span>Kropek</span><span>© 2026</span></footer>
</main>

<script>
// A lighter cousin of the creature in kropek.html: a wandering body of blobs plus feelers.
// Cursor movement plays the role of noise (nervous: fast, spiky, jittery); stillness calms it.
const canvas = document.getElementById('c');
const ctx = canvas.getContext('2d', { alpha: false });
const buf = document.createElement('canvas');
const bctx = buf.getContext('2d');
const RES = 0.5;            // render at half resolution; blur + contrast hide it
const BLUR = 6 * RES;
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

let W, H;
function resize() {
  W = innerWidth; H = innerHeight;
  canvas.width = buf.width = Math.ceil(W * RES);
  canvas.height = buf.height = Math.ceil(H * RES);
  bctx.setTransform(RES, 0, 0, RES, 0, 0);
}
addEventListener('resize', resize);
resize();

const rand = (a, b) => a + Math.random() * (b - a);
const size = () => Math.min(W, H) / 900;   // scale the creature with the viewport

// Mood: pointer speed = noise.
const mood = { nerve: 0, noise: 0 };
let lastX = null, lastY = null;
addEventListener('pointermove', e => {
  if (lastX !== null) mood.noise += Math.hypot(e.clientX - lastX, e.clientY - lastY) * 0.004;
  lastX = e.clientX; lastY = e.clientY;
});
addEventListener('pointerdown', () => { mood.noise += 0.8; jump(); });

const brain = { x: W * 0.62, y: H * 0.5, angle: rand(0, Math.PI * 2), turn: 0, speed: 1, want: 1, timer: 60, vx: 0, vy: 0 };
let leap = null;
function jump() {
  if (leap) return;
  const m = Math.min(W, H) * 0.2;
  leap = { sx: brain.x, sy: brain.y, tx: rand(m, W - m), ty: rand(m, H - m), u: 0, arc: rand(-0.4, 0.4) };
  for (const t of tendrils) if (Math.random() < 0.7) t.grow = true;
}
function wander() {
  if (leap) {
    leap.u = Math.min(leap.u + 1 / 22, 1);
    const e = 1 - Math.pow(1 - leap.u, 3), dx = leap.tx - leap.sx, dy = leap.ty - leap.sy;
    const bow = Math.sin(Math.PI * e) * leap.arc;
    brain.x = leap.sx + dx * e - dy * bow; brain.y = leap.sy + dy * e + dx * bow;
    if (leap.u >= 1) { brain.angle = Math.atan2(dy, dx); brain.speed = 0; leap = null; }
    return;
  }
  const n = mood.nerve;
  brain.turn = (brain.turn + rand(-0.012, 0.012) * (1 + n * 8)) * 0.97;
  const margin = Math.min(W, H) * 0.22;
  if (brain.x < margin || brain.x > W - margin || brain.y < margin || brain.y > H - margin) {
    const home = Math.atan2(H / 2 - brain.y, W / 2 - brain.x);
    brain.turn += Math.atan2(Math.sin(home - brain.angle), Math.cos(home - brain.angle)) * 0.004;
  }
  brain.angle += brain.turn;
  if (--brain.timer <= 0) {
    const r = Math.random();
    if (r < 0.12 + n * 0.4) jump();
    brain.want = r < 0.5 ? 0 : rand(1, 3);
    brain.timer = rand(60, 160) * (1 - n * 0.8);
  }
  const want = Math.max(brain.want, n * 6);
  brain.speed += (want - brain.speed) * 0.04;
  brain.x += Math.cos(brain.angle) * brain.speed + rand(-3, 3) * n;
  brain.y += Math.sin(brain.angle) * brain.speed + rand(-3, 3) * n;
}

// Feelers that lash out on jumps (and at random when nervous), then retract.
const tendrils = Array.from({ length: 7 }, () => ({ angle: rand(0, 7), len: 0, max: 0, curl: rand(-1.5, 1.5), ph: rand(0, 7), grow: false, hold: 0 }));
function updateTendrils(s) {
  for (const t of tendrils) {
    t.angle += Math.sin(tick * 0.015 + t.ph) * 0.01;
    if (!t.grow && t.len < 2 && Math.random() < mood.nerve * 0.03) t.grow = true;
    if (t.grow) {
      if (!t.max) t.max = rand(60, 160) * s;
      t.len += (t.max - t.len) * 0.2;
      if (t.len > t.max * 0.95 && ++t.hold > 20) { t.grow = false; t.hold = 0; }
    } else {
      t.len *= 0.88;
      if (t.len < 2) { t.len = 0; t.max = 0; t.angle = rand(0, 7); t.curl = rand(-1.5, 1.5); }
    }
  }
}

// Points: most live inside a lobed, breathing disc; the rest along the feelers.
const COUNT = 260, TP = 10;
const pts = [];
for (let i = 0; i < COUNT; i++) {
  const u = i < tendrils.length * TP
    ? { td: tendrils[i % tendrils.length], f: (Math.floor(i / tendrils.length) + 1) / TP }
    : { a: rand(0, Math.PI * 2), r: Math.sqrt(Math.random()), spin: rand(-0.004, 0.004), ph: rand(0, 7) };
  u.x = brain.x + rand(-200, 200); u.y = brain.y + rand(-200, 200); u.vx = 0; u.vy = 0;
  pts.push(u);
}
const lobes = [2, 3, 4, 5].map(k => ({ k, amp: 0, want: 0, ph: rand(0, 7), spd: rand(-0.02, 0.02) }));

function target(u, s) {
  if (u.td) {
    const t = u.td, f = u.f;
    const a = t.angle + t.curl * f * f + Math.sin(tick * 0.09 + t.ph + f * 5) * 0.4 * f * (1 + mood.nerve * 3);
    return [brain.x + Math.cos(a) * t.len * f, brain.y + Math.sin(a) * t.len * f];
  }
  const a = u.a + tick * u.spin;
  let shape = 1;
  for (const l of lobes) shape += l.amp * Math.sin(l.k * a + l.ph);
  shape += mood.nerve * 0.8 * Math.pow(Math.max(Math.sin(11 * a + tick * 0.7), 0), 4);   // spikes
  const r = 85 * s * u.r * shape * (1 + 0.08 * Math.sin(tick * 0.05 + u.ph));
  // stretch along the direction of travel
  const sp = Math.hypot(brain.vx, brain.vy), st = 1 + Math.min(sp * 0.04, 1);
  const dx = sp > 0.3 ? brain.vx / sp : 1, dy = sp > 0.3 ? brain.vy / sp : 0;
  const ox = Math.cos(a) * r, oy = Math.sin(a) * r;
  const al = (ox * dx + oy * dy) * st, ac = (-ox * dy + oy * dx) / Math.sqrt(st);
  return [brain.x + al * dx - ac * dy, brain.y + al * dy + ac * dx];
}

let tick = 0;
function step() {
  tick++;
  mood.noise *= 0.9;
  const want = Math.min(mood.noise, 1);
  mood.nerve += (want - mood.nerve) * (want > mood.nerve ? 0.2 : 0.01);   // startle fast, settle slow

  const s = size(), px = brain.x, py = brain.y;
  wander();
  const pad = 90 * s;
  brain.x = Math.min(Math.max(brain.x, pad), W - pad);
  brain.y = Math.min(Math.max(brain.y, pad), H - pad);
  brain.vx = brain.x - px; brain.vy = brain.y - py;

  for (const l of lobes) {
    if (Math.random() < 0.01) l.want = rand(0, 0.35 / l.k);
    l.amp += (l.want + mood.nerve * 0.1 - l.amp) * 0.05;
    l.ph += l.spd;
  }
  updateTendrils(s);

  const jit = 0.1 * (1 + mood.nerve * 2);
  for (const u of pts) {
    const [tx, ty] = target(u, s);
    const dx = tx - u.x, dy = ty - u.y;
    const k = leap ? 0.06 : u.td ? 0.08 : 0.025;
    u.vx = (u.vx + dx * k + rand(-jit, jit)) * 0.86;
    u.vy = (u.vy + dy * k + rand(-jit, jit)) * 0.86;
    u.x += u.vx + brain.vx * 0.85; u.y += u.vy + brain.vy * 0.85;
  }
}

function draw() {
  const s = size(), r = Math.max(7 * s * 1.6, 6);
  bctx.clearRect(0, 0, W, H);
  bctx.fillStyle = '#000';
  bctx.beginPath();
  for (const u of pts) {
    const rr = u.td ? r * (1 - u.f * 0.45) : r * 1.6;
    bctx.moveTo(u.x + rr, u.y);
    bctx.arc(u.x, u.y, rr, 0, Math.PI * 2);
  }
  bctx.fill();
  ctx.filter = 'none';
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.filter = `blur(${BLUR}px)`;
  ctx.drawImage(buf, 0, 0);
}

function loop() {
  if (!document.hidden) { step(); draw(); }
  if (!reduced) requestAnimationFrame(loop);
}
// Let the points gather into a body before the first frame.
for (let i = 0; i < 120; i++) step();
loop();
</script>
</body>
</html>
