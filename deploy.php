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
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kropek deploy</title>
<style>
  body { font: 16px system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
  button { font: inherit; font-size: 1.5rem; padding: .6rem 1.4rem; cursor: pointer; }
  pre { background: #eee; padding: 1rem; white-space: pre-wrap; min-height: 3rem; }
</style>
<h1>Deploy kropek from GitHub</h1>
<p><button id="deploy">Deploy main</button></p>
<pre id="out" aria-live="polite"></pre>
<script>
const out = document.getElementById('out');
const btn = document.getElementById('deploy');
btn.addEventListener('click', async () => {
  btn.disabled = true;
  out.textContent = 'Deploying…';
  try {
    const res = await fetch(location.pathname, { method: 'POST' });
    out.textContent = (res.ok ? 'Done\n' : `Failed (${res.status})\n`) + await res.text();
  } catch (e) {
    out.textContent = 'Request failed: ' + e.message;
  } finally {
    btn.disabled = false;
  }
});
</script>
</html>
