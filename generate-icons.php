<?php
/**
 * generate-icons.php
 *
 * Run this script ONCE from the browser or CLI to generate all required
 * PWA icon PNG files inside the icons/ directory.
 *
 * Requirements: PHP GD extension must be enabled.
 *
 * Usage (CLI):  php generate-icons.php
 * Usage (web):  http://localhost/imposter-game/generate-icons.php
 *
 * After icons are generated, this file is no longer needed and can be
 * deleted for security.
 */

// Prevent timeout for large icon sets
set_time_limit(60);

if (!function_exists('imagecreatetruecolor')) {
    die('Error: PHP GD extension is required. Enable it in php.ini and restart the server.');
}

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$outputDir = __DIR__ . '/icons/';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$generated = [];
$errors    = [];

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    imageantialias($img, true);

    // Background gradient simulation (fill with primary colour)
    $bg = imagecolorallocate($img, 15, 23, 42);    // #0f172a
    imagefill($img, 0, 0, $bg);

    // Draw a rounded-rect background (solid colour approximation)
    $radius  = (int)($size * 0.22);
    $accentR = imagecolorallocate($img, 99,  102, 241); // #6366f1 – primary
    $accentY = imagecolorallocate($img, 245, 158,  11); // #f59e0b – accent
    $white   = imagecolorallocate($img, 241, 245, 249); // #f1f5f9

    // Draw filled rounded rectangle
    $margin = (int)($size * 0.06);
    imagefilledrectangle($img, $margin + $radius, $margin, $size - $margin - $radius, $size - $margin, $accentR);
    imagefilledrectangle($img, $margin, $margin + $radius, $size - $margin, $size - $margin - $radius, $accentR);
    imagefilledellipse($img, $margin + $radius,          $margin + $radius,          $radius * 2, $radius * 2, $accentR);
    imagefilledellipse($img, $size - $margin - $radius,  $margin + $radius,          $radius * 2, $radius * 2, $accentR);
    imagefilledellipse($img, $margin + $radius,          $size - $margin - $radius,  $radius * 2, $radius * 2, $accentR);
    imagefilledellipse($img, $size - $margin - $radius,  $size - $margin - $radius,  $radius * 2, $radius * 2, $accentR);

    // Draw a simple "?" glyph as the icon symbol using filled ellipses
    // For sizes >= 96 try to add a text label
    if ($size >= 96) {
        $fontSize  = max(2, (int)($size * 0.38));
        $cx        = (int)($size / 2);
        $cy        = (int)($size / 2);

        // Use built-in GD font (no TTF needed)
        $fontIndex = 5; // largest built-in font
        $charW     = imagefontwidth($fontIndex);
        $charH     = imagefontheight($fontIndex);
        $text      = '?';
        $tx        = $cx - (int)($charW / 2);
        $ty        = $cy - (int)($charH / 2);
        imagestring($img, $fontIndex, $tx, $ty, $text, $white);
    }

    $outputFile = $outputDir . 'icon-' . $size . 'x' . $size . '.png';
    if (imagepng($img, $outputFile, 6)) {
        $generated[] = 'icon-' . $size . 'x' . $size . '.png';
    } else {
        $errors[] = 'Failed to write icon-' . $size . 'x' . $size . '.png';
    }
    imagedestroy($img);
}

// Output summary
header('Content-Type: text/plain; charset=utf-8');
if (empty($errors)) {
    echo "✅ All " . count($generated) . " icons generated successfully:\n";
    foreach ($generated as $f) {
        echo "   icons/" . $f . "\n";
    }
    echo "\nYou can now delete generate-icons.php.\n";
} else {
    echo "⚠️  Generated " . count($generated) . " icons with " . count($errors) . " error(s):\n";
    foreach ($errors as $e) { echo "   ERROR: $e\n"; }
    foreach ($generated as $f) { echo "   OK: icons/$f\n"; }
}
