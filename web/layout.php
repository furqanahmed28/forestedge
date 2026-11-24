<?php
// web/layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'RANGER';
$is_admin = ($role === 'ADMIN');

function nav_class($page, $current) {
    return ($page === $current) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ForestEdge Portal</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <style>
    :root {
      --green: #2E7D32;
      --green-light: #43A047;
      --green-bg: #E8F5E9;
      --bg: #f5f7f8;
      --white: #fff;
      --border: #e3e6e8;
      --text-dark: #1f1f1f;
      --text-gray: #555;
      --text-light: #777;
      --red: #c62828;
      --yellow: #f9a825;
      --blue: #1976D2;
      --shadow: rgba(0, 0, 0, 0.06);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    body { background-color: var(--bg); color: var(--text-dark); min-height: 100vh; }

    header { background: var(--white); border-bottom: 1px solid var(--border);
      display: flex; justify-content: space-between; align-items: center;
      padding: 1rem 2.5rem; box-shadow: 0 2px 6px var(--shadow);
      position: sticky; top: 0; z-index: 1010; }
    .logo { display: flex; align-items: center; font-size: 1.6rem; font-weight: 700; color: var(--text-dark); text-decoration:none;}
    .logo span { color: var(--green); }
    nav a { text-decoration: none; color: var(--text-gray); margin: 0 1rem; font-weight: 500; transition: 0.2s;
      border-bottom: 2px solid transparent; padding-bottom: 3px; }
    nav a:hover, nav a.active { color: var(--green); border-bottom-color: var(--green); }
    .user-info { color: var(--text-light); font-size: 0.95rem; }
    .btn-logout { background: transparent; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; cursor: pointer; color: #555; text-decoration:none; margin-left:15px; }

    /* ===== METRIC CARDS ===== */
    .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
    @media (max-width: 1100px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .metrics-grid { grid-template-columns: 1fr; } }
    .metric-card {
      background: var(--white); border: 1px solid var(--border); border-radius: 10px;
      padding: 1.2rem 1.5rem; display: flex; align-items: center;
      box-shadow: 0 2px 8px var(--shadow); transition: 0.2s;
    }
    .metric-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .metric-icon { width: 42px; height: 42px; margin-right: 1rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .metric-card-content h3 { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: var(--text-gray); margin-bottom: 0.2rem; line-height: 1.2;}
    .metric-card-content .value { font-size: 1.6rem; font-weight: 700; color: var(--text-dark); }
    .metric-card-content .details { font-size: 0.8rem; color: var(--text-light); margin-top: 0.2rem; }

    /* ===== CONTENT LAYOUT ===== */
    .content-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; }
    @media (max-width: 1100px) { .content-columns { grid-template-columns: 1fr; } }
    
    .alert-card { border: 1px solid var(--border); border-radius: 8px; background: var(--white);
      margin-bottom: 1rem; transition: 0.2s; opacity: 1; }
    .alert-card:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.07); }
    .alert-header { display: flex; justify-content: space-between; align-items: center;
      padding: 0.9rem 1rem; border-bottom: 1px solid var(--border); }
    .alert-title { font-weight: 600; font-size: 1rem; color: var(--text-dark); }
    .alert-body { padding: 0.9rem 1rem 1.1rem; }
    .alert-meta { font-size: 0.85rem; color: var(--text-light); margin-bottom: 0.5rem; line-height: 1.4; }
    .alert-details { background: var(--green-bg); padding: 0.75rem; border-radius: 6px; font-size: 0.9rem; color: var(--text-dark); }
    
    .type-fire, .crit-high { color: var(--red) !important; font-weight: 600; }
    .type-disease { color: var(--yellow) !important; font-weight: 600; }
    .type-other { color: var(--blue) !important; font-weight: 600; }
    
    .crit-medium { color: #f57c00 !important; font-weight: 600; }
    .crit-low { color: var(--yellow) !important; font-weight: 600; }
    .status-new { color: var(--green); font-weight: 600; }

    main { padding: 2rem 2.5rem; max-width: 1600px; margin: auto; }
    h1 { font-size: 2rem; font-weight: 600; margin-bottom: 1.5rem; }
    
    .panel { background: var(--white); border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 8px var(--shadow); margin-bottom: 1.5rem;}
    
    table.data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    table.data-table th, table.data-table td { text-align: left; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); }
    table.data-table th { background: #f3f6f4; font-weight: 600; color: var(--text-gray); }
    table.data-table tr:hover td { background-color: var(--green-bg); }

    .btn-primary { background: var(--green); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; text-decoration:none; display:inline-block;}
    .btn-danger { background: var(--red); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; text-decoration:none; display:inline-block;}

    /* Form Styles */
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500;}
    .form-control { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
    
    /* Modal styles */
    .modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items:center; justify-content:center;}
    .modal-backdrop.show { display: flex; }
    .modal-box { background: white; padding: 2rem; border-radius: 8px; width: 400px; max-width: 90%; }
    
    /* Toasts */
    #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 3000; }
    .toast { background: var(--text-dark); color: white; padding: 1rem; border-radius: 6px; margin-top: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0; transform: translateY(20px); transition: 0.3s; }
    .toast.show { opacity: 1; transform: translateY(0); }
  </style>
</head>
<body>
  <header>
    <a href="index.php" class="logo">Forest<span>Edge</span></a>
    <nav>
      <a href="index.php" class="<?= nav_class('index.php', $current_page) ?>">Dashboard</a>
      <a href="events_list.php" class="<?= nav_class('events_list.php', $current_page) ?>">Events</a>
      <?php if($is_admin): ?>
      <a href="nodes.php" class="<?= nav_class('nodes.php', $current_page) ?>">Nodes</a>
      <a href="clusters.php" class="<?= nav_class('clusters.php', $current_page) ?>">Clusters</a>
      <a href="users.php" class="<?= nav_class('users.php', $current_page) ?>">Users</a>
      <?php endif; ?>
    </nav>
    <div class="user-info">Logged in as: <strong>Role: <?= htmlspecialchars($role); ?></strong> <a href="logout.php" class="btn-logout">Logout</a></div>
  </header>
  
  <div id="toast-container"></div>
  
  <main>
    <!-- Content injected here -->
