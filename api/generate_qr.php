<?php
/**
 * QR Code Generation API
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

header('Content-Type: image/png');

$qr_code = $_GET['code'] ?? '';

if (empty($qr_code)) {
    // Generate error image
    $img = imagecreate(200, 200);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $text_color = imagecolorallocate($img, 255, 0, 0);
    imagestring($img, 3, 50, 90, "Invalid QR Code", $text_color);
    imagepng($img);
    imagedestroy($img);
    exit;
}

// Simple QR code generation using text-based approach
// In production, you would use a proper QR code library
$img = imagecreate(300, 300);
$bg = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 0, 0, 0);

// Add border
imagerectangle($img, 10, 10, 290, 290, $black);

// Add QR code placeholder (simplified pattern)
// This is a basic pattern - in production use a real QR library
$pattern = generateQRPattern($qr_code);
$y = 20;
foreach ($pattern as $row) {
    $x = 20;
    for ($i = 0; $i < strlen($row); $i++) {
        if ($row[$i] === '1') {
            imagefilledrectangle($img, $x, $y, $x + 8, $y + 8, $black);
        }
        $x += 10;
    }
    $y += 10;
}

// Add text below QR code
$text_color = imagecolorallocate($img, 0, 0, 0);
imagestring($img, 3, 10, 280, substr($qr_code, 0, 30), $text_color);

imagepng($img);
imagedestroy($img);

function generateQRPattern($code) {
    // Simple pattern generator based on code hash
    $hash = md5($code);
    $pattern = [];
    
    for ($i = 0; $i < 26; $i++) {
        $row = '';
        for ($j = 0; $j < 26; $j++) {
            $index = ($i * 26 + $j) % strlen($hash);
            $row .= (hexdec($hash[$index]) % 3 === 0) ? '1' : '0';
        }
        $pattern[] = $row;
    }
    
    return $pattern;
}
?>
