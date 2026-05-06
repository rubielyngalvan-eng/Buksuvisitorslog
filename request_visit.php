<?php
session_start();

// ── Database connection ──
$host = "localhost";
$db   = "buksuvisitorslogdb";
$user = "root";
$pass = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Ensure optional person_to_visit column exists
if ($conn->query("SHOW COLUMNS FROM visits_log LIKE 'person_to_visit'")->num_rows === 0) {
    $conn->query("ALTER TABLE visits_log ADD COLUMN person_to_visit VARCHAR(150) DEFAULT NULL");
}

// ── Session — use real session values from login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['name'])) {
    header('Location: login.php');
    exit;
}
$visitor_name = $_SESSION['name'];
$visitor_id = $_SESSION['visitor_id'] ?? null;

if (empty($visitor_id) && isset($_SESSION['role']) && $_SESSION['role'] === 'visitor') {
    $email = $conn->real_escape_string($_SESSION['email'] ?? '');
    if ($email !== '') {
        $visitorResult = $conn->query("SELECT id FROM visitors WHERE email = '$email' LIMIT 1");
        if ($visitorResult && $visitorRow = $visitorResult->fetch_assoc()) {
            $visitor_id = $visitorRow['id'];
            $_SESSION['visitor_id'] = $visitor_id;
        }
    }
}

if (empty($visitor_id)) {
    die('Unable to resolve visitor account. Please log in again.');
}


$error   = '';
$success = '';

// ── Destination options ──
$destinations = [
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
    "Supply Office",
];

