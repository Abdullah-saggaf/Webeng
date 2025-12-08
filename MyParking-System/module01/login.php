<?php
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    header("Location: login.php");
    exit();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
        case 'Admin':
            header("Location: ../admin.php");
            exit();
        case 'staff':
        case 'Staff':
            header("Location: ../staff.php");
            exit();
        default:
            header("Location: ../user.php");
            exit();
    }
}

// Add this temporarily before require_once to debug:
// echo "Current directory: " . __DIR__ . "<br>";
// echo "Looking for: " . realpath(__DIR__ . '/../database/db_functions.php') . "<br>";
// die();

require_once __DIR__ . '/../database/db_functions.php';

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password';
    } else {
        // Try to authenticate user
        $user = verifyUserLogin($email, $password);
        
        if ($user) {
            // Login successful - set session variables
            $_SESSION['user_id'] = $user['user_ID'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Redirect based on user type
            switch ($user['user_type']) {
                case 'admin':
                case 'Admin':
                    header("Location: ../admin.php");
                    exit();
                case 'staff':
                case 'Staff':
                    header("Location: ../staff.php");
                    exit();
                default:
                    header("Location: ../user.php");
                    exit();
            }
        } else {
            $error_message = 'Invalid email/username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="MyParking - Login">
    <meta name="author" content="MyParking Team">

    <title>MyParking - Login</title>

    <!-- CSS File -->
    <link rel="stylesheet" href="../assets/fonts/style.css">
    
    <style>
        /* Login Page Specific Styles */
        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #5568d3;
        }

    </style>
</head>

<body>

    <div class="login-container">
        <div class="login-logo">MY PARKING</div>
        <div class="login-subtitle">Login to your account</div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email or Username</label>
                <input type="text" id="email" name="email" required placeholder="Enter your email or username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>

</body>
</html>
