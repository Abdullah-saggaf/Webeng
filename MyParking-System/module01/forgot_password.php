<?php
/**
 * Forgot Password
 * Module 01 - MyParking System
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../database/db_config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_type']);
}

$message = '';
$messageType = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validate CSRF
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } elseif (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        // Check if user exists (but don't reveal if email is registered)
        $user = getUserByEmail($email);
        
        if ($user) {
            // Generate secure token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            
            // Set expiry time
            $expiresAt = date('Y-m-d H:i:s', time() + (RESET_TOKEN_EXPIRY_MINUTES * 60));
            
            // Save token hash to database
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE User 
                SET reset_token_hash = ?, reset_expires_at = ? 
                WHERE email = ?
            ");
            $stmt->execute([$tokenHash, $expiresAt, $email]);
            
            // In demo mode, show the reset link
            if (DEMO_MODE) {
                $resetLink = APP_BASE_PATH . '/module01/reset_password.php?token=' . $rawToken;
            }
        }
        
        // Always show generic success message (don't reveal if email exists)
        $message = 'If the email exists in our system, a password reset link has been generated.';
        $messageType = 'success';
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MyParking System</title>
    <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/fonts/style.css'; ?>">
    <link rel="stylesheet" href="<?php echo APP_BASE_PATH . '/module01/module01.css'; ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .forgot-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-header h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 8px;
        }
        .forgot-header p {
            color: #6b7280;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .demo-box {
            background: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }
        .demo-box h3 {
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .demo-box a {
            color: #1e40af;
            word-break: break-all;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <h1>Forgot Password</h1>
            <p>Enter your email to receive a password reset link</p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if (DEMO_MODE && $resetLink): ?>
        <div class="demo-box">
            <h3>🔧 DEMO MODE - Reset Link:</h3>
            <a href="<?php echo $resetLink; ?>"><?php echo $resetLink; ?></a>
            <p style="margin-top: 8px; font-size: 12px; color: #1e40af;">
                Click the link above to reset your password. (Link expires in <?php echo RESET_TOKEN_EXPIRY_MINUTES; ?> minutes)
            </p>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       placeholder="Enter your email">
            </div>

            <button type="submit" class="btn">Send Reset Link</button>
        </form>

        <div class="back-link">
            <a href="<?php echo appUrl('/login.php'); ?>">← Back to Login</a>
        </div>
    </div>
</body>
</html>
