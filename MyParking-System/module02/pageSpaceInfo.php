<?php
/**
 * Parking Space Information Page
 * Public page - Shows space details after QR scan or button click
 * Works with auto-detected base URL - accessible via localhost or IP
 */

// Get space ID from URL
$spaceId = isset($_GET['space_id']) ? (int)$_GET['space_id'] : 0;

// Validate space ID
if ($spaceId < 1) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Invalid Space</title></head><body style="font-family: Arial; padding: 20px; text-align: center;"><h2 style="color: #ef4444;">❌ Invalid Space ID</h2><p>Please scan a valid QR code.</p></body></html>');
}

/**
 * Detect which status column exists in parkingspace table
 */
function detectStatusColumn($db): array {
    try {
        // Get all columns from parkingspace table
        $stmt = $db->query("SHOW COLUMNS FROM parkingspace");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_map(function($col) {
            return strtolower($col['Field']);
        }, $columns);
        
        // Priority order for status column detection
        $candidates = [
            'status',
            'availability_status',
            'is_available',
            'is_occupied',
            'space_status',
            'status_id'
        ];
        
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columnNames)) {
                // Found the column, determine its type
                $colInfo = array_filter($columns, function($col) use ($candidate) {
                    return strtolower($col['Field']) === $candidate;
                });
                $colInfo = reset($colInfo);
                
                return [
                    'column' => $candidate,
                    'type' => $colInfo['Type'] ?? 'unknown'
                ];
            }
        }
        
        // No status column found, return null
        return ['column' => null, 'type' => null];
        
    } catch (Exception $e) {
        error_log("Status column detection error: " . $e->getMessage());
        return ['column' => null, 'type' => null];
    }
}

/**
 * Convert raw status value to display text
 */
function mapStatusToDisplay($rawStatus, $columnName, $columnType): array {
    $columnName = strtolower($columnName);
    
    // Handle is_available (boolean: 1 = Available, 0 = Not Available/Occupied)
    if ($columnName === 'is_available') {
        if ($rawStatus == 1 || $rawStatus === 'true' || $rawStatus === true) {
            return ['status' => 'available', 'text' => 'AVAILABLE', 'icon' => '<i class="fas fa-check-circle"></i>'];
        } else {
            return ['status' => 'occupied', 'text' => 'OCCUPIED', 'icon' => '<i class="fas fa-car"></i>'];
        }
    }
    
    // Handle is_occupied (boolean: 1 = Occupied, 0 = Available)
    if ($columnName === 'is_occupied') {
        if ($rawStatus == 1 || $rawStatus === 'true' || $rawStatus === true) {
            return ['status' => 'occupied', 'text' => 'OCCUPIED', 'icon' => '<i class="fas fa-car"></i>'];
        } else {
            return ['status' => 'available', 'text' => 'AVAILABLE', 'icon' => '<i class="fas fa-check-circle"></i>'];
        }
    }
    
    // Handle text-based status columns
    $rawStatusLower = strtolower(trim($rawStatus ?? ''));
    
    if (in_array($rawStatusLower, ['available', 'free', 'open', 'vacant'])) {
        return ['status' => 'available', 'text' => 'AVAILABLE', 'icon' => '<i class="fas fa-check-circle"></i>'];
    }
    
    if (in_array($rawStatusLower, ['occupied', 'taken', 'reserved', 'booked', 'in use'])) {
        return ['status' => 'occupied', 'text' => 'OCCUPIED', 'icon' => '<i class="fas fa-car"></i>'];
    }
    
    if (in_array($rawStatusLower, ['closed', 'maintenance', 'unavailable', 'disabled', 'out of service'])) {
        return ['status' => 'closed', 'text' => 'CLOSED', 'icon' => '<i class="fas fa-ban"></i>'];
    }
    
    // Default: assume available if we can't determine
    return ['status' => 'available', 'text' => 'AVAILABLE', 'icon' => '<i class="fas fa-check-circle"></i>'];
}

