<?php
require_once __DIR__ . '/config.php';
define("API_KEY", "$api_key");

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

function replyKeyboard($key) {
    return json_encode(["keyboard" => $key, "resize_keyboard" => true]);
}

function inlineKeyboard($key) {
    return json_encode(["inline_keyboard" => $key]);
}

function sendMessage($chatId, $text, $params = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ], $params);
    return bot('sendMessage', $data);
}

function answerCallbackQuery($callbackId, $text = '') {
    $data = ['callback_query_id' => $callbackId];
    if ($text !== '') {
        $data['text'] = $text;
    }
    return bot('answerCallbackQuery', $data);
}

function editMessageText($chatId, $messageId, $text, $params = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ], $params);
    return bot('editMessageText', $data);
}

function deleteMessage($chatId, $messageId) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ];
    return bot('deleteMessage', $data);
}

function ensureDataDirs() {
    $base = dirname(__DIR__);
    if (!is_dir($base . '/data')) {
        @mkdir($base . '/data', 0775, true);
    }
    if (!is_dir($base . '/data/cookies')) {
        @mkdir($base . '/data/cookies', 0775, true);
    }
    if (!is_dir($base . '/data/logs')) {
        @mkdir($base . '/data/logs', 0775, true);
    }
}

function writeLog($message, $level = 'INFO') {
    ensureDataDirs();
    $base = dirname(__DIR__);
    $logFile = $base . '/data/logs/bot.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function getStateFilePath($userId) {
    $base = dirname(__DIR__);
    return $base . '/data/state_' . $userId . '.json';
}

function loadState($userId) {
    $file = getStateFilePath($userId);
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return ['step' => 'idle'];
}

