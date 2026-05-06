<?php
session_start();

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
$success = '';

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $full_name = $first_name . ' ' . $last_name;

                // Automatically assign admin role if email ends with @buksu.edu.ph
                $role = str_ends_with($email, '@buksu.edu.ph') ? 'admin' : 'visitor';

                // Insert into users table
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$full_name, $email, $hashed_password, $role]);

                $user_id = $pdo->lastInsertId();

                // Only insert into visitors table if they are a regular visitor, not admin
                if ($role === 'visitor') {
                    $stmt = $pdo->prepare("INSERT INTO visitors (full_name, address, contact_number, email) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$full_name, $address, $phone, $email]);
                }
                
                $pdo->commit();
                header('Location: login.php?registered=1');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>BukSU Visitor Log - Sign Up</title>

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />

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
      padding: 30px 16px;
    }

    /* ── Card Container ── */
    .card {
      background-color: #ffffff;
      width: 100%;
      max-width: 480px;
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

    /* ── Success Message ── */
    .success {
      background-color: #e8f5e9;
      color: #2e7d32;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 18px;
      text-align: center;
    }

    /* ── Section Label ── */
    .section-label {
      font-size: 12px;
      font-weight: 600;
      color: #1a3a5c;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 14px;
    }

    /* ── Two columns side by side ── */
    .row {
      display: flex;
      gap: 14px;
    }

    .row .form-group {
      flex: 1;
    }

    /* ── Form Groups ── */
    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: #374151;
      margin-bottom: 6px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 14px;
      font-family: 'Poppins', sans-serif;
      color: #111827;
      outline: none;
      transition: border-color 0.2s;
      background-color: #ffffff;
    }

    /* Highlight input when focused */
    .form-group input:focus,
    .form-group select:focus {
      border-color: #1a3a5c;
    }

    /* ── Sign Up Button ── */
    .btn-signup {
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
      margin-top: 6px;
      transition: background-color 0.2s;
    }

    .btn-signup:hover {
      background-color: #14304d;
    }

    /* ── Log In Link ── */
    .login-link {
      text-align: center;
      margin-top: 20px;
      font-size: 13px;
      color: #6b7280;
    }

    .login-link a {
      color: #1a3a5c;
      font-weight: 500;
      text-decoration: none;
    }

    .login-link a:hover {
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

    /* ── Small note under password ── */
    .hint {
      font-size: 11px;
      color: #9ca3af;
      margin-top: 4px;
    }
  </style>
</head>

<body>

  <!-- Main signup card -->
  <div class="card">

    <!-- Header / Logo area -->
    <div class="logo">
      <img src="Assets/Shield.png" alt="BukSU Logo" />
      <h1>Create an Account</h1>
      <p>Bukidnon State University — Visitor Log System</p>
    </div>

    <hr class="divider" />

    <!-- Error/Success Messages -->
    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Sign Up Form -->
    <form action="signup.php" method="POST">

      <!-- Section: Personal Information -->
      <p class="section-label">Personal Information</p>

      <!-- First Name and Last Name side by side -->
      <div class="row">
        <div class="form-group">
          <label for="first_name">First Name *</label>
          <input type="text" id="first_name" name="first_name" placeholder="e.g. Maria" required />
        </div>
        <div class="form-group">
          <label for="last_name">Last Name *</label>
          <input type="text" id="last_name" name="last_name" placeholder="e.g. Santos" required />
        </div>
      </div>

      <!-- Address -->
      <div class="form-group">
        <label for="address">Address</label>
        <input type="text" id="address" name="address" placeholder="City / Municipality" />
      </div>

      <!-- Phone Number -->
      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" placeholder="e.g. 09171234567" />
      </div>


      <!-- Section: Account Details -->
      <p class="section-label" style="margin-top: 8px;">Account Details</p>

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required />
        <!-- Hint: BukSU email gets admin role automatically -->
        <p class="hint">Use your <b>@buksu.edu.ph</b> email to register as an admin/staff.</p>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label for="password">Password *</label>
        <input type="password" id="password" name="password" placeholder="Create a password" required />
        <!-- Small hint text below the field -->
        <p class="hint">At least 8 characters</p>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label for="confirm_password">Confirm Password *</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password"
          required />
      </div>

      <!-- Submit Button -->
      <button type="submit" class="btn-signup">Create Account</button>


    </form>

    <!-- Link back to Login page -->
    <div class="login-link">
      Already have an account? <a href="login.php">Log in here</a>
    </div>

    <!-- Back to Home -->
    <div class="back-link">
      <a href="index.php">← Back to Home</a>
    </div>

  </div>

</body>

</html>