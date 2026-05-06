<?php
session_start();


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

// Guard: must have verified the reset code first
if (!isset($_SESSION['email']) || !isset($_SESSION['reset_code_verified']) || !$_SESSION['reset_code_verified']) {
    header("Location: enter_code.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword     = $_POST['newPassword']     ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['error'] = "Please fill in both password fields.";
        header("Location: reset-password.php");
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset-password.php");
        exit();
    }

    // Bug fix: password_hash + update were inside the wrong if-branch in the original
    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $_SESSION['email']]);

        // Clean up session
        unset($_SESSION['email']);
        unset($_SESSION['reset_code_verified']);

        $_SESSION['Success'] = "Your password has been reset successfully!";
        header("Location: login.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Failed to reset password: " . $e->getMessage();
        header("Location: reset-password.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BukSU Visitor Log - Reset Password</title>

  <!-- Google Font — same as login.php -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet"/>

  <style>
    /* ── Reset & Base ── */
    * { margin: 0; padding: 0; box-sizing: border-box; }

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

    /* ── Instruction ── */
    .instruction {
      font-size: 13px;
      color: #6b7280;
      text-align: center;
      margin-bottom: 20px;
      line-height: 1.6;
    }

    /* ── Alerts ── */
    .alert {
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 18px;
      text-align: center;
    }

    .alert-success {
      background-color: #dcfce7;
      color: #14532d;
    }

    .alert-danger {
      background-color: #fdecea;
      color: #c62828;
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

    .form-group input:focus {
      border-color: #1a3a5c;
    }

    .form-group input::placeholder {
      color: #9ca3af;
    }

    /* ── Password strength hint ── */
    .hint {
      font-size: 11px;
      color: #9ca3af;
      margin-top: 4px;
    }

    /* ── Submit Button — same as login.php ── */
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

    .btn-login:hover { background-color: #14304d; }

    /* ── Links — same style as login.php ── */
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

    .signup-link a:hover { text-decoration: underline; }

    .back-link {
      text-align: center;
      margin-top: 14px;
      font-size: 13px;
    }

    .back-link a {
      color: #1a3a5c;
      text-decoration: none;
    }

    .back-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>

  <div class="card">

    <!-- Logo / Header -->
    <div class="logo">
      <img src="Assets/Shield.png" alt="BukSU Logo"/>
      <h1>Visitor Log System</h1>
      <p>Bukidnon State University</p>
    </div>

    <hr class="divider"/>

    <p class="instruction">Enter and confirm your new password below.</p>

    <!-- Alerts -->
    <?php if (isset($_SESSION['Success'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['Success']) ?></div>
      <?php unset($_SESSION['Success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Form -->
    <form action="reset-password.php" method="POST" onsubmit="return validatePasswords()">

      <div class="form-group">
        <label for="newPassword">New Password</label>
        <input
          type="password"
          id="newPassword"
          name="newPassword"
          placeholder="Enter new password"
          required
        />
        <div class="hint">Minimum 8 characters recommended.</div>
      </div>

      <div class="form-group">
        <label for="confirmPassword">Confirm Password</label>
        <input
          type="password"
          id="confirmPassword"
          name="confirmPassword"
          placeholder="Re-enter new password"
          required
        />
      </div>

      <button type="submit" class="btn-login">Reset Password</button>

    </form>

    <div class="signup-link">
      Don't have an account? <a href="signup.php">Sign up here</a>
    </div>

    <div class="back-link">
      <a href="verify-code.php">← Back</a>
    </div>

  </div>

  <script>
    function validatePasswords() {
      const newPassword     = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      if (!newPassword || !confirmPassword) {
        alert('Please fill in both password fields.');
        return false;
      }
      if (newPassword !== confirmPassword) {
        alert('Passwords do not match.');
        return false;
      }
      return true;
    }
  </script>

</body>
</html>