// ── Handle form submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose          = trim($_POST['purpose'] ?? '');
    $destination      = trim($_POST['destination'] ?? '');
    $person_to_visit = trim($_POST['person_to_visit'] ?? '');
    $plate            = trim($_POST['plate'] ?? '');

    // Basic validation
    if (empty($purpose)) {
        $error = 'Please enter your purpose of visit.';
    } elseif (empty($destination)) {
        $error = 'Please select a destination.';
    } else {
        // Insert into visits_log
        $safe_purpose      = $conn->real_escape_string($purpose);
        $safe_destination  = $conn->real_escape_string($destination);
        $safe_person       = $conn->real_escape_string($person_to_visit);
        $today             = date('Y-m-d');

        $result = $conn->query("
            INSERT INTO visits_log (visitor_id, purpose, destination, person_to_visit, appointment_date, status)
            VALUES ($visitor_id, '$safe_purpose', '$safe_destination', '$safe_person', '$today', 'pending')
        ");

        if ($result) {
            header('Location: visitor_dashboard.php');
            exit;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>New Visit Request — BukSU</title>
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
    .main { max-width:600px; margin:40px auto; padding:0 20px; flex:1; width:100%; }

    /* FORM CARD */
    .form-card {
      background:#fff;
      border-radius:12px;
      padding:36px;
      box-shadow:0 2px 12px rgba(0,0,0,0.08);
    }

    /* Card header icon */
    .card-icon {
      width:52px;
      height:52px;
      background:#e8f5e9;
      border-radius:50%;
      display:flex;
      align-items:center;
      justify-content:center;
      margin:0 auto 16px auto;
    }

    .card-icon svg { width:24px; height:24px; color:#2e7d32; }

    .card-title {
      text-align:center;
      font-size:18px;
      font-weight:600;
      color:#1a1a1a;
      margin-bottom:6px;
    }

    .card-subtitle {
      text-align:center;
      font-size:13px;
      color:#6b7280;
      margin-bottom:28px;
    }

    /* Error / success */
    .alert {
      padding:10px 14px;
      border-radius:8px;
      font-size:13px;
      margin-bottom:18px;
      text-align:center;
    }

    .alert-error   { background:#fdecea; color:#c62828; }
    .alert-success { background:#e8f5e9; color:#2e7d32; }

    /* Form fields */
    .form-group { margin-bottom:20px; }

    .form-group label {
      display:flex;
      align-items:center;
      gap:6px;
      font-size:13px;
      font-weight:500;
      color:#374151;
      margin-bottom:7px;
    }

    .form-group label svg { width:14px; height:14px; color:#6b7280; }

    .form-group textarea,
    .form-group select,
    .form-group input {
      width:100%;
      padding:11px 14px;
      border:1px solid #d1d5db;
      border-radius:8px;
      font-size:13px;
      font-family:'Poppins',sans-serif;
      color:#111827;
      outline:none;
      transition:border-color 0.2s;
      background:#fff;
    }

    .form-group textarea { resize:vertical; min-height:90px; }
    .form-group textarea:focus,
    .form-group select:focus,
    .form-group input:focus { border-color:#2e7d32; }

    .field-hint { font-size:11px; color:#9ca3af; margin-top:5px; }

    /* Important notes box */
    .notes-box {
      background:#eff6ff;
      border:1px solid #bfdbfe;
      border-radius:8px;
      padding:14px 16px;
      margin-bottom:24px;
    }

    .notes-box p {
      font-size:12px;
      font-weight:600;
      color:#1a56db;
      margin-bottom:8px;
    }

    .notes-box ul { list-style:none; }

    .notes-box ul li {
      font-size:12px;
      color:#1a56db;
      margin-bottom:4px;
      display:flex;
      align-items:flex-start;
      gap:6px;
    }

    .notes-box ul li::before { content:"•"; font-weight:700; }

    /* Buttons */
    .btn-row { display:flex; gap:12px; }

    .btn-cancel {
      flex:1;
      padding:11px;
      background:#fff;
      color:#374151;
      font-size:13px;
      font-weight:500;
      font-family:'Poppins',sans-serif;
      border:1px solid #d1d5db;
      border-radius:8px;
      cursor:pointer;
      text-align:center;
      text-decoration:none;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    .btn-cancel:hover { background:#f5f6fa; }

    .btn-submit {
      flex:2;
      padding:11px;
      background:#2e7d32;
      color:#fff;
      font-size:13px;
      font-weight:600;
      font-family:'Poppins',sans-serif;
      border:none;
      border-radius:8px;
      cursor:pointer;
      transition:background 0.2s;
    }

    .btn-submit:hover { background:#256427; }

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
    <div class="form-card">

      <!-- Icon -->
      <div class="card-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>

      <h1 class="card-title">New Visit Request</h1>
      <p class="card-subtitle">Submit your campus visit details</p>

      <!-- Alerts -->
      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <!-- Form -->
      <form method="POST" action="request_visit.php">

        <!-- Purpose of Visit -->
        <div class="form-group">
          <label>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Purpose of Visit *
          </label>
          <textarea
            name="purpose"
            placeholder="e.g., Meeting with Dean, Document Submission, Research Collaboration"
            required
          ><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
        </div>

        <!-- Destination -->
        <div class="form-group">
          <label>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Destination *
          </label>
          <select name="destination" required>
            <option value="" disabled selected>Select destination</option>
            <?php foreach ($destinations as $d): ?>
              <option value="<?php echo $d; ?>"
                <?php echo (isset($_POST['destination']) && $_POST['destination'] === $d) ? 'selected' : ''; ?>>
                <?php echo $d; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Person to Visit (optional) -->
        <div class="form-group">
          <label>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A9 9 0 1118.879 6.196 9 9 0 015.121 17.804z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 11a3 3 0 100-6 3 3 0 000 6zm0 2c-2.667 0-8 1.333-8 4v1h16v-1c0-2.667-5.333-4-8-4z"/>
            </svg>
            Person You're Visiting (Optional)
          </label>
          <input
            type="text"
            name="person_to_visit"
            placeholder="e.g., Dean Santos"
            value="<?php echo isset($_POST['person_to_visit']) ? htmlspecialchars($_POST['person_to_visit']) : ''; ?>"
          />
          <p class="field-hint">Enter the name of the person or office contact if known.</p>
        </div>

        <!-- Vehicle Plate (optional) -->
        <div class="form-group">
          <label>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 1h6l2-1z"/>
            </svg>
            Vehicle Plate Number (Optional)
          </label>
          <input
            type="text"
            name="plate"
            placeholder="ABC-1234"
            value="<?php echo isset($_POST['plate']) ? htmlspecialchars($_POST['plate']) : ''; ?>"
          />
          <p class="field-hint">Only if bringing a vehicle to campus</p>
        </div>

        <!-- Important Notes -->
        <div class="notes-box">
          <p>Important Notes:</p>
          <ul>
            <li>Your visit request will be reviewed by security personnel</li>
            <li>You will be notified once your request is approved</li>
            <li>Please bring a valid ID when entering the campus</li>
            <li>Check-in is required upon arrival at the gate</li>
          </ul>
        </div>

        <!-- Buttons -->
        <div class="btn-row">
          <a href="visitor_dashboard.php" class="btn-cancel">Cancel</a>
          <button type="submit" class="btn-submit">Submit Request</button>
          
        </div>

      </form>
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
