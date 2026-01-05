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
                            <?php if ($vehicle['grant_status'] === 'Rejected' && !empty($vehicle['rejection_reason'])): ?>
                                <div class="rejection-alert">
                                    <div class="title">⚠️ Rejection Reason:</div>
                                    <div class="reason"><?php echo htmlspecialchars($vehicle['rejection_reason']); ?></div>
                                    <div class="hint">💡 Edit and resubmit this vehicle below</div>
                                </div>
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
                                <button type="button" onclick="openVehicleModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => $vehicle['vehicle_ID'],
                                    'type' => $vehicle['vehicle_type'],
                                    'model' => $vehicle['vehicle_model'],
                                    'plate' => $vehicle['license_plate']
                                ])); ?>)" style="padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            <?php else: ?>
                                <span style="color:#6b7280; font-size:12px;">Locked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Vehicle Modal -->
<div id="vehicleModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; padding: 20px;" onclick="closeVehicleModal(event)">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); padding: 28px; width: 95%; max-width: 500px; max-height: 90vh; overflow-y: auto;" onclick="event.stopPropagation();">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #1f2937; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-car"></i> Edit Vehicle
            </h3>
            <button type="button" onclick="closeVehicleModal()" style="background: none; border: none; font-size: 24px; color: #6b7280; cursor: pointer; padding: 0; line-height: 1;">×</button>
        </div>
        
        <form id="vehicleEditForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="vehicleId" name="vehicle_id" value="">
            
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Vehicle Type</label>
            <select id="vehicleType" name="vehicle_type" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box;">
                <option value="Car">Car</option>
                <option value="Motorcycle">Motorcycle</option>
            </select>
            
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Vehicle Model</label>
            <input type="text" id="vehicleModel" name="vehicle_model" style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box;">
            
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">License Plate</label>
            <input type="text" id="vehiclePlate" name="license_plate" required style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 14px; font-size: 14px; box-sizing: border-box; text-transform: uppercase;">
            
            <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #374151;">Replace Grant Document (optional)</label>
            <input type="file" name="grant_document" accept="application/pdf,image/png,image/jpeg" style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 18px; font-size: 14px; box-sizing: border-box;">
            <p style="margin: 0 0 18px 0; font-size: 12px; color: #6b7280;">💡 PDF, PNG, or JPG only. Max 2MB.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button type="submit" style="padding: 11px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s;">
                    <i class="fas fa-save"></i> Update
                </button>
                <button type="button" id="deleteVehicleBtn" onclick="deleteVehicle()" style="padding: 11px; background: #ef4444; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s;">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openVehicleModal(vehicleData) {
        document.getElementById('vehicleId').value = vehicleData.id;
        document.getElementById('vehicleType').value = vehicleData.type;
        document.getElementById('vehicleModel').value = vehicleData.model;
        document.getElementById('vehiclePlate').value = vehicleData.plate;
        document.getElementById('vehicleModal').style.display = 'block';
    }

    function closeVehicleModal(event) {
        if (event && event.target.id !== 'vehicleModal') return;
        document.getElementById('vehicleModal').style.display = 'none';
    }

    function deleteVehicle() {
        if (!confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="vehicle_id" value="' + document.getElementById('vehicleId').value + '">';
        document.body.appendChild(form);
        form.submit();
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeVehicleModal();
        }
    });
</script>

<?php renderFooter(); ?>
