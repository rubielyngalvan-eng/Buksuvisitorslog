<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "vendor/autoload.php";
session_start();

// ── Database Connection ──
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

// ── Handle POST Request ──
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: forgot_password.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $foundUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($foundUser) {
        $reset_code = rand(100000, 999999);
        $expires    = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Save reset code + expiry to DB
        // Make sure your users table has: reset_code INT, reset_expires DATETIME
        // Run this SQL if not yet added:
        // ALTER TABLE users ADD COLUMN reset_code INT;
        // ALTER TABLE users ADD COLUMN reset_expires DATETIME;
        $update = $pdo->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE email = ?");
        $update->execute([$reset_code, $expires, $email]);

        $_SESSION['email'] = $email;

        // ── Send Email via PHPMailer ──
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'allynmatt03@gmail.com';
            $mail->Password   = 'jahk mqsg vhin safu';  
            // $mail->Username   = 'rubielyngalvan@gmail.com';   // Your Gmail
           // $mail->Password   = 'rayy dvly rvsy dqts';       // Your 16-char Google App Password
                                                              // Generate at: Google Account > Security > 2-Step Verification > App Passwords
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('allynmatt03@gmail.com', 'BukSU Visitor Log System');
            $mail->addAddress($email, $foundUser['full_name'] ?? '');

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Code - BukSU Visitor Log';
            $mail->Body    = "
                <div style='font-family: Poppins, sans-serif; max-width: 480px; margin: auto; padding: 32px; background: #f9fafb; border-radius: 12px;'>
                    <h2 style='color: #1a3a5c; margin-bottom: 8px;'>Password Reset</h2>
                    <p style='color: #6b7280; font-size: 14px;'>Use the verification code below to reset your password. This code expires in <strong>15 minutes</strong>.</p>
                    <div style='text-align: center; margin: 28px 0;'>
                        <span style='font-size: 36px; font-weight: 700; letter-spacing: 10px; color: #1a3a5c;'>$reset_code</span>
                    </div>
                    <p style='color: #9ca3af; font-size: 12px;'>If you did not request this, you can safely ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'/>
                    <p style='color: #9ca3af; font-size: 11px; text-align: center;'>Bukidnon State University &mdash; Visitor Log System</p>
                </div>
            ";
            $mail->AltBody = "Your BukSU Visitor Log password reset code is: $reset_code. It expires in 15 minutes.";

            $mail->send();

            $_SESSION['email_sent'] = true;
            $_SESSION['Success']    = "Verification code sent to your email.";
            header("Location: verify-code.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['error'] = "Could not send email. Please try again later.";
            // Log actual error server-side for debugging (not shown to user)
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            header("Location: forgot_password.php");
            exit();
        }

    } else {
        // Generic message to prevent email enumeration
        $_SESSION['Success'] = "If that email is registered, a verification code has been sent.";
        header("Location: forgot_password.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BukSU Visitor Log - Forgot Password</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet"/>

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #001f3f;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    .card {
      background-color: #ffffff;
      width: 100%;
      max-width: 420px;
      border-radius: 12px;
      padding: 40px 36px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.75);
    }

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

    .divider {
      border: none;
      border-top: 1px solid #e5e7eb;
      margin-bottom: 24px;
    }

    .instruction {
      font-size: 13px;
      color: #6b7280;
      text-align: center;
      margin-bottom: 20px;
      line-height: 1.6;
    }

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
      color: #6b7280;
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

    <div class="logo">
      <img src="Assets/Shield.png" alt="BukSU Logo"/>
      <h1>Visitor Log System</h1>
      <p>Bukidnon State University</p>
    </div>

    <hr class="divider"/>

    <p class="instruction">
      Enter the email address linked to your account and we'll send you a 6-digit verification code.
    </p>

    <?php if (isset($_SESSION['Success'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['Success']) ?></div>
      <?php unset($_SESSION['Success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form action="forgot_password.php" method="POST">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="Enter your email"
          required
          autocomplete="email"
        />
      </div>

      <button type="submit" class="btn-login">Send Verification Code</button>
    </form>

    <div class="signup-link">
      Don't have an account? <a href="signup.php">Sign up here</a>
    </div>

    <div class="back-link">
      <a href="login.php">← Back to Login</a>
    </div>

  </div>

</body>
</html>