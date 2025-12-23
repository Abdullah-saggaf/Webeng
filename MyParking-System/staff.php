<?php
require_once __DIR__ . '/module01/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/database/db_config.php';

// Require safety_staff role
requireRole(['safety_staff']);

$user = currentUser();

// Get pending vehicles
$pendingVehicles = getPendingVehicles();
$approvedVehicles = getApprovedVehicles();
$rejectedVehicles = getRejectedVehicles();
$stats = [
    'pending' => count($pendingVehicles),
    'approved' => count($approvedVehicles),
    'rejected' => count($rejectedVehicles),
    'total_approvals' => count($approvedVehicles) + count($pendingVehicles) + count($rejectedVehicles)
];

renderHeader('Safety Staff Dashboard');
?>

<div class="card">
    <h2>Vehicle Approval Dashboard</h2>
    <p>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
</div>

<!-- Statistics -->
<div class="grid" style="margin-top: 24px;">
    <div class="card">
        <h3>Pending Approvals</h3>
        <p style="font-size: 24px; font-weight: 700; color: #d97706;"><?php echo $stats['pending']; ?></p>
    </div>
    <div class="card">
        <h3>Approved</h3>
        <p style="font-size: 24px; font-weight: 700; color: #059669;"><?php echo $stats['approved']; ?></p>
    </div>
    <div class="card">
        <h3>Rejected</h3>
        <p style="font-size: 24px; font-weight: 700; color: #dc2626;"><?php echo $stats['rejected']; ?></p>
    </div>
    <div class="card">
        <h3>Total Processed</h3>
        <p style="font-size: 24px; font-weight: 700; color: #4f46e5;"><?php echo $stats['total_approvals']; ?></p>
    </div>
</div>

<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2>Pending Vehicle Approvals</h2>
        <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="btn">View All</a>
    </div>
    
    <?php if ($pendingVehicles): ?>
        <table>
            <thead>
                <tr>
                    <th>License Plate</th>
                    <th>Student</th>
                    <th>Vehicle Type</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $displayVehicles = array_slice($pendingVehicles, 0, 5); // Show first 5
                foreach ($displayVehicles as $vehicle): 
                    $student = getUserById($vehicle['user_ID']);
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($student['username'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                        <td>
                            <a href="<?php echo appUrl('/safety/vehicle-approvals.php?id=' . $vehicle['vehicle_ID']); ?>" class="btn" style="font-size: 12px; padding: 6px 10px;">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($pendingVehicles) > 5): ?>
            <p style="text-align: center; margin-top: 12px; color: #6b7280;">
                <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>">View all <?php echo count($pendingVehicles); ?> pending approvals →</a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="text-align: center; color: #6b7280; padding: 20px;">No pending vehicle approvals. All caught up!</p>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 24px;">
    <h2>Quick Actions</h2>
    <div class="actions">
        <a href="<?php echo APP_BASE_PATH . '/module02/admin/parkingDashboard.php'; ?>" class="btn">Parking Dashboard</a>
        <a href="<?php echo appUrl('/safety/reports.php'); ?>" class="btn">View Reports</a>
        <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="btn">Go to Approvals</a>
    </div>
</div>

<?php renderFooter(); ?>