<?php
require_once __DIR__ . '/vendor/autoload.php';

// Load .env from the project root
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Require these keys to exist in .env
$dotenv->required(['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI']);

// Make available as constants
define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI',  $_ENV['GOOGLE_REDIRECT_URI']);
