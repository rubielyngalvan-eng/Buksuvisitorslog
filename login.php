<?php
session_start();

// ── Load environment variables ───────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection
$host = 'localhost';
$db   = 'buksuvisitorslogdb';
$user = 'root';
$pass = ''; // Default XAMPP password is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = '';
$portal_type = isset($_GET['type']) ? $_GET['type'] : 'visitor';

// Pick up any error passed back from the Google callback
$googleError = '';
if (!empty($_SESSION['google_error'])) {
    $googleError = $_SESSION['google_error'];
    unset($_SESSION['google_error']);
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff') {
        header('Location: admin_dashboard.php');
        exit;
    } else {
        header('Location: visitor_dashboard.php');
        exit;
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // ── reCAPTCHA v2 Verification ──
        $recaptchaSecret   = $_ENV['RECAPTCHA_SECRET_KEY'];
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

        if (empty($recaptchaResponse)) {
            $error = 'Please complete the reCAPTCHA verification.';
        } else {
            $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
            $verifyData = http_build_query([
                'secret'   => $recaptchaSecret,
                'response' => $recaptchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ]);
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $verifyData,
                ],
            ]);
            $verifyResult = file_get_contents($verifyUrl, false, $context);
            $verifyJson   = json_decode($verifyResult, true);

            if (!$verifyJson['success']) {
                $error = 'reCAPTCHA verification failed. Please try again.';
            } else {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    if (password_verify($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];

                        if ($user['role'] === 'visitor') {
                            $visitorStmt = $pdo->prepare("SELECT id FROM visitors WHERE email = ? LIMIT 1");
                            $visitorStmt->execute([$user['email']]);
                            $visitorRow = $visitorStmt->fetch(PDO::FETCH_ASSOC);

                            if ($visitorRow) {
                                $_SESSION['visitor_id'] = $visitorRow['id'];
                            } else {
                                $insertVisitor = $pdo->prepare("INSERT INTO visitors (full_name, email) VALUES (?, ?)");
                                $insertVisitor->execute([$user['name'], $user['email']]);
                                $_SESSION['visitor_id'] = $pdo->lastInsertId();
                            }
                        }

                        // Redirect based on role and portal type
                        if ($user['role'] === 'admin' || $user['role'] === 'staff') {
                            header('Location: admin_dashboard.php');
                        } elseif ($user['role'] === 'visitor') {
                            header('Location: visitor_dashboard.php');
                        } else {
                            header('Location: visitor_dashboard.php');
                        }
                        exit;
                    } else {
                        $error = 'Invalid password.';
                    }
                } else {
                    $error = 'Invalid email.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BukSU Visitor Log - Login</title>

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet"/>

  <!-- Google reCAPTCHA v2 -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <style>
    /* ── Reset & Base ── */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #001f3f;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    /* ── Card Container ── */
    .card {
      background-color: #ffffff;
      width: 100%;
      max-width: 420px;
      border-radius: 12px;
      padding: 40px 36px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.75);    
    }

    /* ── Logo / Header ── */
    .logo {
      text-align: center;
      margin-bottom: 28px;
    }

    .logo img {
      width: 64px;
      height: 64px;
      margin-bottom: 10px;
    }

    .logo h1 {
      font-size: 20px;
      font-weight: 600;
      color: #1a3a5c;
    }

    .logo p {
      font-size: 13px;
      color: #6b7280;
      margin-top: 4px;
    }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid #e5e7eb;
      margin-bottom: 24px;
    }

    /* ── Error Message ── */
    .error {
      background-color: #fdecea;
      color: #c62828;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 18px;
      text-align: center;
    }

    /* ── Form Groups ── */
    .form-group {
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: #374151;
      margin-bottom: 6px;
    }

    .form-group input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 14px;
      font-family: 'Poppins', sans-serif;
      color: #111827;
      outline: none;
      transition: border-color 0.2s;
    }

    /* Highlight input when focused */
    .form-group input:focus {
      border-color: #1a3a5c;
    }

    /* ── Forgot Password ── */
    .forgot {
      text-align: right;
      margin-top: -10px;
      margin-bottom: 20px;
    }

    .forgot a {
      font-size: 12px;
      color: #1a3a5c;
      text-decoration: none;
    }

    .forgot a:hover {
      text-decoration: underline;
    }

    /* ── Login Button ── */
    .btn-login {
      width: 100%;
      padding: 11px;
      background-color: #1a3a5c;
      color: #ffffff;
      font-size: 14px;
      font-weight: 600;
      font-family: 'Poppins', sans-serif;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .btn-login:hover {
      background-color: #14304d;
    }

    /* ── Sign Up Link ── */
    .signup-link {
      text-align: center;
      margin-top: 20px;
      font-size: 13px;
      color: #6b7280;
    }

    .signup-link a {
      color: #1a3a5c;
      font-weight: 500;
      text-decoration: none;
    }

    .signup-link a:hover {
      text-decoration: underline;
    }

    /* ── Back Link ── */
    .back-link {
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
      color: #6b7280;
    }

    .back-link a {
      color: #1a3a5c;
      text-decoration: none;
    }

    .back-link a:hover {
      text-decoration: underline;
    }

    /* ── reCAPTCHA container ── */
    .recaptcha-wrap {
      display: flex;
      justify-content: center;
      margin-bottom: 18px;
    }

    /* ── Divider with OR text ── */
    .or-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 20px 0;
      color: #9ca3af;
      font-size: 12px;
    }
    .or-divider::before,
    .or-divider::after {
      content: '';
      flex: 1;
      border-top: 1px solid #e5e7eb;
    }

    /* ── Google Sign-In Button ── */
    .btn-google {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 11px;
      background-color: #ffffff;
      color: #374151;
      font-size: 14px;
      font-weight: 500;
      font-family: 'Poppins', sans-serif;
      border: 1.5px solid #d1d5db;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      transition: background-color 0.2s, border-color 0.2s, box-shadow 0.2s;
    }
    .btn-google:hover {
      background-color: #f9fafb;
      border-color: #9ca3af;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .btn-google svg {
      flex-shrink: 0;
    }
  </style>
