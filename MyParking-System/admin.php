<?php
require_once __DIR__ . '/module01/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/database/db_config.php';

// Require fk_staff role
requireRole(['fk_staff']);

$user = currentUser();

// Get user statistics
$allUsers = getUsers();
$stats = [
    'total_users' => count($allUsers),
    'students' => count(array_filter($allUsers, fn($u) => $u['user_type'] === 'student')),
    'fk_staff' => count(array_filter($allUsers, fn($u) => $u['user_type'] === 'fk_staff')),
    'safety_staff' => count(array_filter($allUsers, fn($u) => $u['user_type'] === 'safety_staff'))
];

renderHeader('Admin Dashboard');
?>

<div class="card">
    <h2>Administrator Control Center</h2>
    <p>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
</div>

<!-- Statistics -->
<div class="grid">
    <div class="card">
        <h3>Total Users</h3>
        <p style="font-size: 24px; font-weight: 700; color: #4f46e5;"><?php echo $stats['total_users']; ?></p>
    </div>
    <div class="card">
        <h3>Students</h3>
        <p style="font-size: 24px; font-weight: 700; color: #0891b2;"><?php echo $stats['students']; ?></p>
    </div>
    <div class="card">
        <h3>FK Staff</h3>
        <p style="font-size: 24px; font-weight: 700; color: #7c3aed;"><?php echo $stats['fk_staff']; ?></p>
    </div>
    <div class="card">
        <h3>Safety Staff</h3>
        <p style="font-size: 24px; font-weight: 700; color: #f59e0b;"><?php echo $stats['safety_staff']; ?></p>
    </div>
</div>

<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2>Recent Users</h2>
        <a href="<?php echo appUrl('/admin/users.php'); ?>" class="btn">Manage All Users</a>
    </div>
    
    <?php if ($allUsers): ?>
        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $displayUsers = array_slice($allUsers, 0, 8); // Show first 8
                foreach ($displayUsers as $u): 
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['user_ID']); ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <span class="badge" style="background: <?php 
                                $colors = ['student' => '#dbeafe', 'fk_staff' => '#e9d5ff', 'safety_staff' => '#fef3c7'];
                                echo $colors[$u['user_type']] ?? '#f3f4f6';
                            ?>; color: #1f2937;">
                                <?php echo str_replace('_', ' ', ucfirst($u['user_type'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($allUsers) > 8): ?>
            <p style="text-align: center; margin-top: 12px; color: #6b7280;">
                <a href="<?php echo appUrl('/admin/users.php'); ?>">View all <?php echo count($allUsers); ?> users →</a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="text-align: center; color: #6b7280; padding: 20px;">No users in the system yet.</p>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 24px;">
    <h2>Quick Actions</h2>
    <div class="actions">
        <a href="<?php echo appUrl('/admin/users.php'); ?>" class="btn">User Management</a>
        <a href="<?php echo appUrl('/admin/reports.php'); ?>" class="btn">View Reports</a>
    </div>
</div>

<?php renderFooter(); ?>