// Database connection
try {
    require_once __DIR__ . '/../database/db_config.php';
    $db = getDB();
    
    // Detect status column
    $statusInfo = detectStatusColumn($db);
    $statusCol = $statusInfo['column'];
    
    // Build query with detected status column (or without if not found)
    if ($statusCol) {
        $query = "
            SELECT ps.space_ID, ps.space_number, ps.{$statusCol} AS raw_status,
                   pl.parkingLot_name, pl.parkingLot_type, pl.is_booking_lot
            FROM ParkingSpace ps 
            JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID 
            WHERE ps.space_ID = ?
        ";
    } else {
        // No status column found, query without it
        $query = "
            SELECT ps.space_ID, ps.space_number,
                   pl.parkingLot_name, pl.parkingLot_type, pl.is_booking_lot
            FROM ParkingSpace ps 
            JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID 
            WHERE ps.space_ID = ?
        ";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute([$spaceId]);
    $space = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$space) {
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Space Not Found</title></head><body style="font-family: Arial; padding: 20px; text-align: center;"><h2 style="color: #ef4444;">❌ Space Not Found</h2><p>This parking space does not exist.</p></body></html>');
    }
    
    // Check current booking status
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
    $currentBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determine final display status
    if ($statusCol && isset($space['raw_status'])) {
        // Use database status
        $statusDisplay = mapStatusToDisplay($space['raw_status'], $statusCol, $statusInfo['type']);
        
        // Override with booking if occupied
        if ($currentBooking) {
            $statusDisplay = ['status' => 'occupied', 'text' => 'OCCUPIED', 'icon' => '<i class="fas fa-car"></i>'];
        }
    } else {
        // No status column, determine from booking only
        if ($currentBooking) {
            $statusDisplay = ['status' => 'occupied', 'text' => 'OCCUPIED', 'icon' => '<i class="fas fa-car"></i>'];
        } else {
            $statusDisplay = ['status' => 'available', 'text' => 'AVAILABLE', 'icon' => '<i class="fas fa-check-circle"></i>'];
        }
    }
    
    $displayStatus = $statusDisplay['status'];
    $statusText = $statusDisplay['text'];
    $statusIcon = $statusDisplay['icon'];
    
} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("PageSpaceInfo Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Show friendly error to user (no technical details)
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title></head><body style="font-family: Arial; padding: 20px; text-align: center;"><h2 style="color: #ef4444;">❌ Error</h2><p>Unable to load space information. Please try again.</p><p style="color: #9ca3af; font-size: 12px; margin-top: 20px;">If this problem persists, please contact support.</p></body></html>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($space['space_number']); ?> - Parking Space Info</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .space-icon {
            font-size: 50px;
            margin-bottom: 10px;
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
            margin-bottom: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .status.available {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 2px solid #10b981;
        }
        
        .status.occupied {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        
        .status.closed {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            color: #374151;
            border: 2px solid #6b7280;
        }
        
        .status-icon {
            font-size: 28px;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-section {
            margin-top: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 12px;
            transition: transform 0.2s ease;
        }
        
        .info-item:hover {
            transform: translateX(5px);
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
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        
        .booking-alert .time {
            font-size: 14px;
            color: #92400e;
            margin-top: 8px;
            font-weight: 600;
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
            margin-top: 15px;
            padding: 10px;
            background: #f3f4f6;
            border-radius: 8px;
            font-style: italic;
        }
        
        .back-button {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: white;
            color: #4f46e5;
            border: 2px solid #4f46e5;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: #4f46e5;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
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
            
            .status {
                font-size: 18px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="space-icon"><i class="fas fa-parking"></i></div>
                <div class="space-number"><?php echo htmlspecialchars($space['space_number']); ?></div>
                <div class="location"><?php echo htmlspecialchars($space['parkingLot_name']); ?></div>
                <div class="badge"><?php echo htmlspecialchars($space['parkingLot_type']); ?></div>
            </div>
            
            <div class="status <?php echo $displayStatus; ?>">
                <span class="status-icon"><?php echo $statusIcon; ?></span>
                <?php echo $statusText; ?>
            </div>
            
            <div class="info-section">
                <?php if ($displayStatus === 'occupied' && $currentBooking): ?>
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
                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Space ID</div>
                    <div class="info-value">#<?php echo $space['space_ID']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-ticket-alt"></i> Parking Type</div>
                    <div class="info-value">
                        <?php echo $space['is_booking_lot'] ? '<i class="fas fa-calendar-check"></i> Reservation Required' : '<i class="fas fa-car"></i> First-Come First-Served'; ?>
                    </div>
                </div>
                
                <?php if (isset($space['qr_code_value']) && !empty($space['qr_code_value'])): ?>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-tag"></i> QR Code ID</div>
                    <div class="info-value" style="font-family: monospace; font-size: 11px; word-break: break-all; color: #6b7280;">
                        <?php echo htmlspecialchars($space['qr_code_value']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="refresh-notice">
                    🔄 Page refreshes every 30 seconds for latest status
                </div>
            </div>
            
            <a href="admin/manage_parking_spaces.php" class="back-button">
                ← Go Back
            </a>
        </div>
        
        <div class="footer">
            <i class="far fa-clock"></i> Updated: <?php echo date('M d, Y g:i A'); ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds to show latest availability
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // Add smooth scrolling
        window.addEventListener('load', function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    </script>
</body>
</html>