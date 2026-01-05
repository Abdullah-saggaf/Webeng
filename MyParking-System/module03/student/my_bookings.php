<?php
/**
 * My Bookings - Student View
 * Module 3 - MyParking System
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require Student role
requireRole(['student']);

$db = getDB();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    
    if ($action === 'activate' && $bookingId) {
        try {
            // Check if booking belongs to user and is confirmed
            $stmt = $db->prepare("
                SELECT b.* FROM Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                WHERE b.booking_ID = ? 
                AND v.user_ID = ? 
                AND b.booking_status = 'confirmed'
                AND b.booking_date = CURDATE()
            ");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                throw new Exception('Invalid booking or booking is not for today.');
            }
            
            // Activate booking
            $stmt = $db->prepare("
                UPDATE Booking 
                SET booking_status = 'active', 
                    start_time = CURTIME() 
                WHERE booking_ID = ?
            ");
            $stmt->execute([$bookingId]);
            
            $message = 'Booking activated successfully!';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'complete' && $bookingId) {
        try {
            // Complete booking
            $stmt = $db->prepare("
                UPDATE Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                SET b.booking_status = 'completed', 
                    b.end_time = CURTIME() 
                WHERE b.booking_ID = ? 
                AND v.user_ID = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            
            $message = 'Booking completed successfully!';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'cancel' && $bookingId) {
        try {
            // First verify booking belongs to user
            $stmt = $db->prepare("
                SELECT b.booking_ID 
                FROM Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                WHERE b.booking_ID = ? 
                AND v.user_ID = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                throw new Exception('Booking not found or you do not have permission to cancel it.');
            }
            
            // Delete booking from database
            $stmt = $db->prepare("DELETE FROM Booking WHERE booking_ID = ?");
            $stmt->execute([$bookingId]);
            
            $message = 'Booking cancelled and deleted successfully.';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete' && $bookingId) {
        try {
            // First verify booking belongs to user
            $stmt = $db->prepare("
                SELECT b.booking_ID 
                FROM Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                WHERE b.booking_ID = ? 
                AND v.user_ID = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                throw new Exception('Booking not found or you do not have permission to delete it.');
            }
            
            // Delete booking
            $stmt = $db->prepare("DELETE FROM Booking WHERE booking_ID = ?");
            $stmt->execute([$bookingId]);
            
            $message = 'Booking deleted successfully.';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update' && $bookingId) {
        try {
            $newDate = $_POST['booking_date'] ?? '';
            $newStartTime = $_POST['start_time'] ?? '';
            $newEndTime = $_POST['end_time'] ?? '';
            $newVehicleId = (int)($_POST['vehicle_id'] ?? 0);
            
            if (!$newDate || !$newStartTime || !$newEndTime || !$newVehicleId) {
                throw new Exception('All fields are required.');
            }
            
            // Validate vehicle belongs to user and is approved
            $stmt = $db->prepare("SELECT vehicle_ID FROM Vehicle WHERE vehicle_ID = ? AND user_ID = ? AND grant_status = 'Approved'");
            $stmt->execute([$newVehicleId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Please select a valid approved vehicle.');
            }
            
            // Validate time range
            if (strtotime($newStartTime) >= strtotime($newEndTime)) {
                throw new Exception('End time must be after start time.');
            }
            
            // Update booking (only if confirmed)
            $stmt = $db->prepare("
                UPDATE Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                SET b.booking_date = ?,
                    b.start_time = ?,
                    b.end_time = ?,
                    b.vehicle_ID = ?
                WHERE b.booking_ID = ? 
                AND v.user_ID = ? 
                AND b.booking_status = 'confirmed'
            ");
            $stmt->execute([$newDate, $newStartTime, $newEndTime, $newVehicleId, $bookingId, $userId]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Booking updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Cannot update active or completed bookings.';
                $messageType = 'error';
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get user's bookings (exclude cancelled bookings)
$stmt = $db->prepare("
    SELECT 
        b.*,
        ps.space_number,
        pl.parkingLot_name,
        pl.parkingLot_type,
        v.license_plate,
        v.vehicle_model,
        v.vehicle_type,
        b.qr_token
    FROM Booking b
    JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    WHERE v.user_ID = ? AND b.booking_status != 'cancelled'
    ORDER BY b.booking_date DESC, b.created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll();

// Auto-complete expired active bookings
$currentTime = date('Y-m-d H:i:s');
$completeStmt = $db->prepare("
    UPDATE Booking b
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    SET b.booking_status = 'completed',
        b.session_ended_at = b.actual_end_time
    WHERE v.user_ID = ? 
    AND b.booking_status = 'active' 
    AND b.actual_end_time IS NOT NULL 
    AND b.actual_end_time > '1000-01-01 00:00:00'
    AND b.actual_end_time < ?
");
$completeStmt->execute([$userId, $currentTime]);

// If bookings were completed, refresh the list
if ($completeStmt->rowCount() > 0) {
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll();
}

// Get user's vehicles for edit modal
$vehiclesStmt = $db->prepare("SELECT vehicle_ID, vehicle_type, vehicle_model, license_plate, grant_status FROM Vehicle WHERE user_ID = ? ORDER BY grant_status DESC, created_at DESC");
$vehiclesStmt->execute([$userId]);
$userVehicles = $vehiclesStmt->fetchAll();

require_once __DIR__ . '/../../layout.php';
renderHeader('My Bookings');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module03/student/my_bookings.css?v=<?php echo time(); ?>">

<div class="bookings-container">
    <h2>My Bookings</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($bookings)): ?>
    <div class="no-bookings">
        <i class="fas fa-calendar-times"></i>
        <h3>No Bookings Yet</h3>
        <p>You haven't made any parking bookings. Visit the Parking Booking page to reserve a space.</p>
        <a href="<?php echo APP_BASE_PATH; ?>/module03/student/parking_booking.php" class="btn-primary">
            <i class="fas fa-plus"></i> Make a Booking
        </a>
    </div>
    <?php else: ?>
    
    <div class="bookings-grid">
        <?php foreach ($bookings as $booking): 
            $statusClass = strtolower($booking['booking_status']);
            $isToday = $booking['booking_date'] === date('Y-m-d');
            $isPast = $booking['booking_date'] < date('Y-m-d');
        ?>
        <div class="booking-card <?php echo $statusClass; ?>">
            <div class="booking-header">
                <div class="booking-info">
                    <h3><?php echo htmlspecialchars($booking['space_number']); ?></h3>
                    <span class="status-badge <?php echo $statusClass; ?>">
                        <?php echo ucfirst($booking['booking_status']); ?>
                    </span>
                </div>
                <div class="booking-date">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                    <?php if ($isToday): ?>
                        <span class="today-badge">Today</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="booking-details">
                <div class="detail-row">
                    <i class="fas fa-car"></i>
                    <span><?php echo htmlspecialchars($booking['vehicle_model']); ?> - <?php echo htmlspecialchars($booking['license_plate']); ?></span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($booking['parkingLot_name']); ?></span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-tag"></i>
                    <span><?php echo htmlspecialchars($booking['parkingLot_type']); ?></span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-clock"></i>
                    <span>
                        <?php 
                        if ($booking['start_time'] === '00:00:00' && $booking['end_time'] === '23:59:59') {
                            echo 'All Day';
                        } elseif ($booking['booking_status'] === 'active') {
                            echo 'Started: ' . date('g:i A', strtotime($booking['start_time']));
                        } elseif ($booking['booking_status'] === 'completed') {
                            echo 'Duration: ' . date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                        } else {
                            echo date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-row">
                    <i class="fas fa-hashtag"></i>
                    <span>Booking #<?php echo $booking['booking_ID']; ?></span>
                </div>
            </div>
            
            <div class="booking-actions">
                <?php if ($booking['booking_status'] === 'confirmed'): ?>
                    <div class="qr-code-container" style="text-align: center; margin: 15px 0;">
                        <p style="font-weight: 600; color: #1f2937; margin-bottom: 10px;"><i class="fas fa-qrcode"></i> Scan to Start Session</p>
                        <div id="qrcode_<?php echo $booking['booking_ID']; ?>" style="display: inline-block; padding: 10px; background: white; border-radius: 8px;"></div>
                    </div>
                    <button type="button" class="btn-edit" onclick='openEditModal(<?php echo json_encode($booking); ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-cancel" onclick="return confirm('Are you sure you want to cancel and delete this booking? This action cannot be undone.')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                <?php elseif ($booking['booking_status'] === 'active'): ?>
                    <a href="<?php echo APP_BASE_PATH; ?>/module03/parking_session.php?booking_id=<?php echo $booking['booking_ID']; ?>" 
                       class="btn-view-session">
                        <i class="fas fa-eye"></i> View Session
                    </a>
                    <div class="active-indicator">
                        <i class="fas fa-circle pulse"></i> Active Session
                    </div>
                <?php elseif ($booking['booking_status'] === 'completed'): ?>
                    <div class="completed-info" style="text-align: center; padding: 15px; background: #f3f4f6; border-radius: 8px; margin-bottom: 10px;">
                        <i class="fas fa-check-circle" style="color: #10b981; font-size: 24px; margin-bottom: 5px;"></i>
                        <p style="color: #6b7280; font-size: 14px; margin: 0;">Session Completed</p>
                    </div>
                    <form method="POST" style="display: inline; width: 100%;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-delete" onclick="return confirm('Delete this completed booking? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</div>

<!-- Edit Booking Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Booking</h3>
            <button onclick="closeEditModal()" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="booking_id" id="edit_booking_id">
            
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Space</label>
                    <input type="text" id="edit_space" readonly style="background: #f3f4f6; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; width: 100%;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Vehicle <span style="color: #ef4444;">*</span></label>
                    <select name="vehicle_id" id="edit_vehicle_id" required style="padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; width: 100%;">
                        <option value="">-- Select Vehicle --</option>
                        <?php foreach ($userVehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['vehicle_ID']; ?>" 
                                    <?php echo $vehicle['grant_status'] !== 'Approved' ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($vehicle['vehicle_model']); ?> - 
                                <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                <?php if ($vehicle['grant_status'] === 'Approved'): ?>
                                    ✓
                                <?php else: ?>
                                    (<?php echo $vehicle['grant_status']; ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Booking Date <span style="color: #ef4444;">*</span></label>
                    <input type="date" name="booking_date" id="edit_date" required style="padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; width: 100%;" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Start Time <span style="color: #ef4444;">*</span></label>
                    <input type="time" name="start_time" id="edit_start_time" required style="padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; width: 100%;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">End Time <span style="color: #ef4444;">*</span></label>
                    <input type="time" name="end_time" id="edit_end_time" required style="padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; width: 100%;">
                </div>
            </div>
            
            <div class="modal-actions" style="padding: 15px 20px; background: #f9fafb; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeEditModal()" style="padding: 8px 16px; background: #e5e7eb; border: none; border-radius: 6px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Generate QR codes for all confirmed bookings
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($bookings as $booking): ?>
        <?php if ($booking['booking_status'] === 'confirmed' && !empty($booking['qr_token'])): ?>
            new QRCode(document.getElementById('qrcode_<?php echo $booking['booking_ID']; ?>'), {
                text: '<?php echo QR_BASE_URL; ?>/module03/parking_session.php?booking_id=<?php echo $booking['booking_ID']; ?>&token=<?php echo $booking['qr_token']; ?>',
                width: 180,
                height: 180,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        <?php endif; ?>
    <?php endforeach; ?>
});

function openEditModal(booking) {
    document.getElementById('edit_booking_id').value = booking.booking_ID;
    document.getElementById('edit_space').value = booking.space_number;
    document.getElementById('edit_vehicle_id').value = booking.vehicle_ID;
    document.getElementById('edit_date').value = booking.booking_date;
    document.getElementById('edit_start_time').value = booking.start_time;
    document.getElementById('edit_end_time').value = booking.end_time;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
}
</script>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 500px;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-btn:hover {
    color: #1f2937;
}

.btn-edit {
    background: #f59e0b;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-edit:hover {
    background: #d97706;
}

.btn-cancel {
    background: #ef4444;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-cancel:hover {
    background: #dc2626;
}

.btn-delete {
    background: #6b7280;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    transition: all 0.3s ease;
}

.btn-delete:hover {
    background: #ef4444;
    transform: translateY(-2px);
}

.btn-view-session {
    background: #10b981;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    width: 100%;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.btn-view-session:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

.active-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #10b981;
    font-weight: 600;
    font-size: 14px;
    padding: 8px 0;
    margin-top: 8px;
}

.active-indicator .pulse {
    font-size: 10px;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.5;
        transform: scale(0.8);
    }
}

.booking-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
}

.booking-actions form {
    flex: 1;
}

.booking-actions button {
    width: 100%;
}
</style>

<?php renderFooter(); ?>
