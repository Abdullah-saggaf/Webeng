<?php
/**
 * Reset Password
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
$tokenValid = false;
$user = null;

// Get token from URL
$rawToken = $_GET['token'] ?? '';

if (empty($rawToken)) {
    $message = 'Invalid or missing reset token.';
    $messageType = 'error';
} else {
    // Hash the token to match database
    $tokenHash = hash('sha256', $rawToken);
    
    // Find user with this token
    $db = getDB();
    $stmt = $db->prepare("
        SELECT user_ID, username, email, reset_expires_at 
        FROM User 
        WHERE reset_token_hash = ?
    ");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $message = 'Invalid reset token. Please request a new password reset.';
        $messageType = 'error';
    } elseif (strtotime($user['reset_expires_at']) < time()) {
        $message = 'This reset link has expired. Please request a new password reset.';
        $messageType = 'error';
    } else {
        $tokenValid = true;
    }
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate CSRF
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } elseif (empty($password) || empty($confirmPassword)) {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        // Update password and clear reset token
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            UPDATE User 
            SET password = ?, reset_token_hash = NULL, reset_expires_at = NULL 
            WHERE user_ID = ?
        ");
        
        if ($stmt->execute([$passwordHash, $user['user_ID']])) {
            $_SESSION['reset_success'] = true;
            header('Location: ' . appUrl('/login.php?reset=success'));
            exit();
        } else {
            $message = 'Failed to update password. Please try again.';
            $messageType = 'error';
        }
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
    <title>Reset Password - MyParking System</title>
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
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-header h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 8px;
        }
        .reset-header p {
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
        .password-requirements {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1>Reset Password</h1>
            <?php if ($tokenValid): ?>
            <p>Enter your new password for <?php echo htmlspecialchars($user['email']); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter new password">
                <p class="password-requirements">Must be at least 6 characters</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Re-enter new password">
            </div>

            <button type="submit" class="btn">Reset Password</button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="<?php echo appUrl('/login.php'); ?>">← Back to Login</a>
        </div>
    </div>
</body>
</html>
