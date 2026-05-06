<?php
session_start();

require_once __DIR__ . '/../google_config.php'; // loads vendor/autoload.php + .env

// ── Database connection ───────────────────────────────────────────────────────
$host = 'localhost';
$db   = 'buksuvisitorslogdb';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ── Helper: redirect with error ───────────────────────────────────────────────
function redirectWithError(string $msg): void {
    $_SESSION['google_error'] = $msg;
    header('Location: /BVLMS-V8/login.php');
    exit;
}

// ── Set up Google Client ──────────────────────────────────────────────────────
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope('email');
$client->addScope('profile');

// ── Step 1: Validate CSRF state token ────────────────────────────────────────
$returnedState = $_GET['state'] ?? '';
$storedState   = $_SESSION['google_oauth_state'] ?? '';

if (empty($storedState) || !hash_equals($storedState, $returnedState)) {
    redirectWithError('Invalid session state. Please try signing in again.');
}
unset($_SESSION['google_oauth_state']); // one-time use

// ── Step 2: Check for errors from Google ─────────────────────────────────────
if (isset($_GET['error'])) {
    redirectWithError('Google sign-in was cancelled or denied: ' . htmlspecialchars($_GET['error']));
}

// ── Step 3: Exchange authorization code for access token ─────────────────────
$code = $_GET['code'] ?? '';
if (empty($code)) {
    redirectWithError('No authorization code received from Google.');
}

try {
    $token = $client->authenticate($code);
    $client->setAccessToken($token);
} catch (Exception $e) {
    redirectWithError('Failed to authenticate with Google: ' . $e->getMessage());
}

if ($client->isAccessTokenExpired()) {
    redirectWithError('Google access token expired. Please try again.');
}

// ── Step 4: Fetch Google user profile ────────────────────────────────────────
$oauth2     = new Google_Service_Oauth2($client);
$googleUser = $oauth2->userinfo->get();

$googleId    = $googleUser->getId();
$googleName  = $googleUser->getName();
$googleEmail = $googleUser->getEmail();
$googlePic   = $googleUser->getPicture();
$verified    = $googleUser->getVerifiedEmail();

if (empty($googleId) || empty($googleEmail)) {
    redirectWithError('Could not retrieve your Google account information.');
}

if (!$verified) {
    redirectWithError('Your Google email is not verified. Please verify it and try again.');
}

// ── Step 5: Find or auto-register the user ───────────────────────────────────

// 5a. Look up by google_id
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
$stmt->execute([$googleId]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

// 5b. Look up by email and link google_id if found
if (!$dbUser) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$googleEmail]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dbUser) {
        // Link Google ID to the existing account
        $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
        $stmt->execute([$googleId, $dbUser['id']]);
    }
}

// 5c. Auto-register as a new visitor
if (!$dbUser) {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, google_id, role, status, created_at)
         VALUES (?, ?, ?, 'visitor', 'active', NOW())"
    );
    $stmt->execute([$googleName, $googleEmail, $googleId]);
    $newId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$newId]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Step 6: Check account status ─────────────────────────────────────────────
if ($dbUser['status'] !== 'active') {
    redirectWithError('Your account has been deactivated. Please contact the administrator.');
}

// ── Step 7: Create session and redirect ──────────────────────────────────────
$_SESSION['user_id']      = $dbUser['id'];
$_SESSION['name']         = $dbUser['name'];
$_SESSION['email']        = $dbUser['email'];
$_SESSION['role']         = $dbUser['role'];
$_SESSION['google_login'] = true;
$_SESSION['google_pic']   = $googlePic;

if ($dbUser['role'] === 'visitor') {
    $visitorStmt = $pdo->prepare("SELECT id FROM visitors WHERE email = ? LIMIT 1");
    $visitorStmt->execute([$dbUser['email']]);
    $visitorRow = $visitorStmt->fetch(PDO::FETCH_ASSOC);

    if ($visitorRow) {
        $_SESSION['visitor_id'] = $visitorRow['id'];
    } else {
        $insertVisitor = $pdo->prepare("INSERT INTO visitors (full_name, email) VALUES (?, ?)");
        $insertVisitor->execute([$dbUser['name'], $dbUser['email']]);
        $_SESSION['visitor_id'] = $pdo->lastInsertId();
    }
}

if ($dbUser['role'] === 'admin' || $dbUser['role'] === 'staff') {
    header('Location: /BVLMS-V8/admin_dashboard.php');
} else {
    header('Location: /BVLMS-V8/visitor_dashboard.php');
}
exit;
