<?php
require_once __DIR__ . '/auth.php';

// Shared shell: header/nav with role-aware links
function renderHeader($title = 'MyParking') {
    $user = currentUser();
    $role = $user['user_type'] ?? '';
    // Ensure CSRF token exists for safe POST logout
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION['csrf_token'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo htmlspecialchars($title); ?></title>
        <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/fonts/style.css'; ?>">
        <style>
            body { margin: 0; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f7f8fc; color: #1f2937; }
            .shell { max-width: 1100px; margin: 0 auto; padding: 32px 24px 80px; }
            header { background: #0f172a; color: #fff; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
            header .brand { font-weight: 800; letter-spacing: 0.05em; }
            nav a { color: #e2e8f0; text-decoration: none; margin-left: 18px; font-weight: 600; }
            nav a:hover { color: #a5b4fc; }
            .pill { background: #111827; padding: 6px 12px; border-radius: 999px; font-size: 13px; color: #cbd5f5; }
            .card { background: #fff; border-radius: 14px; box-shadow: 0 10px 35px rgba(15,23,42,0.08); padding: 20px; margin-top: 18px; }
            .actions { display: flex; gap: 10px; }
            button, .btn { background: #4f46e5; color: #fff; border: none; padding: 10px 14px; border-radius: 10px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
            button.secondary, .btn.secondary { background: #e5e7eb; color: #111827; }
            table { width: 100%; border-collapse: collapse; margin-top: 14px; }
            th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
            th { background: #f8fafc; text-transform: uppercase; letter-spacing: 0.03em; font-size: 12px; }
            .badge { display: inline-block; padding: 4px 8px; border-radius: 8px; font-size: 12px; font-weight: 700; }
            .badge.pending { background: #fef3c7; color: #92400e; }
            .badge.approved { background: #dcfce7; color: #166534; }
            .badge.rejected { background: #fee2e2; color: #991b1b; }
            form.inline { display: inline; }
            .msg { padding: 12px 14px; border-radius: 10px; margin: 12px 0; font-weight: 600; }
            .msg.error { background: #fee2e2; color: #991b1b; }
            .msg.success { background: #dcfce7; color: #166534; }
            label { font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px; }
            input, select, textarea { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #e5e7eb; margin-bottom: 12px; font-size: 14px; }
            input:focus, select:focus, textarea:focus { outline: 2px solid #a5b4fc; }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        </style>
    </head>
    <body>
        <header>
            <div class="brand">MY PARKING</div>
            <nav>
                <?php if ($role === 'student'): ?>
                    <a href="<?php echo appUrl('/student/main.php'); ?>">Vehicles</a>
                <?php elseif ($role === 'fk_staff'): ?>
                    <a href="<?php echo appUrl('/admin/users.php'); ?>">Users</a>
                    <a href="<?php echo appUrl('/admin/reports.php'); ?>">Reports</a>
                <?php elseif ($role === 'safety_staff'): ?>
                    <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>">Approvals</a>
                    <a href="<?php echo appUrl('/safety/reports.php'); ?>">Reports</a>
                <?php endif; ?>
                <?php if (!empty($user['username'])): ?>
                    <span class="pill">Hi, <?php echo htmlspecialchars($user['username']); ?></span>
                    <form class="inline" action="<?php echo appUrl('/login.php'); ?>" method="POST" style="display:inline; margin-left:12px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="logout" value="1">
                        <button type="submit" class="secondary">Logout</button>
                    </form>
                <?php endif; ?>
            </nav>
        </header>
        <div class="shell">
    <?php
}

// Close shared shell markup
function renderFooter() {
    ?>
        </div>
    </body>
    </html>
    <?php
}
?>
