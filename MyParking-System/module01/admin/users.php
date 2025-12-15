<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../database/db_functions.php';

requireRole(['fk_staff']);

$allowedRoles = ['student', 'fk_staff', 'safety_staff'];
$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $userId = trim($_POST['user_id'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone_number'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['user_type'] ?? '';

            if ($userId === '' || $username === '' || $email === '' || $password === '') {
                throw new Exception('User ID, username, email, and password are required.');
            }
            if (!in_array($role, $allowedRoles, true)) {
                throw new Exception('Invalid role selected.');
            }

            createUser($userId, $username, $email, $phone, $password, $role);
            $message = 'User created successfully.';
        } elseif ($action === 'update') {
            $userId = $_POST['user_id'] ?? '';
            $role = $_POST['user_type'] ?? '';
            if (!in_array($role, $allowedRoles, true)) {
                throw new Exception('Invalid role selected.');
            }

            $fields = [
                'username' => trim($_POST['username'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone_number' => trim($_POST['phone_number'] ?? ''),
                'user_type' => $role
            ];

            if (!empty($_POST['password'])) {
                $fields['password'] = $_POST['password'];
            }

            updateUser($userId, $fields);
            $message = 'User updated successfully.';
        } elseif ($action === 'delete') {
            $userId = $_POST['user_id'] ?? '';
            deleteUser($userId);
            $message = 'User deleted.';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Filters
$filterRole = $_GET['role'] ?? '';
$search = trim($_GET['search'] ?? '');
$users = getUsers([
    'user_type' => $filterRole ?: null,
    'search' => $search ?: null,
]);

renderHeader('Membership Management');
?>

<div class="card">
    <h2>Membership Management</h2>
    <?php if ($message): ?>
        <div class="msg <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="grid">
        <div>
            <h3>Create User</h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="action" value="create">
                <label>User ID</label>
                <input type="text" name="user_id" required>

                <label>Username</label>
                <input type="text" name="username" required>

                <label>Email</label>
                <input type="email" name="email" required>

                <label>Phone Number</label>
                <input type="text" name="phone_number">

                <label>Password</label>
                <input type="password" name="password" required>

                <label>Role</label>
                <select name="user_type" required>
                    <option value="student">Student</option>
                    <option value="fk_staff">FK Staff (Admin)</option>
                    <option value="safety_staff">Safety Staff</option>
                </select>

                <button type="submit">Create User</button>
            </form>
        </div>
        <div>
            <h3>Search & Filter</h3>
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <label>Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ID, username or email">
                <label>Role</label>
                <select name="role">
                    <option value="">All</option>
                    <option value="student" <?php echo $filterRole === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="fk_staff" <?php echo $filterRole === 'fk_staff' ? 'selected' : ''; ?>>FK Staff</option>
                    <option value="safety_staff" <?php echo $filterRole === 'safety_staff' ? 'selected' : ''; ?>>Safety Staff</option>
                </select>
                <button type="submit" class="secondary">Apply</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <h3>User List</h3>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['user_ID']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                        <td><?php echo htmlspecialchars($user['user_type']); ?></td>
                        <td>
                            <details>
                                <summary>Edit/Delete</summary>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_ID']); ?>">
                                    <label>Username</label>
                                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    <label>Email</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <label>Phone</label>
                                    <input type="text" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                                    <label>Role</label>
                                    <select name="user_type" required>
                                        <?php foreach ($allowedRoles as $role): ?>
                                            <option value="<?php echo $role; ?>" <?php echo $user['user_type'] === $role ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ', $role)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Password (leave blank to keep)</label>
                                    <input type="password" name="password" placeholder="New password (optional)">
                                    <div class="actions">
                                        <button type="submit">Update</button>
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_ID']); ?>">
                                            <button type="submit" class="secondary">Delete</button>
                                        </form>
                                    </div>
                                </form>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
