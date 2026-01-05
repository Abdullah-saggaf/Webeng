<?php
/**
 * Parking Session Management
 * Module 3 - MyParking System
 * Supports both authenticated session and QR token access
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../database/db_config.php';
require_once __DIR__ . '/../module01/auth.php'; // Always load for constants

$db = getDB();
$message = '';
$messageType = '';

// Get booking ID and token from URL
$bookingId = $_GET['booking_id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$bookingId) {
    header("Location: " . APP_BASE_PATH . "/module03/student/my_bookings.php");
    exit();
}

// Token-based authentication (for QR code access)
if ($token) {
    // Verify token matches booking
    $stmt = $db->prepare("SELECT booking_ID FROM Booking WHERE booking_ID = ? AND qr_code_value = ?");
    $stmt->execute([$bookingId, $token]);
    $validToken = $stmt->fetch();
    
    if (!$validToken) {
        die("Invalid or expired QR code. Please scan a valid QR code.");
    }
    
    // Token is valid - no session required
    $userId = null; // Will be retrieved from booking
} else {
    // Session-based authentication (for logged-in students)
    requireRole(['student']);
    $userId = $_SESSION['user_id'];
}

// Get booking details with student and vehicle information
$query = "
    SELECT 
        b.*,
        ps.space_number,
        pl.parkingLot_name,
        pl.parkingLot_type,
        v.license_plate,
        v.vehicle_model,
        v.user_ID,
        u.username as student_name
    FROM Booking b
    JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    JOIN User u ON v.user_ID = u.user_ID
    WHERE b.booking_ID = ?
";

if ($userId) {
    // If logged in, verify user owns this booking
    $query .= " AND v.user_ID = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$bookingId, $userId]);
} else {
    // Token access - no user verification needed
    $stmt = $db->prepare($query);
    $stmt->execute([$bookingId]);
}

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
            // Calculate end time based on duration type
            $actualEndTime = '';
            $startTime = date('Y-m-d H:i:s');
            
            if ($durationType === '30min') {
                $actualEndTime = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            } elseif ($durationType === '1hour') {
                $actualEndTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
            } elseif ($durationType === '2hours') {
                $actualEndTime = date('Y-m-d H:i:s', strtotime('+2 hours'));
            } elseif ($durationType === 'specific' && $endTime) {
                // Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL format
                $actualEndTime = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $endTime)));
            } else {
                throw new Exception('Please select a valid duration');
            }
            
            // Validate that end time is in the future
            if (empty($actualEndTime) || strtotime($actualEndTime) <= strtotime($startTime)) {
                throw new Exception('Invalid end time calculated. Please try again.');
            }
            
            // Update booking status to active with all session details
            $updateStmt = $db->prepare("
                UPDATE Booking 
                SET booking_status = 'active',
                    actual_start_time = ?,
                    actual_end_time = ?,
                    session_started_at = ?
                WHERE booking_ID = ?
            ");
            $updateStmt->execute([
                $startTime, 
                $actualEndTime, 
                $startTime,
                $bookingId
            ]);
            
            if ($updateStmt->rowCount() > 0) {
                $message = 'Parking session confirmed successfully!';
                $messageType = 'success';
                
                // Refresh booking data
                if ($userId) {
                    $query = "
                        SELECT 
                            b.*,
                            ps.space_number,
                            pl.parkingLot_name,
                            pl.parkingLot_type,
                            v.license_plate,
                            v.vehicle_model,
                            v.user_ID,
                            u.username as student_name
                        FROM Booking b
                        JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
                        JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
                        JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                        JOIN User u ON v.user_ID = u.user_ID
                        WHERE b.booking_ID = ? AND v.user_ID = ?
                    ";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bookingId, $userId]);
                } else {
                    $query = "
                        SELECT 
                            b.*,
                            ps.space_number,
                            pl.parkingLot_name,
                            pl.parkingLot_type,
                            v.license_plate,
                            v.vehicle_model,
                            v.user_ID,
                            u.username as student_name
                        FROM Booking b
                        JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
                        JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
                        JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                        JOIN User u ON v.user_ID = u.user_ID
                        WHERE b.booking_ID = ?
                    ";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$bookingId]);
                }
                $booking = $stmt->fetch();
            } else {
                throw new Exception('Failed to update booking status');
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Include layout if user is logged in, otherwise use minimal header
if ($token) {
    // Minimal header for QR code access (no navigation)
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Parking Session - MyParking System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="<?php echo QR_BASE_URL; ?>/module03/parking_session.css?v=<?php echo time(); ?>">
        <style>
            * { box-sizing: border-box; }
            body { background: #f3f4f6; margin: 0; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        </style>
    </head>
    <body>
    <?php
} else {
    // Full layout for logged-in users
    require_once __DIR__ . '/../layout.php';
    renderHeader('Parking Session');
    ?>
    <link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module03/parking_session.css?v=<?php echo time(); ?>">
    <?php
}
?>

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
                            <input type="radio" name="duration_type" value="specific" required>
                            <span class="option-content">
                                <i class="fas fa-calendar-alt"></i>
                                <strong>Specific End Time</strong>
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group specific-time-group" id="specificTimeGroup" style="display: none;">
                    <label>Select End Date and Time</label>
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
            <?php if ($token): ?>
                <button onclick="window.close()" class="btn-back">
                    <i class="fas fa-times"></i> Close
                </button>
            <?php else: ?>
                <a href="<?php echo APP_BASE_PATH; ?>/module03/student/my_bookings.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to My Bookings
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const durationRadios = document.querySelectorAll('input[name="duration_type"]');
    const specificTimeGroup = document.getElementById('specificTimeGroup');
    
    durationRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'specific') {
                specificTimeGroup.style.display = 'block';
            } else {
                specificTimeGroup.style.display = 'none';
            }
        });
    });
});
</script>

<?php 
if ($token) {
    // Close minimal HTML for QR access
    echo '</body></html>';
} else {
    // Full footer for logged-in users
    renderFooter();
}
?>
