<?php
// Wi-Fi client IP for the kiosk overlay: {"ip":"192.168.43.17"|null,"age":seconds_since_it_appeared}.
// The IP is the address the phone hotspot gave the dongle (client mode, docs/hotspot.md); the AP's own
// 10.42.0.1 doesn't count. When the IP was first seen is kept in /dev/shm, so a kiosk reload (deploy)
// doesn't count as a new connection, but a reboot or reconnect does.
header('Content-Type: application/json');
header('Cache-Control: no-store');

$ip = null;
foreach (explode("\n", (string) shell_exec('ip -4 -o addr show scope global 2>/dev/null')) as $line) {
    if (preg_match('/^\d+:\s+(wl\S+)\s+inet\s+([\d.]+)\//', $line, $m) && $m[2] !== '10.42.0.1') {
        $ip = $m[2];
        break;
    }
}

$file = '/dev/shm/kropek-net.json';
$seen = json_decode((string) @file_get_contents($file), true) ?: [];
if (!isset($seen['since']) || $seen['ip'] !== $ip) {
    $seen = ['ip' => $ip, 'since' => time()];
    @file_put_contents($file, json_encode($seen), LOCK_EX);
}

echo json_encode(['ip' => $ip, 'age' => time() - $seen['since']]);
