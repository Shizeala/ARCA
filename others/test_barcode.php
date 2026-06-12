<?php
require '/../config.php';

$image = "test.png"; // your uploaded image with barcode

function decode_barcode($image_path) {
    $fullPath = __DIR__ . '/' . $image_path;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://zxing.org/w/decode");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "file" => new CURLFile($fullPath)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (preg_match('/Raw text<\/td><td[^>]*>(.*?)<\/td>/', $response, $m)) {
        return trim($m[1]);
    }

    return null;
}

$result = decode_barcode($image);

echo "Decoded: " . ($result ?? "FAILED");