<?php
session_start();

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: login.php?type=admin');
    exit;
}

$host = 'localhost'; $db = 'buksuvisitorslogdb'; $user = 'root'; $pass = '';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

// ── Handle new incident form submission ──
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_incident'])) {
    $visit_id    = intval($_POST['visit_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $action      = trim($_POST['action_taken'] ?? '');

    if (empty($description)) {
        $error = 'Please enter an incident description.';
    } else {
        $safe_desc   = $conn->real_escape_string($description);
        $safe_action = $conn->real_escape_string($action);
        $vid         = $visit_id > 0 ? $visit_id : 'NULL';
        $conn->query("
            INSERT INTO emergency_logs (visit_id, description, action_taken)
            VALUES ($vid, '$safe_desc', '$safe_action')
        ");
        $success = 'Incident logged successfully.';
    }
}

// ── Fetch all emergency logs ──
$logs = $conn->query("
    SELECT el.id, el.visit_id, el.description, el.action_taken, el.created_at,
           vl.purpose, vl.destination, v.full_name, v.email
    FROM emergency_logs el
    LEFT JOIN visits_log vl ON vl.id = el.visit_id
    LEFT JOIN visitors v ON v.id = vl.visitor_id
    ORDER BY el.created_at DESC
");

// ── Stats ──
$total_incidents = $conn->query("SELECT COUNT(*) AS c FROM emergency_logs")->fetch_assoc()['c'];
$today_incidents = $conn->query("SELECT COUNT(*) AS c FROM emergency_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$week_incidents  = $conn->query("SELECT COUNT(*) AS c FROM emergency_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

// ── Fetch visits for the dropdown (to link an incident to a visit) ──
$visits_dropdown = $conn->query("
    SELECT vl.id, v.full_name, vl.purpose FROM visits_log vl
    JOIN visitors v ON v.id = vl.visitor_id
    ORDER BY vl.created_at DESC LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Emergency Incident Logs — BukSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:#f5f6fa;min-height:100vh;display:flex;flex-direction:column}

    /* NAVBAR */
    .navbar{background:#2e7d32;height:60px;padding:0 32px;display:flex;align-items:center;justify-content:space-between}
    .navbar-left{display:flex;align-items:center;gap:12px}
    .navbar-logo{width:38px;height:38px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .navbar-logo img{width:34px;height:34px;object-fit:contain}
    .navbar-title h2{font-size:15px;font-weight:600;color:#fff;line-height:1.2}
    .navbar-title p{font-size:10px;color:#c8e6c9}
    .navbar-right{display:flex;align-items:center;gap:16px}
    .navbar-user p{font-size:13px;color:#fff;font-weight:500}
    .navbar-user span{font-size:10px;color:#c8e6c9;display:block;text-align:right}
    .btn-logout{padding:7px 16px;background:rgba(255,255,255,0.15);color:#fff;font-size:12px;font-family:'Poppins',sans-serif;border:1px solid rgba(255,255,255,0.3);border-radius:6px;cursor:pointer}
    .btn-logout:hover{background:rgba(255,255,255,0.25)}

    /* MAIN */
    .main{max-width:860px;margin:0 auto;padding:32px 20px;flex:1;width:100%}

    /* PAGE HEADER */
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;background:#fff;border-radius:10px;padding:20px 24px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .header-left{display:flex;align-items:center;gap:10px}
    .header-left svg{width:22px;height:22px;color:#c62828;flex-shrink:0}
    .header-left h1{font-size:18px;font-weight:700;color:#c62828}
    .header-left p{font-size:12px;color:#6b7280;margin-top:2px}
    .header-actions{display:flex;gap:10px;align-items:center}
    .btn-log{padding:9px 18px;background:#c62828;color:#fff;font-size:13px;font-weight:600;font-family:'Poppins',sans-serif;border:none;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:5px}
    .btn-log:hover{background:#b71c1c}
    .btn-back{padding:9px 18px;background:#fff;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;color:#374151;text-decoration:none}
    .btn-back:hover{background:#f5f6fa}

    /* ALERT */
    .alert{padding:10px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;text-align:center}
    .alert-error{background:#fdecea;color:#c62828}
    .alert-success{background:#e8f5e9;color:#2e7d32}

    /* INCIDENT CARD */
    .incident-card{background:#fff;border-radius:10px;border-left:4px solid #c62828;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.06)}

    /* Card top row */
    .card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
    .card-title-row{display:flex;align-items:center;gap:10px}
    .card-title-icon{width:32px;height:32px;background:#fdecea;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .card-title-icon svg{width:16px;height:16px;color:#c62828}
    .card-title{font-size:14px;font-weight:700;color:#1a1a1a}
    .card-meta{display:flex;align-items:center;gap:14px;margin-top:4px;font-size:12px;color:#6b7280}
    .card-meta span{display:flex;align-items:center;gap:4px}
    .card-meta svg{width:12px;height:12px}
    .badge-emergency{font-size:10px;font-weight:700;color:#c62828;background:#fdecea;padding:3px 10px;border-radius:4px;letter-spacing:.3px}

    /* Purpose / destination row */
    .card-details{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;font-size:12px;color:#6b7280}
    .card-details b{color:#374151;font-weight:500}

    /* Description / action boxes */
    .section-label{font-size:11px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;display:flex;align-items:center;gap:5px}
    .section-label svg{width:13px;height:13px;color:#6b7280}
    .section-box{background:#fef2f2;border-radius:6px;padding:10px 14px;font-size:13px;color:#4b5563;line-height:1.6;margin-bottom:12px}
    .section-box-green{background:#f0fdf4;border-radius:6px;padding:10px 14px;font-size:13px;color:#4b5563;line-height:1.6}

    /* STATS */
    .stats-panel{background:#fff;border-radius:10px;padding:20px 24px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-top:24px}
    .stats-panel h3{font-size:13px;font-weight:600;color:#374151;margin-bottom:14px}
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .stat-box{border-radius:8px;padding:16px;text-align:center}
    .stat-box-red   {background:#fdecea}
    .stat-box-yellow{background:#fef9c3}
    .stat-box-blue  {background:#eff6ff}
    .stat-number{font-size:28px;font-weight:700;margin-bottom:4px}
    .stat-box-red    .stat-number{color:#c62828}
    .stat-box-yellow .stat-number{color:#d97706}
    .stat-box-blue   .stat-number{color:#1a56db}
    .stat-label{font-size:12px;color:#6b7280}

    /* EMPTY STATE */
    .empty{text-align:center;padding:48px 24px;background:#fff;border-radius:10px;color:#9ca3af;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .empty svg{width:40px;height:40px;color:#fecaca;margin:0 auto 12px auto;display:block}
    .empty p{font-size:13px}

    /* MODAL OVERLAY */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:12px;padding:28px 28px 24px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
    .modal-header h2{font-size:16px;font-weight:700;color:#c62828}
    .modal-close{background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer;line-height:1}
    .modal-close:hover{color:#374151}
    .form-group{margin-bottom:16px}
    .form-group label{display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px}
    .form-group select,
    .form-group textarea{width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;outline:none}
    .form-group select:focus,
    .form-group textarea:focus{border-color:#c62828}
    .form-group textarea{resize:vertical;min-height:80px}
    .modal-footer{display:flex;gap:10px;margin-top:20px}
    .btn-cancel-modal{flex:1;padding:10px;background:#fff;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;color:#374151;cursor:pointer}
    .btn-submit-modal{flex:2;padding:10px;background:#c62828;color:#fff;font-size:13px;font-weight:600;font-family:'Poppins',sans-serif;border:none;border-radius:8px;cursor:pointer}
    .btn-submit-modal:hover{background:#b71c1c}

    /* FOOTER */
    .footer{background:#1a1a2e;text-align:center;padding:20px;margin-top:0}
    .footer p{font-size:12px;color:#9ca3af}
    .footer p:last-child{font-size:11px;color:#6b7280;margin-top:4px}
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="navbar-left">
      <div class="navbar-logo"><img src="Assets/Shield.png" alt="BukSU"/></div>
      <div class="navbar-title"><h2>BukSU</h2><p>Visitor Log Monitoring System</p></div>
    </div>
    <div class="navbar-right">
      <div class="navbar-user"><p>&#9679; Admin User</p><span>ADMIN</span></div>
      <form action="logout.php" method="POST">
        <button class="btn-logout" type="submit">&#8594; Logout</button>
      </form>
    </div>
  </nav>

  <!-- MAIN -->
  <div class="main">

    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <div>
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
        </div>
        <div>
          <h1>Emergency Incident Logs</h1>
          <p>Document and track emergency incidents</p>
        </div>
      </div>
      <div class="header-actions">
        <!-- Opens modal -->
        <button class="btn-log" onclick="openModal()">+ Log Incident</button>
        <a href="admin_dashboard.php" class="btn-back">Back to Dashboard</a>
      </div>
    </div>

    <!-- Incident Cards -->
    <?php if ($logs && $logs->num_rows > 0): ?>
      <?php $ctr = 1; while ($log = $logs->fetch_assoc()): ?>
      <div class="incident-card">

        <!-- Top row -->
        <div class="card-top">
          <div>
            <div class="card-title-row">
              <div class="card-title-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
              </div>
              <div class="card-title">Incident Report #<?php echo $ctr; ?></div>
            </div>

            <!-- Visitor name + date -->
            <div class="card-meta" style="margin-left:42px">
              <span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A9 9 0 1118.88 6.196M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <?php echo htmlspecialchars($log['full_name'] ?: 'Unknown Visitor'); ?>
              </span>
              <span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
              </span>
            </div>
          </div>
          <span class="badge-emergency">EMERGENCY</span>
        </div>

        <!-- Visit details -->
        <?php if ($log['purpose'] || $log['destination']): ?>
        <div class="card-details">
          <div><b>Visit Purpose:</b> <?php echo htmlspecialchars($log['purpose'] ?: 'N/A'); ?></div>
          <div><b>Destination:</b> <?php echo htmlspecialchars($log['destination'] ?: 'N/A'); ?></div>
        </div>
        <?php endif; ?>

        <!-- Incident Description -->
        <div class="section-label">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Incident Description
        </div>
        <div class="section-box"><?php echo htmlspecialchars($log['description'] ?: 'No description provided'); ?></div>

        <!-- Action Taken -->
        <div class="section-label">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Action Taken
        </div>
        <div class="section-box-green"><?php echo htmlspecialchars($log['action_taken'] ?: 'No action recorded'); ?></div>

      </div>
      <?php $ctr++; endwhile; ?>

    <?php else: ?>
      <div class="empty">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <p>No emergency incidents recorded yet.</p>
      </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div class="stats-panel">
      <h3>Summary Statistics</h3>
      <div class="stats-grid">
        <div class="stat-box stat-box-red">
          <div class="stat-number"><?php echo $total_incidents; ?></div>
          <div class="stat-label">Total Incidents</div>
        </div>
        <div class="stat-box stat-box-yellow">
          <div class="stat-number"><?php echo $today_incidents; ?></div>
          <div class="stat-label">Today</div>
        </div>
        <div class="stat-box stat-box-blue">
          <div class="stat-number"><?php echo $week_incidents; ?></div>
          <div class="stat-label">This Week</div>
        </div>
      </div>
    </div>

  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Bukidnon State University — Entrance and Security Office</p>
    <p>Malaybalay City, Bukidnon, Philippines</p>
  </footer>

  <!-- LOG INCIDENT MODAL -->
  <div class="modal-overlay" id="modalOverlay" onclick="closeOnOverlay(event)">
    <div class="modal">
      <div class="modal-header">
        <h2>&#9888; Log New Incident</h2>
        <button class="modal-close" onclick="closeModal()">&#10005;</button>
      </div>

      <form method="POST" action="emergency_logs.php">
        <input type="hidden" name="log_incident" value="1"/>

        <!-- Link to a visit (optional) -->
        <div class="form-group">
          <label>Link to Visit (Optional)</label>
          <select name="visit_id">
            <option value="">— Not linked to a specific visit —</option>
            <?php
            if ($visits_dropdown) {
                $visits_dropdown->data_seek(0);
                while ($vd = $visits_dropdown->fetch_assoc()):
            ?>
            <option value="<?php echo $vd['id']; ?>">
              #<?php echo $vd['id']; ?> — <?php echo htmlspecialchars($vd['full_name']); ?> (<?php echo htmlspecialchars(substr($vd['purpose'], 0, 30)); ?>)
            </option>
            <?php endwhile; } ?>
          </select>
        </div>

        <!-- Description -->
        <div class="form-group">
          <label>Incident Description *</label>
          <textarea name="description" placeholder="Describe what happened..." required></textarea>
        </div>

        <!-- Action Taken -->
        <div class="form-group">
          <label>Action Taken</label>
          <textarea name="action_taken" placeholder="What action was taken in response?"></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel-modal" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-submit-modal">Submit Incident</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openModal()  { document.getElementById('modalOverlay').classList.add('open'); }
    function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
    // Close if clicking the dark overlay (not the modal itself)
    function closeOnOverlay(e) {
      if (e.target === document.getElementById('modalOverlay')) closeModal();
    }

    // Auto-open modal if there was a validation error so the user doesn't lose their input
    <?php if ($error): ?>
      openModal();
    <?php endif; ?>
  </script>

<?php $conn->close(); ?>
</body>
</html>