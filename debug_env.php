<?php
// Quick debug — delete this file after fixing!
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo '<pre>';
echo 'RECAPTCHA_SITE_KEY   = ' . ($_ENV['6Le019ksAAAABrLVwGWudQXF9OIWHpmKxFmRWWx']   ?? 'NOT LOADED') . "\n";
echo 'RECAPTCHA_SECRET_KEY = ' . substr($_ENV['6Le019ksAAAABFQuL1f6QPAwo9A1Y2inJmaZ2xJ'] ?? 'NOT LOADED', 0, 10) . '...' . "\n";
echo 'GOOGLE_CLIENT_ID     = ' . substr($_ENV['896541389889-4uvs5bk7uqkqpg6cfhb5hrbgjil99698.apps.googleusercontent.com'] ?? 'NOT LOADED', 0, 20) . '...' . "\n";
echo '</pre>';
