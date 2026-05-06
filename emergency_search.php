<?php
// ── Database connection ──
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ── Handle search ──
$search  = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = null;

if ($search !== '') {
    // Search by name, email, phone, or address
    $safe    = $conn->real_escape_string($search);
    $results = $conn->query("
        SELECT 
            v.id,
            v.full_name,
            v.email,
            v.contact_number,
            v.address,
            vl.purpose,
            vl.destination,
            vl.status,
            vl.created_at
        FROM visitors v
        LEFT JOIN visits_log vl ON vl.visitor_id = v.id
        WHERE 
            v.full_name      LIKE '%$safe%' OR
            v.email          LIKE '%$safe%' OR
            v.contact_number LIKE '%$safe%' OR
            v.address        LIKE '%$safe%'
        ORDER BY vl.created_at DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Visitor Search — BukSU</title>
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

    /* SEARCH BOX */
    .search-panel { background:#fff; border-radius:10px; padding:22px; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:20px; }
    .search-row { display:flex; gap:10px; }
    .search-input { flex:1; padding:11px 16px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; font-family:'Poppins',sans-serif; outline:none; }
    .search-input:focus { border-color:#1a3a5c; }
    .btn-search { padding:11px 24px; background:#1a3a5c; color:#fff; font-size:13px; font-weight:600; font-family:'Poppins',sans-serif; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:6px; }
    .btn-search:hover { background:#14304d; }

    /* RESULT CARDS */
    .result-card { background:#fff; border-radius:10px; padding:18px 22px; margin-bottom:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
    .result-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    .visitor-name { font-size:14px; font-weight:600; color:#1a1a1a; }
    .visitor-email { font-size:12px; color:#6b7280; margin-top:2px; }

    .badge { font-size:11px; font-weight:600; padding:3px 10px; border-radius:4px; }
    .badge-pending  { color:#d97706; background:#fef9c3; }
    .badge-approved { color:#2e7d32; background:#e8f5e9; }
    .badge-rejected { color:#6b7280; background:#f3f4f6; }
    .badge-completed{ color:#1a56db; background:#eff6ff; }
    .badge-none     { color:#9ca3af; background:#f9fafb; }

    .result-details { display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:12px; color:#6b7280; margin-bottom:8px; }
    .result-details b { color:#374151; font-weight:500; }

    .result-visit { font-size:12px; color:#6b7280; padding-top:10px; border-top:1px solid #f0f0f0; }
    .result-visit b { color:#374151; }

    /* Empty / hint state */
    .empty { text-align:center; padding:40px; color:#9ca3af; font-size:13px; background:#fff; border-radius:10px; }
    .hint  { text-align:center; padding:32px; color:#9ca3af; font-size:13px; }

    /* Results count */
    .result-count { font-size:13px; color:#6b7280; margin-bottom:14px; }
    .result-count b { color:#1a1a1a; }

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
        <h1>Visitor Search</h1>
        <p>Quick search for emergency situations</p>
      </div>
      <a href="admin_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <!-- Search Box -->
    <div class="search-panel">
      <form method="GET" action="search.php">
        <div class="search-row">
          <input
            type="text"
            name="q"
            class="search-input"
            placeholder="Search by name, email, phone, or address..."
            value="<?php echo htmlspecialchars($search); ?>"
            autofocus
          />
          <button type="submit" class="btn-search">&#128269; Search</button>
        </div>
      </form>
    </div>

    <!-- Results -->
    <?php if ($search === ''): ?>
      <p class="hint">Enter a name, email, phone number, or address above to search for a visitor.</p>

    <?php elseif ($results && $results->num_rows > 0): ?>
      <p class="result-count">Found <b><?php echo $results->num_rows; ?></b> result(s) for "<b><?php echo htmlspecialchars($search); ?></b>"</p>

      <?php while ($r = $results->fetch_assoc()):
        // Badge
        if (!$r['status']) {
          $badge = 'badge-none'; $label = 'NO VISITS';
        } elseif ($r['status'] === 'pending') {
          $badge = 'badge-pending'; $label = 'PENDING';
        } elseif ($r['status'] === 'approved') {
          $badge = 'badge-approved'; $label = 'APPROVED';
        } elseif ($r['status'] === 'completed') {
          $badge = 'badge-completed'; $label = 'COMPLETED';
        } else {
          $badge = 'badge-rejected'; $label = strtoupper($r['status']);
        }
      ?>
      <div class="result-card">
        <div class="result-top">
          <div>
            <div class="visitor-name"><?php echo htmlspecialchars($r['full_name']); ?></div>
            <div class="visitor-email"><?php echo htmlspecialchars($r['email']); ?></div>
          </div>
          <span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span>
        </div>

        <!-- Visitor details -->
        <div class="result-details">
          <div><b>Phone:</b> <?php echo htmlspecialchars($r['contact_number'] ?? '—'); ?></div>
          <div><b>Address:</b> <?php echo htmlspecialchars($r['address'] ?? '—'); ?></div>
        </div>

        <!-- Latest visit info -->
        <?php if ($r['purpose']): ?>
        <div class="result-visit">
          <b>Latest Visit:</b> <?php echo htmlspecialchars($r['purpose']); ?>
          &nbsp;&#8594;&nbsp; <?php echo htmlspecialchars($r['destination']); ?>
          &nbsp;&nbsp;|&nbsp;&nbsp;
          <?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>

    <?php else: ?>
      <div class="empty">No visitors found matching "<b><?php echo htmlspecialchars($search); ?></b>".</div>
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
