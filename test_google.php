<?php

echo "=== PHP Google Connectivity Test ===\n\n";

// 1. cURL Test
echo "1. cURL Test:\n";
$ch = curl_init('https://www.googleapis.com/oauth2/v3/certs');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$start = microtime(true);
$response = curl_exec($ch);
$elapsed = round(microtime(true) - $start, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
echo "   Time: {$elapsed}s\n";
echo "   Error: " . ($curlError ?: 'none') . "\n";
echo "   Response length: " . strlen($response) . " bytes\n\n";

// 2. file_get_contents Test
echo "2. file_get_contents Test:\n";
$start = microtime(true);
$ctx = stream_context_create([
    'http' => ['timeout' => 10],
    'ssl' => ['verify_peer' => true],
]);
$response2 = @file_get_contents('https://www.googleapis.com/oauth2/v3/certs', false, $ctx);
$elapsed2 = round(microtime(true) - $start, 2);
echo "   Time: {$elapsed2}s\n";
echo "   Response length: " . strlen($response2 ?: '') . " bytes\n\n";

// 3. Guzzle Test (google/apiclient ይጠቀመዋል)
echo "3. Guzzle Test (google/apiclient uses this):\n";
require __DIR__ . '/vendor/autoload.php';

try {
    $start = microtime(true);
    $client = new \GuzzleHttp\Client([
        'timeout' => 10,
        'verify' => true,
    ]);
    $res = $client->get('https://www.googleapis.com/oauth2/v3/certs');
    $elapsed3 = round(microtime(true) - $start, 2);
    echo "   Status: " . $res->getStatusCode() . "\n";
    echo "   Time: {$elapsed3}s\n";
    echo "   Body length: " . strlen($res->getBody()) . " bytes\n";
} catch (\Exception $e) {
    echo "   ❌ Guzzle Error: " . $e->getMessage() . "\n";
    echo "   Error class: " . get_class($e) . "\n";
}

echo "\n=== Done ===\n";
