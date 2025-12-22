<?php
/**
 * Space Info View (after QR scan)
 * Public page - no login required
 */

require_once __DIR__ . '/../database/db_config.php';

$spaceId = (int)($_GET['space_id'] ?? 0);

if ($spaceId < 1) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Invalid Space ID</h2>
        </div>');
}

$db = getDB();

// Get space details
$stmt = $db->prepare("
    SELECT ps.*, pl.parkingLot_name, pl.parkingLot_type, pl.is_booking_lot
    FROM ParkingSpace ps 
    JOIN ParkingLot pl ON ps.parkingLot_ID=pl.parkingLot_ID 
    WHERE ps.space_ID=?
");
$stmt->execute([$spaceId]);
$space = $stmt->fetch();

if (!$space) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Space Not Found</h2>
        </div>');
}

// Check current availability
$stmt = $db->prepare("
    SELECT b.booking_ID, b.booking_status, b.start_time, b.end_time, 
           u.username
    FROM Booking b
    JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
    JOIN User u ON v.user_ID = u.user_ID
    WHERE b.space_ID = ? 
      AND b.booking_date = CURDATE()
      AND b.booking_status IN ('confirmed', 'active')
      AND CURTIME() BETWEEN b.start_time AND b.end_time
    LIMIT 1
");
$stmt->execute([$spaceId]);
$currentBooking = $stmt->fetch();

$isAvailable = !$currentBooking;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Space Info - <?php echo htmlspecialchars($space['space_number']); ?></title>
    <link rel="stylesheet" href="space_qr_view.css">
</head>
<body>
    <div class="container">
        <div class="info-card">
            <div class="header">
                <div class="space-number">🅿️ <?php echo htmlspecialchars($space['space_number']); ?></div>
                <div class="area-name"><?php echo htmlspecialchars($space['parkingLot_name']); ?></div>
                <div class="area-type"><?php echo htmlspecialchars($space['parkingLot_type']); ?></div>
            </div>
            
            <div class="status-badge <?php echo $isAvailable ? 'status-available' : 'status-occupied'; ?>">
                <?php echo $isAvailable ? '✅ AVAILABLE' : '🚫 OCCUPIED'; ?>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Space ID</div>
                    <div class="info-value">#<?php echo $space['space_ID']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">QR Code</div>
                    <div class="info-value" style="font-family: monospace; font-size: 13px; word-break: break-all;">
                        <?php echo htmlspecialchars($space['qr_code_value']); ?>
                    </div>
                </div>
                
                <?php if (!$isAvailable && $currentBooking): ?>
                <div class="info-item booking-details">
                    <div class="info-label">⚠️ Current Booking</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($currentBooking['username']); ?>
                        <div style="font-size: 13px; color: #92400e; margin-top: 5px;">
                            <?php echo date('g:i A', strtotime($currentBooking['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($currentBooking['end_time'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <div class="info-label">Booking Type</div>
                    <div class="info-value">
                        <?php echo $space['is_booking_lot'] ? '📅 Bookable' : '🚗 First-Come, First-Served'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value">
                        <?php echo date('F d, Y', strtotime($space['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="timestamp">
            ⏰ Last updated: <?php echo date('M d, Y g:i A'); ?>
        </div>
    </div>
    
    <script>
    // Auto-refresh every 30 seconds
    setTimeout(function() {
        location.reload();
    }, 30000);
    </script>
</body>
</html>
