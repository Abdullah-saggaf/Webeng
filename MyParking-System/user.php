<?php
require_once __DIR__ . '/module01/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/database/db_config.php';

// Require student role
requireRole(['student']);

$user = currentUser();
$userId = $user['user_id'];

// Get student's vehicles
$vehicles = getVehiclesByUser($userId);
$stats = [
    'total_vehicles' => count($vehicles),
    'approved' => count(array_filter($vehicles, fn($v) => $v['grant_status'] === 'Approved')),
    'pending' => count(array_filter($vehicles, fn($v) => $v['grant_status'] === 'Pending')),
    'rejected' => count(array_filter($vehicles, fn($v) => $v['grant_status'] === 'Rejected'))
];

renderHeader('Student Dashboard');
?>

<div class="card">
    <h2>Your Vehicle Dashboard</h2>
    <p>Welcome back, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
</div>

<!-- Statistics -->
<div class="grid">
    <div class="card">
        <h3>Total Vehicles</h3>
        <p style="font-size: 24px; font-weight: 700; color: #4f46e5;"><?php echo $stats['total_vehicles']; ?></p>
    </div>
    <div class="card">
        <h3>Approved</h3>
        <p style="font-size: 24px; font-weight: 700; color: #059669;"><?php echo $stats['approved']; ?></p>
    </div>
    <div class="card">
        <h3>Pending</h3>
        <p style="font-size: 24px; font-weight: 700; color: #d97706;"><?php echo $stats['pending']; ?></p>
    </div>
    <div class="card">
        <h3>Rejected</h3>
        <p style="font-size: 24px; font-weight: 700; color: #dc2626;"><?php echo $stats['rejected']; ?></p>
    </div>
</div>

<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2>My Vehicles</h2>
        <a href="<?php echo appUrl('/student/main.php'); ?>" class="btn">Manage Vehicles</a>
    </div>
    
    <?php if ($vehicles): ?>
        <table>
            <thead>
                <tr>
                    <th>License Plate</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_model'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge <?php echo strtolower($vehicle['grant_status']); ?>">
                                <?php echo $vehicle['grant_status']; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; color: #6b7280; padding: 20px;">No vehicles registered yet. <a href="<?php echo appUrl('/student/main.php'); ?>">Register your first vehicle</a></p>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
