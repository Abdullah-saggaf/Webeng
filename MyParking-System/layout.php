<?php
require_once __DIR__ . '/module01/auth.php';

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
    // === ACTIVE MENU DETECTION ===
    // Get current page filename
    $currentPage = basename($_SERVER['PHP_SELF']);
    $currentPath = $_SERVER['REQUEST_URI'];
    
    // Helper function to check if menu item should be active
    function isActive($pageName, $currentPage, $currentPath = '') {
        // Check exact filename match
        if ($currentPage === $pageName) {
            return 'active';
        }
        // Check if path contains the page name (for nested pages like /admin/users.php)
        if (!empty($currentPath) && strpos($currentPath, '/' . $pageName) !== false) {
            return 'active';
        }
        return '';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo htmlspecialchars($title); ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/fonts/style.css'; ?>">
        <style>
            /* Clean Body */
            body { 
                color: #333;
                margin: 0;
                padding: 0;
                display: flex;
                min-height: 100vh;
                background-color: #f3f4f6;
            }
            
            @keyframes backgroundFloat {
                0% {
                    transform: scale(1) translate(0, 0) rotate(0deg);
                    opacity: 0.9;
                }
                20% {
                    transform: scale(1.1) translate(-3%, 2%) rotate(1deg);
                    opacity: 1;
                }
                40% {
                    transform: scale(1.15) translate(2%, -3%) rotate(-1deg);
                    opacity: 0.95;
                }
                60% {
                    transform: scale(1.12) translate(-2%, 3%) rotate(0.5deg);
                    opacity: 1;
                }
                80% {
                    transform: scale(1.08) translate(3%, -2%) rotate(-0.5deg);
                    opacity: 0.9;
                }
                100% {
                    transform: scale(1) translate(0, 0) rotate(0deg);
                    opacity: 0.9;
                }
            }
            
            /* Professional Glass Cards */
            .card { 
                background: rgba(255, 255, 255, 0.95); 
                backdrop-filter: blur(20px);
                border-radius: 20px; 
                box-shadow: 
                    0 20px 50px rgba(0, 0, 0, 0.2),
                    0 0 0 1px rgba(255, 255, 255, 0.1);
                padding: 30px; 
                margin-top: 20px; 
                border: 1px solid rgba(255, 255, 255, 0.3);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
                position: relative;
            }
            
            .card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            }
            
            .card:hover {
                transform: translateY(-5px);
                box-shadow: 
                    0 30px 70px rgba(0, 0, 0, 0.3),
                    0 0 0 1px rgba(255, 255, 255, 0.2);
            }
            .actions { display: flex; gap: 15px; flex-wrap: wrap; }
            
            /* Professional Buttons */
            button, .btn { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff; 
                border: none; 
                padding: 12px 24px; 
                border-radius: 12px; 
                cursor: pointer; 
                font-weight: 600; 
                font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
                font-size: 14px;
                letter-spacing: 0.3px;
                text-decoration: none; 
                display: inline-flex; 
                align-items: center; 
                gap: 8px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                position: relative;
                overflow: hidden;
            }
            
            button::before, .btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
            }
            
            button:hover::before, .btn:hover::before {
                left: 100%;
            }
            
            button:hover, .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            }
            
            button.secondary, .btn.secondary { 
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
            }
            
            button.secondary:hover, .btn.secondary:hover {
                box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
            }
            /* Professional Table Styling */
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px; 
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            }
            
            th, td { 
                text-align: left; 
                padding: 15px 20px; 
                border-bottom: 1px solid rgba(0, 0, 0, 0.05); 
                font-size: 14px;
                transition: background 0.3s ease;
            }
            
            th { 
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                text-transform: uppercase; 
                letter-spacing: 0.05em; 
                font-size: 12px; 
                font-weight: 700;
                color: #4a5568;
                border-bottom: 2px solid #e2e8f0;
            }
            
            tbody tr:hover {
                background: rgba(102, 126, 234, 0.05);
            }
            
            tbody tr:last-child td {
                border-bottom: none;
            }
            /* Professional Badges */
            .badge { 
                display: inline-block; 
                padding: 6px 12px; 
                border-radius: 20px; 
                font-size: 11px; 
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            .badge.pending { background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%); color: #92400e; }
            .badge.approved { background: linear-gradient(135deg, #dcfce7 0%, #22c55e 100%); color: #166534; }
            .badge.rejected { background: linear-gradient(135deg, #fee2e2 0%, #ef4444 100%); color: #991b1b; }
            
            form.inline { display: inline; }
            
            /* Professional Messages */
            .msg { 
                padding: 16px 20px; 
                border-radius: 12px; 
                margin: 15px 0; 
                font-weight: 600;
                border-left: 4px solid;
                backdrop-filter: blur(10px);
            }
            .msg.error { 
                background: rgba(254, 226, 226, 0.9); 
                color: #991b1b; 
                border-color: #ef4444;
                box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
            }
            .msg.success { 
                background: rgba(220, 252, 231, 0.9); 
                color: #166534; 
                border-color: #22c55e;
                box-shadow: 0 4px 15px rgba(34, 197, 94, 0.2);
            }
            
            /* Professional Form Inputs */
            label { font-weight: 600; font-size: 14px; display: block; margin-bottom: 8px; color: #374151; }
            input, select, textarea { 
                width: 100%; 
                padding: 12px 16px; 
                border-radius: 12px; 
                border: 2px solid #e5e7eb; 
                margin-bottom: 15px; 
                font-size: 14px;
                transition: all 0.3s ease;
                background: rgba(255, 255, 255, 0.9);
            }
            input:focus, select:focus, textarea:focus { 
                outline: none; 
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                background: rgba(255, 255, 255, 1);
            }
            .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
            
            /* Professional Clean Sidebar Design */
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                width: 280px;
                height: 100vh;
                background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
                border-right: 1px solid rgba(255, 255, 255, 0.1);
                z-index: 1000;
                overflow-y: auto;
                transition: all 0.3s ease;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            }
            
            .sidebar-header {
                padding: 25px 20px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                text-align: center;
                background: rgba(255, 255, 255, 0.02);
            }
            
            .sidebar-logo {
                font-size: 18px;
                font-weight: 700;
                color: #ffffff;
                text-decoration: none;
                letter-spacing: 1px;
                display: block;
                transition: all 0.3s ease;
            }
            
            .sidebar-logo:hover {
                opacity: 0.8;
            }
            
            .sidebar-nav {
                padding: 20px 0;
            }
            
            .sidebar-nav a, .sidebar-dropdown-toggle {
                display: flex;
                align-items: center;
                gap: 15px;
                color: rgba(255, 255, 255, 0.9);
                text-decoration: none;
                padding: 15px 25px;
                font-weight: 500;
                font-size: 15px;
                font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
                letter-spacing: 0.3px;
                border: none;
                background: none;
                width: 100%;
                text-align: left;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
            }
            
            .sidebar-nav a::before, .sidebar-dropdown-toggle::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                width: 4px;
                background: #ffffff;
                transform: scaleY(0);
                transition: transform 0.3s ease;
            }
            
            .sidebar-nav a:hover, .sidebar-dropdown-toggle:hover {
                background: rgba(255, 255, 255, 0.08);
                color: #ffffff;
            }
            
            .sidebar-nav a:hover::before, .sidebar-dropdown-toggle:hover::before {
                transform: scaleY(1);
            }
            
            /* Active state - Only for current page */
            .sidebar-nav a.active {
                background: rgba(255, 255, 255, 0.2);
                color: #ffffff;
                font-weight: 600;
            }
            
            .sidebar-nav a.active::before {
                transform: scaleY(1);
                width: 4px;
                background: #ffffff;
            }
            
            .sidebar-nav a .icon, .sidebar-nav a span:first-child, .sidebar-nav a i {
                font-size: 16px;
                width: 18px;
                text-align: center;
                color: #000000;
                opacity: 0.9;
            }
            
            .sidebar-nav a:hover .icon, .sidebar-nav a:hover span:first-child, .sidebar-nav a:hover i {
                opacity: 1;
                color: #000000;
            }
            
            .sidebar-nav a.active .icon, .sidebar-nav a.active span:first-child, .sidebar-nav a.active i {
                color: #000000;
                opacity: 1;
            }
            
            .sidebar-dropdown {
                position: relative;
            }
            
            .sidebar-dropdown-toggle {
                justify-content: space-between;
            }
            
            .sidebar-dropdown-toggle::after {
                content: '▼';
                font-size: 10px;
                transition: transform 0.2s ease;
                opacity: 0.6;
            }
            
            .sidebar-dropdown-toggle:hover::after {
                opacity: 1;
            }
            
            .sidebar-dropdown-menu {
                background: rgba(0, 0, 0, 0.15);
                display: none;
                margin: 0;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
            }
            
            .sidebar-dropdown-menu.show {
                display: block;
            }
            
            .sidebar-dropdown-menu a {
                padding: 12px 24px 12px 48px;
                font-size: 13px;
                color: rgba(255, 255, 255, 0.7);
                margin: 0;
                background: transparent;
            }
            
            .sidebar-dropdown-menu a:hover {
                background: rgba(255, 255, 255, 0.08);
                color: rgba(255, 255, 255, 0.9);
                padding-left: 52px;
            }
            
            /* Clean Top Header - Full Width from Left Corner */
            .top-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 70px;
                background: rgba(255, 255, 255, 0.95);
                border-bottom: 1px solid #e5e7eb;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 30px;
                z-index: 1001;
                backdrop-filter: blur(10px);
            }
            
            .header-left {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .system-logo {
                font-size: 20px;
                font-weight: 700;
                font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                background-size: 200% auto;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                text-decoration: none;
                letter-spacing: 1px;
                text-transform: uppercase;
                position: relative;
                display: inline-block;
                padding: 10px 20px;
                animation: logoGradientFlow 5s ease-in-out infinite;
            }
            
            .system-logo::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
                border-radius: 16px;
                border: 1.5px solid rgba(102, 126, 234, 0.2);
                z-index: -1;
                transition: all 0.3s ease;
                animation: labelPulse 4s ease-in-out infinite;
            }
            
            @keyframes logoGradientFlow {
                0%, 100% {
                    background-position: 0% center;
                }
                50% {
                    background-position: 100% center;
                }
            }
            
            @keyframes labelPulse {
                0%, 100% {
                    border-color: rgba(102, 126, 234, 0.2);
                    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
                }
                50% {
                    border-color: rgba(118, 75, 162, 0.4);
                    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.2);
                }
            }
            
            .header-right {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .user-greeting {
                color: #6b7280;
                font-weight: 500;
                font-size: 14px;
            }
            
            .header-logout-btn {
                background: #ef4444;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 500;
                font-size: 13px;
                transition: background 0.3s ease;
            }
            
            .header-logout-btn:hover {
                background: #dc2626;
            }
            
            /* Main Content with Animated UMP Background */
            .main-content {
                position: relative;
                margin-left: 280px;
                margin-top: 70px;
                padding: 30px;
                min-height: calc(100vh - 70px);
                background: transparent;
                width: calc(100vw - 280px);
                box-sizing: border-box;
            }
            
            .main-content::before {
                content: '';
                position: fixed;
                top: 70px;
                left: 280px;
                right: 0;
                bottom: 0;
                background-image: url('<?php echo APP_BASE_PATH . '/images/ump.png'; ?>');
                background-size: 60%;
                background-position: center;
                background-repeat: no-repeat;
                z-index: -2;
                filter: brightness(1.1) contrast(1.05);
            }
            
            .main-content::after {
                content: '';
                position: fixed;
                top: 70px;
                left: 280px;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(243, 244, 246, 0.3) 100%);
                z-index: -1;
            }
            
            /* Mobile Responsive */
            .mobile-sidebar-toggle {
                display: none;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: #374151;
                border: none;
                color: white;
                padding: 10px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
            }
            
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                }
                .sidebar.show {
                    transform: translateX(0);
                }
                .mobile-sidebar-toggle {
                    display: block;
                }
                .top-header {
                    padding: 0 30px 0 60px;
                }
                .main-content {
                    margin-left: 0;
                    margin-top: 70px;
                    width: 100vw;
                }
            }
            
            /* Professional Role-Specific Themes */
            .user-theme .sidebar {
                background: linear-gradient(180deg, #2563eb 0%, #1e40af 100%);
            }
            
            .user-theme .sidebar-nav a:hover::before,
            .user-theme .sidebar-nav a.active::before {
                background: rgba(147, 197, 253, 0.8);
            }
            
            .user-theme .top-header {
                background: rgba(239, 246, 255, 0.95);
                border-bottom: 1px solid #dbeafe;
            }
            
            /* Student theme logo colors */
            .user-theme .system-logo {
                background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
                background-size: 200% auto;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .user-theme .system-logo::before {
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(30, 64, 175, 0.08) 100%);
                border: 1.5px solid rgba(37, 99, 235, 0.2);
            }
            
            @keyframes userLabelPulse {
                0%, 100% {
                    border-color: rgba(37, 99, 235, 0.2);
                    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
                }
                50% {
                    border-color: rgba(30, 64, 175, 0.4);
                    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
                }
            }
            
            .user-theme .system-logo::before {
                animation: userLabelPulse 4s ease-in-out infinite;
            }
            
            /* Admin Theme */
            .admin-theme {
                position: relative;
            }
            
            .admin-theme .sidebar {
                background: linear-gradient(180deg, rgba(168, 85, 247, 0.95) 0%, rgba(124, 58, 237, 0.95) 100%);
                backdrop-filter: blur(10px);
            }
            
            .admin-theme .sidebar-nav a:hover::before,
            .admin-theme .sidebar-nav a.active::before {
                background: rgba(196, 181, 253, 0.8);
            }
            
            .admin-theme .top-header {
                background: rgba(250, 245, 255, 0.98);
                border-bottom: 1px solid rgba(243, 232, 255, 0.8);
                backdrop-filter: blur(20px);
            }
            
            .admin-theme .card {
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(25px);
                border: 1px solid rgba(168, 85, 247, 0.2);
                box-shadow: 
                    0 25px 60px rgba(168, 85, 247, 0.15),
                    0 0 0 1px rgba(255, 255, 255, 0.3);
            }
            
            .admin-theme .card:hover {
                border-color: rgba(168, 85, 247, 0.4);
                box-shadow: 
                    0 35px 80px rgba(168, 85, 247, 0.25),
                    0 0 0 1px rgba(255, 255, 255, 0.4);
                transform: translateY(-8px);
            }
            
            /* Admin theme logo colors */
            .admin-theme .system-logo {
                background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
                background-size: 200% auto;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .admin-theme .system-logo::before {
                background: linear-gradient(135deg, rgba(168, 85, 247, 0.08) 0%, rgba(124, 58, 237, 0.08) 100%);
                border: 1.5px solid rgba(168, 85, 247, 0.2);
            }
            
            @keyframes adminLabelPulse {
                0%, 100% {
                    border-color: rgba(168, 85, 247, 0.2);
                    box-shadow: 0 2px 8px rgba(168, 85, 247, 0.1);
                }
                50% {
                    border-color: rgba(124, 58, 237, 0.4);
                    box-shadow: 0 4px 16px rgba(168, 85, 247, 0.2);
                }
            }
            
            .admin-theme .system-logo::before {
                animation: adminLabelPulse 4s ease-in-out infinite;
            }
            
            .staff-theme .sidebar {
                background: linear-gradient(180deg, #059669 0%, #047857 100%);
            }
            
            .staff-theme .sidebar-nav a:hover::before,
            .staff-theme .sidebar-nav a.active::before {
                background: rgba(134, 239, 172, 0.8);
            }
            
            .staff-theme .top-header {
                background: rgba(236, 253, 245, 0.95);
                border-bottom: 1px solid #dcfce7;
            }
            
            /* Staff theme logo colors */
            .staff-theme .system-logo {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
                background-size: 200% auto;
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .staff-theme .system-logo::before {
                background: linear-gradient(135deg, rgba(5, 150, 105, 0.08) 0%, rgba(4, 120, 87, 0.08) 100%);
                border: 1.5px solid rgba(5, 150, 105, 0.2);
            }
            
            @keyframes staffLabelPulse {
                0%, 100% {
                    border-color: rgba(5, 150, 105, 0.2);
                    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.1);
                }
                50% {
                    border-color: rgba(4, 120, 87, 0.4);
                    box-shadow: 0 4px 16px rgba(5, 150, 105, 0.2);
                }
            }
            
            .staff-theme .system-logo::before {
                animation: staffLabelPulse 4s ease-in-out infinite;
            }
            
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
        <!-- Mobile Sidebar Toggle -->
        <button class="mobile-sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">☰</button>
        
        <!-- Left Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <!-- Logo moved to top header only -->
            </div>
            <nav class="sidebar-nav">
                <?php if ($role === 'student'): ?>
                    <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/user.php'); ?>" class="<?php echo isActive('user.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo appUrl('/student/main.php'); ?>" class="<?php echo isActive('main.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-car"></i> Vehicles
                    </a>
                    <a href="#" class="<?php echo isActive('parking-booking.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-calendar-check"></i> Parking Booking
                    </a>
                    <a href="#" class="<?php echo isActive('my-summons.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-file-invoice"></i> My Summons
                    </a>
                    <a href="#" class="<?php echo isActive('my-bookings.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-clipboard-list"></i> My Bookings
                    </a>
                    <a href="#" class="<?php echo isActive('demerit-points.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-exclamation-triangle"></i> Demerit Points
                    </a>
                    <a href="#" class="<?php echo isActive('profile.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                <?php elseif ($role === 'fk_staff'): ?>
                    <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/admin.php'); ?>" class="<?php echo isActive('admin.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo appUrl('/admin/users.php'); ?>" class="<?php echo isActive('users.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-users-cog"></i> Manage Users
                    </a>
                    <a href="<?php echo appUrl('/admin/reports.php'); ?>" class="<?php echo isActive('reports.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <a href="#" class="<?php echo isActive('parking-areas.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-map-marked-alt"></i> Parking Areas
                    </a>
                    <a href="#" class="<?php echo isActive('parking-spaces.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-th"></i> Parking Spaces
                    </a>
                    <a href="#" class="<?php echo isActive('system-settings.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                <?php elseif ($role === 'safety_staff'): ?>
                    <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/staff.php'); ?>" class="<?php echo isActive('staff.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="<?php echo isActive('vehicle-approvals.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-check-circle"></i> Vehicle Approvals
                    </a>
                    <a href="#" class="<?php echo isActive('traffic-summons.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-exclamation-circle"></i> Traffic Summons
                    </a>
                    <a href="#" class="<?php echo isActive('safety-dashboard.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-shield-alt"></i> Safety Dashboard
                    </a>
                    <a href="#" class="<?php echo isActive('issue-summon.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-edit"></i> Issue Summon
                    </a>
                    <a href="<?php echo appUrl('/safety/reports.php'); ?>" class="<?php echo isActive('reports.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-chart-line"></i> Reports
                    </a>
                    <a href="#" class="<?php echo isActive('profile.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-left">
                <span class="system-logo">MY PARKING</span>
            </div>
            <div class="header-right">
                <?php if (!empty($user['username'])): ?>
                    <span class="user-greeting">Hi, <?php echo htmlspecialchars($user['username']); ?></span>
                    <form class="inline" action="<?php echo appUrl('/login.php'); ?>" method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="logout" value="1">
                        <button type="submit" class="header-logout-btn">Logout</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            function toggleSidebarDropdown(event) {
                event.stopPropagation();
                const dropdown = event.target.nextElementSibling;
                const wasOpen = dropdown.classList.contains('show');
                
                // Close all dropdowns
                document.querySelectorAll('.sidebar-dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                // Toggle current dropdown
                if (!wasOpen) {
                    dropdown.classList.add('show');
                }
            }
            
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('show');
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.matches('.sidebar-dropdown-toggle')) {
                    document.querySelectorAll('.sidebar-dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });
            
            // Close mobile sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('sidebar');
                    const toggle = document.querySelector('.mobile-sidebar-toggle');
                    
                    if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // Close mobile sidebar when window is resized above 768px
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    document.getElementById('sidebar').classList.remove('show');
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

