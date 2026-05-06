<?php
$host = "localhost"; $db = "buksuvisitorslogdb"; $user = "root"; $pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$date        = isset($_GET['date']) ? $_GET['date']  : date('Y-m-d');

$just_generated = isset($_GET['generate']) && $_GET['generate'] === '1';

if ($report_type === 'weekly') {
    $date_from = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $date_to   = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
} elseif ($report_type === 'monthly') {
    $date_from = date('Y-m-01', strtotime($date));
    $date_to   = date('Y-m-t',  strtotime($date));
} else {
    $report_type = 'daily';
    $date_from   = $date;
    $date_to     = $date;
}

$display_from = date('M d, Y', strtotime($date_from));
$display_to   = date('M d, Y', strtotime($date_to));

// ── Stats ──
$total     = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'];
$completed = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status='completed' AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'];
$pending   = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status='pending'   AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'];
$approved  = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status='approved'  AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'];
$rejected  = $conn->query("SELECT COUNT(*) AS c FROM visits_log WHERE status='rejected'  AND DATE(created_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['c'];

// ── Total registered users ──
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='visitor'")->fetch_assoc()['c'];

// ── Destination visit counts ──
$all_destinations = [
    "Admin Office",
    "College of Arts and Sciences",
    "College of Business",
    "College of Education",
    "College of Law",
    "College of Nursing",
    "College of Public Administration & Governance",
    "College of Technology",
    "Finance Office",
    "General Education Department",
    "HR Department",
    "IT Department",
    "Library",
    "Main Campus",
    "Registrar Office",
    "Research Office",
    "Student Affairs Office",
    "Supply Office"
];

$dest_result = $conn->query("
    SELECT destination, COUNT(*) AS visit_count
    FROM visits_log
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
      AND destination IS NOT NULL AND destination != ''
    GROUP BY destination
    ORDER BY visit_count DESC
");

$dest_data = [];
while ($row = $dest_result->fetch_assoc()) {
    $dest_data[$row['destination']] = (int)$row['visit_count'];
}

// Build ordered arrays — only include destinations that have visits
$chart_labels  = [];
$chart_counts  = [];
foreach ($all_destinations as $d) {
    if (isset($dest_data[$d]) && $dest_data[$d] > 0) {
        $chart_labels[] = $d;
        $chart_counts[] = $dest_data[$d];
    }
}

// ── Visit log ──
$visit_log = $conn->query("
    SELECT vl.id, v.full_name, vl.purpose, vl.destination, vl.status, vl.created_at
    FROM visits_log vl JOIN visitors v ON v.id = vl.visitor_id
    WHERE DATE(vl.created_at) BETWEEN '$date_from' AND '$date_to'
    ORDER BY vl.created_at DESC
");

// ── Log to reports table ONLY when generate button was clicked ──
if ($just_generated) {
    $type_safe = $conn->real_escape_string($report_type);
    $conn->query("INSERT INTO reports (generated_by, report_type) VALUES (1, '$type_safe')");
}

// ── Report history ──
$history = $conn->query("
    SELECT r.report_type, r.created_at, u.name AS generated_by
    FROM reports r LEFT JOIN users u ON u.id = r.generated_by
    ORDER BY r.created_at DESC LIMIT 10
");

// JSON-encode for JS
$js_labels = json_encode($chart_labels);
$js_counts = json_encode($chart_counts);
$js_stats  = json_encode([
    'Total Users'     => (int)$total_users,
    'Approved'        => (int)$approved,
    'Rejected'        => (int)$rejected,
    'Total Visits'    => (int)$total,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports — BukSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

    /* MAIN */
    .main{max-width:1100px;margin:0 auto;padding:28px 20px;flex:1;width:100%}
    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px}
    .page-header h1{font-size:20px;font-weight:600;color:#1a1a1a}
    .page-header p{font-size:13px;color:#6b7280;margin-top:3px}
    .btn-back{padding:8px 18px;background:#fff;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;color:#374151;text-decoration:none}

    /* FILTER */
    .filter-panel{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:20px}
    .filter-row{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap}
    .filter-group{display:flex;flex-direction:column;gap:6px}
    .filter-group label{font-size:12px;font-weight:500;color:#374151}
    .filter-select,.filter-date{padding:9px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;outline:none}
    .filter-select:focus,.filter-date:focus{border-color:#1a3a5c}
    .btn-generate{padding:9px 20px;background:#7b1fa2;color:#fff;font-size:13px;font-weight:600;font-family:'Poppins',sans-serif;border:none;border-radius:8px;cursor:pointer;align-self:flex-end}
    .btn-generate:hover{background:#6a1b9a}
    .btn-pdf{padding:9px 20px;background:#1a3a5c;color:#fff;font-size:13px;font-weight:600;font-family:'Poppins',sans-serif;border:none;border-radius:8px;cursor:pointer;align-self:flex-end}
    .btn-pdf:hover{background:#14304d}
    .period-label{font-size:12px;color:#6b7280;margin-top:12px}

    /* STATS */
    .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}
    .stat-card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .stat-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
    .stat-icon svg{width:18px;height:18px}
    .icon-blue{background:#e8f0fe;color:#1a73e8}
    .icon-green{background:#e8f5e9;color:#2e7d32}
    .icon-yellow{background:#fef9c3;color:#d97706}
    .stat-number{font-size:28px;font-weight:700;color:#1a1a1a;margin-bottom:4px}
    .stat-title{font-size:12px;color:#6b7280}

    /* BREAKDOWN */
    .breakdown{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:20px}
    .breakdown h3{font-size:14px;font-weight:600;color:#1a1a1a;margin-bottom:14px}
    .breakdown-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
    .breakdown-item{border-radius:8px;padding:14px;text-align:center}
    .b-pending{background:#fef9c3}.b-approved{background:#eff6ff}.b-checkin{background:#e8f5e9}.b-completed{background:#f3f4f6}.b-rejected{background:#fdecea}
    .breakdown-number{font-size:22px;font-weight:700;margin-bottom:4px}
    .b-pending .breakdown-number{color:#d97706}.b-approved .breakdown-number{color:#1a56db}.b-checkin .breakdown-number{color:#2e7d32}.b-completed .breakdown-number{color:#6b7280}.b-rejected .breakdown-number{color:#c62828}
    .breakdown-label{font-size:11px;color:#6b7280;font-weight:500}

    /* CHARTS */
    .charts-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
    .chart-panel{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .chart-panel h3{font-size:14px;font-weight:600;color:#1a1a1a;margin-bottom:6px}
    .chart-panel p.chart-sub{font-size:11px;color:#9ca3af;margin-bottom:16px}
    .chart-wrap{position:relative;width:100%;display:flex;justify-content:center}
    .chart-wrap canvas{max-height:280px}
    .no-data-msg{text-align:center;padding:40px 0;color:#9ca3af;font-size:13px}

    /* DESTINATION TABLE */
    .dest-table-wrap{margin-top:16px;max-height:220px;overflow-y:auto}
    .dest-table{width:100%;border-collapse:collapse;font-size:12px}
    .dest-table th{padding:8px 10px;text-align:left;font-size:10px;font-weight:600;color:#6b7280;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #f0f0f0;position:sticky;top:0}
    .dest-table td{padding:8px 10px;color:#374151;border-bottom:1px solid #f9fafb}
    .dest-table tbody tr:hover{background:#f9fafb}
    .dest-rank{font-weight:700;color:#7b1fa2}
    .dest-bar-wrap{width:100px;background:#f3f4f6;border-radius:4px;height:8px;display:inline-block;vertical-align:middle;margin-right:6px}
    .dest-bar{height:8px;border-radius:4px;background:linear-gradient(90deg,#7b1fa2,#ba68c8)}

    /* TABLE */
    .panel{background:#fff;border-radius:10px;padding:20px 22px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:20px}
    .panel h3{font-size:14px;font-weight:600;color:#1a1a1a;margin-bottom:14px}
    table{width:100%;border-collapse:collapse}
    th{padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #f0f0f0}
    td{padding:11px 12px;font-size:12px;color:#374151;border-bottom:1px solid #f9fafb}
    tbody tr:hover{background:#f9fafb}
    .badge{font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px}
    .badge-pending{color:#d97706;background:#fef9c3}
    .badge-approved{color:#2e7d32;background:#e8f5e9}
    .badge-rejected{color:#6b7280;background:#f3f4f6}
    .badge-completed{color:#1a56db;background:#eff6ff}
    .empty-row td{text-align:center;color:#9ca3af;padding:24px}

    /* HISTORY */
    .history-item{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #f5f5f5}
    .history-item:last-child{border-bottom:none}
    .history-icon{width:36px;height:36px;background:#f3e8fd;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
    .history-info strong{font-size:13px;font-weight:600;color:#1a1a1a;display:block;text-transform:uppercase}
    .history-info span{font-size:11px;color:#9ca3af}

    /* FOOTER */
    .footer{background:#1a1a2e;text-align:center;padding:20px;margin-top:0}
    .footer p{font-size:12px;color:#9ca3af}
    .footer p:last-child{font-size:11px;color:#6b7280;margin-top:4px}

    /* ── PDF PRINT STYLES ── */
    @media print {
      body { background: #fff !important; }
      .navbar, .filter-panel, .btn-back, .btn-pdf, .btn-generate,
      .page-header .btn-back, .footer, .panel:last-child { display: none !important; }
      .main { padding: 0; max-width: 100%; }
      .page-header { margin-bottom: 16px; }
      .stat-card, .breakdown, .panel, .chart-panel { box-shadow: none; border: 1px solid #e5e7eb; }
      .print-header { display: block !important; }
      .charts-row { grid-template-columns: 1fr 1fr; }
    }

    /* Print header shown only when printing */
    .print-header {
      display: none;
      text-align: center;
      padding-bottom: 16px;
      border-bottom: 2px solid #2e7d32;
      margin-bottom: 20px;
    }
    .print-header h2 { font-size: 18px; color: #1a1a1a; }
    .print-header p  { font-size: 12px; color: #6b7280; margin-top: 4px; }
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
      <form action="logout.php" method="POST"><button class="btn-logout" type="submit">&#8594; Logout</button></form>
    </div>
  </nav>

  <div class="main">

    <!-- Print Header -->
    <div class="print-header">
      <h2>BukSU Visitor Log Monitoring System</h2>
      <p><?php echo strtoupper($report_type); ?> REPORT &nbsp;|&nbsp; Period: <?php echo $display_from; ?> - <?php echo $display_to; ?></p>
      <p>Generated: <?php echo date('F d, Y h:i A'); ?> &nbsp;|&nbsp; Bukidnon State University — Entrance and Security Office</p>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div><h1>Reports &amp; Analytics</h1><p>Generate comprehensive visitor activity reports</p></div>
      <a href="admin_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
      <form method="GET" action="reports.php">
        <div class="filter-row">
          <div class="filter-group">
            <label>Report Type</label>
            <select name="type" class="filter-select">
              <option value="daily"   <?php echo $report_type==='daily'   ? 'selected':''; ?>>Daily Report</option>
              <option value="weekly"  <?php echo $report_type==='weekly'  ? 'selected':''; ?>>Weekly Report</option>
              <option value="monthly" <?php echo $report_type==='monthly' ? 'selected':''; ?>>Monthly Report</option>
            </select>
          </div>
          <div class="filter-group">
            <label>Select Date</label>
            <input type="date" name="date" class="filter-date" value="<?php echo $date; ?>"/>
          </div>
          <input type="hidden" name="generate" value="1"/>
          <button type="submit" class="btn-generate">&#8595; Generate Report</button>
          <button type="button" class="btn-pdf" onclick="window.print()">&#128196; Download PDF</button>
        </div>
        <p class="period-label">&#128197; Report Period: <b><?php echo $display_from; ?> - <?php echo $display_to; ?></b>
          <?php if ($just_generated): ?>
            &nbsp; <span style="color:#2e7d32;font-weight:500;">&#10003; Report saved to history</span>
          <?php endif; ?>
        </p>
      </form>
    </div>

    <!-- Stat Cards -->
    <div class="stats">
      <div class="stat-card">
        <div class="stat-icon icon-blue">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $total; ?></div>
        <div class="stat-title">Total Visits</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon icon-green">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $completed; ?></div>
        <div class="stat-title">Completed Visits</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon icon-yellow">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="stat-number"><?php echo $pending; ?></div>
        <div class="stat-title">Pending Approvals</div>
      </div>
    </div>

    <!-- Status Breakdown -->
    <div class="breakdown">
      <h3>Status Breakdown</h3>
      <div class="breakdown-grid">
        <div class="breakdown-item b-pending"><div class="breakdown-number"><?php echo $pending; ?></div><div class="breakdown-label">Pending</div></div>
        <div class="breakdown-item b-approved"><div class="breakdown-number"><?php echo $approved; ?></div><div class="breakdown-label">Approved</div></div>
        <div class="breakdown-item b-checkin"><div class="breakdown-number"><?php echo $approved; ?></div><div class="breakdown-label">Checked In</div></div>
        <div class="breakdown-item b-completed"><div class="breakdown-number"><?php echo $completed; ?></div><div class="breakdown-label">Completed</div></div>
        <div class="breakdown-item b-rejected"><div class="breakdown-number"><?php echo $rejected; ?></div><div class="breakdown-label">Rejected</div></div>
      </div>
    </div>

    <!-- ── CHARTS ROW ── -->
    <div class="charts-row">

      <!-- Pie Chart 1: Most Visited Destinations -->
      <div class="chart-panel">
        <h3>&#128205; Most Visited Destinations</h3>
        <p class="chart-sub">Visit distribution by campus location for this period</p>
        <?php if (count($chart_labels) > 0): ?>
          <div class="chart-wrap"><canvas id="destChart"></canvas></div>
          <div class="dest-table-wrap">
            <table class="dest-table">
              <thead><tr><th>#</th><th>Destination</th><th>Visits</th><th>Share</th></tr></thead>
              <tbody>
                <?php
                  $max_count = max($chart_counts);
                  foreach ($chart_labels as $i => $lbl):
                    $cnt   = $chart_counts[$i];
                    $pct   = $total > 0 ? round($cnt / $total * 100) : 0;
                    $width = $max_count > 0 ? round($cnt / $max_count * 100) : 0;
                ?>
                <tr>
                  <td class="dest-rank"><?php echo $i+1; ?></td>
                  <td><?php echo htmlspecialchars($lbl); ?></td>
                  <td><strong><?php echo $cnt; ?></strong></td>
                  <td>
                    <span class="dest-bar-wrap"><span class="dest-bar" style="width:<?php echo $width; ?>%"></span></span>
                    <?php echo $pct; ?>%
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="no-data-msg">&#128202; No destination data for this period</div>
        <?php endif; ?>
      </div>

      <!-- Pie Chart 2: Visit Stats Overview -->
      <div class="chart-panel">
        <h3>&#128200; Visit Statistics Overview</h3>
        <p class="chart-sub">Total users, approved, rejected, and total visits</p>
        <div class="chart-wrap"><canvas id="statsChart"></canvas></div>
        <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="background:#e8f0fe;border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#1a73e8"><?php echo $total_users; ?></div>
            <div style="font-size:11px;color:#6b7280">Total Users</div>
          </div>
          <div style="background:#e8f5e9;border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#2e7d32"><?php echo $approved; ?></div>
            <div style="font-size:11px;color:#6b7280">Approved Visits</div>
          </div>
          <div style="background:#fdecea;border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#c62828"><?php echo $rejected; ?></div>
            <div style="font-size:11px;color:#6b7280">Rejected Visits</div>
          </div>
          <div style="background:#f3e8fd;border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#7b1fa2"><?php echo $total; ?></div>
            <div style="font-size:11px;color:#6b7280">Total Visits</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Visit Log -->
    <div class="panel">
      <h3>Detailed Visit Log</h3>
      <table>
        <thead>
          <tr><th>#</th><th>Visitor Name</th><th>Purpose</th><th>Destination</th><th>Date &amp; Time</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if ($visit_log && $visit_log->num_rows > 0): ?>
            <?php while ($row = $visit_log->fetch_assoc()):
              if ($row['status']==='pending')        $b='badge-pending';
              elseif ($row['status']==='approved')   $b='badge-approved';
              elseif ($row['status']==='completed')  $b='badge-completed';
              else $b='badge-rejected';
            ?>
            <tr>
              <td><?php echo $row['id']; ?></td>
              <td><?php echo htmlspecialchars($row['full_name']); ?></td>
              <td><?php echo htmlspecialchars($row['purpose']); ?></td>
              <td><?php echo htmlspecialchars($row['destination']); ?></td>
              <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
              <td><span class="badge <?php echo $b; ?>"><?php echo strtoupper($row['status']); ?></span></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="empty-row"><td colspan="6">No visits found for this period</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Report History -->
    <div class="panel">
      <h3>Report Generation History</h3>
      <?php if ($history && $history->num_rows > 0): ?>
        <?php while ($h = $history->fetch_assoc()): ?>
        <div class="history-item">
          <div class="history-icon">&#128196;</div>
          <div class="history-info">
            <strong><?php echo strtoupper($h['report_type']); ?> Report</strong>
            <span><?php echo date('M d, Y h:i A', strtotime($h['created_at'])); ?></span>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p style="font-size:13px;color:#9ca3af;text-align:center;padding:20px 0">No reports generated yet. Click "Generate Report" to create one.</p>
      <?php endif; ?>
    </div>

  </div>

  <footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Bukidnon State University &mdash; Entrance and Security Office</p>
    <p>Malaybalay City, Bukidnon, Philippines</p>
  </footer>

  <!-- ── Chart.js scripts ── -->
  <script>
    // ── Colour palette (18 colours for 18 destinations) ──
    const PALETTE = [
      '#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f',
      '#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ac',
      '#1a73e8','#2e7d32','#7b1fa2','#c62828','#0097a7',
      '#f57c00','#558b2f','#6a1b9a'
    ];

    // ── Destination Pie Chart ──
    const destLabels = <?php echo $js_labels; ?>;
    const destCounts = <?php echo $js_counts; ?>;

    if (destLabels.length > 0) {
      const destCtx = document.getElementById('destChart').getContext('2d');
      new Chart(destCtx, {
        type: 'pie',
        data: {
          labels: destLabels,
          datasets: [{
            data: destCounts,
            backgroundColor: PALETTE.slice(0, destLabels.length),
            borderWidth: 2,
            borderColor: '#fff'
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                font: { family: 'Poppins', size: 10 },
                padding: 10,
                boxWidth: 12
              }
            },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                  const pct   = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                  return ` ${ctx.label}: ${ctx.parsed} visits (${pct}%)`;
                }
              }
            }
          }
        }
      });
    }

    // ── Stats Pie Chart ──
    const statsData = <?php echo $js_stats; ?>;
    const statsCtx  = document.getElementById('statsChart').getContext('2d');
    new Chart(statsCtx, {
      type: 'doughnut',
      data: {
        labels: Object.keys(statsData),
        datasets: [{
          data: Object.values(statsData),
          backgroundColor: ['#1a73e8','#2e7d32','#c62828','#7b1fa2'],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        cutout: '55%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { family: 'Poppins', size: 11 },
              padding: 12,
              boxWidth: 14
            }
          },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                return ` ${ctx.label}: ${ctx.parsed}`;
              }
            }
          }
        }
      }
    });
  </script>

<?php $conn->close(); ?>
</body>
</html>