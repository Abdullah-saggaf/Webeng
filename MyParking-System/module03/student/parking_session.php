<?php
/**
 * Parking Session Management
 * Handles QR code scanning, vehicle selection, and parking session start/end
 */

require_once __DIR__ . '/../../database/db_config.php';
require_once __DIR__ . '/../../module01/auth.php';

// Only students can access
requireRole(['student']);

$user = currentUser();
$userId = $user['user_id'];
$db = getDB();

// Get booking ID from QR code scan
$bookingId = $_GET['booking_id'] ?? null;

if (!$bookingId) {
    echo "<h3>Debug Information:</h3>";
    echo "<pre>";
    echo "GET parameters: ";
    print_r($_GET);
    echo "\nFull URL: " . $_SERVER['REQUEST_URI'] ?? 'not set';
    echo "\nExpected parameter: booking_id";
    echo "</pre>";
    die("Invalid booking ID - no booking_id parameter found in URL");
}

// Get booking details
$stmt = $db->prepare("
    SELECT b.*, 
           ps.space_number,
           pl.lot_name,
           pl.location as lot_location,
           v.license_plate
    FROM Booking b
    JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
    JOIN ParkingLot pl ON ps.lot_ID = pl.lot_ID
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    WHERE b.booking_ID = ? AND b.user_ID = ?
");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    die("Booking not found or access denied");
}

