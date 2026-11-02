<?php
// NUKE PROTOCOL: pick a GIF to show full-screen on the kiosk instead of Kropek.
// GIFs come from the repo's gifs/ and from uploads in ../kropek-gifs (outside the document root,
// because ~/php on the box is replaced on deploy). No auth, like config.php.
//   GET ?json          -> {"gif": name|null}             (polled by kropek.html)
//   GET ?file=NAME     -> the GIF itself
//   POST {"gif": ...}  -> show NAME, or null for Kropek
//   POST ?upload=NAME  -> raw GIF body (not multipart, so PHP's 2 MB upload limit doesn't apply)
//   GET                -> picker page
const MAX_UPLOAD = 20 * 1024 * 1024;
$file = dirname(__DIR__) . '/kropek-nuke.json';
$uploadDir = dirname(__DIR__) . '/kropek-gifs';

// name => path; names are unique across both folders (uploads never take a repo name)
$gifs = [];
foreach ([__DIR__ . '/gifs', $uploadDir] as $dir) {
    foreach (glob("$dir/*.gif") ?: [] as $path) $gifs[basename($path)] ??= $path;
}
ksort($gifs, SORT_NATURAL | SORT_FLAG_CASE);

function current_gif(string $file, array $gifs): ?string {
    $g = (json_decode((string) @file_get_contents($file), true) ?: [])['gif'] ?? null;
    return is_string($g) && isset($gifs[$g]) ? $g : null;   // a GIF removed by a deploy turns nuke off
}
function fail(int $code, string $msg) { http_response_code($code); exit(json_encode(['error' => $msg])); }

// Stores an uploaded GIF under a safe, unused name derived from the client's file name.
function upload(string $want, string $dir, array $gifs): string {
    $base = strtr(basename($want), ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z']);
    $base = strtolower(preg_replace('/\.gif$/i', '', $base));
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base), '-');
    $base = substr($base, 0, 60) ?: 'gif';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) fail(500, 'nie udało się utworzyć katalogu');
    $in = fopen('php://input', 'rb');
    for ($n = 1; ; $n++) {                                   // exclusive create: no overwrite, no race
        $name = $base . ($n > 1 ? "-$n" : '') . '.gif';
        if (isset($gifs[$name])) continue;
        $out = @fopen("$dir/$name", 'xb');
        if ($out) break;
        if (!file_exists("$dir/$name")) fail(500, 'nie udało się zapisać');
    }
    $size = stream_copy_to_stream($in, $out, MAX_UPLOAD + 1);
    fclose($out);
    $head = (string) file_get_contents("$dir/$name", false, null, 0, 6);
    $err = $size === false ? 'nie udało się zapisać'
        : ($size > MAX_UPLOAD ? 'plik większy niż 20 MB'
        : ($head !== 'GIF87a' && $head !== 'GIF89a' ? 'to nie jest plik GIF' : null));
    if ($err) { unlink("$dir/$name"); fail($size > MAX_UPLOAD ? 413 : 400, $err); }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (isset($_GET['upload'])) exit(json_encode(['uploaded' => upload((string) $_GET['upload'], $uploadDir, $gifs)]));
    $in = json_decode(file_get_contents('php://input'), true);
    $gif = is_array($in) ? ($in['gif'] ?? null) : false;
    if ($gif !== null && !(is_string($gif) && isset($gifs[$gif]))) fail(400, 'nieznany GIF');
    $tmp = "$file.tmp";
    if (file_put_contents($tmp, json_encode(['gif' => $gif])) === false || !rename($tmp, $file)) fail(500, 'nie udało się zapisać');
    exit(json_encode(['gif' => $gif]));
}
if (isset($_GET['file'])) {
    $path = $gifs[(string) $_GET['file']] ?? null;           // only names from the listing: no path traversal
    if (!$path) { http_response_code(404); exit; }
    header('Content-Type: image/gif');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: max-age=300');
    readfile($path);
    exit;
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
  <div class="actions">
    <button id="off" class="btn-primary">Wyłącz — wróć do Kropka</button>
    <label class="btn" for="upload">⬆ Wgraj GIF</label><input type="file" id="upload" class="visually-hidden" accept="image/gif" multiple>
    <span id="status" class="status" aria-live="polite"></span>
  </div>
<div id="grid" class="gif-grid">
<?php foreach (array_keys($gifs) as $g): $h = htmlspecialchars($g); ?>
  <button type="button" data-gif="<?= $h ?>" aria-pressed="<?= $g === $active ? 'true' : 'false' ?>">
    <img src="nuke.php?file=<?= htmlspecialchars(rawurlencode($g)) ?>" alt="" loading="lazy"><span><?= $h ?></span>
  </button>
<?php endforeach; ?>
</div>
<?php if (!$gifs): ?><p class="muted">Brak GIF-ów. Wgraj pierwszy przyciskiem powyżej.</p><?php endif; ?>
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

// Upload: each file goes as the raw request body; the page reloads to show the new tiles.
document.getElementById('upload').addEventListener('change', async e => {
  const files = [...e.target.files];
  try {
    for (const [i, f] of files.entries()) {
      if (f.size > <?= MAX_UPLOAD ?>) throw new Error(f.name + ': plik większy niż 20 MB');
      status.textContent = `Wgrywanie ${i + 1}/${files.length}: ${f.name}…`; status.className = 'status';
      const res = await fetch('nuke.php?upload=' + encodeURIComponent(f.name), { method: 'POST', headers: { 'Content-Type': 'image/gif' }, body: f });
      const data = await res.json();
      if (!res.ok) throw new Error(f.name + ': ' + (data.error || res.status));
    }
    location.reload();
  } catch (err) { status.textContent = 'Błąd: ' + err.message; status.className = 'status bad'; e.target.value = ''; }
});
</script>
</html>