</head>
<body>

  <!-- Main login card -->
  <div class="card">

    <!-- Header / Logo area -->
    <div class="logo">
      <img src="Assets/Shield.png" alt="BukSU Logo" />
      <h1>Visitor Log System</h1>
      <p>Bukidnon State University</p>
    </div>

    <hr class="divider" />

    <!-- Error / Google Error Messages -->
    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($googleError): ?>
      <div class="error"><?php echo htmlspecialchars($googleError); ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form action="login.php" method="POST">

      <!-- Email Field -->
      <div class="form-group">
        <label for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="Enter your email"
          required
        />
      </div>

      <!-- Password Field -->
      <div class="form-group">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="Enter your password"
          required
        />
      </div>

      <!-- Forgot Password -->
      <div class="forgot">
        <a href="forgot_password.php">Forgot password?</a>
      </div>

      <!-- reCAPTCHA Widget -->
      <div class="recaptcha-wrap">
        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($_ENV['RECAPTCHA_SITE_KEY']); ?>"></div>
      </div>

      <!-- Submit Button -->
      <button type="submit" class="btn-login">Log In</button>

    </form>

    <!-- OR Divider -->
    <div class="or-divider">or</div>

    <!-- Sign in with Google -->
    <a id="btn-google-signin" href="googleAuth/google_login.php" class="btn-google">
      <!-- Google "G" logo -->
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        <path fill="none" d="M0 0h48v48H0z"/>
      </svg>
      Sign in with Google
    </a>

    <!-- Link to Sign Up page -->
    <div class="signup-link">
      Don't have an account? <a href="signup.php">Sign up here</a>
    </div>

    <!-- Back to Home -->
    <div class="back-link">
      <a href="index.php">← Back to Home</a>
    </div>

  </div>

</body>
</html>