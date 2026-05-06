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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BukSU Visitor Log Monitoring System</title>

  
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>

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
      color: #1a1a1a;
    }

    /* ── Navbar ── */
    .navbar {
      background-color: #2e7d32;
      padding: 14px 40px;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .navbar-logo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .navbar-logo img {
      width: 36px;
      height: 36px;
      object-fit: contain;
    }

    .navbar-text h2 {
      font-size: 15px;
      font-weight: 600;
      color: #ffffff;
      line-height: 1.2;
    }

    .navbar-text p {
      font-size: 11px;
      color: #c8e6c9;
    }

    /* ── Main Content ── */
    .main {
      max-width: 900px;
      margin: 40px auto;
      padding: 0 20px;
    }

    /* ── Hero Card ── */
    .hero-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 50px 40px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.50);    
      margin-bottom: 28px;
    }

    /* Shield icon circle */
    .hero-icon {
      width: 64px;
      height: 64px;
      background-color: #e8f5e9;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px auto;
    }

    .hero-icon svg {
      width: 30px;
      height: 30px;
      color: #2e7d32;
    }

    .hero-card h1 {
      font-size: 24px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 10px;
    }

    .hero-card p {
      font-size: 13px;
      color: #6b7280;
      max-width: 500px;
      margin: 0 auto 28px auto;
      line-height: 1.7;
    }

    /* ── Single Login Button ── */
    .btn-login {
      padding: 12px 40px;
      background-color: #2e7d32;
      color: #ffffff;
      font-size: 15px;
      font-weight: 600;
      font-family: 'Poppins', sans-serif;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .btn-login:hover {
      background-color: #256427;
    }

    /* ── Feature Cards Row ── */
    .features {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }

    .feature-card {
      background-color: #ffffff;
      border-radius: 10px;
      padding: 20px 16px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.50);    
    }

    /* Icon circle for each feature */
    .feature-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 12px;
    }

    .feature-icon svg {
      width: 20px;
      height: 20px;
    }

    /* Different background colors per feature */
    .icon-blue   { background-color: #e8f0fe; color: #1a73e8; }
    .icon-green  { background-color: #e8f5e9; color: #2e7d32; }
    .icon-purple { background-color: #f3e8fd; color: #7b1fa2; }
    .icon-red    { background-color: #fdecea; color: #c62828; }

    .feature-card h3 {
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 6px;
    }

    .feature-card p {
      font-size: 12px;
      color: #6b7280;
      line-height: 1.6;
    }

    /* ── About Card ── */
    .about-card {
      background-color: #ffffff;
      border-radius: 12px;
      padding: 30px 32px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.50);    
    }

    .about-card h2 {
      font-size: 17px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 10px;
    }

    .about-card > p {
      font-size: 13px;
      color: #6b7280;
      line-height: 1.7;
      margin-bottom: 16px;
    }

    .about-card h4 {
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 10px;
    }

    /* ── Benefits List ── */
    .benefits-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .benefits-list li {
      font-size: 13px;
      color: #374151;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }

    /* Bullet dot */
    .benefits-list li::before {
      content: "•";
      color: #2e7d32;
      font-weight: 700;
      margin-top: 1px;
    }

    /* ── Footer ── */
    .footer {
      text-align: center;
      padding: 30px;
      font-size: 12px;
      color: #9ca3af;
    }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar">
    <div class="navbar-logo">
      <img src="Assets/Shield.png" alt="BukSU Logo" />
    </div>
    <div class="navbar-text">
      <h2>BukSU</h2>
      <p>Visitor Log Monitoring System</p>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="main">

    <!-- Hero Card -->
    <div class="hero-card">

      <!-- Shield icon -->
      <div class="hero-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>

      <h1>BukSU Visitor Log Monitoring System</h1>
      <p>Bukidnon State University's digital solution for efficient, secure, and real-time visitor management</p>

      <!-- Single Login Button -->
      <div class="hero-buttons">
        <button class="btn-login" onclick="window.location.href='login.php'">Log In to the System</button>
      </div>

    </div>

    <!-- Feature Cards -->
    <div class="features">

      <!-- Visitor Registration -->
      <div class="feature-card">
        <div class="feature-icon icon-blue">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <h3>Visitor Registration</h3>
        <p>Quick and easy digital registration for all campus visitors</p>
      </div>

      <!-- Real-Time Monitoring -->
      <div class="feature-card">
        <div class="feature-icon icon-green">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <h3>Real-Time Monitoring</h3>
        <p>Live dashboard for security personnel to track all visitors</p>
      </div>

      <!-- Automated Reports -->
      <div class="feature-card">
        <div class="feature-icon icon-purple">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
          </svg>
        </div>
        <h3>Automated Reports</h3>
        <p>Generate daily, weekly, and monthly visitor activity reports</p>
      </div>

      <!-- Emergency Search -->
      <div class="feature-card">
        <div class="feature-icon icon-red">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>
        </div>
        <h3>Emergency Search</h3>
        <p>Quick search and retrieval during emergency situations</p>
      </div>

    </div>

    <!-- About the System -->
    <div class="about-card">
      <h2>About the System</h2>
      <p>
        The BukSU Visitor Log Monitoring System is a web-based application designed to replace
        traditional manual logbooks with a modern, efficient digital platform. Developed for
        Bukidnon State University's Entrance and Security Office, this system addresses the
        challenges of manual record-keeping including time delays, data inaccuracy, and lack
        of real-time monitoring.
      </p>

      <h4>Key Benefits:</h4>

      <!-- Benefits list -->
      <ul class="benefits-list">
        <li>Eliminates queuing and reduces wait times at campus entrances</li>
        <li>Ensures complete and accurate visitor records with built-in validation</li>
        <li>Provides real-time visibility of all campus visitors</li>
        <li>Enables quick data retrieval during emergency situations</li>
        <li>Generates comprehensive reports for administrative review</li>
        <li>Maintains secure backup of all visitor data</li>
      </ul>
    </div>

  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Bukidnon State University — Visitor Log Monitoring System
  </div>

</body>
</html>