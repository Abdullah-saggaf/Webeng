<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../layout.php';
require_once __DIR__ . '/../../database/db_config.php';

requireRole(['safety_staff']);

$message = '';
$messageType = 'success';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $vehicleId = (int)($_POST['vehicle_id'] ?? 0);

        if ($vehicleId <= 0) {
            throw new Exception('Vehicle not found.');
        }

        if ($action === 'approve') {
            setVehicleStatus($vehicleId, 'Approved');
            $message = 'Vehicle approved.';
        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            setVehicleStatus($vehicleId, 'Rejected', $reason);
            $message = 'Vehicle rejected' . (!empty($reason) ? ' with reason.' : '.');
        }
    }
} catch (Exception $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

$licenseFilter = trim($_GET['license_plate'] ?? '');
$userFilter = trim($_GET['user_ID'] ?? '');

$pendingVehicles = getPendingVehicles([
    'license_plate' => $licenseFilter ?: null,
    'user_ID' => $userFilter ?: null,
]);

// Get approved and rejected vehicles
$db = getDB();
$approvedQuery = $db->prepare("
    SELECT v.*, u.username, u.user_ID as owner_id 
    FROM Vehicle v 
    JOIN User u ON v.user_ID = u.user_ID 
    WHERE v.grant_status = 'Approved'
    ORDER BY v.created_at DESC
");
$approvedQuery->execute();
$approvedVehicles = $approvedQuery->fetchAll();

$rejectedQuery = $db->prepare("
    SELECT v.*, u.username, u.user_ID as owner_id 
    FROM Vehicle v 
    JOIN User u ON v.user_ID = u.user_ID 
    WHERE v.grant_status = 'Rejected'
    ORDER BY v.created_at DESC
");
$rejectedQuery->execute();
$rejectedVehicles = $rejectedQuery->fetchAll();

renderHeader('Vehicle Approvals');
?>

<div class="card">
    <h2>Pending Vehicle Approvals</h2>
    <?php if ($message): ?>
        <div class="msg <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <!-- Filter Section -->
    <div style="background: #f9fafb; padding: 16px; border-radius: 10px; margin-bottom: 20px;">
        <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; align-items: flex-end;">
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">License Plate</label>
                <input type="text" name="license_plate" value="<?php echo htmlspecialchars($licenseFilter); ?>" placeholder="e.g., ABC 1234" style="width: 100%;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px;">Student ID</label>
                <input type="text" name="user_ID" value="<?php echo htmlspecialchars($userFilter); ?>" placeholder="e.g., 12345" style="width: 100%;">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn" style="padding: 10px 20px;">Search</button>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn" style="padding: 10px 20px; background: #6b7280; text-decoration: none; display: flex; align-items: center;">Reset</a>
            </div>
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>License Plate</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Student</th>
                    <th>Document</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingVehicles as $vehicle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_model']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['owner_id'] . ' — ' . $vehicle['username']); ?></td>
                        <td>
                            <?php if ($vehicle['grant_document']): ?>
                                <a class="btn secondary" href="<?php echo appUrl('/safety/view_grant.php?vehicle_id=' . $vehicle['vehicle_ID']); ?>" target="_blank">View</a>
                            <?php else: ?>
                                <span class="badge pending">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                <!-- Approve Form -->
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="flex-shrink: 0; margin: 0;">
                                    <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; white-space: nowrap; font-size: 13px;">✓ Approve</button>
                                </form>
                                
                                <!-- Reject Form with Reason -->
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: flex; gap: 6px; align-items: center; flex: 1; min-width: 280px; margin: 0;">
                                    <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="text" name="rejection_reason" placeholder="Rejection reason..." required style="flex: 1; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; background: white;">
                                    <button type="submit" style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; white-space: nowrap; font-size: 13px;">✗ Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Approved Vehicles Section -->
<div class="card" style="margin-top: 32px;">
    <h2 style="color: #10b981; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-check-circle"></i> Approved Vehicles
    </h2>
    <p style="color: #6b7280; margin-bottom: 16px;">View and manage approved vehicles. You can still reject if needed.</p>
    
    <?php if (empty($approvedVehicles)): ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
            <p>No approved vehicles yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>License Plate</th>
                        <th>Type</th>
                        <th>Model</th>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Approved Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvedVehicles as $vehicle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['vehicle_model']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['owner_id'] . ' — ' . $vehicle['username']); ?></td>
                            <td>
                                <?php if ($vehicle['grant_document']): ?>
                                    <a class="btn secondary" href="<?php echo appUrl('/safety/view_grant.php?vehicle_id=' . $vehicle['vehicle_ID']); ?>" target="_blank">View</a>
                                <?php else: ?>
                                    <span class="badge pending">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: #6b7280; font-size: 13px;">
                                    <?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: flex; gap: 6px; align-items: center; margin: 0;">
                                    <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="text" name="rejection_reason" placeholder="Reason to revoke..." required style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; background: white; flex: 1; min-width: 150px;">
                                    <button type="submit" style="padding: 8px 16px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; white-space: nowrap; font-size: 13px;">Revoke</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Rejected Vehicles Section -->
<div class="card" style="margin-top: 32px;">
    <h2 style="color: #ef4444; display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-times-circle"></i> Rejected Vehicles
    </h2>
    <p style="color: #6b7280; margin-bottom: 16px;">View vehicles that were rejected. Students can resubmit them for re-review.</p>
    
    <?php if (empty($rejectedVehicles)): ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 12px; opacity: 0.5;"></i>
            <p>No rejected vehicles.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>License Plate</th>
                        <th>Type</th>
                        <th>Model</th>
                        <th>Student</th>
                        <th>Rejection Reason</th>
                        <th>Document</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rejectedVehicles as $vehicle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['vehicle_model']); ?></td>
                            <td><?php echo htmlspecialchars($vehicle['owner_id'] . ' — ' . $vehicle['username']); ?></td>
                            <td>
                                <span style="color: #7f1d1d; background: #fee2e2; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo htmlspecialchars($vehicle['rejection_reason'] ?? 'No reason provided'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($vehicle['grant_document']): ?>
                                    <a class="btn secondary" href="<?php echo appUrl('/safety/view_grant.php?vehicle_id=' . $vehicle['vehicle_ID']); ?>" target="_blank">View</a>
                                <?php else: ?>
                                    <span class="badge pending">Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="margin: 0;">
                                    <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; white-space: nowrap; font-size: 13px;">Re-approve</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
