<?php
session_start();

require_once __DIR__ . '/../google_config.php'; // load vendor/autoload.php + .env

// ── Guard: already logged in ──────────────────────────────────────────────────
if (isset($_SESSION['user_id'])) {
    $dest = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff')
        ? '/BVLMS-V8/admin_dashboard.php'
        : '/BVLMS-V8/visitor_dashboard.php';
    header("Location: $dest");
    exit;
}

// ── Set up Google Client ──────────────────────────────────────────────────────
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope('email');
$client->addScope('profile');
$client->setAccessType('online');
$client->setApprovalPrompt('force'); // Always show account picker

// ── Generate CSRF state token ─────────────────────────────────────────────────
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

// ── Redirect to Google ────────────────────────────────────────────────────────
header('Location: ' . $client->createAuthUrl());
exit;