function saveState($userId, $state) {
    ensureDataDirs();
    $file = getStateFilePath($userId);
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function buildStationKeyboard($map, $mode) {
    $rows = [];
    $row = [];
    foreach ($map as $entry) {
        $label = isset($entry['name']) ? $entry['name'] : (isset($entry['code']) ? $entry['code'] : 'Station');
        $code = isset($entry['code']) ? $entry['code'] : '';
        $row[] = [
            'text' => $label,
            'callback_data' => $mode . ':' . $code
        ];
        if (count($row) === 2) {
            $rows[] = $row;
            $row = [];
        }
    }
    if (count($row) > 0) {
        $rows[] = $row;
    }
    
    // Add back button for arrival station selection
    if ($mode === 'arv') {
        $rows[] = [['text' => '🔙 Ortga', 'callback_data' => 'back_to_dep']];
    }
    
    return inlineKeyboard($rows);
}

function cookieFilePath() {
    ensureDataDirs();
    $base = dirname(__DIR__);
    return $base . '/data/cookies/railway.cookies.txt';
}

function fetchXsrfTokenFromCookieJar($cookieFile) {
    if (!is_file($cookieFile)) return null;
    $raw = file_get_contents($cookieFile);
    $lines = explode("\n", $raw);
    foreach ($lines as $line) {
        if (strpos($line, "XSRF-TOKEN") !== false) {
            $parts = preg_split('/\s+/', trim($line));
            $value = end($parts);
            return urldecode($value);
        }
    }
    return null;
}

function railwayApiFetchTrains($date, $depCode, $arvCode) {
    $cookieFile = cookieFilePath();
    $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://eticket.railway.uz/sanctum/csrf-cookie',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: ' . $ua,
            'Origin: https://eticket.railway.uz',
            'Referer: https://eticket.railway.uz/uz/home'
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);

    $xsrf = fetchXsrfTokenFromCookieJar($cookieFile);

    $payload = json_encode([
        'directions' => [
            'forward' => [
                'date' => $date,
                'depStationCode' => $depCode,
                'arvStationCode' => $arvCode
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    // Use config variables
    global $railway_xsrf_token, $railway_cookies;
    $xsrf = $railway_xsrf_token;
    $cookies = $railway_cookies;
    
    writeLog("Using XSRF token: {$xsrf}");
    writeLog("Using cookies: {$cookies}");
    
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => 'https://eticket.railway.uz/api/v3/handbook/trains/list',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_COOKIE => $cookies,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: uz',
            'Connection: keep-alive',
            'Content-Type: application/json',
            'User-Agent: ' . $ua,
            'Origin: https://eticket.railway.uz',
            'Referer: https://eticket.railway.uz/uz/home',
            'X-XSRF-TOKEN: ' . $xsrf,
            'device-type: BROWSER',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'sec-ch-ua: "Not;A=Brand";v="99", "Google Chrome";v="139", "Chromium";v="139"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"'
        ]
    ]);
    $resp = curl_exec($ch2);
    $err = curl_error($ch2);
    curl_close($ch2);

    if ($err) {
        return [ 'error' => $err ];
    }
    $json = json_decode($resp, true);
    if ($json === null) {
        return [ 'raw' => mb_substr($resp, 0, 1000) ];
    }
    return $json;
}

function extractTrainList($response) {
    if ($response === null) return [];
    if (isset($response['trains']) && is_array($response['trains'])) return $response['trains'];
    if (isset($response['data']) && is_array($response['data'])) {
        if (isset($response['data']['trains']) && is_array($response['data']['trains'])) return $response['data']['trains'];
        if (isset($response['data']['directions']['forward']['trains']) && is_array($response['data']['directions']['forward']['trains'])) {
            return $response['data']['directions']['forward']['trains'];
        }
        foreach ($response['data'] as $v) {
            if (is_array($v) && isset($v[0]) && is_array($v[0])) return $v;
        }
    }
    if (is_array($response) && isset($response[0]) && is_array($response[0])) return $response;
    return [];
}

function formatTrainsMessage($trains, $limit = 10) {
    if (empty($trains)) return "Hech narsa topilmadi.";
    $lines = [];
    $count = 0;
    foreach ($trains as $t) {
        $count++;
        if ($count > $limit) break;
        $num = isset($t['number']) ? $t['number'] : (isset($t['trainNumber']) ? $t['trainNumber'] : (isset($t['train']) ? $t['train'] : ''));
        $brand = isset($t['brand']) ? $t['brand'] : '';
        $depName = isset($t['originRoute']['depStationName']) ? $t['originRoute']['depStationName'] : '';
        $arvName = isset($t['originRoute']['arvStationName']) ? $t['originRoute']['arvStationName'] : '';
        $dep = isset($t['departureDate']) ? $t['departureDate'] : (isset($t['depTime']) ? $t['depTime'] : (isset($t['departureTime']) ? $t['departureTime'] : ''));
        $arv = isset($t['arrivalDate']) ? $t['arrivalDate'] : (isset($t['arvTime']) ? $t['arvTime'] : (isset($t['arrivalTime']) ? $t['arrivalTime'] : ''));
        $timeOnWay = isset($t['timeOnWay']) ? $t['timeOnWay'] : '';

        $minTariff = null;
        $totalSeats = 0;
        $hasEmptyCars = false;
        if (isset($t['cars']) && is_array($t['cars'])) {
            if (empty($t['cars'])) {
                $hasEmptyCars = true;
            } else {
                foreach ($t['cars'] as $car) {
                    $carSeats = isset($car['freeSeats']) ? intval($car['freeSeats']) : 0;
                    $totalSeats += $carSeats;
                    if (isset($car['tariffs']) && is_array($car['tariffs'])) {
                        foreach ($car['tariffs'] as $tariff) {
                            if (isset($tariff['tariff'])) {
                                $price = intval($tariff['tariff']);
                                if ($minTariff === null || $price < $minTariff) $minTariff = $price;
                            }
                        }
                    }
                }
            }
        } else {
            $hasEmptyCars = true;
        }

        $pricePart = $minTariff !== null ? (" – narx: " . number_format($minTariff, 0, '.', ' ') . " so'm") : '';
        
        if ($hasEmptyCars) {
            $seatsPart = " – ❌ Joy mavjud emas";
        } elseif ($totalSeats > 0) {
            $seatsPart = " – ✅ joylar: " . $totalSeats;
        } else {
            $seatsPart = " – ❌ Joy qolmagan";
        }

        $title = trim(($brand ? ($brand . ' ') : '') . $num);
        $line = trim(
            '<b>' . htmlspecialchars($title) . '</b>' . "\n" .
            htmlspecialchars($dep) . ' (' . htmlspecialchars($depName) . ') → ' . htmlspecialchars($arv) . ' (' . htmlspecialchars($arvName) . ')' .
            ($timeOnWay !== '' ? (' – ' . htmlspecialchars($timeOnWay)) : '') .
            $pricePart . $seatsPart
        );
        $lines[] = $line;
    }
    return implode("\n\n", $lines);
}

function getStationByCode($map, $code) {
    foreach ($map as $entry) {
        if (isset($entry['code']) && (string)$entry['code'] === (string)$code) return $entry;
    }
    return null;
}



function handleUpdate($map) {
    ensureDataDirs();
    require_once __DIR__ . '/../helpers.php';
    $input = file_get_contents('php://input');
    writeLog("Received webhook: " . substr($input, 0, 500));
    
    $update = json_decode($input);
    if (!$update) {
        writeLog("Failed to parse JSON from webhook", 'ERROR');
        http_response_code(200);
        return;
    }

    $message = isset($update->message) ? $update->message : null;
    $callback = isset($update->callback_query) ? $update->callback_query : null;

    $chat_id = null;
    $from_id = null;
    $text = null;
    $message_id = null;

    if ($callback) {
        $chat_id = $callback->message->chat->id ?? null;
        $from_id = $callback->from->id ?? null;
        $message_id = $callback->message->message_id ?? null;
    } elseif ($message) {
        $chat_id = $message->chat->id ?? null;
        $from_id = $message->from->id ?? null;
        $text = $message->text ?? null;
        $message_id = $message->message_id ?? null;
    }

    if (!$chat_id || !$from_id) {
        writeLog("Missing chat_id or from_id", 'ERROR');
        http_response_code(200);
        return;
    }

    writeLog("Processing update for chat_id: {$chat_id}, from_id: {$from_id}");
    $state = loadState($chat_id);
    writeLog("Loaded state: " . json_encode($state));

    if ($callback && isset($callback->data)) {
        $data = $callback->data;
        writeLog("Callback data received: {$data}");
        if (strpos($data, 'dep:') === 0) {
            $code = substr($data, 4);
            writeLog("Setting departure station: {$code}");
            $state['dep'] = $code;
            $state['step'] = 'choose_arv';
            saveState($chat_id, $state);
            answerCallbackQuery($callback->id);
            $kb = buildStationKeyboard($map, 'arv');
            $station = getStationByCode($map, $code);
            $label = $station ? $station['name'] : $code;
            editMessageText($chat_id, $message_id, "✅ <b>" . htmlspecialchars($label) . "</b>\n\n📍 Qayerga borasiz?\nBorish stansiyasini tanlang:", [
                'reply_markup' => $kb
            ]);
            return;
        }
        if (strpos($data, 'arv:') === 0) {
            $code = substr($data, 4);
            writeLog("Setting arrival station: {$code}");
            $state['arv'] = $code;
            $state['step'] = 'input_date';
            saveState($chat_id, $state);
            answerCallbackQuery($callback->id);
            $depStation = getStationByCode($map, $state['dep'] ?? '');
            $arvStation = getStationByCode($map, $code);
            $depName = $depStation ? $depStation['name'] : ($state['dep'] ?? '');
            $arvName = $arvStation ? $arvStation['name'] : $code;
            editMessageText($chat_id, $message_id, "✅ <b>" . htmlspecialchars($depName) . "</b> → <b>" . htmlspecialchars($arvName) . "</b>\n\n📅 Sana kiriting (YYYY-MM-DD):\nMasalan: " . date('Y-m-d', time() + 86400));
            return;
        }
        
        if ($data === 'back_to_dep') {
            writeLog("User clicked back to departure selection");
            answerCallbackQuery($callback->id);
            $state['step'] = 'choose_dep';
            unset($state['dep'], $state['arv'], $state['date']);
            saveState($chat_id, $state);
            $kb = buildStationKeyboard($map, 'dep');
            editMessageText($chat_id, $message_id, "🚄 <b>Temir yo'l biletlari</b>\n\n📍 Qayerdan ketasiz?\nKetish stansiyasini tanlang:", [
                'reply_markup' => $kb
            ]);
            return;
        }
        
        if ($data === 'search_trains') {
            writeLog("User selected search trains");
            answerCallbackQuery($callback->id);
            $state['step'] = 'choose_dep';
            saveState($chat_id, $state);
            $kb = buildStationKeyboard($map, 'dep');
            editMessageText($chat_id, $message_id, "🚄 <b>Temir yo'l biletlari</b>\n\n📍 Qayerdan ketasiz?\nKetish stansiyasini tanlang:", [
                'reply_markup' => $kb
            ]);
            return;
        }
        
        if ($data === 'my_follows') {
            writeLog("User selected my follows");
            answerCallbackQuery($callback->id);
            
            $follows = loadFollows();
            $userFollows = [];
            
            // Filter follows for this user
            foreach ($follows as $followId => $follow) {
                if ($follow['chat_id'] == $chat_id && $follow['active']) {
                    $userFollows[$followId] = $follow;
                }
            }
            
            if (empty($userFollows)) {
                editMessageText($chat_id, $message_id, "📋 <b>Mening kuzatuvlarim</b>\n\n❌ Hozircha faol kuzatuvlar yo'q.\n\n🚄 Yangi kuzatuv qo'shish uchun quyidagi tugmani bosing:", [
                    'reply_markup' => inlineKeyboard([
                        [['text' => '🚄 Poyezd qidirish', 'callback_data' => 'search_trains']],
                        [['text' => '🔙 Bosh menyu', 'callback_data' => 'back_to_main']]
                    ])
                ]);
                return;
            }
            
            $followsText = "📋 <b>Mening kuzatuvlarim</b>\n\n";
            $followsKeyboard = [];
            
            foreach ($userFollows as $followId => $follow) {
                $depStation = getStationByCode($map, $follow['dep']);
                $arvStation = getStationByCode($map, $follow['arv']);
                $depName = $depStation ? $depStation['name'] : $follow['dep'];
                $arvName = $arvStation ? $arvStation['name'] : $follow['arv'];
                
                $typeText = ($follow['type'] === 'day') ? '📅 Kunlik' : '🚂 Poyezd';
                $trainInfo = ($follow['type'] === 'train' && $follow['train_number']) ? " ({$follow['train_number']})" : '';
                
                $followsText .= "• {$typeText}{$trainInfo}\n";
                $followsText .= "📍 {$depName} → {$arvName}\n";
                $followsText .= "📅 {$follow['date']}\n";
                $followsText .= "🕐 " . date('d.m.Y H:i', strtotime($follow['created_at'])) . "\n\n";
                
                // Add cancel button for each follow
                $followsKeyboard[] = [['text' => "❌ {$depName}→{$arvName} ({$follow['date']})", 'callback_data' => "cancel_follow_{$followId}"]];
            }
            
            $followsKeyboard[] = [['text' => '🚄 Yangi qidiruv', 'callback_data' => 'search_trains']];
            $followsKeyboard[] = [['text' => '🔙 Bosh menyu', 'callback_data' => 'back_to_main']];
            
            editMessageText($chat_id, $message_id, $followsText, [
                'reply_markup' => inlineKeyboard($followsKeyboard)
            ]);
            return;
        }
        
        if ($data === 'back_to_main') {
            writeLog("User clicked back to main menu");
            answerCallbackQuery($callback->id);
            $state['step'] = 'main_menu';
            saveState($chat_id, $state);
            
            $startKeyboard = [
                [['text' => '🚄 Poyezd qidirish', 'callback_data' => 'search_trains']],
                [['text' => '📋 Mening kuzatuvlarim', 'callback_data' => 'my_follows']]
            ];
            
            editMessageText($chat_id, $message_id, "🚄 <b>Temir yo'l biletlari xizmati</b>\n\nNima qilmoqchisiz?", [
                'reply_markup' => inlineKeyboard($startKeyboard)
            ]);
            return;
        }
        
        // Handle cancel follow buttons
        if (strpos($data, 'cancel_follow_') === 0) {
            $followId = str_replace('cancel_follow_', '', $data);
            writeLog("User wants to cancel follow: {$followId}");
            answerCallbackQuery($callback->id);
            
            $follows = loadFollows();
            if (isset($follows[$followId]) && $follows[$followId]['chat_id'] == $chat_id) {
                $follows[$followId]['active'] = false;
                saveFollows($follows);
                
                $depStation = getStationByCode($map, $follows[$followId]['dep']);
                $arvStation = getStationByCode($map, $follows[$followId]['arv']);
                $depName = $depStation ? $depStation['name'] : $follows[$followId]['dep'];
                $arvName = $arvStation ? $arvStation['name'] : $follows[$followId]['arv'];
                
                editMessageText($chat_id, $message_id, "✅ <b>Kuzatuv bekor qilindi</b>\n\n" .
                    "📍 {$depName} → {$arvName}\n" .
                    "📅 {$follows[$followId]['date']}\n\n" .
                    "Boshqa amallar uchun tugmalarni bosing:", [
                    'reply_markup' => inlineKeyboard([
                        [['text' => '📋 Mening kuzatuvlarim', 'callback_data' => 'my_follows']],
                        [['text' => '🚄 Yangi qidiruv', 'callback_data' => 'search_trains']],
                        [['text' => '🔙 Bosh menyu', 'callback_data' => 'back_to_main']]
                    ])
                ]);
            } else {
                editMessageText($chat_id, $message_id, "❌ Kuzatuv topilmadi yoki sizniki emas.", [
                    'reply_markup' => inlineKeyboard([
                        [['text' => '📋 Mening kuzatuvlarim', 'callback_data' => 'my_follows']],
                        [['text' => '🔙 Bosh menyu', 'callback_data' => 'back_to_main']]
                    ])
                ]);
            }
            return;
        }
        
        if ($data === 'follow_day') {
            writeLog("User selected follow by day");
            answerCallbackQuery($callback->id, "Kunlik kuzatuv yoqildi!");
            
            $depStation = getStationByCode($map, $state['dep'] ?? '');
            $arvStation = getStationByCode($map, $state['arv'] ?? '');
            $depName = $depStation ? $depStation['name'] : ($state['dep'] ?? '');
            $arvName = $arvStation ? $arvStation['name'] : ($state['arv'] ?? '');
            
            // Save follow preference
            $followId = addFollow($chat_id, 'day', $state['dep'], $state['arv'], $state['date']);
            writeLog("Created day follow with ID: {$followId}");
            
            editMessageText($chat_id, $message_id, "📅 <b>Kunlik kuzatuv yoqildi</b>\n\n" .
                "Yo'nalish: {$depName} → {$arvName}\n" .
                "Sana: {$state['date']}\n\n" .
                "✅ Agar biron bir poyezdda joy chiqsa, sizga xabar beramiz!", [
                'reply_markup' => inlineKeyboard([
                    [['text' => '📋 Mening kuzatuvlarim', 'callback_data' => 'my_follows']],
                    [['text' => '🚄 Yangi qidiruv', 'callback_data' => 'search_trains']],
                    [['text' => '🔙 Bosh menyu', 'callback_data' => 'back_to_main']]
                ])
            ]);
            
            $state['step'] = 'idle';
            saveState($chat_id, $state);
            return;
        }
        
        if ($data === 'follow_train') {
            writeLog("User selected follow by train");
            answerCallbackQuery($callback->id);
            
            if (!isset($state['trains']) || empty($state['trains'])) {
                editMessageText($chat_id, $message_id, "❌ Poyezdlar ma'lumoti topilmadi. Qaytadan qidirib ko'ring.\n\n🔄 Yangi qidiruv uchun /start ni bosing");
                return;
            }
            
            // Create train selection keyboard
            $trainButtons = [];
            $trainRow = [];
            foreach ($state['trains'] as $train) {
                $num = isset($train['number']) ? $train['number'] : '';
                $brand = isset($train['brand']) ? $train['brand'] : '';
                $label = trim(($brand ? ($brand . ' ') : '') . $num);
                if ($label) {
                    $trainRow[] = ['text' => $label, 'callback_data' => 'follow_specific:' . $num];
                    if (count($trainRow) === 2) {
                        $trainButtons[] = $trainRow;
                        $trainRow = [];
                    }
                }
            }
            if (count($trainRow) > 0) {
                $trainButtons[] = $trainRow;
            }
            
            $trainKeyboard = inlineKeyboard($trainButtons);
            editMessageText($chat_id, $message_id, "🚂 <b>Qaysi poyezdni kuzatmoqchisiz?</b>\n\nPoyezdni tanlang:", ['reply_markup' => $trainKeyboard]);
            
            $state['step'] = 'choose_train_follow';
            saveState($chat_id, $state);
            return;
        }
        
        if (strpos($data, 'follow_specific:') === 0) {
            $trainNumber = substr($data, 16);
            writeLog("User selected to follow train: {$trainNumber}");
            answerCallbackQuery($callback->id, "Poyezd kuzatuvi yoqildi!");
            
            $depStation = getStationByCode($map, $state['dep'] ?? '');
            $arvStation = getStationByCode($map, $state['arv'] ?? '');
            $depName = $depStation ? $depStation['name'] : ($state['dep'] ?? '');
            $arvName = $arvStation ? $arvStation['name'] : ($state['arv'] ?? '');
            
            // Find the specific train info
            $selectedTrain = null;
            if (isset($state['trains'])) {
                foreach ($state['trains'] as $train) {
                    if (isset($train['number']) && $train['number'] === $trainNumber) {
                        $selectedTrain = $train;
                        break;
                    }
                }
            }
            
            $trainInfo = $selectedTrain ? ("\nPoyezd: " . (isset($selectedTrain['brand']) ? $selectedTrain['brand'] . ' ' : '') . $trainNumber) : "\nPoyezd: {$trainNumber}";
            
            // Save train follow preference
            $followId = addFollow($chat_id, 'train', $state['dep'], $state['arv'], $state['date'], $trainNumber);
            writeLog("Created train follow with ID: {$followId}");
            
            editMessageText($chat_id, $message_id, "🚂 <b>Poyezd kuzatuvi yoqildi</b>\n\n" .
                "Yo'nalish: {$depName} → {$arvName}\n" .
                "Sana: {$state['date']}{$trainInfo}\n\n" .
                "✅ Agar bu poyezdda joy chiqsa, sizga xabar beramiz!", [
                'reply_markup' => inlineKeyboard([
                    [['text' => '📋 Mening kuzatuvlarim', 'callback_data' => 'my_follows']],
                    [['text' => '🚄 Yangi qidiruv', 'callback_data' => 'search_trains']],
                    [['text' => '🔙 Bosh menyu', 'callback_data' => 'back_to_main']]
                ])
            ]);
            
            $state['step'] = 'idle';
            saveState($chat_id, $state);
            return;
        }
    }

    if ($text === '/start') {
        writeLog("User started conversation with /start");
        $state = ['step' => 'main_menu'];
        saveState($chat_id, $state);
        
        // Build start keyboard with two options
        $startKeyboard = [
            [['text' => '🚄 Poyezd qidirish', 'callback_data' => 'search_trains']],
            [['text' => '📋 Mening kuzatuvlarim', 'callback_data' => 'my_follows']]
        ];
        
        sendMessage($chat_id, "🚄 <b>Temir yo'l biletlari xizmati</b>\n\nNima qilmoqchisiz?", [
            'reply_markup' => inlineKeyboard($startKeyboard)
        ]);
        return;
    }

    if (($state['step'] ?? 'idle') === 'input_date' && $text) {
        writeLog("Received text for date input: {$text}");
        
        // Delete user's message for cleaner interface
        deleteMessage($chat_id, $message_id);
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($text))) {
            $state['date'] = trim($text);
            saveState($chat_id, $state);
            writeLog("Fetching trains for date: {$state['date']}, dep: {$state['dep']}, arv: {$state['arv']}");
            
            $depStation = getStationByCode($map, $state['dep'] ?? '');
            $arvStation = getStationByCode($map, $state['arv'] ?? '');
            $depName = $depStation ? $depStation['name'] : ($state['dep'] ?? '');
            $arvName = $arvStation ? $arvStation['name'] : ($state['arv'] ?? '');
            
            $resp = railwayApiFetchTrains($state['date'], $state['dep'], $state['arv']);
            writeLog("API response: " . json_encode($resp));
            if (isset($resp['error'])) {
                sendMessage($chat_id, "❌ Xatolik: " . htmlspecialchars($resp['error']) . "\n\n🔄 Qaytadan urining: /start");
                return;
            }
            $trains = extractTrainList($resp);
            writeLog("Extracted " . count($trains) . " trains");
            
            $msg = "🚂 <b>{$depName} → {$arvName}</b>\n📅 {$state['date']}\n\n" . formatTrainsMessage($trains);
            
            // Create follow button
            $followKeyboard = inlineKeyboard([[
                ['text' => '📅 Follow by Day', 'callback_data' => 'follow_day'],
                ['text' => '🚂 Follow by Train', 'callback_data' => 'follow_train']
            ]]);
            
            sendMessage($chat_id, $msg, ['reply_markup' => $followKeyboard]);
            
            // Store trains data for follow functionality
            $state['trains'] = $trains;
            $state['step'] = 'show_results';
            saveState($chat_id, $state);
            return;
        } else {
            writeLog("Invalid date format received: {$text}");
            sendMessage($chat_id, "❌ Noto'g'ri sana formati. Iltimos, YYYY-MM-DD ko'rinishida kiriting.\n\nMasalan: " . date('Y-m-d', time() + 86400));
            return;
        }
    }

    if (($state['step'] ?? 'idle') !== 'idle') {
        if (($state['step'] ?? '') === 'choose_dep') {
            $kb = buildStationKeyboard($map, 'dep');
            sendMessage($chat_id, "Iltimos, ketish stansiyasini tanlang:", [ 'reply_markup' => $kb ]);
        } elseif (($state['step'] ?? '') === 'choose_arv') {
            $kb = buildStationKeyboard($map, 'arv');
            sendMessage($chat_id, "Iltimos, borish stansiyasini tanlang:", [ 'reply_markup' => $kb ]);
        } elseif (($state['step'] ?? '') === 'input_date') {
            sendMessage($chat_id, "Sana kiriting (YYYY-MM-DD):");
        }
        return;
    }

    if ($text) {
        writeLog("Unhandled text message: {$text}");
        deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "❓ Buyruq topilmadi.\n\n🚄 Boshlash uchun /start ni bosing");
    }
    
    writeLog("End of handleUpdate processing");
}
