<?php
require_once __DIR__ . '/botsdk/config.php';
require_once __DIR__ . '/helpers.php';

define("API_KEY", "$api_key");

// Define required functions for monitor
function writeLog($message, $level = 'INFO') {
    if (!is_dir(__DIR__ . '/data/logs')) {
        @mkdir(__DIR__ . '/data/logs', 0775, true);
    }
    $logFile = __DIR__ . '/data/logs/bot.log';
    $monitorLogFile = __DIR__ . '/data/logs/monitor.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] MONITOR: {$message}" . PHP_EOL;
    
    // Write to both log files
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    file_put_contents($monitorLogFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        return null;
    } else {
        return json_decode($res, true);
    }
}

function sendMessage($chatId, $text, $params = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ], $params);
    return bot('sendMessage', $data);
}

function getRailwayTrains($date, $depCode, $arvCode) {
    global $railway_xsrf_token, $railway_cookies;
    
    $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';
    
    $payload = json_encode([
        'directions' => [
            'forward' => [
                'date' => $date,
                'depStationCode' => $depCode,
                'arvStationCode' => $arvCode
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://eticket.railway.uz/api/v3/handbook/trains/list',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIE => $railway_cookies,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: uz',
            'Connection: keep-alive',
            'Content-Type: application/json',
            'User-Agent: ' . $ua,
            'Origin: https://eticket.railway.uz',
            'Referer: https://eticket.railway.uz/uz/home',
            'X-XSRF-TOKEN: ' . $railway_xsrf_token,
            'device-type: BROWSER'
        ]
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        writeLog("API error: {$err}", 'ERROR');
        return null;
    }
    
    $json = json_decode($resp, true);
    if ($json === null) {
        writeLog("Failed to parse API response", 'ERROR');
        return null;
    }
    
    return $json;
}

function extractTrains($response) {
    if (!$response || !isset($response['data']['directions']['forward']['trains'])) {
        return [];
    }
    return $response['data']['directions']['forward']['trains'];
}

function getTotalSeats($train) {
    $totalSeats = 0;
    if (isset($train['cars']) && is_array($train['cars'])) {
        foreach ($train['cars'] as $car) {
            $carSeats = isset($car['freeSeats']) ? intval($car['freeSeats']) : 0;
            $totalSeats += $carSeats;
        }
    }
    return $totalSeats;
}

function findTrainByNumber($trains, $trainNumber) {
    foreach ($trains as $train) {
        if (isset($train['number']) && $train['number'] === $trainNumber) {
            return $train;
        }
    }
    return null;
}

function getStationName($stationCode) {
    $mapFile = __DIR__ . '/map.json';
    if (is_file($mapFile)) {
        $mapData = json_decode(file_get_contents($mapFile), true);
        if (isset($mapData['map'])) {
            foreach ($mapData['map'] as $station) {
                if (isset($station['code']) && $station['code'] === $stationCode) {
                    return isset($station['name']) ? $station['name'] : $stationCode;
                }
            }
        }
    }
    return $stationCode; // Fallback to code if name not found
}

function checkAndNotify() {
    writeLog("Starting monitoring check...");
    
    // 1. Get follow trains
    $follows = loadFollows();
    if (empty($follows)) {
        writeLog("No active follows found");
        return;
    }
    
    $updated = false;
    $today = strtotime(date('Y-m-d'));
    
    foreach ($follows as $followId => &$follow) {
        if (!$follow['active']) continue;
        
        writeLog("Checking follow {$followId} for chat {$follow['chat_id']}");
        
        // Check if date has passed
        $followDate = strtotime($follow['date']);
        if ($followDate < $today) {
            writeLog("Follow {$followId} expired (date passed), deactivating");
            $follow['active'] = false;
            $updated = true;
            continue;
        }
        
        // 2. Get tokens from config (already loaded via global)
        // 3. Get train info from API
        $response = getRailwayTrains($follow['date'], $follow['dep'], $follow['arv']);
        if (!$response) {
            writeLog("Failed to get trains for follow {$followId}");
            continue;
        }
        
        $trains = extractTrains($response);
        $follow['last_checked'] = date('Y-m-d H:i:s');
        
        // 4. Check if free seats available that suit follow details
        if ($follow['type'] === 'day') {
            // Check total available seats for all trains
            $totalAvailable = 0;
            $availableTrains = [];
            
            foreach ($trains as $train) {
                $trainSeats = getTotalSeats($train);
                if ($trainSeats > 0) {
                    $totalAvailable += $trainSeats;
                    $availableTrains[] = [
                        'number' => $train['number'] ?? '',
                        'brand' => $train['brand'] ?? '',
                        'seats' => $trainSeats,
                        'departure' => $train['departureDate'] ?? '',
                        'arrival' => $train['arrivalDate'] ?? ''
                    ];
                }
            }
            
            // Send notification if seats increased and we have available seats
            if ($totalAvailable > $follow['last_available_seats'] && $totalAvailable > 0) {
                $trainList = "";
                foreach (array_slice($availableTrains, 0, 5) as $train) { // Show max 5 trains
                    $trainName = trim(($train['brand'] ? ($train['brand'] . ' ') : '') . $train['number']);
                    $trainList .= "🚂 {$trainName} - {$train['seats']} joy\n";
                }
                
                $depName = getStationName($follow['dep']);
                $arvName = getStationName($follow['arv']);
                
                $message = "🎉 <b>Joy topildi!</b>\n\n" .
                    "📅 Kunlik kuzatuv\n" .
                    "📍 {$depName} → {$arvName}\n" .
                    "📅 {$follow['date']}\n\n" .
                    "💺 Jami: {$totalAvailable} ta joy mavjud\n\n" .
                    "Mavjud poyezdlar:\n{$trainList}\n" .
                    "Tezroq band qiling! 🏃‍♂️";
                
                // 5. Send message to user
                $result = sendMessage($follow['chat_id'], $message);
                if ($result) {
                    writeLog("Day follow notification sent for {$followId}: {$totalAvailable} seats available");
                } else {
                    writeLog("Failed to send notification for {$followId}", 'ERROR');
                }
            }
            
            $follow['last_available_seats'] = $totalAvailable;
            
        } elseif ($follow['type'] === 'train' && $follow['train_number']) {
            // Check specific train
            $train = findTrainByNumber($trains, $follow['train_number']);
            if ($train) {
                $trainSeats = getTotalSeats($train);
                writeLog("Train {$follow['train_number']}: current={$trainSeats}, last={$follow['last_available_seats']}");
                
                // Send notification if seats increased and we have available seats
                if ($trainSeats > $follow['last_available_seats'] && $trainSeats > 0) {
                    $brand = isset($train['brand']) ? $train['brand'] : '';
                    $trainName = trim(($brand ? ($brand . ' ') : '') . $follow['train_number']);
                    $dep = isset($train['departureDate']) ? $train['departureDate'] : '';
                    $arv = isset($train['arrivalDate']) ? $train['arrivalDate'] : '';
                    
                    $depName = getStationName($follow['dep']);
                    $arvName = getStationName($follow['arv']);
                    
                    $message = "🎉 <b>Joy topildi!</b>\n\n" .
                        "🚂 Poyezd kuzatuvi\n" .
                        "🚂 {$trainName}\n" .
                        "📍 {$depName} → {$arvName}\n" .
                        "📅 {$follow['date']}\n" .
                        "🕐 {$dep} → {$arv}\n\n" .
                        "💺 {$trainSeats} ta joy mavjud\n\n" .
                        "Tezroq band qiling! 🏃‍♂️";
                    
                    // 5. Send message to user
                    $result = sendMessage($follow['chat_id'], $message);
                    if ($result) {
                        writeLog("Train follow notification sent for {$followId}: {$trainSeats} seats available for train {$follow['train_number']}");
                    } else {
                        writeLog("Failed to send notification for {$followId}", 'ERROR');
                    }
                }
                
                $follow['last_available_seats'] = $trainSeats;
            } else {
                writeLog("Train {$follow['train_number']} not found in results for follow {$followId}");
            }
        }
        
        $updated = true;
    }
    
    if ($updated) {
        saveFollows($follows);
        writeLog("Follows updated and saved");
    }
    
    writeLog("Monitoring check completed successfully");
}

// Main execution
try {
    checkAndNotify();
} catch (Exception $e) {
    writeLog("Monitoring check failed: " . $e->getMessage(), 'ERROR');
}
?>
