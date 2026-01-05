<?php
/**
 * Parking Session Management
 * Module 3 - MyParking System
 */

require_once __DIR__ . '/../module01/auth.php';
require_once __DIR__ . '/../database/db_config.php';

// Require Student role
requireRole(['student']);

$db = getDB();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get booking ID from URL
$bookingId = $_GET['booking_id'] ?? null;

if (!$bookingId) {
    header("Location: " . APP_BASE_PATH . "/module03/student/my_bookings.php");
    exit();
}

// Get booking details with student and vehicle information
$stmt = $db->prepare("
    SELECT 
        b.*,
        ps.space_number,
        pl.parkingLot_name,
        pl.parkingLot_type,
        v.license_plate,
        v.vehicle_model,
        u.username as student_name
    FROM Booking b
    JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    JOIN User u ON v.user_ID = u.user_ID
    WHERE b.booking_ID = ? AND v.user_ID = ?
");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found or access denied");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'confirm_parking') {
        $durationType = $_POST['duration_type'] ?? '';
        $customDuration = $_POST['custom_duration'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        
        try {
            $db->beginTransaction();
            
            // Calculate end time based on duration type
            $actualEndTime = '';
            $startTime = date('Y-m-d H:i:s');
            
            if ($durationType === '30min') {
                $actualEndTime = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            } elseif ($durationType === '1hour') {
                $actualEndTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
            } elseif ($durationType === '2hours') {
                $actualEndTime = date('Y-m-d H:i:s', strtotime('+2 hours'));
            } elseif ($durationType === 'custom' && $customDuration) {
                $actualEndTime = date('Y-m-d H:i:s', strtotime("+{$customDuration} minutes"));
            } elseif ($durationType === 'specific' && $endTime) {
                $actualEndTime = $endTime;
            } else {
                throw new Exception('Please select a valid duration');
            }
            
            // Update booking status to active
            $updateStmt = $db->prepare("
                UPDATE Booking 
                SET booking_status = 'active',
                    actual_start_time = ?,
                    actual_end_time = ?,
                    session_started_at = NOW()
                WHERE booking_ID = ?
            ");
            $updateStmt->execute([$startTime, $actualEndTime, $bookingId]);
            
            $db->commit();
            
            $message = 'Parking session confirmed successfully!';
            $messageType = 'success';
            
            // Refresh booking data
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

require_once __DIR__ . '/../layout.php';
renderHeader('Parking Session');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module03/parking_session.css?v=<?php echo time(); ?>">

<div class="parking-session-container">
    <div class="session-card">
        <h2><i class="fas fa-parking"></i> Parking Session</h2>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Booking Details Section -->
        <div class="booking-details-section">
            <h3>Booking Details</h3>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <label><i class="fas fa-user"></i> Student Name</label>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['student_name']); ?></div>
                </div>
                
                <div class="detail-item">
                    <label><i class="fas fa-car"></i> Vehicle Plate Number</label>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['license_plate']); ?></div>
                </div>
                
                <div class="detail-item">
                    <label><i class="fas fa-map-marker-alt"></i> Parking Area</label>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['parkingLot_name']); ?></div>
                </div>
                
                <div class="detail-item">
                    <label><i class="fas fa-parking"></i> Space Number</label>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['space_number']); ?></div>
                </div>
                
                <div class="detail-item">
                    <label><i class="fas fa-calendar"></i> Booking Date</label>
                    <div class="detail-value"><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></div>
                </div>
                
                <div class="detail-item">
                    <label><i class="fas fa-info-circle"></i> Booking Status</label>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo strtolower($booking['booking_status']); ?>">
                            <?php echo ucfirst($booking['booking_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($booking['booking_status'] === 'confirmed'): ?>
        <!-- Parking Confirmation Form -->
        <div class="confirmation-section">
            <h3>Confirm Your Parking</h3>
            <p class="section-description">Select your expected parking duration to start your session</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="confirm_parking">
                
                <div class="form-group">
                    <label>Expected Duration <span class="required">*</span></label>
                    
                    <div class="duration-options">
                        <label class="duration-option">
                            <input type="radio" name="duration_type" value="30min" required>
                            <span class="option-content">
                                <i class="fas fa-clock"></i>
                                <strong>30 Minutes</strong>
                            </span>
                        </label>
                        
                        <label class="duration-option">
                            <input type="radio" name="duration_type" value="1hour" required>
                            <span class="option-content">
                                <i class="fas fa-clock"></i>
                                <strong>1 Hour</strong>
                            </span>
                        </label>
                        
                        <label class="duration-option">
                            <input type="radio" name="duration_type" value="2hours" required>
                            <span class="option-content">
                                <i class="fas fa-clock"></i>
                                <strong>2 Hours</strong>
                            </span>
                        </label>
                        
                        <label class="duration-option">
                            <input type="radio" name="duration_type" value="custom" required>
                            <span class="option-content">
                                <i class="fas fa-edit"></i>
                                <strong>Custom Duration</strong>
                            </span>
                        </label>
                        
                        <label class="duration-option">
                            <input type="radio" name="duration_type" value="specific" required>
                            <span class="option-content">
                                <i class="fas fa-calendar-alt"></i>
                                <strong>Specific End Time</strong>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group custom-duration-group" id="customDurationGroup" style="display: none;">
                    <label>Enter Duration (minutes)</label>
                    <input type="number" name="custom_duration" class="form-control" placeholder="e.g., 45" min="1" max="480">
                </div>
                
                <div class="form-group specific-time-group" id="specificTimeGroup" style="display: none;">
                    <label>Select End Time</label>
                    <input type="datetime-local" name="end_time" class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                
                <button type="submit" class="btn-confirm">
                    <i class="fas fa-check-circle"></i> Confirm Parking
                </button>
            </form>
        </div>
        
        <?php elseif ($booking['booking_status'] === 'active'): ?>
        <!-- Active Session Info -->
        <div class="active-session-info">
            <div class="active-badge">
                <i class="fas fa-circle"></i> Session Active
            </div>
            <div class="session-times">
                <p><strong>Started:</strong> <?php echo date('M d, Y g:i A', strtotime($booking['actual_start_time'])); ?></p>
                <p><strong>Expected End:</strong> <?php echo date('M d, Y g:i A', strtotime($booking['actual_end_time'])); ?></p>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Completed/Cancelled Session -->
        <div class="session-completed">
            <i class="fas fa-check-circle"></i>
            <p>This parking session has been <?php echo strtolower($booking['booking_status']); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="<?php echo APP_BASE_PATH; ?>/module03/student/my_bookings.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to My Bookings
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const durationRadios = document.querySelectorAll('input[name="duration_type"]');
    const customDurationGroup = document.getElementById('customDurationGroup');
    const specificTimeGroup = document.getElementById('specificTimeGroup');
    
    durationRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDurationGroup.style.display = 'block';
                specificTimeGroup.style.display = 'none';
            } else if (this.value === 'specific') {
                specificTimeGroup.style.display = 'block';
                customDurationGroup.style.display = 'none';
            } else {
                customDurationGroup.style.display = 'none';
                specificTimeGroup.style.display = 'none';
            }
        });
    });
});
</script>

<?php renderFooter(); ?>
