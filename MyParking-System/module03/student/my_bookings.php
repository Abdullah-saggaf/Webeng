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
            // Cancel booking (for pending or confirmed status)
            $stmt = $db->prepare("
                UPDATE Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                SET b.booking_status = 'cancelled' 
                WHERE b.booking_ID = ? 
                AND v.user_ID = ? 
                AND b.booking_status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$bookingId, $userId]);
            
            if ($stmt->rowCount() > 0) {
                $message = 'Booking cancelled successfully.';
                $messageType = 'success';
            } else {
                $message = 'Cannot cancel active or completed bookings.';
                $messageType = 'error';
            }
            
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
}

// Get user's bookings
$stmt = $db->prepare("
    SELECT 
        b.*,
        ps.space_number,
        ps.qr_code_value,
        pl.parkingLot_name,
        pl.parkingLot_type,
        v.license_plate
    FROM Booking b
    JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    WHERE v.user_ID = ?
    ORDER BY b.booking_date DESC, b.created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/../../layout.php';
renderHeader('My Bookings');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module03/student/my_bookings.css?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

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
            $isPending = $booking['booking_status'] === 'pending';
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
            
            <?php if ($isPending): ?>
            <div class="pending-section">
                <i class="fas fa-hourglass-half"></i>
                <p class="pending-message">Waiting for admin confirmation</p>
            </div>
            <?php elseif ($booking['booking_status'] === 'active' || ($booking['booking_status'] === 'confirmed' && $isToday)): ?>
            <div class="qr-section">
                <div class="qr-code" id="qr-<?php echo $booking['booking_ID']; ?>"></div>
                <p class="qr-label">Scan to Verify</p>
                <script>
                new QRCode(document.getElementById("qr-<?php echo $booking['booking_ID']; ?>"), {
                    text: "<?php echo htmlspecialchars($booking['qr_code_value']); ?>",
                    width: 200,
                    height: 200,
                    correctLevel: QRCode.CorrectLevel.H
                });
                </script>
            </div>
            <?php endif; ?>
            
            <div class="booking-actions">
                <?php if ($isPending): ?>
                <form method="POST" style="display: inline; width: 100%;">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                    <button type="submit" class="btn-cancel-pending" onclick="return confirm('Cancel this pending booking request?')">
                        <i class="fas fa-times-circle"></i> Cancel Request
                    </button>
                </form>
                <?php elseif ($booking['booking_status'] === 'confirmed' && !$isPast): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-activate">
                            <i class="fas fa-play"></i> Activate
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this booking?')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                <?php elseif ($booking['booking_status'] === 'active'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-complete" onclick="return confirm('Complete this booking?')">
                            <i class="fas fa-check"></i> Complete
                        </button>
                    </form>
                <?php elseif ($booking['booking_status'] === 'confirmed' && !$isPast): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this booking?')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if (in_array($booking['booking_status'], ['confirmed', 'active', 'completed', 'cancelled'])): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                        <button type="submit" class="btn-delete" onclick="return confirm('Permanently delete this booking? This action cannot be undone.')">
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

<?php renderFooter(); ?>