// Check if booking is valid for session
if (!in_array($booking['booking_status'], ['confirmed', 'active'])) {
    die("This booking cannot be used for parking (Status: {$booking['booking_status']})");
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start_session') {
        // Start parking session
        $vehicleId = $_POST['vehicle_id'] ?? null;
        $plateNumber = $_POST['plate_number'] ?? null;
        $startTime = $_POST['start_time'] ?? null;
        
        if (!$vehicleId || !$plateNumber || !$startTime) {
            $message = "All fields are required";
            $messageType = "error";
        } else {
            try {
                $db->beginTransaction();
                
                // Update booking with actual session data
                $updateStmt = $db->prepare("
                    UPDATE Booking 
                    SET booking_status = 'active',
                        actual_start_time = ?,
                        session_started_at = NOW()
                    WHERE booking_ID = ?
                ");
                $updateStmt->execute([$startTime, $bookingId]);
                
                // Create parking log entry
                $logStmt = $db->prepare("
                    INSERT INTO ParkingLog (user_ID, vehicle_ID, space_ID, check_in_time, booking_ID)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $logStmt->execute([$userId, $vehicleId, $booking['space_ID'], $startTime, $bookingId]);
                
                $db->commit();
                
                $message = "Parking session started successfully!";
                $messageType = "success";
                
                // Refresh booking data
                $stmt->execute([$bookingId, $userId]);
                $booking = $stmt->fetch();
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error starting session: " . $e->getMessage();
                $messageType = "error";
            }
        }
        
    } elseif ($action === 'end_session') {
        // End parking session
        $endTime = $_POST['end_time'] ?? null;
        
        if (!$endTime) {
            $message = "End time is required";
            $messageType = "error";
        } else {
            try {
                $db->beginTransaction();
                
                // Update booking
                $updateStmt = $db->prepare("
                    UPDATE Booking 
                    SET booking_status = 'completed',
                        actual_end_time = ?,
                        session_ended_at = NOW()
                    WHERE booking_ID = ?
                ");
                $updateStmt->execute([$endTime, $bookingId]);
                
                // Update parking log
                $logStmt = $db->prepare("
                    UPDATE ParkingLog 
                    SET check_out_time = ?
                    WHERE booking_ID = ? AND check_out_time IS NULL
                    ORDER BY log_ID DESC LIMIT 1
                ");
                $logStmt->execute([$endTime, $bookingId]);
                
                $db->commit();
                
                $message = "Parking session ended successfully!";
                $messageType = "success";
                
                // Refresh booking data
                $stmt->execute([$bookingId, $userId]);
                $booking = $stmt->fetch();
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error ending session: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// Get user's vehicles for dropdown
$vehicleStmt = $db->prepare("SELECT * FROM Vehicle WHERE user_ID = ? AND approved = 1");
$vehicleStmt->execute([$userId]);
$vehicles = $vehicleStmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Session - MyParking</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .booking-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-confirmed {
            background: #ffc107;
            color: #000;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-completed {
            background: #6c757d;
            color: white;
        }
        
        .form-section {
            margin: 30px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        select, input[type="text"], input[type="datetime-local"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-start {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-start:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-end {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-end:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 87, 108, 0.4);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            margin-top: 20px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .session-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #2196F3;
        }
        
        .session-info p {
            margin: 5px 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚗 Parking Session</h1>
        
        <?php if ($message): ?>
            <div class="message message-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="booking-info">
            <div class="info-row">
                <span class="info-label">Booking ID:</span>
                <span class="info-value">#<?php echo $booking['booking_ID']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Parking Space:</span>
                <span class="info-value"><?php echo htmlspecialchars($booking['lot_name']); ?> - Space <?php echo $booking['space_number']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Location:</span>
                <span class="info-value"><?php echo htmlspecialchars($booking['lot_location']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Booked Time:</span>
                <span class="info-value">
                    <?php echo date('M j, g:i A', strtotime($booking['start_time'])); ?> - 
                    <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                    <?php echo ucfirst($booking['booking_status']); ?>
                </span>
            </div>
        </div>
        
        <?php if ($booking['booking_status'] === 'confirmed'): ?>
            <!-- Start Session Form -->
            <div class="form-section">
                <h2 style="margin-bottom: 20px; color: #333;">Start Your Parking Session</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="start_session">
                    
                    <div class="form-group">
                        <label for="vehicle_id">Select Vehicle</label>
                        <select name="vehicle_id" id="vehicle_id" required onchange="updatePlateNumber()">
                            <option value="">Choose a vehicle...</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['vehicle_ID']; ?>" 
                                        data-plate="<?php echo htmlspecialchars($vehicle['plate_number']); ?>">
                                    <?php echo htmlspecialchars($vehicle['vehicle_model']); ?> - 
                                    <?php echo htmlspecialchars($vehicle['plate_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="plate_number">Plate Number</label>
                        <input type="text" 
                               name="plate_number" 
                               id="plate_number" 
                               placeholder="Will auto-fill from vehicle selection" 
                               required 
                               readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Actual Start Time</label>
                        <input type="datetime-local" 
                               name="start_time" 
                               id="start_time" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" 
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-start">🚀 Start Parking Session</button>
                </form>
            </div>
            
        <?php elseif ($booking['booking_status'] === 'active'): ?>
            <!-- Active Session Info -->
            <div class="session-info">
                <h3 style="margin-bottom: 10px; color: #2196F3;">✅ Session Active</h3>
                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['license_plate']); ?></p>
                <p><strong>Started At:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['actual_start_time'])); ?></p>
                <p><strong>Session Time:</strong> <?php echo date('g:i A', strtotime($booking['session_started_at'])); ?></p>
            </div>
            
            <!-- End Session Form -->
            <div class="form-section">
                <h2 style="margin-bottom: 20px; color: #333;">End Your Parking Session</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="end_session">
                    
                    <div class="form-group">
                        <label for="end_time">Actual End Time</label>
                        <input type="datetime-local" 
                               name="end_time" 
                               id="end_time" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" 
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-end">🏁 End Parking Session</button>
                </form>
            </div>
            
        <?php else: ?>
            <!-- Completed -->
            <div class="session-info" style="background: #f0f0f0; border-left-color: #6c757d;">
                <h3 style="margin-bottom: 10px; color: #666;">✓ Session Completed</h3>
                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['license_plate']); ?></p>
                <p><strong>Start:</strong> <?php echo date('M j, g:i A', strtotime($booking['actual_start_time'])); ?></p>
                <p><strong>End:</strong> <?php echo date('M j, g:i A', strtotime($booking['actual_end_time'])); ?></p>
            </div>
        <?php endif; ?>
        
        <a href="<?php echo APP_BASE_PATH; ?>/module03/student/my_bookings.php" class="btn btn-back">
            ← Back to My Bookings
        </a>
    </div>
    
    <script>
        function updatePlateNumber() {
            const select = document.getElementById('vehicle_id');
            const plateInput = document.getElementById('plate_number');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                plateInput.value = selectedOption.dataset.plate;
            } else {
                plateInput.value = '';
            }
        }
    </script>
</body>
</html>
