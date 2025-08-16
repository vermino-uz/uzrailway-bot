<?php

function loadFollows() {
    $file = __DIR__ . '/data/follows.json';
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return [];
}

function saveFollows($follows) {
    if (!is_dir(__DIR__ . '/data')) {
        @mkdir(__DIR__ . '/data', 0775, true);
    }
    $file = __DIR__ . '/data/follows.json';
    file_put_contents($file, json_encode($follows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function addFollow($chatId, $type, $dep, $arv, $date, $trainNumber = null) {
    $follows = loadFollows();
    $followId = $chatId . '_' . time() . '_' . rand(1000, 9999);
    
    $follows[$followId] = [
        'chat_id' => $chatId,
        'type' => $type, // 'day' or 'train'
        'dep' => $dep,
        'arv' => $arv,
        'date' => $date,
        'train_number' => $trainNumber,
        'created_at' => date('Y-m-d H:i:s'),
        'last_checked' => null,
        'last_available_seats' => 0,
        'active' => true
    ];
    
    saveFollows($follows);
    if (function_exists('writeLog')) {
        writeLog("Added follow: {$followId} for chat {$chatId}, type: {$type}");
    }
    return $followId;
}

?>
