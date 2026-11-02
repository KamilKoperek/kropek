<?php
// NUKE PROTOCOL: pick a GIF from gifs/ to show full-screen on the kiosk instead of Kropek.
// GET ?json -> {"gif": name|null}; POST {"gif": name|null} -> validate, save, return state; GET -> picker page.
// State lives next to the document root (~/php on the box is replaced on deploy). No auth, like config.php.
$file = dirname(__DIR__) . '/kropek-nuke.json';
$gifs = array_map('basename', glob(__DIR__ . '/gifs/*.gif') ?: []);

function current_gif(string $file, array $gifs): ?string {
    $g = (json_decode((string) @file_get_contents($file), true) ?: [])['gif'] ?? null;
    return in_array($g, $gifs, true) ? $g : null;   // a GIF removed by a deploy turns nuke off
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $in = json_decode(file_get_contents('php://input'), true);
    $gif = is_array($in) ? ($in['gif'] ?? null) : false;
    if ($gif !== null && !in_array($gif, $gifs, true)) { http_response_code(400); exit('{"error":"nieznany GIF"}'); }
    $tmp = "$file.tmp";
    if (file_put_contents($tmp, json_encode(['gif' => $gif])) === false || !rename($tmp, $file)) {
        http_response_code(500); exit('{"error":"nie udało się zapisać"}');
    }
    exit(json_encode(['gif' => $gif]));
}
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    exit(json_encode(['gif' => current_gif($file, $gifs)]));
}
$active = current_gif($file, $gifs);
?>
<!doctype html>
<html lang="pl">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NUKE PROTOCOL</title>
<link rel="stylesheet" href="ui.css">
<header class="topbar"><nav>
  <a class="brand" href="index.html">Kropek</a>
  <a href="voice.html">Głos</a><a href="config.php">Ustawienia</a><a href="deploy.php">Wdrożenie</a><a class="nuke-link" href="nuke.php" aria-current="page">Nuke</a>
</nav></header>
<main>
  <h1 class="nuke-title">☢ NUKE PROTOCOL</h1>
  <p class="lead">Kliknij GIF, żeby wyświetlić go na ekranie kiosku zamiast Kropka (w ciągu ~1 s).</p>
  <div class="actions"><button id="off" class="btn-primary">Wyłącz — wróć do Kropka</button> <span id="status" class="status" aria-live="polite"></span></div>
<div id="grid" class="gif-grid">
<?php foreach ($gifs as $g): $h = htmlspecialchars($g); ?>
  <button type="button" data-gif="<?= $h ?>" aria-pressed="<?= $g === $active ? 'true' : 'false' ?>">
    <img src="gifs/<?= htmlspecialchars(rawurlencode($g)) ?>" alt="" loading="lazy"><span><?= $h ?></span>
  </button>
<?php endforeach; ?>
</div>
<?php if (!$gifs): ?><p class="muted">Brak GIF-ów w katalogu <code>gifs/</code>.</p><?php endif; ?>
</main>
<script>
const status = document.getElementById('status');
const buttons = [...document.querySelectorAll('#grid button')];
async function set(gif) {
  status.textContent = 'Wysyłanie…'; status.className = 'status';
  try {
    const res = await fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ gif }) });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || res.status);
    for (const b of buttons) b.setAttribute('aria-pressed', b.dataset.gif === data.gif);
    status.textContent = data.gif ? 'Na ekranie: ' + data.gif : 'Kropek wrócił'; status.className = 'status ok';
  } catch (err) { status.textContent = 'Błąd: ' + err.message; status.className = 'status bad'; }
}
for (const b of buttons) b.addEventListener('click', () => set(b.dataset.gif));
document.getElementById('off').addEventListener('click', () => set(null));
</script>
</html>
