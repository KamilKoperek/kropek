<?php
// Mic sensitivity for kropek.html. Stored outside the document root (~/php is replaced on deploy).
// GET ?json -> current values; POST (JSON body) -> validate, save, return saved values; GET -> tuning page.
// No auth, like deploy.php: anyone on the LAN / hotspot can tune it.
const LIMITS = [                       // key => [min, max, default]
    'speechMin' => [0, 100, 40],       // speech % of recent frames that counts as speech (calms it)
    'dbQuiet'   => [-90, 0, -45],      // peak level (dBFS) at or below which it stays unbothered
    'dbLoud'    => [-90, 0, -30],      // peak level at which it is fully nervous
];
$file = (getenv('HOME') ?: '/home/kropek') . '/kropek-config.json';

function load(string $file): array {
    $saved = json_decode((string) @file_get_contents($file), true);
    return clean(is_array($saved) ? $saved : []);
}
function clean(array $in): array {
    $out = [];
    foreach (LIMITS as $k => [$min, $max, $def]) {
        $v = $in[$k] ?? $def;
        $out[$k] = is_numeric($v) ? max($min, min($max, round((float) $v, 1))) : $def;
    }
    if ($out['dbLoud'] <= $out['dbQuiet']) $out['dbLoud'] = min(0, $out['dbQuiet'] + 1);
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) { http_response_code(400); exit('{"error":"oczekiwano obiektu JSON"}'); }
    $cfg = clean($in);
    $tmp = "$file.tmp";
    if (file_put_contents($tmp, json_encode($cfg)) === false || !rename($tmp, $file)) {
        http_response_code(500); exit('{"error":"nie udało się zapisać"}');
    }
    exit(json_encode($cfg));
}
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    exit(json_encode(load($file)));
}
$cfg = load($file);
?>
<!doctype html>
<html lang="pl">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kropek – ustawienia</title>
<style>
  body { font: 16px system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
  label { display: block; margin: 1.2rem 0 .3rem; font-weight: 600; }
  .row { display: flex; gap: .8rem; align-items: center; }
  input[type=range] { flex: 1; }
  output { min-width: 5rem; text-align: right; font-variant-numeric: tabular-nums; }
  small { color: #555; }
  button { font: inherit; padding: .5rem 1.2rem; cursor: pointer; margin-right: .5rem; }
  #meter { position: relative; height: 1.6rem; background: #eee; border: 1px solid #999; margin: .4rem 0; }
  #bar { position: absolute; inset: 0 auto 0 0; width: 0; background: #888; }
  #bar.loud { background: #c33; } #bar.speech { background: #3a3; }
  .mark { position: absolute; top: -3px; bottom: -3px; width: 2px; background: #000; }
  #live { font-variant-numeric: tabular-nums; }
</style>
<h1>Czułość mikrofonu</h1>
<p><small>Kiosk stosuje zmiany w ciągu ~2 s od zapisu. Miernik pokazuje bieżący sygnał z mikrofonu na tle aktualnych (niezapisanych) ustawień.</small></p>

<div id="meter" aria-hidden="true"><div id="bar"></div><div class="mark" id="mQuiet"></div><div class="mark" id="mLoud"></div></div>
<div id="live">czekam na mikrofon…</div>

<form id="f">
  <label for="dbQuiet">Poziom ciszy — ignoruj hałas poniżej</label>
  <div class="row"><input type="range" id="dbQuiet" min="-90" max="0" step="1"><output for="dbQuiet"></output></div>
  <small>Podnieś, jeśli szum w pomieszczeniu go denerwuje.</small>

  <label for="dbLoud">Poziom głośny — pełne zdenerwowanie od</label>
  <div class="row"><input type="range" id="dbLoud" min="-90" max="0" step="1"><output for="dbLoud"></output></div>
  <small>Obniż, żeby bardziej reagował na umiarkowany hałas. Musi być wyżej niż poziom ciszy.</small>

  <label for="speechMin">Próg mowy</label>
  <div class="row"><input type="range" id="speechMin" min="0" max="100" step="1"><output for="speechMin"></output></div>
  <small>Procent mowy potrzebny, żeby go uspokoić. Podnieś, jeśli hałas jest brany za głos.</small>

  <p><button type="submit">Zapisz</button><button type="button" id="defaults">Domyślne</button> <span id="status" aria-live="polite"></span></p>
</form>
<script>
const saved = <?= json_encode($cfg) ?>;
const defaults = <?= json_encode(array_map(fn($l) => $l[2], LIMITS)) ?>;
const keys = Object.keys(defaults), el = id => document.getElementById(id);
const unit = { dbQuiet: ' dB', dbLoud: ' dB', speechMin: ' %' };
const pos = db => Math.min(Math.max((db + 90) / 90, 0), 1) * 100 + '%'; // meter: -90..0 dB

function show() {
  for (const k of keys) el(k).nextElementSibling.textContent = el(k).value + unit[k];
  el('mQuiet').style.left = pos(+el('dbQuiet').value);
  el('mLoud').style.left = pos(+el('dbLoud').value);
}
function fill(cfg) { for (const k of keys) el(k).value = cfg[k]; show(); }
for (const k of keys) el(k).addEventListener('input', () => { el('status').textContent = 'niezapisane'; show(); });
el('defaults').addEventListener('click', () => { fill(defaults); el('status').textContent = 'niezapisane'; });
fill(saved);

el('f').addEventListener('submit', async e => {
  e.preventDefault();
  if (+el('dbLoud').value <= +el('dbQuiet').value) { el('status').textContent = 'Poziom głośny musi być wyżej niż poziom ciszy'; return; }
  const body = Object.fromEntries(keys.map(k => [k, +el(k).value]));
  try {
    const res = await fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || res.status);
    fill(data);
    el('status').textContent = 'Zapisano';
  } catch (err) { el('status').textContent = 'Błąd zapisu: ' + err.message; }
});

// Live mic, evaluated the same way kropek.html does (peak level and speech % of new frames).
let seq = 0;
async function poll() {
  try {
    const d = await (await fetch('vad.php', { cache: 'no-store' })).json();
    const fresh = d.frames.slice(d.frames.length - Math.min(Math.max(d.seq - seq, 0), d.frames.length));
    seq = d.seq;
    if (d.age === null || d.age > 2) el('live').textContent = 'brak danych z mikrofonu (czy kiosk-vad działa?)';
    else if (fresh.length) {
      let sp = 0, db = -90;
      for (const [, s, l] of fresh) { sp += s; db = Math.max(db, l); }
      sp /= fresh.length;
      const q = +el('dbQuiet').value, L = +el('dbLoud').value;
      const loud = Math.min(Math.max((db - q) / (L - q), 0), 1), speech = sp >= +el('speechMin').value;
      el('bar').style.width = pos(db);
      el('bar').className = speech ? 'speech' : loud > 0 ? 'loud' : '';
      el('live').textContent = `szczyt ${db.toFixed(1)} dB · mowa ${sp.toFixed(0)} % → `
        + (speech ? 'mowa (spokój)' : `głośność ${loud.toFixed(2)}`);
    }
  } catch { el('live').textContent = 'błąd odczytu mikrofonu'; }
  setTimeout(poll, 100);
}
poll();
</script>
</html>
