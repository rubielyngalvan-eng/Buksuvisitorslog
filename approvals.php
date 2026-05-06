<?php
// ── Database connection ──
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ── Handle Approve / Reject actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_id = intval($_POST['visit_id']);
    $action   = $_POST['action'];

    if ($action === 'approve') {
        $conn->query("UPDATE visits_log SET status = 'approved' WHERE id = $visit_id");
    } elseif ($action === 'reject') {
        $conn->query("UPDATE visits_log SET status = 'rejected' WHERE id = $visit_id");
    }

    // Redirect to avoid form resubmission on refresh
    header("Location: approvals.php?tab=" . $action . "d");
    exit;
}

// ── Active tab filter ──
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// ── Build query based on active tab ──
if ($tab === 'all') {
    $where = "";
} elseif ($tab === 'approved') {
    $where = "WHERE vl.status = 'approved'";
} elseif ($tab === 'rejected') {
    $where = "WHERE vl.status = 'rejected'";
} else {
    // Default: pending
    $tab   = 'pending';
    $where = "WHERE vl.status = 'pending'";
}

// ── Fetch visits with visitor info ──
$visits = $conn->query("
    SELECT 
        vl.id,
        vl.purpose,
        vl.destination,
        vl.status,
        vl.created_at,
        v.full_name,
        v.email,
        v.contact_number,
        v.address
    FROM visits_log vl
    JOIN visitors v ON v.id = vl.visitor_id
    $where
    ORDER BY vl.created_at DESC
");

// ── Count per tab for badges ──
$count_pending  = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status = 'pending'")->fetch_assoc()['c'];
$count_approved = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status = 'approved'")->fetch_assoc()['c'];
$count_rejected = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status = 'rejected'")->fetch_assoc()['c'];
$count_all      = $conn->query("SELECT COUNT(*) AS c FROM visits_log")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Visit Approvals — BukSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Poppins',sans-serif; background:#f5f6fa; min-height:100vh; display:flex; flex-direction:column; }

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
    .main { max-width:900px; margin:0 auto; padding:28px 20px; flex:1; width:100%; }

    /* PAGE HEADER */
    .page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; }
    .page-header h1 { font-size:20px; font-weight:600; color:#1a1a1a; }
    .page-header p  { font-size:13px; color:#6b7280; margin-top:3px; }
    .btn-back { padding:8px 18px; background:#fff; border:1px solid #d1d5db; border-radius:8px; font-size:13px; font-family:'Poppins',sans-serif; color:#374151; cursor:pointer; text-decoration:none; }
    .btn-back:hover { background:#f5f6fa; }

    /* TABS */
    .tabs { display:flex; gap:8px; margin-bottom:20px; }
    .tab { padding:8px 18px; border-radius:8px; font-size:13px; font-weight:500; font-family:'Poppins',sans-serif; cursor:pointer; text-decoration:none; border:none; display:flex; align-items:center; gap:6px; transition:background 0.2s; }
    .tab-default { background:#fff; color:#6b7280; border:1px solid #e5e7eb; }
    .tab-default:hover { background:#f5f6fa; }
    .tab-active-pending  { background:#c62828; color:#fff; }
    .tab-active-approved { background:#2e7d32; color:#fff; }
    .tab-active-rejected { background:#6b7280; color:#fff; }
    .tab-active-all      { background:#1a3a5c; color:#fff; }
    .tab-badge { background:rgba(255,255,255,0.3); border-radius:20px; padding:1px 7px; font-size:11px; }
    .tab-badge-dark { background:#e5e7eb; color:#374151; border-radius:20px; padding:1px 7px; font-size:11px; }

    /* VISIT CARD */
    .visit-card { background:#fff; border-radius:10px; padding:20px 22px; margin-bottom:14px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .visit-card-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; }
    .visitor-info { display:flex; align-items:center; gap:14px; }
    .visitor-avatar { width:40px; height:40px; background:#e8f0fe; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; color:#1a73e8; font-weight:600; flex-shrink:0; }
    .visitor-name { font-size:14px; font-weight:600; color:#1a1a1a; }
    .visitor-email { font-size:12px; color:#6b7280; margin-top:2px; }

    /* Status badge */
    .badge { font-size:11px; font-weight:600; padding:3px 12px; border-radius:4px; }
    .badge-pending  { color:#d97706; background:#fef9c3; }
    .badge-approved { color:#2e7d32; background:#e8f5e9; }
    .badge-rejected { color:#6b7280; background:#f3f4f6; }

    /* Visitor details */
    .visitor-details { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:14px; font-size:12px; color:#6b7280; }
    .visitor-details b { color:#374151; font-weight:500; }

    /* Purpose/destination row */
    .visit-meta { display:flex; gap:24px; margin-bottom:16px; font-size:12px; color:#6b7280; }
    .visit-meta-item { display:flex; align-items:center; gap:6px; }
    .visit-meta-item b { color:#374151; }

    /* Action buttons */
    .actions { display:flex; gap:10px; }
    .btn-approve { padding:9px 20px; background:#2e7d32; color:#fff; font-size:13px; font-weight:500; font-family:'Poppins',sans-serif; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:6px; width:100%; justify-content:center; }
    .btn-approve:hover { background:#256427; }
    .btn-reject  { padding:9px 20px; background:#c62828; color:#fff; font-size:13px; font-weight:500; font-family:'Poppins',sans-serif; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:6px; width:100%; justify-content:center; }
    .btn-reject:hover  { background:#b71c1c; }

    /* Empty state */
    .empty { text-align:center; padding:40px; color:#9ca3af; font-size:13px; background:#fff; border-radius:10px; }

    /* FOOTER */
    .footer { background:#1a1a2e; text-align:center; padding:20px; margin-top:0; }
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
        <p>&#9679; Admin User</p>
        <span>ADMIN</span>
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
        <h1>Visit Approvals</h1>
        <p>Review and manage visitor requests</p>
      </div>
      <a href="admin_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <a href="approvals.php?tab=all"
         class="tab <?php echo $tab === 'all' ? 'tab-active-all' : 'tab-default'; ?>">
        All
        <span class="<?php echo $tab === 'all' ? 'tab-badge' : 'tab-badge-dark'; ?>"><?php echo $count_all; ?></span>
      </a>
      <a href="approvals.php?tab=pending"
         class="tab <?php echo $tab === 'pending' ? 'tab-active-pending' : 'tab-default'; ?>">
        Pending
        <span class="<?php echo $tab === 'pending' ? 'tab-badge' : 'tab-badge-dark'; ?>"><?php echo $count_pending; ?></span>
      </a>
      <a href="approvals.php?tab=approved"
         class="tab <?php echo $tab === 'approved' ? 'tab-active-approved' : 'tab-default'; ?>">
        Approved
        <span class="<?php echo $tab === 'approved' ? 'tab-badge' : 'tab-badge-dark'; ?>"><?php echo $count_approved; ?></span>
      </a>
      <a href="approvals.php?tab=rejected"
         class="tab <?php echo $tab === 'rejected' ? 'tab-active-rejected' : 'tab-default'; ?>">
        Rejected
        <span class="<?php echo $tab === 'rejected' ? 'tab-badge' : 'tab-badge-dark'; ?>"><?php echo $count_rejected; ?></span>
      </a>
    </div>

    <!-- Visit Cards -->
    <?php if ($visits && $visits->num_rows > 0): ?>
      <?php while ($v = $visits->fetch_assoc()):
        // Get first letter of name for avatar
        $initial = strtoupper(substr($v['full_name'], 0, 1));

        // Badge class
        if ($v['status'] === 'pending')  $badge = 'badge-pending';
        elseif ($v['status'] === 'approved') $badge = 'badge-approved';
        else $badge = 'badge-rejected';
      ?>
      <div class="visit-card">

        <!-- Top: visitor info + status badge -->
        <div class="visit-card-top">
          <div class="visitor-info">
            <div class="visitor-avatar"><?php echo $initial; ?></div>
            <div>
              <div class="visitor-name"><?php echo htmlspecialchars($v['full_name']); ?></div>
              <div class="visitor-email"><?php echo htmlspecialchars($v['email']); ?></div>
            </div>
          </div>
          <span class="badge <?php echo $badge; ?>"><?php echo strtoupper($v['status']); ?></span>
        </div>

        <!-- Visitor details grid -->
        <div class="visitor-details">
          <div><b>Phone:</b> <?php echo htmlspecialchars($v['contact_number'] ?? '—'); ?></div>
          <div><b>Requested:</b> <?php echo date('M d, Y h:i A', strtotime($v['created_at'])); ?></div>
          <div><b>Address:</b> <?php echo htmlspecialchars($v['address'] ?? '—'); ?></div>
        </div>

        <!-- Purpose and Destination -->
        <div class="visit-meta">
          <div class="visit-meta-item">
            &#128196; <b>Purpose of Visit</b>&nbsp; <?php echo htmlspecialchars($v['purpose']); ?>
          </div>
          <div class="visit-meta-item">
            &#128205; <b>Destination</b>&nbsp; <?php echo htmlspecialchars($v['destination']); ?>
          </div>
        </div>

        <!-- Approve / Reject buttons (only show for pending) -->
        <?php if ($v['status'] === 'pending'): ?>
        <div class="actions">
          <form method="POST" action="approvals.php" style="flex:1">
            <input type="hidden" name="visit_id" value="<?php echo $v['id']; ?>"/>
            <input type="hidden" name="action" value="approve"/>
            <button type="submit" class="btn-approve">&#10003; Approve Visit</button>
          </form>
          <form method="POST" action="approvals.php" style="flex:1">
            <input type="hidden" name="visit_id" value="<?php echo $v['id']; ?>"/>
            <input type="hidden" name="action" value="reject"/>
            <button type="submit" class="btn-reject">&#10007; Reject Visit</button>
          </form>
        </div>
        <?php endif; ?>

      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty">No <?php echo $tab; ?> requests found.</div>
    <?php endif; ?>

  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Bukidnon State University &mdash; Entrance and Security Office</p>
    <p>Malaybalay City, Bukidnon, Philippines</p>
  </footer>

<?php $conn->close(); ?>
</body>
</html>
