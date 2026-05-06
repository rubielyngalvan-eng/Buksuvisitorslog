<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Get visitor info from session
$visitor_name = $_SESSION['name'];
$visitor_id = $_SESSION['visitor_id'] ?? null;

// ── Database connection ──
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (empty($visitor_id) && isset($_SESSION['email']) && $_SESSION['role'] === 'visitor') {
    $email = $conn->real_escape_string($_SESSION['email']);
    $result = $conn->query("SELECT id FROM visitors WHERE email = '$email' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $visitor_id = $row['id'];
        $_SESSION['visitor_id'] = $visitor_id;
    }
}

if (empty($visitor_id)) {
    die('Unable to resolve visitor account. Please log in again.');
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── Stat: Pending visits ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE visitor_id = $visitor_id 
    AND status = 'pending'
");
$pending = $res->fetch_assoc()['total'];

// ── Stat: Approved visits ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE visitor_id = $visitor_id 
    AND status = 'approved'
");
$approved = $res->fetch_assoc()['total'];

// ── Stat: Completed visits ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE visitor_id = $visitor_id 
    AND status = 'completed'
");
$completed = $res->fetch_assoc()['total'];

// ── Stat: Total visits ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE visitor_id = $visitor_id
    AND status IN ('approved', 'completed')
");
$total_visits = $res->fetch_assoc()['total'];

// ── Recent visits with check-in/out times from history ──
$recent_result = $conn->query("
    SELECT 
        vl.id,
        vl.purpose,
        vl.destination,
        vl.status,
        vl.appointment_date,
        vl.created_at,
        MAX(CASE WHEN h.action = 'IN'  THEN h.timestamp END) AS check_in,
        MAX(CASE WHEN h.action = 'OUT' THEN h.timestamp END) AS check_out
    FROM visits_log vl
    LEFT JOIN history h ON h.visit_id = vl.id
    WHERE vl.visitor_id = $visitor_id
    GROUP BY vl.id
    ORDER BY vl.created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Visitor Dashboard — BukSU Visitor Log</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>

  <style>
    /* ── Reset ── */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #001f3f;
      color: #1a1a1a;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ════════════════════════
       NAVBAR (green)
    ════════════════════════ */
    .navbar {
      background-color: #2e7d32;
      padding: 0 32px;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .navbar-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .navbar-logo {
      width: 38px;
      height: 38px;
      background-color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .navbar-logo img {
      width: 34px;
      height: 34px;
      object-fit: contain;
    }

    .navbar-title h2 { font-size: 15px; font-weight: 600; color: #fff; line-height: 1.2; }
    .navbar-title p  { font-size: 10px; color: #c8e6c9; }

    .navbar-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .navbar-user p    { font-size: 13px; color: #fff; font-weight: 500; text-align: right; }
    .navbar-user span { font-size: 10px; color: #c8e6c9; display: block; text-align: right; }

    .btn-logout {
      padding: 7px 16px;
      background-color: rgba(255,255,255,0.15);
      color: #fff;
      font-size: 12px;
      font-weight: 500;
      font-family: 'Poppins', sans-serif;
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .btn-logout:hover { background-color: rgba(255,255,255,0.25); }

    /* ════════════════════════
       MAIN CONTENT
    ════════════════════════ */
    .main {
      max-width: 900px;
      margin: 0 auto;
      padding: 28px 20px;
      flex: 1;
      width: 100%;
    }

    /* ── Welcome Banner (blue) ── */
    .welcome-banner {
      background-color: #1a56db;
      border-radius: 12px;
      padding: 28px 32px;
      margin-bottom: 24px;
    }

    .welcome-banner h1 {
      font-size: 22px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 6px;
    }

    .welcome-banner p {
      font-size: 13px;
      color: #bfdbfe;
    }

    /* ── Two action cards ── */
    .action-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 24px;
    }

    .action-card {
      background-color: #fff;
      border-radius: 10px;
      padding: 20px 22px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25);
      display: flex;
      align-items: center;
      gap: 16px;
      text-decoration: none;
      transition: box-shadow 0.2s;
    }

    .action-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }

    /* Icon circle */
    .action-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .action-icon svg { width: 22px; height: 22px; }

    .icon-green  { background-color: #e8f5e9; color: #2e7d32; }
    .icon-purple { background-color: #f3e8fd; color: #7b1fa2; }

    .action-text h3 { font-size: 14px; font-weight: 600; color: #1a1a1a; }
    .action-text p  { font-size: 12px; color: #6b7280; margin-top: 3px; }

    /* ── Stat Row ── */
    .stat-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-item {
      background-color: #fff;
      border-radius: 10px;
      padding: 18px 16px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25);
    }

    /* Status label row */
    .stat-item-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 8px;
    }

    .stat-item-label svg { width: 14px; height: 14px; }

    /* Color per status */
    .label-orange { color: #d97706; }
    .label-green  { color: #16a34a; }
    .label-blue   { color: #1a56db; }
    .label-gray   { color: #6b7280; }

    .stat-item-number {
      font-size: 28px;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1;
    }

    /* ── Recent Visits Panel ── */
    .panel {
      background-color: #fff;
      border-radius: 10px;
      padding: 22px;
      box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25);
      margin-bottom: 28px;
    }

    .panel h3 {
      font-size: 15px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 16px;
    }

    /* ── Visit Card ── */
    .visit-card {
      border: 1px solid #f0f0f0;
      border-radius: 8px;
      padding: 16px 18px;
      margin-bottom: 12px;
    }

    .visit-card:last-child { margin-bottom: 0; }

    /* Top row: purpose + status badge */
    .visit-card-top {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 10px;
    }

    .visit-card-top strong {
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
    }

    /* Status badges */
    .badge {
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 4px;
      white-space: nowrap;
    }

    .badge-checkedin  { color: #16a34a; background-color: #e8f5e9; }
    .badge-checkedout { color: #6b7280; background-color: #f3f4f6; }
    .badge-pending    { color: #d97706; background-color: #fef9c3; }
    .badge-approved   { color: #1a56db; background-color: #eff6ff; }
    .badge-rejected   { color: #c62828; background-color: #fdecea; }
    .badge-completed  { color: #6b7280; background-color: #f3f4f6; }

    /* Detail rows */
    .visit-detail {
      font-size: 12px;
      color: #6b7280;
      line-height: 1.9;
    }

    .visit-detail b { font-weight: 500; color: #374151; }

    /* Empty state */
    .empty-state {
      font-size: 13px;
      color: #9ca3af;
      text-align: center;
      padding: 24px 0;
    }

    /* ── Footer ── */
    .footer {
      background-color: #1a1a2e;
      text-align: center;
      padding: 20px 32px;
    }

    .footer p { font-size: 12px; color: #9ca3af; }
    .footer p:last-child { font-size: 11px; color: #6b7280; margin-top: 4px; }
  </style>
</head>
<body>

  <!-- ════ NAVBAR ════ -->
  <nav class="navbar">
    <div class="navbar-left">
      <div class="navbar-logo">
      <img src="Assets/Shield.png" alt="BukSU Logo" />
      </div>
      <div class="navbar-title">
        <h2>BukSU</h2>
        <p>Visitor Log Monitoring System</p>
      </div>
    </div>

    <div class="navbar-right">
      <div class="navbar-user">
        <!-- Show visitor name from session -->
        <p>&#9679; <?php echo htmlspecialchars($visitor_name); ?></p>
        <span>Visitor</span>
      </div>
      <form action="logout.php" method="POST">
        <button type="submit" class="btn-logout">&#8594; Logout</button>
      </form>
    </div>
  </nav>

  <!-- ════ MAIN ════ -->
  <div class="main">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <h1>Welcome, <?php echo htmlspecialchars($visitor_name); ?>!</h1>
      <p>Manage your campus visits and view your visit history</p>
    </div>

    <!-- Two Action Cards -->
    <div class="action-grid">

      <!-- Request New Visit -->
      <a href="request_visit.php" class="action-card">
        <div class="action-icon icon-green">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
        </div>
        <div class="action-text">
          <h3>Request New Visit</h3>
          <p>Submit a new campus visit request</p>
        </div>
      </a>

      <!-- Visit History -->
      <a href="visit_history.php" class="action-card">
        <div class="action-icon icon-purple">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <div class="action-text">
          <h3>Visit History</h3>
          <p>View all your past visits</p>
        </div>
      </a>

    </div>

    <!-- Stat Row -->
    <div class="stat-row">

      <!-- Pending -->
      <div class="stat-item">
        <div class="stat-item-label label-orange">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Pending
        </div>
        <div class="stat-item-number"><?php echo $pending; ?></div>
      </div>

      <!-- Approved -->
      <div class="stat-item">
        <div class="stat-item-label label-green">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Approved
        </div>
        <div class="stat-item-number"><?php echo $approved; ?></div>
      </div>

      <!-- Completed -->
      <div class="stat-item">
        <div class="stat-item-label label-blue">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
          Completed
        </div>
        <div class="stat-item-number"><?php echo $completed; ?></div>
      </div>

      <!-- Total Visits -->
      <div class="stat-item">
        <div class="stat-item-label label-gray">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Total Visits
        </div>
        <div class="stat-item-number"><?php echo $total_visits; ?></div>
      </div>

    </div>

    <!-- Recent Visits -->
    <div class="panel">
      <h3>Recent Visits</h3>

      <?php if ($recent_result && $recent_result->num_rows > 0): ?>
        <?php while ($v = $recent_result->fetch_assoc()):

          // Pick badge class and label based on status
          // Using if/else for PHP version compatibility
          if ($v['status'] == 'approved' && $v['check_in'] && !$v['check_out']) {
            $badge_class = 'badge-checkedin';
            $badge_label = 'CHECKED IN';
          } elseif ($v['status'] == 'completed' || ($v['check_in'] && $v['check_out'])) {
            $badge_class = 'badge-checkedout';
            $badge_label = 'CHECKED OUT';
          } elseif ($v['status'] == 'pending') {
            $badge_class = 'badge-pending';
            $badge_label = 'PENDING';
          } elseif ($v['status'] == 'approved') {
            $badge_class = 'badge-approved';
            $badge_label = 'APPROVED';
          } elseif ($v['status'] == 'rejected') {
            $badge_class = 'badge-rejected';
            $badge_label = 'REJECTED';
          } else {
            $badge_class = 'badge-completed';
            $badge_label = strtoupper($v['status']);
          }
        ?>
        <div class="visit-card">

          <!-- Purpose + status badge -->
          <div class="visit-card-top">
            <strong><?php echo htmlspecialchars($v['purpose']); ?></strong>
            <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
          </div>

          <!-- Visit details -->
          <div class="visit-detail">
            <b>Destination:</b> <?php echo htmlspecialchars($v['destination']); ?><br>
            <b>Requested:</b> <?php echo date('M d, Y h:i A', strtotime($v['created_at'])); ?><br>

            <!-- Show check-in time only if available -->
            <?php if ($v['check_in']): ?>
              <b>Check-in:</b> <?php echo date('M d, Y h:i A', strtotime($v['check_in'])); ?><br>
            <?php endif; ?>

            <!-- Show check-out time only if available -->
            <?php if ($v['check_out']): ?>
              <b>Check-out:</b> <?php echo date('M d, Y h:i A', strtotime($v['check_out'])); ?>
            <?php endif; ?>
          </div>

        </div>
        <?php endwhile; ?>

      <?php else: ?>
        <p class="empty-state">No visits found. Click "Request New Visit" to get started.</p>
      <?php endif; ?>

    </div>

  </div>

  <!-- ════ FOOTER ════ -->
  <footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Bukidnon State University &mdash; Entrance and Security Office</p>
    <p>Malaybalay City, Bukidnon, Philippines</p>
  </footer>

<?php $conn->close(); ?>
</body>
</html>
