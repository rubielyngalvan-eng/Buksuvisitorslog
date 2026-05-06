<?php
session_start();

// ── Database connection ──
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ── Session — use real session values from login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['name'])) {
    header('Location: login.php');
    exit;
}
$visitor_name = $_SESSION['name'];
$visitor_id   = $_SESSION['visitor_id'] ?? null;

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

// ── Active tab filter ──
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// ── Build WHERE clause based on tab ──
if ($tab === 'pending') {
    $where = "AND vl.status = 'pending'";
} elseif ($tab === 'approved') {
    $where = "AND vl.status = 'approved'";
} elseif ($tab === 'checkedin') {
    // Checked in = approved visits (currently inside campus)
    $where = "AND vl.status = 'approved'";
} elseif ($tab === 'checkedout') {
    // Checked out = completed visits
    $where = "AND vl.status = 'completed'";
} elseif ($tab === 'rejected') {
    $where = "AND vl.status = 'rejected'";
} else {
    $where = "";
}

// ── Fetch visits with check-in/out times from history ──
$visits = $conn->query("
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
    $where
    GROUP BY vl.id
    ORDER BY vl.created_at DESC
");

// ── Counts per tab ──
$count_all       = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE visitor_id = $visitor_id")->fetch_assoc()['c'];
$count_pending   = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE visitor_id = $visitor_id AND status = 'pending'")->fetch_assoc()['c'];
$count_approved  = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE visitor_id = $visitor_id AND status = 'approved'")->fetch_assoc()['c'];
$count_rejected  = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE visitor_id = $visitor_id AND status = 'rejected'")->fetch_assoc()['c'];
$count_completed = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE visitor_id = $visitor_id AND status = 'completed'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Visit History — BukSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Poppins',sans-serif; background:#f5f6fa; min-height:100vh; }

    /* NAVBAR */
    .navbar { background:#2e7d32; height:60px; padding:0 32px; display:flex; align-items:center; justify-content:space-between; }
    .navbar-left { display:flex; align-items:center; gap:12px; }
    .navbar-logo { width:38px; height:38px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
    .navbar-logo img { width:34px; height:34px; object-fit:contain; }
    .navbar-title h2 { font-size:15px; font-weight:600; color:#fff; line-height:1.2; }
    .navbar-title p  { font-size:10px; color:#c8e6c9; }
    .navbar-right { display:flex; align-items:center; gap:16px; }
    .navbar-user p    { font-size:13px; color:#fff; font-weight:500; }
    .navbar-user span { font-size:10px; color:#c8e6c9; display:block; text-align:right; }
    .btn-logout { padding:7px 16px; background:rgba(255,255,255,0.15); color:#fff; font-size:12px; font-family:'Poppins',sans-serif; border:1px solid rgba(255,255,255,0.3); border-radius:6px; cursor:pointer; }
    .btn-logout:hover { background:rgba(255,255,255,0.25); }

    /* MAIN */
    .main { max-width:860px; margin:0 auto; padding:28px 20px; }

    /* PAGE HEADER */
    .page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; }
    .page-header h1 { font-size:20px; font-weight:600; color:#1a1a1a; }
    .page-header p  { font-size:13px; color:#6b7280; margin-top:3px; }
    .btn-back { padding:8px 18px; background:#fff; border:1px solid #d1d5db; border-radius:8px; font-size:13px; font-family:'Poppins',sans-serif; color:#374151; cursor:pointer; text-decoration:none; }
    .btn-back:hover { background:#f5f6fa; }

    /* PANEL */
    .panel { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 10px rgba(0,0,0,0.07); margin-bottom:20px; }

    /* TABS */
    .tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
    .tab { padding:7px 16px; border-radius:20px; font-size:12px; font-weight:500; font-family:'Poppins',sans-serif; cursor:pointer; text-decoration:none; border:1px solid #e5e7eb; color:#6b7280; background:#fff; transition:all 0.2s; }
    .tab:hover { background:#f5f6fa; }
    .tab-active-all       { background:#1a3a5c; color:#fff; border-color:#1a3a5c; }
    .tab-active-pending   { background:#d97706; color:#fff; border-color:#d97706; }
    .tab-active-approved  { background:#1a56db; color:#fff; border-color:#1a56db; }
    .tab-active-checkedin { background:#2e7d32; color:#fff; border-color:#2e7d32; }
    .tab-active-checkedout{ background:#6b7280; color:#fff; border-color:#6b7280; }
    .tab-active-rejected  { background:#c62828; color:#fff; border-color:#c62828; }

    /* VISIT CARD */
    .visit-card { border:1px solid #f0f0f0; border-radius:10px; padding:18px 20px; margin-bottom:12px; transition:box-shadow 0.2s; }
    .visit-card:last-child { margin-bottom:0; }
    .visit-card:hover { box-shadow:0 2px 10px rgba(0,0,0,0.08); }

    /* Card top row */
    .visit-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
    .visit-purpose { font-size:14px; font-weight:600; color:#1a1a1a; }

    /* Status badges */
    .badge { font-size:11px; font-weight:600; padding:3px 10px; border-radius:4px; white-space:nowrap; }
    .badge-pending   { color:#d97706; background:#fef9c3; }
    .badge-approved  { color:#1a56db; background:#eff6ff; }
    .badge-checkedin { color:#2e7d32; background:#e8f5e9; }
    .badge-checkedout{ color:#6b7280; background:#f3f4f6; }
    .badge-rejected  { color:#c62828; background:#fdecea; }
    .badge-completed { color:#6b7280; background:#f3f4f6; }

    /* Detail rows */
    .visit-details { display:grid; grid-template-columns:1fr 1fr; gap:6px 20px; font-size:12px; color:#6b7280; }
    .detail-item { display:flex; align-items:flex-start; gap:6px; }
    .detail-item svg { width:13px; height:13px; margin-top:1px; flex-shrink:0; }
    .detail-item b { color:#374151; font-weight:500; }

    /* Check in/out times */
    .time-row { display:flex; gap:20px; margin-top:10px; padding-top:10px; border-top:1px solid #f5f5f5; font-size:12px; }
    .time-item { display:flex; align-items:center; gap:5px; }
    .time-item svg { width:13px; height:13px; }
    .time-checkin  { color:#2e7d32; }
    .time-checkout { color:#c62828; }
    .time-pending  { color:#9ca3af; }

    /* Empty state */
    .empty { text-align:center; padding:36px; color:#9ca3af; font-size:13px; }

    /* STAT ROW at bottom */
    .stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
    .stat-item { text-align:center; padding:16px; border:1px solid #f0f0f0; border-radius:8px; }
    .stat-number { font-size:22px; font-weight:700; margin-bottom:4px; }
    .s-pending  { color:#d97706; }
    .s-approved { color:#1a56db; }
    .s-checkedin{ color:#2e7d32; }
    .s-completed{ color:#6b7280; }
    .stat-label { font-size:11px; color:#9ca3af; }

    /* FOOTER */
    .footer { background:#1a1a2e; text-align:center; padding:20px; margin-top:20px; }
    .footer p { font-size:12px; color:#9ca3af; }
    .footer p:last-child { font-size:11px; color:#6b7280; margin-top:4px; }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="navbar-left">
      <div class="navbar-logo">
        <img src="Assets/Shield.png" alt="BukSU"/>
      </div>
      <div class="navbar-title">
        <h2>BukSU</h2>
        <p>Visitor Log Monitoring System</p>
      </div>
    </div>
    <div class="navbar-right">
      <div class="navbar-user">
        <p>&#9679; <?php echo htmlspecialchars($visitor_name); ?></p>
        <span>Visitor</span>
      </div>
      <form action="logout.php" method="POST">
        <button class="btn-logout" type="submit">&#8594; Logout</button>
      </form>
    </div>
  </nav>

  <!-- MAIN -->
  <div class="main">

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Visit History</h1>
        <p>Complete record of all your campus visits</p>
      </div>
      <a href="visitor_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <!-- Main Panel -->
    <div class="panel">

      <!-- Tabs -->
      <div class="tabs">
        <a href="visit_history.php?tab=all"
           class="tab <?php echo $tab === 'all' ? 'tab-active-all' : ''; ?>">
           All Visits
        </a>
        <a href="visit_history.php?tab=pending"
           class="tab <?php echo $tab === 'pending' ? 'tab-active-pending' : ''; ?>">
           Pending
        </a>
        <a href="visit_history.php?tab=approved"
           class="tab <?php echo $tab === 'approved' ? 'tab-active-approved' : ''; ?>">
           Approved
        </a>
        <a href="visit_history.php?tab=checkedin"
           class="tab <?php echo $tab === 'checkedin' ? 'tab-active-checkedin' : ''; ?>">
           Checked In
        </a>
        <a href="visit_history.php?tab=checkedout"
           class="tab <?php echo $tab === 'checkedout' ? 'tab-active-checkedout' : ''; ?>">
           Checked Out
        </a>
        <a href="visit_history.php?tab=rejected"
           class="tab <?php echo $tab === 'rejected' ? 'tab-active-rejected' : ''; ?>">
           Rejected
        </a>
      </div>

      <!-- Visit Cards -->
      <?php if ($visits && $visits->num_rows > 0): ?>
        <?php while ($v = $visits->fetch_assoc()):

          // Determine badge
          if ($v['check_out']) {
            $badge = 'badge-checkedout'; $label = 'CHECKED OUT';
          } elseif ($v['check_in']) {
            $badge = 'badge-checkedin';  $label = 'CHECKED IN';
          } elseif ($v['status'] === 'pending') {
            $badge = 'badge-pending';    $label = 'PENDING';
          } elseif ($v['status'] === 'approved') {
            $badge = 'badge-approved';   $label = 'APPROVED';
          } elseif ($v['status'] === 'rejected') {
            $badge = 'badge-rejected';   $label = 'REJECTED';
          } elseif ($v['status'] === 'completed') {
            $badge = 'badge-completed';  $label = 'COMPLETED';
          } else {
            $badge = 'badge-pending';    $label = strtoupper($v['status']);
          }
        ?>
        <div class="visit-card">

          <!-- Purpose + badge -->
          <div class="visit-top">
            <span class="visit-purpose"><?php echo htmlspecialchars($v['purpose']); ?></span>
            <span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span>
          </div>

          <!-- Details grid -->
          <div class="visit-details">
            <div class="detail-item">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              </svg>
              <span><b>Destination:</b> <?php echo htmlspecialchars($v['destination']); ?></span>
            </div>
            <div class="detail-item">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <span><b>Requested:</b> <?php echo date('M d, Y', strtotime($v['created_at'])); ?></span>
            </div>
          </div>

          <!-- Check-in / Check-out times -->
          <div class="time-row">
            <?php if ($v['check_in']): ?>
              <div class="time-item time-checkin">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Check-in: <?php echo date('M d, Y h:i A', strtotime($v['check_in'])); ?>
              </div>
            <?php else: ?>
              <div class="time-item time-pending">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Not checked in yet
              </div>
            <?php endif; ?>

            <?php if ($v['check_out']): ?>
              <div class="time-item time-checkout">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Check-out: <?php echo date('M d, Y h:i A', strtotime($v['check_out'])); ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
        <?php endwhile; ?>

      <?php else: ?>
        <div class="empty">No visits found<?php echo $tab !== 'all' ? ' for this filter' : ''; ?>. <a href="request_visit.php" style="color:#2e7d32;">Request a visit</a> to get started.</div>
      <?php endif; ?>

    </div>

    <!-- Stats Summary -->
    <div class="panel">
      <div class="stat-row">
        <div class="stat-item">
          <div class="stat-number s-pending"><?php echo $count_pending; ?></div>
          <div class="stat-label">Pending</div>
        </div>
        <div class="stat-item">
          <div class="stat-number s-approved"><?php echo $count_approved; ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-item">
          <div class="stat-number s-checkedin"><?php echo $count_approved; ?></div>
          <div class="stat-label">Checked In</div>
        </div>
        <div class="stat-item">
          <div class="stat-number s-completed"><?php echo $count_completed; ?></div>
          <div class="stat-label">Completed</div>
        </div>
      </div>
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
