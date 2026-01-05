<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../layout.php';
require_once __DIR__ . '/../../database/db_config.php';

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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
        <!-- Create User Form -->
        <div style="background: #f9fafb; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
            <h3 style="margin-top: 0; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-user-plus"></i> Create User
            </h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="action" value="create">
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">User ID</label>
                <input type="text" name="user_id" required style="width: 100%; margin-bottom: 16px;">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Username</label>
                <input type="text" name="username" required style="width: 100%; margin-bottom: 16px;">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Email</label>
                <input type="email" name="email" required style="width: 100%; margin-bottom: 16px;">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Phone Number</label>
                <input type="text" name="phone_number" style="width: 100%; margin-bottom: 16px;">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Password</label>
                <input type="password" name="password" required style="width: 100%; margin-bottom: 16px;">

                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Role</label>
                <select name="user_type" required style="width: 100%; margin-bottom: 20px;">
                    <option value="student">Student</option>
                    <option value="fk_staff">FK Staff (Admin)</option>
                    <option value="safety_staff">Safety Staff</option>
                </select>

                <button type="submit" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-check"></i> Create User
                </button>
            </form>
        </div>

        <!-- Search & Filter Form -->
        <div style="background: #f9fafb; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
            <h3 style="margin-top: 0; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-search"></i> Search & Filter
            </h3>
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ID, username or email" style="width: 100%; margin-bottom: 16px;">
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Filter by Role</label>
                <select name="role" style="width: 100%; margin-bottom: 20px;">
                    <option value="">All Roles</option>
                    <option value="student" <?php echo $filterRole === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="fk_staff" <?php echo $filterRole === 'fk_staff' ? 'selected' : ''; ?>>FK Staff</option>
                    <option value="safety_staff" <?php echo $filterRole === 'safety_staff' ? 'selected' : ''; ?>>Safety Staff</option>
                </select>
                
                <div style="display: flex; gap: 8px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="flex: 1; padding: 12px; background: #6b7280; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User List Section -->
<div class="card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-users"></i> User List (<?php echo count($users); ?> users)
        </h2>
    </div>
    
    <?php if (empty($users)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #6b7280;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <p style="font-size: 16px; margin: 0;">No users found matching your criteria.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); border-bottom: 2px solid #d1d5db;">
                        <th style="padding: 14px 16px; text-align: left; font-weight: 700; color: #1f2937; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">User ID</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 700; color: #1f2937; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Name</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 700; color: #1f2937; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Email</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 700; color: #1f2937; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Phone</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 700; color: #1f2937; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Role</th>
                        <th style="padding: 14px 16px; text-align: center; font-weight: 700; color: #1f2937; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: background 0.2s;">
                            <td style="padding: 14px 16px; color: #1f2937; font-weight: 600; font-size: 14px;">
                                <span style="background: #e0e7ff; color: #4f46e5; padding: 4px 8px; border-radius: 6px; font-family: monospace;">
                                    <?php echo htmlspecialchars($user['user_ID']); ?>
                                </span>
                            </td>
                            <td style="padding: 14px 16px; color: #374151; font-size: 14px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-user-circle" style="color: #667eea; font-size: 18px;"></i>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </td>
                            <td style="padding: 14px 16px; color: #6b7280; font-size: 14px;"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td style="padding: 14px 16px; color: #6b7280; font-size: 14px;"><?php echo htmlspecialchars($user['phone_number'] ?? '—'); ?></td>
                            <td style="padding: 14px 16px;">
                                <?php
                                $roleColor = match($user['user_type']) {
                                    'student' => '#dbeafe',
                                    'fk_staff' => '#e0e7ff',
                                    'safety_staff' => '#fce7f3',
                                    default => '#f3f4f6'
                                };
                                $roleTextColor = match($user['user_type']) {
                                    'student' => '#0369a1',
                                    'fk_staff' => '#4f46e5',
                                    'safety_staff' => '#be185d',
                                    default => '#6b7280'
                                };
                                $roleLabel = match($user['user_type']) {
                                    'student' => 'Student',
                                    'fk_staff' => 'FK Staff',
                                    'safety_staff' => 'Safety Staff',
                                    default => $user['user_type']
                                };
                                ?>
                                <span style="background: <?php echo $roleColor; ?>; color: <?php echo $roleTextColor; ?>; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block;">
                                    <?php echo $roleLabel; ?>
                                </span>
                            </td>
                            <td style="padding: 14px 16px; text-align: center;">
                                <button type="button" onclick="openEditModal('<?php echo htmlspecialchars($user['user_ID']); ?>')" style="padding: 8px 12px; background: #667eea; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s;">
                                    <i class="fas fa-cog"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; padding: 20px;" onclick="closeEditModal(event)">
        <div class="modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); padding: 28px; width: 95%; max-width: 450px; max-height: 90vh; overflow-y: auto;" onclick="event.stopPropagation();">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4 style="margin: 0; color: #1f2937; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-edit"></i> Edit User
                </h4>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; color: #6b7280; cursor: pointer; padding: 0; line-height: 1; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
            
            <form id="editUserForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="margin-bottom: 0;">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="modalUserId" name="user_id" value="">
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Username</label>
                <input type="text" id="modalUsername" name="username" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box;">
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Email</label>
                <input type="email" id="modalEmail" name="email" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box;">
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Phone</label>
                <input type="text" id="modalPhone" name="phone_number" style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box;">
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Role</label>
                <select id="modalUserType" name="user_type" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box;">
                    <?php foreach ($allowedRoles as $role): ?>
                        <option value="<?php echo $role; ?>"><?php echo ucfirst(str_replace('_',' ', $role)); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Password (leave blank to keep)</label>
                <input type="password" id="modalPassword" name="password" placeholder="New password (optional)" style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 18px; font-size: 14px; box-sizing: border-box;">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button type="submit" style="padding: 11px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s;">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" id="deleteUserBtn" onclick="deleteUser()" style="padding: 11px; background: #ef4444; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s;">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Store user data for modal population
        const userData = <?php echo json_encode(array_map(function($u) {
            return [
                'id' => $u['user_ID'],
                'username' => $u['username'],
                'email' => $u['email'],
                'phone' => $u['phone_number'],
                'type' => $u['user_type']
            ];
        }, $users)); ?>;

        function openEditModal(userId) {
            const user = userData.find(u => u.id == userId);
            if (!user) return;
            
            document.getElementById('modalUserId').value = user.id;
            document.getElementById('modalUsername').value = user.username;
            document.getElementById('modalEmail').value = user.email;
            document.getElementById('modalPhone').value = user.phone;
            document.getElementById('modalUserType').value = user.type;
            document.getElementById('modalPassword').value = '';
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal(event) {
            if (event && event.target.id !== 'editModal') return;
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteUser() {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
            
            const userId = document.getElementById('modalUserId').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="' + userId + '">';
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>

    <?php endif; ?>
</div>

<?php renderFooter(); ?>
