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
    
    elseif ($action === 'confirm_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        
        if ($bookingId) {
            try {
                $stmt = $db->prepare("UPDATE Booking SET booking_status='confirmed' WHERE booking_ID=? AND booking_status='pending'");
                $stmt->execute([$bookingId]);
                $message = "Booking confirmed successfully!";
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
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM Booking WHERE space_ID=? AND booking_date >= CURDATE() AND booking_status IN ('pending', 'confirmed', 'active')");
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

// Get ALL pending bookings (not filtered by search/area) for priority display
$allPendingBookingsList = [];
$sql = "SELECT b.*, ps.space_number, pl.parkingLot_name, 
        u.username as student_name, v.vehicle_model as vehicle_model, v.license_plate
        FROM Booking b
        JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
        JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
        JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
        JOIN User u ON v.user_ID = u.user_ID
        WHERE b.booking_status = 'pending'
        AND b.booking_date >= CURDATE()
        ORDER BY b.created_at ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$allPendingBookingsList = $stmt->fetchAll();

// Get pending bookings for spaces (for table display)
$pendingBookings = [];
if (!empty($spaces)) {
    $spaceIds = array_column($spaces, 'space_ID');
    $placeholders = implode(',', array_fill(0, count($spaceIds), '?'));
    $sql = "SELECT b.*, ps.space_number, pl.parkingLot_name, 
            u.username as student_name, v.vehicle_model as vehicle_model
            FROM Booking b
            JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
            JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
            JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
            JOIN User u ON v.user_ID = u.user_ID
            WHERE b.space_ID IN ($placeholders) 
            AND b.booking_status = 'pending'
            AND b.booking_date >= CURDATE()
            ORDER BY b.booking_date ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($spaceIds);
    $pendingBookingsFiltered = $stmt->fetchAll();
    
    // Group by space_ID
    foreach ($pendingBookingsFiltered as $booking) {
        $pendingBookings[$booking['space_ID']][] = $booking;
    }
}

require_once __DIR__ . '/../../layout.php';
renderHeader('Manage Parking Spaces');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/manage_parking_spaces.css?v=<?php echo time(); ?>">

<div class="spaces-container">
    <h2>Parking Space Management</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Pending Bookings Section -->
    <?php if (!empty($allPendingBookingsList)): ?>
    <div class="pending-bookings-section">
        <div class="section-header">
            <h3><i class="fas fa-clock"></i> Pending Booking Requests</h3>
            <span class="badge-count"><?php echo count($allPendingBookingsList); ?> Waiting</span>
        </div>
        <div class="pending-bookings-grid">
            <?php foreach ($allPendingBookingsList as $booking): ?>
            <div class="pending-booking-card">
                <div class="card-header">
                    <div class="student-info">
                        <i class="fas fa-user-circle"></i>
                        <strong><?php echo htmlspecialchars($booking['student_name']); ?></strong>
                    </div>
                    <span class="pending-badge"><i class="fas fa-hourglass-half"></i> Pending</span>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <i class="fas fa-parking"></i>
                        <span><strong>Space:</strong> <?php echo htmlspecialchars($booking['space_number']); ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><strong>Area:</strong> <?php echo htmlspecialchars($booking['parkingLot_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-calendar"></i>
                        <span><strong>Date:</strong> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-clock"></i>
                        <span><strong>Time:</strong> 
                        <?php 
                        if ($booking['start_time'] === '00:00:00' && $booking['end_time'] === '23:59:59') {
                            echo 'All Day';
                        } else {
                            echo date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                        }
                        ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-car"></i>
                        <span><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['license_plate']); ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="confirm_booking">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-confirm-large">
                            <i class="fas fa-check-circle"></i> Confirm Booking
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
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
            
            <button onclick="openBatchModal()" class="btn-generate"><i class="fas fa-bolt"></i> Generate Spaces</button>
        </div>
        
        <div class="controls-right">
            <form method="GET" class="search-form">
                <?php if ($selectedArea): ?>
                <input type="hidden" name="area_id" value="<?php echo $selectedArea; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search by space number..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
            </form>
            <button onclick="openCreateModal()" class="btn-add"><i class="fas fa-plus"></i> Add Space</button>
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
                    <th>Pending Bookings</th>
                    <th>Created</th>
                    <th>View QR</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($spaces)): ?>
                <tr><td colspan="8" style="text-align: center; padding: 40px;">No parking spaces found</td></tr>
                <?php else: ?>
                <?php foreach ($spaces as $index => $space): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td class="space-number"><?php echo htmlspecialchars($space['space_number']); ?></td>
                    <td><?php echo htmlspecialchars($space['parkingLot_name']); ?></td>
                    <td class="qr-code"><?php echo htmlspecialchars($space['qr_code_value']); ?></td>
                    <td>
                        <?php if (isset($pendingBookings[$space['space_ID']])): ?>
                            <div class="pending-bookings-cell">
                                <?php foreach ($pendingBookings[$space['space_ID']] as $booking): ?>
                                <div class="pending-booking-item">
                                    <span class="booking-student"><i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['student_name']); ?></span>
                                    <span class="booking-date"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="confirm_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                                        <button type="submit" class="btn-confirm" title="Confirm Booking">
                                            <i class="fas fa-check"></i> Confirm
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #999;">No pending bookings</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($space['created_at'])); ?></td>
                    <td>
                        <a href="<?php echo APP_BASE_PATH; ?>/module02/space_qr.php?space_id=<?php echo $space['space_ID']; ?>" 
                           target="_blank" class="btn-qr"><i class="fas fa-qrcode"></i> View QR</a>
                    </td>
                    <td class="actions">
                        <button onclick='openEditModal(<?php echo json_encode($space); ?>)' 
                                class="btn-edit"><i class="fas fa-edit"></i> Edit</button>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Delete this parking space?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="space_id" value="<?php echo $space['space_ID']; ?>">
                            <button type="submit" class="btn-delete"><i class="fas fa-trash"></i> Delete</button>
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
            <h3><i class="fas fa-plus-circle"></i> Add New Parking Space</h3>
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
            <h3><i class="fas fa-bolt"></i> Generate Multiple Spaces</h3>
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
                <button type="submit" class="btn-primary"><i class="fas fa-bolt"></i> Generate</button>
                <button type="button" onclick="closeModal('batchModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Space Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Parking Space</h3>
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
