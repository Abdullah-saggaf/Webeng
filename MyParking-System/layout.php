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
        <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/module01/module01.css'; ?>">
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
                min-width: 0;
                box-sizing: border-box;
            }
            
            .card h2, .card h3 {
                font-size: clamp(16px, 2vw, 24px);
                margin: 0 0 12px 0;
                word-wrap: break-word;
            }
            
            .card p {
                font-size: clamp(12px, 1.5vw, 16px);
                word-wrap: break-word;
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
            
            /* Responsive Card Adjustments */
            @media (max-width: 1400px) {
                .card {
                    padding: 24px;
                    border-radius: 16px;
                }
            }
            
            @media (max-width: 1024px) {
                .card {
                    padding: 20px;
                    border-radius: 14px;
                }
            }
            
            @media (max-width: 768px) {
                .card {
                    padding: 16px;
                    border-radius: 12px;
                    margin-top: 15px;
                }
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
                font-size: clamp(12px, 1.2vw, 14px);
                letter-spacing: 0.3px;
                text-decoration: none; 
                display: inline-flex; 
                align-items: center; 
                gap: 8px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                position: relative;
                overflow: hidden;
                white-space: nowrap;
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
            
            /* Responsive Button Adjustments */
            @media (max-width: 1024px) {
                button, .btn {
                    padding: 10px 20px;
                }
            }
            
            @media (max-width: 768px) {
                button, .btn {
                    padding: 8px 16px;
                }
            }
            
            /* Message Boxes */
            .msg {
                padding: 15px 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                font-weight: 500;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideDown 0.3s ease-out;
            }
            
            .msg.success {
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .msg.error {
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Form Input Styling */
            input[type="text"],
            input[type="email"],
            input[type="tel"],
            input[type="password"],
            select,
            textarea {
                width: 100%;
                padding: 12px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 14px;
                font-family: 'Inter', 'Segoe UI', sans-serif;
                transition: all 0.3s ease;
                box-sizing: border-box;
                margin-bottom: 15px;
            }
            
            input:focus,
            select:focus,
            textarea:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #374151;
                font-size: 14px;
            }
            
            /* Professional Table Styling */
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px; 
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
                display: table;
            }
            
            th, td { 
                text-align: left; 
                padding: 15px 20px; 
                border-bottom: 1px solid rgba(0, 0, 0, 0.05); 
                font-size: clamp(11px, 1.2vw, 14px);
                transition: background 0.3s ease;
                word-wrap: break-word;
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            th { 
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                text-transform: uppercase; 
                letter-spacing: 0.05em; 
                font-size: clamp(10px, 1vw, 12px); 
                font-weight: 700;
                color: #4a5568;
                border-bottom: 2px solid #e2e8f0;
                white-space: nowrap;
            }
            
            tbody tr:hover {
                background: rgba(102, 126, 234, 0.05);
            }
            
            tbody tr:last-child td {
                border-bottom: none;
            }
            
            /* Responsive Table Adjustments */
            @media (max-width: 1400px) {
                th, td {
                    padding: 12px 15px;
                }
            }
            
            @media (max-width: 1024px) {
                th, td {
                    padding: 10px 12px;
                }
                
                table {
                    font-size: 12px;
                }
            }
            
            @media (max-width: 768px) {
                th, td {
                    padding: 8px 10px;
                }
                
                /* Optional: Make table scrollable on small screens */
                .card {
                    overflow-x: auto;
                }
                
                table {
                    min-width: 600px;
                }
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
            
            /* Responsive Grid System */
            .grid { 
                display: grid; 
                grid-template-columns: repeat(4, 1fr); 
                gap: 20px;
                width: 100%;
                box-sizing: border-box;
            }
            
            /* 2-column grid variant for pages with fewer stats */
            .grid-2col {
                grid-template-columns: repeat(2, 1fr) !important;
                max-width: 800px;
            }
            
            /* Grid items - Cards inside grid */
            .grid > .card {
                margin-top: 0 !important;
                min-width: 0;
                width: 100%;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
            }
            
            /* Responsive font sizes for grid stat cards */
            .grid .card h3 {
                font-size: clamp(12px, 1.5vw, 18px) !important;
                margin-bottom: 8px !important;
                margin-top: 0 !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                width: 100%;
            }
            
            .grid .card p {
                font-size: clamp(16px, 2.5vw, 28px) !important;
                font-weight: 700 !important;
                margin: 0 !important;
            }
            
            /* Responsive adjustments for different zoom levels and screen sizes */
            @media (max-width: 1600px) {
                .grid {
                    gap: 16px;
                }
            }
            
            @media (max-width: 1400px) {
                .grid {
                    gap: 14px;
                }
            }
            
            @media (max-width: 1200px) {
                .grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 12px;
                }
            }
            
            @media (max-width: 768px) {
                .grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                }
                
                .grid-2col {
                    grid-template-columns: 1fr !important;
                }
            }
            
            @media (max-width: 480px) {
                .grid {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }
            }
            
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
                display: inline-flex;
                align-items: center;
                gap: 12px;
                padding: 10px 20px;
                animation: logoGradientFlow 5s ease-in-out infinite;
            }
            
            .system-logo img {
                width: 40px;
                height: 40px;
                object-fit: contain;
                filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
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
                max-width: 100%;
                overflow-x: hidden;
            }
            
            .main-content::before {
                content: '';
                position: fixed;
                top: 70px;
                left: 280px;
                right: 0;
                bottom: 0;
                background: transparent;
                z-index: -2;
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
                    <a href="<?php echo appUrl('/student/vehicles.php'); ?>" class="<?php echo isActive('vehicles.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-car"></i> Vehicles
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module03/student/parking_booking.php'; ?>" class="<?php echo isActive('parking_booking.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-calendar-check"></i> Parking Booking
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module02/student/parkingAvailability.php'; ?>" class="<?php echo isActive('parkingAvailability.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-chart-bar"></i> Parking Availability
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module03/student/my_bookings.php'; ?>" class="<?php echo isActive('my_bookings.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-clipboard-list"></i> My Bookings
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module04/student/my_summons.php'; ?>" class="<?php echo isActive('my_summons.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-file-invoice"></i> My Summons
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module04/student/demerit_points.php'; ?>" class="<?php echo isActive('demerit_points.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-exclamation-triangle"></i> Demerit Points
                    </a>
                <?php elseif ($role === 'fk_staff'): ?>
                    <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/admin.php'); ?>" class="<?php echo isActive('admin.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo appUrl('/admin/users.php'); ?>" class="<?php echo isActive('users.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-users-cog"></i> Manage Users
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module02/admin/manage_parking_areas.php'; ?>" class="<?php echo isActive('manage_parking_areas.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-map-marked-alt"></i> Parking Areas
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module02/admin/manage_parking_spaces.php'; ?>" class="<?php echo isActive('manage_parking_spaces.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-th"></i> Parking Spaces
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module03/admin/manage_bookings.php'; ?>" class="<?php echo isActive('manage_bookings.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-calendar-check"></i> View & Manage Bookings
                    </a>
                <?php elseif ($role === 'safety_staff'): ?>
                    <a href="<?php echo htmlspecialchars(APP_BASE_PATH . '/staff.php'); ?>" class="<?php echo isActive('staff.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="<?php echo isActive('vehicle-approvals.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-check-circle"></i> Vehicle Approvals
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module02/admin/parkingDashboard.php'; ?>" class="<?php echo isActive('parkingDashboard.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-chart-bar"></i> Parking Dashboard
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module04/safety/safety_dashboard.php'; ?>" class="<?php echo isActive('safety_dashboard.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-shield-alt"></i> Safety Dashboard
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module04/safety/traffic_summons.php'; ?>" class="<?php echo isActive('traffic_summons.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-exclamation-circle"></i> Traffic Summons
                    </a>
                    <a href="<?php echo APP_BASE_PATH . '/module04/safety/issue_summon.php'; ?>" class="<?php echo isActive('issue_summon.php', $currentPage, $currentPath); ?>">
                        <i class="fas fa-edit"></i> Issue Summon
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-left">
                <span class="system-logo">
                    <img src="<?php echo APP_BASE_PATH . '/images/ump.png'; ?>" alt="UMP Logo">
                    MY PARKING
                </span>
            </div>
            <div class="header-right">
                <?php if (!empty($user['username'])): ?>
                    <div class="user-dropdown">
                        <button class="dropdown-toggle" type="button" id="userDropdownBtn">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="userDropdownMenu">
                            <?php
                            // Determine profile URL based on role
                            $profileUrl = APP_BASE_PATH . '/module01/student/profile.php';
                            if ($role === 'fk_staff') {
                                $profileUrl = APP_BASE_PATH . '/module01/admin/profile.php';
                            } elseif ($role === 'safety_staff') {
                                $profileUrl = APP_BASE_PATH . '/module01/safety/profile.php';
                            }
                            ?>
                            <a href="<?php echo htmlspecialchars($profileUrl); ?>" class="dropdown-item">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <form action="<?php echo appUrl('/login.php'); ?>" method="POST" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="logout" value="1">
                                <button type="submit" class="dropdown-item logout-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            // User dropdown toggle
            document.addEventListener('DOMContentLoaded', function() {
                const dropdownBtn = document.getElementById('userDropdownBtn');
                const dropdownMenu = document.getElementById('userDropdownMenu');
                
                if (dropdownBtn && dropdownMenu) {
                    dropdownBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        dropdownMenu.classList.toggle('show');
                    });
                    
                    // Close dropdown when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                            dropdownMenu.classList.remove('show');
                        }
                    });
                }
            });
            
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

