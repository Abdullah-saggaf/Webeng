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
        $reason = trim($_POST['rejection_reason'] ?? '');

        if ($vehicleId <= 0) {
            throw new Exception('Vehicle not found.');
        }

        if ($action === 'approve') {
            setVehicleStatus($vehicleId, 'Approved', null);
            $message = 'Vehicle approved.';
        } elseif ($action === 'reject') {
            setVehicleStatus($vehicleId, 'Rejected', $reason ?: null);
            $message = 'Vehicle rejected.';
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

renderHeader('Vehicle Approvals');
?>

<div class="card">
    <h2>Pending Vehicle Approvals</h2>
    <?php if ($message): ?>
        <div class="msg <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="actions">
        <div style="flex:1;">
            <label>License Plate</label>
            <input type="text" name="license_plate" value="<?php echo htmlspecialchars($licenseFilter); ?>">
        </div>
        <div style="flex:1;">
            <label>Student ID</label>
            <input type="text" name="user_ID" value="<?php echo htmlspecialchars($userFilter); ?>">
        </div>
        <div style="display:flex; align-items:flex-end; gap:10px;">
            <button type="submit" class="secondary">Filter</button>
            <a class="btn secondary" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Reset</a>
        </div>
    </form>

    <div style="overflow-x:auto; margin-top:12px;">
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
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline" style="margin-bottom:6px;">
                                <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit">Approve</button>
                            </form>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="inline">
                                <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="text" name="rejection_reason" placeholder="Reason (optional)" style="margin-bottom:8px;">
                                <button type="submit" class="secondary">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
