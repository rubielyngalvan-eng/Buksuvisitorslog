<?php
session_start();

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: login.php?type=admin');
    exit;
}

// ── Database connection ──
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$activity_result = $conn->query("\n    SELECT 
        v.full_name,
        vl.purpose,
        vl.status,
        vl.created_at
    FROM visits_log vl
    JOIN visitors v ON v.id = vl.visitor_id
    ORDER BY vl.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>All Activities — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background-color: #001f3f; color: #1a1a1a; min-height: 100vh; }
    .container { max-width: 960px; margin: 0 auto; padding: 28px 20px; }
    .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
    .header h1 { font-size: 22px; font-weight: 700; color: #fff; }
    .header p { font-size: 13px; color: #c8e6c9; }
    .btn-back { display: inline-block; padding: 10px 16px; background-color: #2e7d32; color: #fff; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; }
    .panel { background-color: #ffffff; border-radius: 12px; padding: 22px; box-shadow: 0 4px 20px rgba(255, 215, 0, 0.25); }
    .panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
    .panel-title { font-size: 18px; font-weight: 700; color: #1a1a1a; }
    .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-bottom: 1px solid #f3f4f6; }
    .activity-item:last-child { border-bottom: none; }
    .activity-info strong { font-size: 14px; font-weight: 600; color: #1a1a1a; display: block; }
    .activity-info p { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .badge { font-size: 11px; font-weight: 600; padding: 6px 12px; border-radius: 999px; white-space: nowrap; }
    .badge-pending   { color: #b45309; background-color: #fef3c7; }
    .badge-approved  { color: #166534; background-color: #dcfce7; }
    .badge-rejected  { color: #7f1d1d; background-color: #fee2e2; }
    .badge-completed { color: #4b5563; background-color: #f3f4f6; }
    .empty-state { font-size: 13px; color: #9ca3af; text-align: center; padding: 24px 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div>
        <h1>All Activities</h1>
        <p>View the complete activity log for all visitor requests.</p>
      </div>
      <a href="admin_dashboard.php" class="btn-back">Back to Dashboard</a>
    </div>

    <div class="panel">
      <?php if ($activity_result && $activity_result->num_rows > 0): ?>
        <?php while ($a = $activity_result->fetch_assoc()):
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
      <?php else: ?>
        <p class="empty-state">No activity records found.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>
