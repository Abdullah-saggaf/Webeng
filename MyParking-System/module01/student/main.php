<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../layout.php';
require_once __DIR__ . '/../../database/db_config.php';

requireRole(['student']);

$user = currentUser();
$message = '';
$messageType = 'success';

function uploadGrant($file, $userId) {
    if (empty($file['name'])) {
        throw new Exception('Grant document is required.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed.');
    }

    $allowed = ['application/pdf', 'image/png', 'image/jpeg'];
    if (!in_array($file['type'], $allowed, true)) {
        throw new Exception('Only PDF, JPG, and PNG files are allowed.');
    }

    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large (max 2MB).');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'grant_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    
    // Create storage directory if it doesn't exist
    $storageDir = __DIR__ . '/../../storage/grants';
    if (!file_exists($storageDir)) {
        if (!mkdir($storageDir, 0755, true)) {
            throw new Exception('Unable to create storage directory.');
        }
    }
    
    $targetDir = realpath($storageDir);
    if ($targetDir === false) {
        throw new Exception('Storage path not found.');
    }
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Unable to save uploaded file.');
    }

    return 'storage/grants/' . $newName;
}

function removeGrantFile($relativePath) {
    if (!$relativePath) {
        return;
    }
    $fullPath = realpath(__DIR__ . '/../../' . $relativePath);
    if ($fullPath && file_exists($fullPath)) {
        @unlink($fullPath);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $vehicleType = trim($_POST['vehicle_type'] ?? '');
            $vehicleModel = trim($_POST['vehicle_model'] ?? '');
            $licensePlate = strtoupper(trim($_POST['license_plate'] ?? ''));

            if ($vehicleType === '' || $licensePlate === '') {
                throw new Exception('Vehicle type and license plate are required.');
            }

            $grantPath = uploadGrant($_FILES['grant_document'], $user['user_id']);
            addVehicle($user['user_id'], $vehicleType, $vehicleModel, $licensePlate, $grantPath);
            $message = 'Vehicle submitted for approval.';
        }

        if ($action === 'update') {
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            $vehicle = getVehicleForUser($vehicleId, $user['user_id']);
            if (!$vehicle) {
                throw new Exception('Vehicle not found.');
            }
            if ($vehicle['grant_status'] === 'Approved') {
                throw new Exception('Approved vehicles cannot be modified.');
            }

            $fields = [
                'vehicle_type' => trim($_POST['vehicle_type'] ?? ''),
                'vehicle_model' => trim($_POST['vehicle_model'] ?? ''),
                'license_plate' => strtoupper(trim($_POST['license_plate'] ?? '')),
                'grant_status' => 'Pending',
                'rejection_reason' => null
            ];

            if (!empty($_FILES['grant_document']['name'])) {
                $grantPath = uploadGrant($_FILES['grant_document'], $user['user_id']);
                $fields['grant_document'] = $grantPath;
                removeGrantFile($vehicle['grant_document']);
            }

            updateVehicle($vehicleId, $user['user_id'], $fields);
            $message = 'Vehicle updated and resubmitted.';
        }

        if ($action === 'delete') {
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            $vehicle = getVehicleForUser($vehicleId, $user['user_id']);
            if (!$vehicle) {
                throw new Exception('Vehicle not found.');
            }
            if ($vehicle['grant_status'] === 'Approved') {
                throw new Exception('Approved vehicles cannot be deleted.');
            }
            removeGrantFile($vehicle['grant_document']);
            deleteVehicle($vehicleId, $user['user_id']);
            $message = 'Vehicle deleted.';
        }
    }
} catch (Exception $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

$vehicles = getVehiclesByUser($user['user_id']);

renderHeader('Student Vehicles');
?>

<div class="card">
    <h2>Register Vehicle</h2>
    <?php if ($message): ?>
        <div class="msg <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create">
        <label>Vehicle Type</label>
        <select name="vehicle_type" required>
            <option value="">Select type</option>
            <option value="Car">Car</option>
            <option value="Motorcycle">Motorcycle</option>
        </select>

        <label>Vehicle Model</label>
        <input type="text" name="vehicle_model" placeholder="e.g. Perodua Myvi">

        <label>License Plate</label>
        <input type="text" name="license_plate" required>

        <label>Grant Document (PDF/PNG/JPG)</label>
        <input type="file" name="grant_document" accept="application/pdf,image/png,image/jpeg" required>

        <button type="submit">Submit for Approval</button>
    </form>
</div>

<div class="card">
    <h2>Your Vehicles</h2>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>License Plate</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Grant</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_model']); ?></td>
                        <td>
                            <span class="badge <?php echo strtolower($vehicle['grant_status']); ?>">
                                <?php echo htmlspecialchars($vehicle['grant_status']); ?>
                            </span>
                            <?php if (!empty($vehicle['rejection_reason'])): ?>
                                <div style="color:#991b1b; font-size:12px;">Reason: <?php echo htmlspecialchars($vehicle['rejection_reason']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($vehicle['grant_document']): ?>
                                <span class="badge pending">Stored</span>
                            <?php else: ?>
                                <span class="badge rejected">Missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($vehicle['grant_status'] !== 'Approved'): ?>
                                <details>
                                    <summary>Edit</summary>
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                        <label>Vehicle Type</label>
                                        <select name="vehicle_type" required>
                                            <option value="Car" <?php echo $vehicle['vehicle_type']==='Car'?'selected':''; ?>>Car</option>
                                            <option value="Motorcycle" <?php echo $vehicle['vehicle_type']==='Motorcycle'?'selected':''; ?>>Motorcycle</option>
                                        </select>
                                        <label>Model</label>
                                        <input type="text" name="vehicle_model" value="<?php echo htmlspecialchars($vehicle['vehicle_model']); ?>">
                                        <label>License Plate</label>
                                        <input type="text" name="license_plate" value="<?php echo htmlspecialchars($vehicle['license_plate']); ?>" required>
                                        <label>Replace Grant (optional)</label>
                                        <input type="file" name="grant_document" accept="application/pdf,image/png,image/jpeg">
                                        <button type="submit">Update</button>
                                    </form>
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Delete this vehicle?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle['vehicle_ID']; ?>">
                                        <button type="submit" class="secondary">Delete</button>
                                    </form>
                                </details>
                            <?php else: ?>
                                <span style="color:#6b7280; font-size:12px;">Locked (approved)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
