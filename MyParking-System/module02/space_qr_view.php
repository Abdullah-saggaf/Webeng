<?php
/**
 * Parking Space Information Page
 * Shows detailed info after QR scan or button click
 */

// Get space ID from URL
$spaceId = isset($_GET['space_id']) ? (int)$_GET['space_id'] : 0;

// Validate space ID
if ($spaceId < 1) {
    die('
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Space</title></head>
    <body style="font-family: Arial; padding: 20px; text-align: center;">
    <h2 style="color: #ef4444;">❌ Invalid Space ID</h2>
    <p>Please scan a valid QR code.</p>
    </body></html>');
}

// Database connection
try {
    require_once __DIR__ . '/../database/db_config.php';
    $db = getDB();
    
    // Get space details
    $stmt = $db->prepare("
        SELECT ps.*, pl.parkingLot_name, pl.parkingLot_type, pl.is_booking_lot
        FROM ParkingSpace ps 
        JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID 
        WHERE ps.space_ID = ?
    ");
    $stmt->execute([$spaceId]);
    $space = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$space) {
        die('
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Space Not Found</title></head>
        <body style="font-family: Arial; padding: 20px; text-align: center;">
        <h2 style="color: #ef4444;">❌ Space Not Found</h2>
        <p>This parking space does not exist.</p>
        </body></html>');
    }
    
    // Check current availability
    $isLocked = !$space['is_booking_lot'];
    
    $stmt = $db->prepare("
        SELECT b.booking_ID, b.booking_status, b.start_time, b.end_time, 
               u.username, b.booking_date
        FROM Booking b
        JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
        JOIN User u ON v.user_ID = u.user_ID
        WHERE b.space_ID = ? 
          AND b.booking_date >= CURDATE()
          AND b.booking_status IN ('confirmed', 'active')
        ORDER BY b.booking_date ASC, b.start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$spaceId]);
    $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $isAvailable = !$currentBooking && !$isLocked;
    
} catch (Exception $e) {
    die('
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title></head>
    <body style="font-family: Arial; padding: 20px; text-align: center;">
    <h2 style="color: #ef4444;">❌ Error</h2>
    <p>Unable to load space information.</p>
    <p style="color: #666; font-size: 12px;">' . htmlspecialchars($e->getMessage()) . '</p>
    </body></html>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($space['space_number']); ?> - Space Info</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #1f2937;
        }
        
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .space-number {
            font-size: 42px;
            font-weight: 800;
            color: #4f46e5;
            margin-bottom: 8px;
        }
        
        .location {
            font-size: 18px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
        }
        
        .status.available {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status.occupied {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .info-section {
            margin-top: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        
        .info-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 500;
        }
        
        .booking-alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        
        .booking-alert .time {
            font-size: 14px;
            color: #92400e;
            margin-top: 8px;
        }
        
        .footer {
            text-align: center;
            color: white;
            font-size: 14px;
            margin-top: 15px;
            opacity: 0.9;
        }
        
        .refresh-notice {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            margin-top: 20px;
            font-style: italic;
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .card {
                padding: 20px;
            }
            
            .space-number {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="space-number">🅿️ <?php echo htmlspecialchars($space['space_number']); ?></div>
                <div class="location"><?php echo htmlspecialchars($space['parkingLot_name']); ?></div>
                <div class="badge"><?php echo htmlspecialchars($space['parkingLot_type']); ?></div>
            </div>
            
            <div class="status <?php echo $isAvailable ? 'available' : 'occupied'; ?>">
                <?php if ($isLocked): ?>
                    🔒 LOCKED - AREA CLOSED FOR EVENT
                <?php elseif ($isAvailable): ?>
                    ✅ AVAILABLE
                <?php else: ?>
                    🚫 OCCUPIED
                <?php endif; ?>
            </div>
            
            <div class="info-section">
                <?php if ($isLocked): ?>
                <div class="booking-alert">
                    <div class="info-label">🔒 Area Locked</div>
                    <div class="info-value">This parking area is temporarily closed for an event or maintenance.</div>
                </div>
                <?php elseif (!$isAvailable && $currentBooking): ?>
                <div class="booking-alert">
                    <div class="info-label">⚠️ Current Booking</div>
                    <div class="info-value"><?php echo htmlspecialchars($currentBooking['username']); ?></div>
                    <div class="time">
                        <?php 
                        echo date('g:i A', strtotime($currentBooking['start_time'])); 
                        echo ' - '; 
                        echo date('g:i A', strtotime($currentBooking['end_time'])); 
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <div class="info-label">Space ID</div>
                    <div class="info-value">#<?php echo $space['space_ID']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Parking Type</div>
                    <div class="info-value">
                        <?php echo $space['is_booking_lot'] ? '📅 Bookable' : '🚗 First-Come First-Served'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">QR Code</div>
                    <div class="info-value" style="font-family: monospace; font-size: 11px; word-break: break-all;">
                        <?php echo htmlspecialchars($space['qr_code_value']); ?>
                    </div>
                </div>
                
                <div class="refresh-notice">
                    🔄 Page auto-refreshes every 30 seconds
                </div>
            </div>
        </div>
        
        <div class="footer">
            ⏰ Last updated: <?php echo date('M d, Y g:i A'); ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>
