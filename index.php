<?php
require_once __DIR__ . '/botsdk/config.php';

$mapData = json_decode(file_get_contents(__DIR__ . '/map.json'), true);
$map = isset($mapData['map']) ? $mapData['map'] : [];

require_once __DIR__ . '/botsdk/tg.php';

if (function_exists('handleUpdate')) {
	handleUpdate($map);
}
?>