<?php
// Deploy GitHub main to this kiosk: POST runs ~/bin/deploy (fetch main -> mirror into ~/php -> reload cog).
// No auth by design: the button only pulls what is already on GitHub main. POST-only so links,
// crawlers and browser prefetch cannot trigger it.
// curl -X POST http://192.168.1.6/deploy.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    $home = getenv('HOME') ?: '/home/kropek';
    exec("$home/bin/deploy 2>&1", $lines, $status);
    http_response_code($status === 0 ? 200 : 500);
    exit(implode("\n", $lines) . "\n");
}
?>
<!doctype html>
<html lang="pl">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kropek – wdrożenie</title>
<link rel="stylesheet" href="ui.css">
<header class="topbar"><nav>
  <a class="brand" href="index.html">Kropek</a>
  <a href="voice.html">Głos</a><a href="config.php">Ustawienia</a><a href="deploy.php" aria-current="page">Wdrożenie</a><a class="nuke-link" href="nuke.php">Nuke</a>
</nav></header>
<main>
  <h1>Wdrożenie z GitHuba</h1>
  <p class="lead">Pobiera <code>main</code> na skrzynkę, kopiuje do katalogu strony i przeładowuje kiosk.</p>
  <div class="card">
    <div class="actions"><button id="deploy" class="btn-primary btn-lg">Wdróż main</button><span id="state" class="status" aria-live="polite"></span></div>
    <pre id="out" class="log">Czekam…</pre>
  </div>
</main>
<script>
const out = document.getElementById('out');
const btn = document.getElementById('deploy'), state = document.getElementById('state');
btn.addEventListener('click', async () => {
  btn.disabled = true;
  out.textContent = 'Wdrażanie…'; state.textContent = ''; state.className = 'status';
  try {
    const res = await fetch(location.pathname, { method: 'POST' });
    out.textContent = await res.text();
    state.textContent = res.ok ? 'Gotowe' : `Błąd (${res.status})`; state.className = 'status ' + (res.ok ? 'ok' : 'bad');
  } catch (e) {
    out.textContent = e.message; state.textContent = 'Błąd połączenia'; state.className = 'status bad';
  } finally {
    btn.disabled = false;
  }
});
</script>
</html>
