<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../database/db_config.php';

// Login/landing controller: handles logout, CSRF, throttling, credential check, and view render

// Handle logout request early (support GET and POST, prefer POST with CSRF)
// Only process if user is actually logged in to avoid redirect loops
if (isLoggedIn() && ((isset($_GET['logout']) && $_GET['logout'] === '1') ||
    (isset($_POST['logout']) && $_POST['logout'] === '1'))) {
    // For POST, validate CSRF token if provided
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedToken = $_POST['csrf_token'] ?? '';
        if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $postedToken)) {
            // If CSRF invalid, do not proceed; fall through to normal flow with error
            $error_message = 'Invalid logout request.';
        } else {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                // Expire using original params
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                // Also expire with path '/' as a safety net
                setcookie(session_name(), '', time() - 42000, '/');
            }
            session_destroy();
            // Start new session for fresh login
            session_start();
            header('Location: ' . appUrl('/login.php'));
            exit();
        }
    } else {
        // GET fallback (no CSRF) for compatibility
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
        // Start new session for fresh login
        session_start();
        header('Location: ' . appUrl('/login.php'));
        exit();
    }
}

// Skip login form when already authenticated
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_type']);
}

$error_message = '';
$success_message = '';

// Check for password reset success
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success_message = 'Your password has been reset successfully. Please log in with your new password.';
}

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Throttling parameters
$maxAttempts = 5;
$windowSeconds = 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';

    // CSRF validation first
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error_message = 'Invalid request. Please refresh and try again.';
    } else {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lastTs = $_SESSION['last_attempt_ts'] ?? 0;
        $now = time();

        if ($attempts >= $maxAttempts && ($now - $lastTs) < $windowSeconds) {
            $error_message = 'Too many attempts. Please try again in 1 minute.';
        } else {
            if ($identifier === '' || $password === '') {
                $error_message = 'Please enter both username/email and password.';
            } else {
                $user = verifyUserLogin($identifier, $password);

                if ($user) {
                    // Only allow known roles
                    $validRoles = ['student', 'fk_staff', 'safety_staff'];
                    if (!in_array($user['user_type'], $validRoles, true)) {
                        $error_message = 'Your account role is not permitted to sign in here.';
                    } else {
                        // Successful login: reset throttling and stash identity
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_ID'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['last_attempt_ts'] = 0;
                        redirectByRole($user['user_type']);
                    }
                } else {
                    // Track failed attempt for throttling window
                    $error_message = 'Invalid email/username or password';
                    $_SESSION['login_attempts'] = $attempts + 1;
                    $_SESSION['last_attempt_ts'] = $now;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="MyParking - Login">
    <meta name="author" content="MyParking Team">

    <title>MyParking - Login</title>

    <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/fonts/style.css'; ?>">
    <style>
        :root {
            --umpsa-blue: #005b9a;
            --overlay: rgba(9, 23, 34, 0.65);
            --card-bg: rgba(255, 255, 255, 0.96);
            --border: #dce3ed;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; }

        body {
            background: url('<?php echo APP_BASE_PATH; ?>/images/fk-aerialview.jpg') center/cover no-repeat fixed;
            position: relative;
            color: #0f172a;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--overlay);
            backdrop-filter: blur(2px);
        }

        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.10);
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 14px 22px;
        }

        .site-header .brand {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .site-header .brand-logo {
            height: 54px;
            width: auto;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
        }

        .site-header .brand-text {
            display: flex;
            flex-direction: column;
        }

        .site-header .brand-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #ffffff;
            line-height: 1.2;
        }

        .site-header .brand-subtitle {
            font-size: 13px;
            font-weight: 600;
            color: #d1dce8;
            margin-top: 2px;
        }

        main {
            position: relative;
            z-index: 1;
            min-height: calc(100vh - 80px);
            padding: 100px 18px 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .hero {
            color: #e5edf5;
            text-align: center;
            margin-bottom: 24px;
            max-width: 520px;
        }

        .hero p {
            margin: 0;
            line-height: 1.6;
            font-size: 15px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(0, 23, 43, 0.28);
            padding: 28px;
            width: 100%;
            max-width: 420px;
        }

        .card h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: #0f172a;
        }

        .card p.desc {
            margin: 0 0 20px 0;
            color: #475569;
            font-size: 14px;
        }

        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; color: #0f172a; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #f8fafc;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        input:focus {
            outline: 2px solid rgba(0,91,154,0.25);
            border-color: var(--umpsa-blue);
            background: #fff;
        }

        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 14px;
            font-weight: 600;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 14px;
            font-weight: 600;
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(120deg, var(--umpsa-blue), #0a72c2);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(0,91,154,0.28);
            transition: transform 0.12s ease, box-shadow 0.2s ease;
        }
        button[type="submit"]:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(0,91,154,0.32); }
        button[type="submit"]:active { transform: translateY(0); }

        footer {
            position: fixed;
            bottom: 12px;
            width: 100%;
            text-align: center;
            color: #d5dfed;
            font-size: 13px;
            z-index: 1;
        }

        @media (max-width: 720px) {
            .site-header .brand-logo { height: 46px; }
            .site-header .brand-title { font-size: 18px; }
            .site-header .brand-subtitle { font-size: 12px; }
            main { padding: 90px 18px 50px; }
            .card { padding: 24px; }
        }
    </style>
</head>

<body>
    <header class="site-header">
        <div class="brand">
            <img class="brand-logo" src="<?php echo APP_BASE_PATH; ?>/images/logo-umpsa.jpg" alt="UMPSA Logo">
            <div class="brand-text">
                <div class="brand-title">MyParking System</div>
                <div class="brand-subtitle">Faculty of Computing, UMPSA</div>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <p>Access parking management for students, FK staff, and the Safety Management Unit in one secure portal.</p>
        </section>

        <section class="card" aria-label="Login form">
            <h2>Sign In</h2>
            <p class="desc">Sign in with your MyParking account </p>

            <?php if (!empty($success_message)): ?>
                <div class="success-message" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                <div class="form-group">
                    <label for="identifier">Username or Email</label>
                    <input type="text" id="identifier" name="identifier" required placeholder="Enter your username or email" value="<?php echo isset($identifier) ? htmlspecialchars($identifier) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:8px; margin-top:-6px;">
                    <input type="checkbox" id="show-password" style="width:auto;">
                    <label for="show-password" style="margin:0; font-weight:600;">Show password</label>
                </div>

                <div style="text-align:right; margin-top:8px; margin-bottom:16px;">
                    <a href="<?php echo appUrl('/forgot_password.php'); ?>" style="color: var(--umpsa-blue); text-decoration:none; font-size:14px; font-weight:600;">Forgot password?</a>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                <button type="submit">Login</button>
            </form>
        </section>
    </main>

    <footer>
        © Universiti Malaysia Pahang Al-Sultan Abdullah
    </footer>

    <script>
        (function() {
            const toggle = document.getElementById('show-password');
            const pwd = document.getElementById('password');
            if (toggle && pwd) {
                toggle.addEventListener('change', function () {
                    pwd.type = this.checked ? 'text' : 'password';
                });
            }
        })();
    </script>
</body>
</html>
