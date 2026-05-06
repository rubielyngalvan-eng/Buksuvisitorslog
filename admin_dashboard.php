<?php
session_start();

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: login.php?type=admin');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── Database connection ──
// Change these values to match your XAMPP setup
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);

// Stop the page if connection fails
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── Handle admin checkout action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout' && isset($_POST['visit_id'])) {
    $visit_id = intval($_POST['visit_id']);
    $conn->query("UPDATE visits_log SET status = 'completed' WHERE id = $visit_id");
    $conn->query("INSERT INTO history (visit_id, action, timestamp) VALUES ($visit_id, 'OUT', NOW())");
    header('Location: admin_dashboard.php');
    exit;
}

// ── Stat: Total visits today ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE DATE(created_at) = CURDATE()
    and status = 'approved'
");
$total_visits = $res->fetch_assoc()['total'];

// ── Stat: Pending approvals ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE status = 'pending'
");
$pending_approvals = $res->fetch_assoc()['total'];

// ── Stat: Currently inside campus (approved but not completed) ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visits_log 
    WHERE status = 'approved'
");
$currently_inside = $res->fetch_assoc()['total'];

// ── Stat: Total registered visitors ──
$res = $conn->query("
    SELECT COUNT(*) AS total 
    FROM visitors
");
$registered = $res->fetch_assoc()['total'];

// ── Currently in Campus list ──
$campus_result = $conn->query("
    SELECT 
        vl.id,
        v.full_name,
        vl.purpose,
        vl.destination,
        vl.created_at
    FROM visits_log vl
    JOIN visitors v ON v.id = vl.visitor_id
    WHERE vl.status = 'approved'
    ORDER BY vl.created_at DESC
    LIMIT 5
");

// ── Recent Activity (last 5 visits) ──
$activity_result = $conn->query("
    SELECT 
        v.full_name,
        vl.purpose,
        vl.status,
        vl.created_at
    FROM visits_log vl
    JOIN visitors v ON v.id = vl.visitor_id
    ORDER BY vl.created_at DESC
    LIMIT 5
");

// ── Recent Emergency Logs ──
$emergency_result = $conn->query("
    SELECT 
        el.description,
        el.action_taken,
        el.created_at
    FROM emergency_logs el
    ORDER BY el.created_at DESC
    LIMIT 3
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — BukSU Visitor Log</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background-color: #001f3f; color: #1a1a1a; min-height: 100vh; display: flex; flex-direction: column; }

    /* NAVBAR */
    .navbar { background-color: #2e7d32; padding: 0 32px; height: 60px; display: flex; align-items: center; justify-content: space-between; }
    .navbar-left { display: flex; align-items: center; gap: 12px; }
    .navbar-logo { width: 38px; height: 38px; background-color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .navbar-logo img { width: 34px; height: 34px; object-fit: contain; }
    .navbar-title h2 { font-size: 15px; font-weight: 600; color: #ffffff; line-height: 1.2; }
    .navbar-title p { font-size: 10px; color: #c8e6c9; }
    .navbar-right { display: flex; align-items: center; gap: 16px; }
    .navbar-admin p { font-size: 13px; color: #ffffff; font-weight: 500; }
    .navbar-admin span { font-size: 10px; color: #c8e6c9; display: block; text-align: right; }
    .btn-logout { padding: 7px 16px; background-color: rgba(255,255,255,0.15); color: #ffffff; font-size: 12px; font-weight: 500; font-family: 'Poppins', sans-serif; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; cursor: pointer; transition: background-color 0.2s; }
    .btn-logout:hover { background-color: rgba(255,255,255,0.25); }
    .btn-checkout { padding: 8px 14px; background-color: #c62828; color: #ffffff; font-size: 12px; font-weight: 600; text-transform: uppercase; border: none; border-radius: 8px; cursor: pointer; transition: transform 0.2s, background-color 0.2s; }
    .btn-checkout:hover { background-color: #a62828; transform: translateY(-1px); }

    /* HERO */
    .hero-banner { background-color: #c62828; padding: 24px 32px; }
    .hero-banner h1 { font-size: 22px; font-weight: 700; color: #ffffff; }
    .hero-banner p { font-size: 13px; color: #ffcdd2; margin-top: 4px; }

    /* MAIN */
    .main { padding: 28px 32px; max-width: 1100px; margin: 0 auto; flex: 1; width: 100%; }

    /* STAT CARDS */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
    .stat-card { background-color: #ffffff; border-radius: 10px; padding: 20px 18px; box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25); }
    .stat-label { font-size: 11px; color: #9ca3af; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; min-height: 16px; }
    .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .dot-yellow { background-color: #f59e0b; }
    .dot-green  { background-color: #16a34a; }
    .stat-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .stat-icon svg { width: 20px; height: 20px; }
    .icon-blue   { background-color: #e8f0fe; color: #1a73e8; }
    .icon-yellow { background-color: #fef9c3; color: #d97706; }
    .icon-green  { background-color: #e8f5e9; color: #2e7d32; }
    .icon-purple { background-color: #f3e8fd; color: #7b1fa2; }
    .stat-number { font-size: 32px; font-weight: 700; color: #1a1a1a; line-height: 1; margin-bottom: 6px; }
    .stat-title { font-size: 12px; color: #6b7280; }

    /* QUICK ACTIONS */
    .quick-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .action-card { background-color: #ffffff; border-radius: 10px; padding: 18px 20px; box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25); display: flex; align-items: center; gap: 14px; cursor: pointer; text-decoration: none; transition: box-shadow 0.2s; }
    .action-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
    .action-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .action-icon svg { width: 20px; height: 20px; }
    .action-text h3 { font-size: 13px; font-weight: 600; color: #1a1a1a; }
    .action-text p  { font-size: 12px; color: #6b7280; margin-top: 2px; }

    /* TWO COL */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }

    /* PANEL */
    .panel { background-color: #ffffff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25); }
    .panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .panel-title-dot { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #1a1a1a; }
    .panel-title-dot::before { content: ''; width: 8px; height: 8px; background-color: #16a34a; border-radius: 50%; display: inline-block; }
    .panel-title-plain { font-size: 14px; font-weight: 600; color: #1a1a1a; }
    .panel-count { font-size: 12px; color: #6b7280; }

    /* VISITOR CARD */
    .visitor-card { border: 1px solid #f0f0f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; }
    .visitor-card:last-child { margin-bottom: 0; }
    .visitor-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .visitor-card-top strong { font-size: 13px; font-weight: 600; color: #1a1a1a; }
    .badge-active { font-size: 11px; font-weight: 600; color: #16a34a; background-color: #e8f5e9; padding: 3px 10px; border-radius: 20px; }
    .visitor-card p { font-size: 12px; color: #6b7280; line-height: 1.8; }
    .visitor-card p b { font-weight: 500; color: #374151; }
    .empty-state { font-size: 13px; color: #9ca3af; text-align: center; padding: 20px 0; }

    /* ACTIVITY */
    .activity-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
    .activity-item:last-child { border-bottom: none; }
    .activity-info strong { font-size: 13px; font-weight: 600; color: #1a1a1a; display: block; }
    .activity-info p { font-size: 12px; color: #6b7280; margin-top: 2px; }

    /* BADGES */
    .badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 4px; white-space: nowrap; flex-shrink: 0; margin-left: 10px; }
    .badge-pending   { color: #d97706; background-color: #fef9c3; }
    .badge-approved  { color: #16a34a; background-color: #e8f5e9; }
    .badge-rejected  { color: #c62828; background-color: #fdecea; }
    .badge-completed { color: #6b7280; background-color: #f3f4f6; }

    /* EMERGENCY */
    .emergency-panel { background-color: #fff5f5; border: 1px solid #fecaca; border-radius: 10px; padding: 20px; margin-bottom: 28px; }
    .emergency-header { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
    .emergency-header h3 { font-size: 14px; font-weight: 600; color: #c62828; }
    .emergency-header svg { width: 18px; height: 18px; color: #c62828; }
    .emergency-item { background-color: #ffffff; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; }
    .emergency-item:last-child { margin-bottom: 0; }
    .emergency-item .e-title { font-size: 13px; font-weight: 500; color: #c62828; margin-bottom: 6px; }
    .emergency-item .e-action { font-size: 12px; color: #374151; }
    .emergency-item .e-action b { font-weight: 600; }
    .emergency-item .e-time { font-size: 11px; color: #9ca3af; margin-top: 6px; }
    .view-all { font-size: 13px; color: #c62828; font-weight: 500; text-decoration: none; display: inline-block; margin-top: 12px; }
    .view-all:hover { text-decoration: underline; }

    /* FOOTER */
    .footer { background-color: #1a1a2e; text-align: center; padding: 20px 32px; margin-top: 0; }
    .footer p { font-size: 12px; color: #9ca3af; }
    .footer p:last-child { font-size: 11px; color: #6b7280; margin-top: 4px; }
  </style>
</head>
<body>

  <!-- NAVBAR -->
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
      <div class="navbar-admin">
        <p>&#9679; Admin User</p>
        <span>ADMIN</span>
      </div>
      <form action="logout.php" method="POST">
        <button type="submit" class="btn-logout">&#8594; Logout</button>
      </form>
    </div>
  </nav>

  <!-- HERO BANNER -->
  <div class="hero-banner">
    <h1>Admin Dashboard</h1>
    <p>Real-time visitor monitoring and management system</p>
  </div>

  <!-- MAIN -->
  <div class="main">

    <!-- Stat Cards -->
    <div class="stats">

      <div class="stat-card">
        <div class="stat-label"><span>Today</span></div>
        <div class="stat-icon icon-blue">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $total_visits; ?></div>
        <div class="stat-title">Total Visits</div>
      </div>

      <div class="stat-card">
        <div class="stat-label"><span></span><span class="dot dot-yellow"></span></div>
        <div class="stat-icon icon-yellow">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $pending_approvals; ?></div>
        <div class="stat-title">Pending Approvals</div>
      </div>

      <div class="stat-card">
        <div class="stat-label"><span></span><span class="dot dot-green"></span></div>
        <div class="stat-icon icon-green">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $currently_inside; ?></div>
        <div class="stat-title">Currently in Campus</div>
      </div>

      <div class="stat-card">
        <div class="stat-label"><span></span></div>
        <div class="stat-icon icon-purple">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A9 9 0 1118.88 6.196M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $registered; ?></div>
        <div class="stat-title">Registered Visitors</div>
      </div>

    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="approvals.php" class="action-card">
        <div class="action-icon icon-yellow">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="action-text">
          <h3>Approve Visits</h3>
          <p><?php echo $pending_approvals; ?> pending request<?php echo $pending_approvals != 1 ? 's' : ''; ?></p>
        </div>
      </a>
      <a href="emergency_search.php" class="action-card">
        <div class="action-icon icon-blue">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
          </svg>
        </div>
        <div class="action-text">
          <h3>Search Visitors</h3>
          <p>Quick emergency search</p>
        </div>
      </a>
      <a href="reports.php" class="action-card">
        <div class="action-icon icon-purple">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
          </svg>
        </div>
        <div class="action-text">
          <h3>Generate Reports</h3>
          <p>Daily, weekly, monthly</p>
        </div>
      </a>
    </div>

    <!-- Two Column -->
    <div class="two-col">

      <!-- Currently in Campus -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title-dot">Currently in Campus</span>
          <span class="panel-count"><?php echo $currently_inside; ?> active</span>
        </div>
        <?php if ($campus_result->num_rows > 0): ?>
          <?php while ($v = $campus_result->fetch_assoc()): ?>
          <div class="visitor-card">
            <div class="visitor-card-top">
              <strong><?php echo htmlspecialchars($v['full_name']); ?></strong>
              <span class="badge-active">ACTIVE</span>
            </div>
            <p><b>Purpose:</b> <?php echo htmlspecialchars($v['purpose']); ?></p>
            <p><b>Destination:</b> <?php echo htmlspecialchars($v['destination']); ?></p>
            <p>Checked in: <?php echo date('h:i A', strtotime($v['created_at'])); ?></p>
            <form method="POST" action="admin_dashboard.php" onsubmit="return confirm('Are you sure you want to check out this visitor?');">
              <input type="hidden" name="visit_id" value="<?php echo intval($v['id']); ?>" />
              <input type="hidden" name="action" value="checkout" />
              <button type="submit" class="btn-checkout">Check Out</button>
            </form>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="empty-state">No visitors currently on campus.</p>
        <?php endif; ?>
      </div>

      <!-- Recent Activity -->
      <div class="panel">
        <div class="panel-header">
          <span class="panel-title-plain">Recent Activity</span>
        </div>
        <?php if ($activity_result->num_rows > 0): ?>
          <?php while ($a = $activity_result->fetch_assoc()):
            // if/else instead of match() for wider PHP version support
            if ($a['status'] == 'pending')        $badge_class = 'badge-pending';
            elseif ($a['status'] == 'approved')   $badge_class = 'badge-approved';
            elseif ($a['status'] == 'rejected')   $badge_class = 'badge-rejected';
            elseif ($a['status'] == 'completed')  $badge_class = 'badge-completed';
            else                                   $badge_class = '';
          ?>
          <div class="activity-item">
            <div class="activity-info">
              <strong><?php echo htmlspecialchars($a['full_name']); ?></strong>
              <p><?php echo htmlspecialchars($a['purpose']); ?></p>
              <p><?php echo date('M d, Y h:i A', strtotime($a['created_at'])); ?></p>
            </div>
            <span class="badge <?php echo $badge_class; ?>"><?php echo strtoupper($a['status']); ?></span>
          </div>
          <?php endwhile; ?>
          <a href="admin_activities.php" class="view-all">Show all activities &#8594;</a>
        <?php else: ?>
          <p class="empty-state">No recent activity.</p>
        <?php endif; ?>
      </div>

    </div>

    <!-- Emergency Logs -->
    <div class="emergency-panel">
      <div class="emergency-header">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <h3>Recent Emergency Logs</h3>
      </div>
      <?php if ($emergency_result->num_rows > 0): ?>
        <?php while ($e = $emergency_result->fetch_assoc()): ?>
        <div class="emergency-item">
          <div class="e-title"><?php echo htmlspecialchars($e['description']); ?></div>
          <div class="e-action"><b>Action Taken:</b> <?php echo htmlspecialchars($e['action_taken']); ?></div>
          <div class="e-time"><?php echo date('M d, Y h:i A', strtotime($e['created_at'])); ?></div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="empty-state" style="color:#c62828;">No emergency logs recorded.</p>
      <?php endif; ?>
      <a href="emergency_logs.php" class="view-all">View all emergency logs &#8594;</a>
    </div>

  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Bukidnon State University &mdash; Entrance and Security Office</p>
    <p>Malaybalay City, Bukidnon, Philippines</p>
  </footer>

<?php $conn->close(); ?>
</body>
</html>