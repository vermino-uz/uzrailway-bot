<?php
require_once __DIR__ . '/monitor.php';

echo "🔄 Refreshing cookies and XSRF token...\n";

$result = refreshCookies();
if ($result) {
    echo "✅ Cookies refreshed successfully!\n";
    echo "XSRF Token: " . $result['xsrf'] . "\n";
    echo "Cookies saved to: " . getCookiesFile() . "\n";
    
    // Test the new cookies
    echo "\n🧪 Testing new cookies...\n";
    $testResult = railwayApiFetchTrains('2025-08-17', '2900000', '2900700');
    if (isset($testResult['error'])) {
        echo "❌ Test failed: " . $testResult['error'] . "\n";
    } elseif (isset($testResult['data'])) {
        echo "✅ Test successful - API responding correctly\n";
    } else {
        echo "⚠️ Test result unclear: " . json_encode($testResult) . "\n";
    }
} else {
    echo "❌ Failed to refresh cookies\n";
    exit(1);
}
?>
