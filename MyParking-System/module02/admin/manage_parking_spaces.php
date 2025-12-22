<?php
/**
 * Manage Parking Spaces - Admin View
 * Module 2 - MyParking System
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require FK Staff role (admin)
requireRole(['fk_staff']);

$db = getDB();
$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $areaId = (int)($_POST['area_id'] ?? 0);
        $spaceNumber = trim($_POST['space_number'] ?? '');
        
        if ($areaId && $spaceNumber) {
            try {
                $qrCode = "SPACE_{$areaId}_{$spaceNumber}_" . time();
                $stmt = $db->prepare("INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES (?, ?, ?)");
                $stmt->execute([$areaId, $spaceNumber, $qrCode]);
                $message = "Parking space created successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'batch_create') {
        $areaId = (int)($_POST['area_id'] ?? 0);
        $prefix = trim($_POST['prefix'] ?? 'A');
        $startNum = (int)($_POST['start_number'] ?? 1);
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($areaId && $quantity > 0 && $quantity <= 100) {
            try {
                $db->beginTransaction();
                $created = 0;
                
                for ($i = 0; $i < $quantity; $i++) {
                    $num = $startNum + $i;
                    $spaceNumber = $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
                    $qrCode = "SPACE_{$areaId}_{$spaceNumber}_" . time() . "_$i";
                    
                    $stmt = $db->prepare("INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES (?, ?, ?)");
                    $stmt->execute([$areaId, $spaceNumber, $qrCode]);
                    $created++;
                }
                
                $db->commit();
                $message = "Successfully created $created parking spaces!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $db->rollBack();
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = "Invalid input. Quantity must be between 1 and 100.";
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update') {
        $spaceId = (int)($_POST['space_id'] ?? 0);
        $spaceNumber = trim($_POST['space_number'] ?? '');
        $areaId = (int)($_POST['area_id'] ?? 0);
        
        if ($spaceId && $spaceNumber && $areaId) {
            try {
                $stmt = $db->prepare("UPDATE ParkingSpace SET space_number=?, parkingLot_ID=? WHERE space_ID=?");
                $stmt->execute([$spaceNumber, $areaId, $spaceId]);
                $message = "Parking space updated successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'delete') {
        $spaceId = (int)($_POST['space_id'] ?? 0);
        
        // Check if space has active bookings
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM Booking WHERE space_ID=? AND booking_end > NOW()");
        $stmt->execute([$spaceId]);
        $count = $stmt->fetch()['cnt'];
        
        if ($count > 0) {
            $message = "Cannot delete space with active bookings!";
            $messageType = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM ParkingSpace WHERE space_ID=?");
                $stmt->execute([$spaceId]);
                $message = "Parking space deleted successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get all areas for dropdown
$areas = $db->query("SELECT * FROM ParkingLot ORDER BY parkingLot_name")->fetchAll();

// Filters
$selectedArea = (int)($_GET['area_id'] ?? 0);
$search = $_GET['search'] ?? '';

// Build query
$whereClause = 'WHERE 1=1';
$params = [];

if ($selectedArea) {
    $whereClause .= ' AND ps.parkingLot_ID=?';
    $params[] = $selectedArea;
}

if ($search) {
    $whereClause .= ' AND ps.space_number LIKE ?';
    $params[] = "%$search%";
}

// Get spaces
$sql = "SELECT ps.*, pl.parkingLot_name 
        FROM ParkingSpace ps 
        JOIN ParkingLot pl ON ps.parkingLot_ID=pl.parkingLot_ID 
        $whereClause 
        ORDER BY pl.parkingLot_name, ps.space_number";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$spaces = $stmt->fetchAll();

require_once __DIR__ . '/../../module01/layout.php';
renderHeader('Manage Parking Spaces');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/manage_parking_spaces.css">

<div class="spaces-container">
    <h1 class="page-title">🅿️ Parking Space Management</h1>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Controls -->
    <div class="controls-bar">
        <div class="controls-left">
            <form method="GET" class="filter-form">
                <label>Parking Area:</label>
                <select name="area_id" onchange="this.form.submit()">
                    <option value="">All Areas</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['parkingLot_ID']; ?>" 
                            <?php echo $selectedArea == $area['parkingLot_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($area['parkingLot_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <button onclick="openBatchModal()" class="btn-generate">⚡ Generate Spaces</button>
        </div>
        
        <div class="controls-right">
            <form method="GET" class="search-form">
                <?php if ($selectedArea): ?>
                <input type="hidden" name="area_id" value="<?php echo $selectedArea; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search by space number..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search">🔍 Search</button>
            </form>
            <button onclick="openCreateModal()" class="btn-add">➕ Add Space</button>
        </div>
    </div>
    
    <!-- Table -->
    <div class="table-wrapper">
        <table class="spaces-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Space Number</th>
                    <th>Parking Area</th>
                    <th>QR Code Value</th>
                    <th>Created</th>
                    <th>View QR</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($spaces)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 40px;">No parking spaces found</td></tr>
                <?php else: ?>
                <?php foreach ($spaces as $index => $space): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td class="space-number"><?php echo htmlspecialchars($space['space_number']); ?></td>
                    <td><?php echo htmlspecialchars($space['parkingLot_name']); ?></td>
                    <td class="qr-code"><?php echo htmlspecialchars($space['qr_code_value']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($space['created_at'])); ?></td>
                    <td>
                        <a href="<?php echo APP_BASE_PATH; ?>/module02/space_qr.php?space_id=<?php echo $space['space_ID']; ?>" 
                           target="_blank" class="btn-qr">🔳 View QR</a>
                    </td>
                    <td class="actions">
                        <button onclick='openEditModal(<?php echo json_encode($space); ?>)' 
                                class="btn-edit">✏️ Edit</button>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Delete this parking space?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="space_id" value="<?php echo $space['space_ID']; ?>">
                            <button type="submit" class="btn-delete">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Single Space Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>➕ Add New Parking Space</h3>
            <button onclick="closeModal('createModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Parking Area <span class="required">*</span></label>
                <select name="area_id" required>
                    <option value="">Select Area</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['parkingLot_ID']; ?>">
                        <?php echo htmlspecialchars($area['parkingLot_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Space Number <span class="required">*</span></label>
                <input type="text" name="space_number" placeholder="e.g., A-001" required>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">💾 Create</button>
                <button type="button" onclick="closeModal('createModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Batch Generate Modal -->
<div id="batchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>⚡ Generate Multiple Spaces</h3>
            <button onclick="closeModal('batchModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="batch_create">
            
            <div class="form-group">
                <label>Parking Area <span class="required">*</span></label>
                <select name="area_id" required>
                    <option value="">Select Area</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['parkingLot_ID']; ?>">
                        <?php echo htmlspecialchars($area['parkingLot_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Prefix <span class="required">*</span></label>
                    <input type="text" name="prefix" value="A" maxlength="3" required>
                </div>
                
                <div class="form-group">
                    <label>Start Number <span class="required">*</span></label>
                    <input type="number" name="start_number" value="1" min="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Quantity (max 100) <span class="required">*</span></label>
                <input type="number" name="quantity" value="10" min="1" max="100" required>
                <small>Example: Prefix "A", Start 1, Quantity 10 = A-001 to A-010</small>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">⚡ Generate</button>
                <button type="button" onclick="closeModal('batchModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Space Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✏️ Edit Parking Space</h3>
            <button onclick="closeModal('editModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="space_id" id="edit_space_id">
            
            <div class="form-group">
                <label>Parking Area <span class="required">*</span></label>
                <select name="area_id" id="edit_area_id" required>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['parkingLot_ID']; ?>">
                        <?php echo htmlspecialchars($area['parkingLot_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Space Number <span class="required">*</span></label>
                <input type="text" name="space_number" id="edit_space_number" required>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">💾 Update</button>
                <button type="button" onclick="closeModal('editModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function openBatchModal() {
    document.getElementById('batchModal').style.display = 'flex';
}

function openEditModal(space) {
    document.getElementById('edit_space_id').value = space.space_ID;
    document.getElementById('edit_area_id').value = space.parkingLot_ID;
    document.getElementById('edit_space_number').value = space.space_number;
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php renderFooter(); ?>
