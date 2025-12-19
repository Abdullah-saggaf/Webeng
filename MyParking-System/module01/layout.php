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
    
    // Determine body class based on role
    $bodyClass = '';
    if ($role === 'student') {
        $bodyClass = 'user-theme';
    } elseif ($role === 'fk_staff') {
        $bodyClass = 'admin-theme';
    } elseif ($role === 'safety_staff') {
        $bodyClass = 'staff-theme';
    }
    
    // Determine dashboard URL based on role
    $dashboardUrl = APP_BASE_PATH . '/user.php';
    if ($role === 'fk_staff') {
        $dashboardUrl = APP_BASE_PATH . '/admin.php';
    } elseif ($role === 'safety_staff') {
        $dashboardUrl = APP_BASE_PATH . '/staff.php';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo htmlspecialchars($title); ?></title>
        <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/fonts/style.css'; ?>">
        <style>
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
            
            /* Enhanced Navigation Layout */
            .header-content { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
            .nav-left { flex-shrink: 0; }
            .nav-center { flex: 1; display: flex; justify-content: center; }
            .nav-right { flex-shrink: 0; display: flex; align-items: center; gap: 10px; }
            .nav { display: flex; align-items: center; gap: 0.5rem; flex-wrap: nowrap; }
            .nav > a { white-space: nowrap; }
            
            /* User Area Styling */
            .user-pill { background: rgba(0,0,0,0.1); padding: 5px 12px; border-radius: 999px; font-size: 13px; white-space: nowrap; }
            .logout-btn { background: rgba(0,0,0,0.15); color: inherit; border: 1px solid rgba(0,0,0,0.2); padding: 5px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.2s; }
            .logout-btn:hover { background: rgba(0,0,0,0.25); }
            
            /* Dropdown Styling */
            .dropdown { position: relative; display: inline-block; }
            .dropdown-toggle { background: transparent; border: none; color: inherit; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; font-weight: 500; font-size: 1rem; transition: all 0.3s; }
            .dropdown-toggle:hover { background: rgba(0,0,0,0.1); }
            .user-theme .dropdown-toggle:hover { background: #2196F3; color: white; }
            .admin-theme .dropdown-toggle:hover { background: #9C27B0; color: white; }
            .staff-theme .dropdown-toggle:hover { background: #4CAF50; color: white; }
            .dropdown-menu { display: none; position: absolute; top: 100%; right: 0; background: white; min-width: 180px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 8px; z-index: 1000; }
            .dropdown-menu.show { display: block; }
            .dropdown-menu a { display: block; padding: 10px 16px; color: #333; text-decoration: none; transition: background 0.2s; white-space: nowrap; }
            .dropdown-menu a:hover { background: #f5f5f5; }
            
            /* Mobile Menu Toggle */
            .mobile-menu-toggle { display: none; background: transparent; border: none; color: inherit; font-size: 1.5rem; cursor: pointer; padding: 0.25rem 0.5rem; }
            
            /* Mobile Responsive */
            @media (max-width: 900px) {
                .header-content { flex-wrap: wrap; }
                .nav-left { order: 1; }
                .nav-right { order: 2; margin-left: auto; }
                .nav-center { order: 3; width: 100%; justify-content: flex-start; margin-top: 0.5rem; }
                .mobile-menu-toggle { display: block; order: 2; margin-right: 0.5rem; }
                .nav { display: none; flex-direction: column; width: 100%; align-items: flex-start; gap: 0; }
                .nav.show { display: flex; }
                .nav > a, .dropdown-toggle { display: block; width: 100%; text-align: left; padding: 0.75rem 1rem; }
                .dropdown-menu { position: static; box-shadow: none; background: rgba(0,0,0,0.05); border-left: 3px solid rgba(0,0,0,0.2); margin: 0; }
                .dropdown-menu a { padding-left: 2rem; }
                .nav-right { gap: 6px; }
                .user-pill { font-size: 11px; padding: 4px 8px; }
                .logout-btn { font-size: 11px; padding: 4px 10px; }
            }
        </style>
    </head>
    <body class="<?php echo htmlspecialchars($bodyClass); ?>">
        <header class="header">
            <div class="header-content">
                <div class="nav-left">
                    <a class="logo" href="<?php echo htmlspecialchars($dashboardUrl); ?>">MY PARKING</a>
                </div>
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">☰</button>
                <div class="nav-center">
                    <nav class="nav" id="mainNav">
                        <?php if ($role === 'student'): ?>
                            <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/user.php'); ?>">Dashboard</a>
                            <a href="<?php echo appUrl('/student/main.php'); ?>">Vehicles</a>
                            <a href="#">Parking Booking</a> <!-- TODO: link -->
                            <a href="#">My Summons</a> <!-- TODO: link -->
                            <div class="dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(event)">More ▼</button>
                                <div class="dropdown-menu">
                                    <a href="#">My Bookings</a> <!-- TODO: link -->
                                    <a href="#">Demerit Points</a> <!-- TODO: link -->
                                    <a href="#">Profile</a> <!-- TODO: link -->
                                </div>
                            </div>
                        <?php elseif ($role === 'fk_staff'): ?>
                            <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/admin.php'); ?>">Dashboard</a>
                            <a href="<?php echo appUrl('/admin/users.php'); ?>">Manage Users</a>
                            <a href="<?php echo appUrl('/admin/reports.php'); ?>">Reports</a>
                            <a href="#">Parking Areas</a> <!-- TODO: link -->
                            <div class="dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(event)">More ▼</button>
                                <div class="dropdown-menu">
                                    <a href="#">Parking Spaces</a> <!-- TODO: link -->
                                    <a href="#">System Settings</a> <!-- TODO: link -->
                                </div>
                            </div>
                        <?php elseif ($role === 'safety_staff'): ?>
                            <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/staff.php'); ?>">Dashboard</a>
                            <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>">Vehicle Approvals</a>
                            <a href="#">Traffic Summons</a> <!-- TODO: link -->
                            <a href="#">Safety Dashboard</a> <!-- TODO: link -->
                            <div class="dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(event)">More ▼</button>
                                <div class="dropdown-menu">
                                    <a href="#">Issue Summon</a> <!-- TODO: link -->
                                    <a href="<?php echo appUrl('/safety/reports.php'); ?>">Reports</a>
                                    <a href="#">Profile</a> <!-- TODO: link -->
                                </div>
                            </div>
                        <?php endif; ?>
                    </nav>
                </div>
                <div class="nav-right">
                    <?php if (!empty($user['username'])): ?>
                        <span class="user-pill">Hi, <?php echo htmlspecialchars($user['username']); ?></span>
                        <form class="inline" action="<?php echo appUrl('/login.php'); ?>" method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="logout" value="1">
                            <button type="submit" class="logout-btn">Logout</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <script>
            function toggleDropdown(event) {
                event.stopPropagation();
                const dropdown = event.target.nextElementSibling;
                const wasOpen = dropdown.classList.contains('show');
                
                // Close all dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                // Toggle current dropdown
                if (!wasOpen) {
                    dropdown.classList.add('show');
                }
            }
            
            function toggleMobileMenu() {
                const nav = document.getElementById('mainNav');
                nav.classList.toggle('show');
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.matches('.dropdown-toggle')) {
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });
            
            // Close mobile menu when window is resized above 900px
            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) {
                    document.getElementById('mainNav').classList.remove('show');
                }
            });
        </script>
        
        <main class="main-content">
    <?php
}

// Close shared shell markup
function renderFooter() {
    ?>
        </main>
    </body>
    </html>
    <?php
}
?>
