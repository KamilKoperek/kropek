<?php
// Latest VAD frames written by the kiosk-vad service (~/vad -> /dev/shm/kropek-vad.json).
// {"age":seconds_since_last_write,"seq":N,"frames":[[vad,speech%,level_dBFS],...]}
header('Content-Type: application/json');
header('Cache-Control: no-store');
$file = '/dev/shm/kropek-vad.json';
clearstatcache();
$json = @file_get_contents($file);
if ($json === false) {
    exit('{"age":null,"seq":0,"frames":[]}');
}
printf('{"age":%.2f,%s', max(0, microtime(true) - filemtime($file)), substr(trim($json), 1));
