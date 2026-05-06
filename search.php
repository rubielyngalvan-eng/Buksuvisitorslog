<?php
$host = "localhost"; $db = "buksuvisitorslogdb"; $user = "root"; $pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$search  = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $vr   = $conn->query("
        SELECT id, full_name, email, contact_number, address
        FROM visitors
        WHERE full_name LIKE '%$safe%' OR email LIKE '%$safe%'
           OR contact_number LIKE '%$safe%' OR address LIKE '%$safe%'
        ORDER BY full_name ASC LIMIT 20
    ");
    while ($v = $vr->fetch_assoc()) {
        $vid = $v['id'];
        $hr  = $conn->query("
            SELECT vl.id, vl.purpose, vl.destination, vl.status, vl.created_at,
                   MAX(CASE WHEN h.action='IN'  THEN h.timestamp END) AS check_in,
                   MAX(CASE WHEN h.action='OUT' THEN h.timestamp END) AS check_out
            FROM visits_log vl LEFT JOIN history h ON h.visit_id = vl.id
            WHERE vl.visitor_id = $vid GROUP BY vl.id ORDER BY vl.created_at DESC
        ");
        $v['visits'] = [];
        while ($visit = $hr->fetch_assoc()) $v['visits'][] = $visit;
        $results[] = $v;
    }
}

// Return JSON for AJAX live search
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Visitor Search — BukSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:#f5f6fa;min-height:100vh;display:flex;flex-direction:column}
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
    .main{max-width:900px;margin:0 auto;padding:28px 20px;flex:1;width:100%}
    .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px}
    .page-header h1{font-size:20px;font-weight:600;color:#1a1a1a}
    .page-header p{font-size:13px;color:#6b7280;margin-top:3px}
    .btn-back{padding:8px 18px;background:#fff;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:'Poppins',sans-serif;color:#374151;text-decoration:none}
    .search-panel{background:#fff;border-radius:10px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:20px;position:relative}
    .search-row{display:flex;gap:10px;align-items:center}
    .search-input{flex:1;padding:11px 16px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:'Poppins',sans-serif;outline:none}
    .search-input:focus{border-color:#1a3a5c}
    .btn-search{padding:11px 24px;background:#1a3a5c;color:#fff;font-size:13px;font-weight:600;font-family:'Poppins',sans-serif;border:none;border-radius:8px;cursor:pointer}
    .spinner{width:18px;height:18px;border:2px solid #e5e7eb;border-top-color:#1a3a5c;border-radius:50%;animation:spin .6s linear infinite;display:none;flex-shrink:0}
    @keyframes spin{to{transform:rotate(360deg)}}
    /* Live dropdown */
    .live-dropdown{position:absolute;top:calc(100% - 10px);left:22px;right:22px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:200;max-height:280px;overflow-y:auto;display:none}
    .live-item{padding:11px 16px;cursor:pointer;border-bottom:1px solid #f5f5f5;display:flex;align-items:center;gap:12px}
    .live-item:last-child{border-bottom:none}
    .live-item:hover{background:#f5f6fa}
    .live-avatar{width:32px;height:32px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#1a73e8;flex-shrink:0}
    .live-name{font-size:13px;font-weight:500;color:#1a1a1a}
    .live-sub{font-size:11px;color:#9ca3af;margin-top:1px}
    /* Result cards */
    .result-count{font-size:13px;color:#6b7280;margin-bottom:14px}
    .result-count b{color:#1a1a1a}
    .result-card{background:#fff;border-radius:10px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden}
    .visitor-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;cursor:pointer}
    .visitor-header:hover{background:#f9fafb}
    .visitor-left{display:flex;align-items:center;gap:14px}
    .visitor-avatar{width:42px;height:42px;background:#e8f0fe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:600;color:#1a73e8;flex-shrink:0}
    .visitor-name{font-size:14px;font-weight:600;color:#1a1a1a}
    .visitor-sub{font-size:12px;color:#6b7280;margin-top:3px}
    .chevron{font-size:18px;color:#9ca3af;transition:transform .2s}
    .chevron.open{transform:rotate(180deg)}
    /* Visit history */
    .visit-history{display:none;border-top:1px solid #f0f0f0}
    .visit-history.open{display:block}
    .history-head{padding:10px 20px;background:#f9fafb;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px}
    .visit-row{display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:10px;padding:12px 20px;border-bottom:1px solid #f5f5f5;align-items:center;font-size:12px}
    .visit-row:last-child{border-bottom:none}
    .vr-purpose{font-weight:500;color:#1a1a1a}
    .vr-dest,.vr-date{color:#6b7280}
    .badge{font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;white-space:nowrap}
    .badge-pending{color:#d97706;background:#fef9c3}
    .badge-approved{color:#2e7d32;background:#e8f5e9}
    .badge-rejected{color:#c62828;background:#fdecea}
    .badge-completed{color:#6b7280;background:#f3f4f6}
    .no-visits{padding:16px 20px;font-size:13px;color:#9ca3af;text-align:center}
    .hint,.empty{text-align:center;padding:40px;color:#9ca3af;font-size:13px}
    .empty{background:#fff;border-radius:10px}
    .footer{background:#1a1a2e;text-align:center;padding:20px;margin-top:0}
    .footer p{font-size:12px;color:#9ca3af}
    .footer p:last-child{font-size:11px;color:#6b7280;margin-top:4px}
  </style>
</head>
<body>
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
    <div class="page-header">
      <div><h1>Visitor Search</h1><p>Quick search for emergency situations</p></div>
      <a href="admin_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <!-- Search Box -->
    <div class="search-panel" id="searchPanel">
      <form method="GET" action="search.php">
        <div class="search-row">
          <input type="text" id="searchInput" name="q" class="search-input"
            placeholder="Search by name, email, phone, or address..."
            value="<?php echo htmlspecialchars($search); ?>"
            autocomplete="off" autofocus/>
          <div class="spinner" id="spinner"></div>
          <button type="submit" class="btn-search">&#128269; Search</button>
        </div>
      </form>
      <div class="live-dropdown" id="liveDropdown"></div>
    </div>

    <!-- Results -->
    <div id="resultsSection">
      <?php if ($search === ''): ?>
        <p class="hint">Enter a name, email, phone number, or address above to search for a visitor.</p>

      <?php elseif (count($results) > 0): ?>
        <p class="result-count">Found <b><?php echo count($results); ?></b> result(s) for "<b><?php echo htmlspecialchars($search); ?></b>"</p>
        <?php foreach ($results as $r):
          $init = strtoupper(substr($r['full_name'], 0, 1));
          $vc   = count($r['visits']);
        ?>
        <div class="result-card">
          <div class="visitor-header" onclick="toggleHistory(this)">
            <div class="visitor-left">
              <div class="visitor-avatar"><?php echo $init; ?></div>
              <div>
                <div class="visitor-name"><?php echo htmlspecialchars($r['full_name']); ?></div>
                <div class="visitor-sub">
                  <?php echo htmlspecialchars($r['email']); ?> &nbsp;&#183;&nbsp;
                  <?php echo htmlspecialchars($r['contact_number'] ?? '—'); ?> &nbsp;&#183;&nbsp;
                  <?php echo htmlspecialchars($r['address'] ?? '—'); ?> &nbsp;&#183;&nbsp;
                  <b><?php echo $vc; ?> visit<?php echo $vc !== 1 ? 's' : ''; ?></b>
                </div>
              </div>
            </div>
            <span class="chevron">&#8964;</span>
          </div>

          <div class="visit-history">
            <div class="history-head">Visit History</div>
            <?php if ($vc > 0): ?>
              <?php foreach ($r['visits'] as $vst):
                if ($vst['status'] === 'pending')        $b = 'badge-pending';
                elseif ($vst['status'] === 'approved')   $b = 'badge-approved';
                elseif ($vst['status'] === 'completed')  $b = 'badge-completed';
                else $b = 'badge-rejected';
              ?>
              <div class="visit-row">
                <div class="vr-purpose"><?php echo htmlspecialchars($vst['purpose']); ?></div>
                <div class="vr-dest"><?php echo htmlspecialchars($vst['destination']); ?></div>
                <div class="vr-date"><?php echo date('M d, Y h:i A', strtotime($vst['created_at'])); ?></div>
                <span class="badge <?php echo $b; ?>"><?php echo strtoupper($vst['status']); ?></span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="no-visits">No visit records found.</p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="empty">No visitors found matching "<b><?php echo htmlspecialchars($search); ?></b>".</div>
      <?php endif; ?>
    </div>
  </div>

  <footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> Bukidnon State University &mdash; Entrance and Security Office</p>
    <p>Malaybalay City, Bukidnon, Philippines</p>
  </footer>

  <script>
    const input    = document.getElementById('searchInput');
    const dropdown = document.getElementById('liveDropdown');
    const spinner  = document.getElementById('spinner');
    let   timer    = null;

    // Live search — fires 300ms after user stops typing
    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();
      dropdown.style.display = 'none';
      if (q.length < 2) { spinner.style.display = 'none'; return; }

      spinner.style.display = 'inline-block';

      timer = setTimeout(() => {
        fetch(`search.php?ajax=1&q=${encodeURIComponent(q)}`)
          .then(r => r.json())
          .then(data => {
            spinner.style.display = 'none';
            dropdown.innerHTML = '';

            if (!data.length) {
              dropdown.innerHTML = '<div class="live-item"><span style="color:#9ca3af;font-size:13px;">No results found</span></div>';
            } else {
              data.forEach(v => {
                const el = document.createElement('div');
                el.className = 'live-item';
                el.innerHTML = `
                  <div class="live-avatar">${v.full_name.charAt(0).toUpperCase()}</div>
                  <div>
                    <div class="live-name">${esc(v.full_name)}</div>
                    <div class="live-sub">${esc(v.email)} &nbsp;&#183;&nbsp; ${v.visits.length} visit(s)</div>
                  </div>`;
                el.addEventListener('click', () => {
                  window.location.href = `search.php?q=${encodeURIComponent(v.full_name)}`;
                });
                dropdown.appendChild(el);
              });
            }
            dropdown.style.display = 'block';
          })
          .catch(() => spinner.style.display = 'none');
      }, 300);
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', e => {
      if (!document.getElementById('searchPanel').contains(e.target))
        dropdown.style.display = 'none';
    });

    // Expand / collapse visit history
    function toggleHistory(header) {
      const history = header.nextElementSibling;
      const chevron = header.querySelector('.chevron');
      const open    = history.classList.toggle('open');
      chevron.classList.toggle('open', open);
    }

    function esc(str) {
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
