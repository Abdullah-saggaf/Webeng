<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../layout.php';
require_once __DIR__ . '/../../database/db_config.php';

requireRole(['student']);

$user = currentUser();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($username) || empty($email)) {
            throw new Exception('Username and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Update profile
        $db = getDB();
        
        // Check if changing password
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                throw new Exception('Current password is required to set a new password.');
            }

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM User WHERE user_ID = :user_id");
            $stmt->execute([':user_id' => $user['user_id']]);
            $userData = $stmt->fetch();

            if (!password_verify($currentPassword, $userData['password'])) {
                throw new Exception('Current password is incorrect.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match.');
            }

            if (strlen($newPassword) < 6) {
                throw new Exception('Password must be at least 6 characters.');
            }

            // Update with new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE User SET username = :username, email = :email, phone_number = :phone, password = :password WHERE user_ID = :user_id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':phone' => $phone,
                ':password' => $hashedPassword,
                ':user_id' => $user['user_id']
            ]);
        } else {
            // Update without changing password
            $stmt = $db->prepare("UPDATE User SET username = :username, email = :email, phone_number = :phone WHERE user_ID = :user_id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':phone' => $phone,
                ':user_id' => $user['user_id']
            ]);
        }

        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['email'] = $email;
        
        $message = 'Profile updated successfully!';
        $user = currentUser(); // Refresh user data
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

renderHeader('My Profile');
?>

<div class="card">
    <h2>My Profile</h2>
    
    <?php if ($message): ?>
        <div class="msg <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div style="max-width: 600px;">
            <label>User ID</label>
            <input type="text" value="<?php echo htmlspecialchars($user['user_id']); ?>" disabled style="background: #f3f4f6; cursor: not-allowed;">
            
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            
            <label>Phone Number</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="e.g., 0123456789">
            
            <label>Role</label>
            <input type="text" value="Student" disabled style="background: #f3f4f6; cursor: not-allowed;">
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">
            
            <h3 style="margin-bottom: 15px;">Change Password (Optional)</h3>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 20px;">Leave blank to keep current password</p>
            
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="Enter current password">
            
            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Enter new password (min 6 characters)">
            
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm new password">
            
            <div class="actions" style="margin-top: 30px;">
                <button type="submit" class="btn">Update Profile</button>
                <a href="<?php echo appUrl('/user.php'); ?>" class="btn secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php renderFooter(); ?>
