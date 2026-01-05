<?php
/**
 * Parking Booking - Student View
 * Module 3 - MyParking System
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require Student role
requireRole(['student']);

$db = getDB();
$message = '';
$messageType = '';

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    $spaceId = (int)($_POST['space_id'] ?? 0);
    $bookingDate = $_POST['booking_date'] ?? date('Y-m-d');
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    try {
        // Validate selected vehicle belongs to user and is approved
        $stmt = $db->prepare("SELECT vehicle_ID FROM Vehicle WHERE vehicle_ID = ? AND user_ID = ? AND grant_status = 'approved'");
        $stmt->execute([$vehicleId, $userId]);
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) {
            throw new Exception('Please select a valid approved vehicle.');
        }
        
        // Check if space is already booked for this date
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt 
            FROM Booking 
            WHERE space_ID = ? 
            AND booking_date = ? 
            AND booking_status IN ('confirmed', 'active')
        ");
        $stmt->execute([$spaceId, $bookingDate]);
        $existing = $stmt->fetch()['cnt'];
        
        if ($existing > 0) {
            throw new Exception('This space is already booked for the selected date.');
        }
        
        // Check if user already has a booking for this date
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt 
            FROM Booking b
            JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
            WHERE v.user_ID = ? 
            AND b.booking_date = ? 
            AND b.booking_status IN ('confirmed', 'active')
        ");
        $stmt->execute([$userId, $bookingDate]);
        $userBooking = $stmt->fetch()['cnt'];
        
        if ($userBooking > 0) {
            throw new Exception('You already have a booking for this date.');
        }
        
        // Get time selection from user
        if (empty($_POST['start_time']) || empty($_POST['end_time'])) {
            throw new Exception('Start time and end time are required.');
        }
        
        $startTime = $_POST['start_time'] . ':00';
        $endTime = $_POST['end_time'] . ':00';
        
        // Validate time range
        if (strtotime($startTime) >= strtotime($endTime)) {
            throw new Exception('End time must be after start time.');
        }
        
        // Generate unique QR code value for this booking
        $qrCodeValue = 'BK-' . uniqid() . '-' . bin2hex(random_bytes(8));
        
        // Create booking with confirmed status and QR code
        $stmt = $db->prepare("
            INSERT INTO Booking (vehicle_ID, space_ID, booking_date, start_time, end_time, booking_status, qr_code_value) 
            VALUES (?, ?, ?, ?, ?, 'confirmed', ?)
        ");
        $stmt->execute([$vehicleId, $spaceId, $bookingDate, $startTime, $endTime, $qrCodeValue]);
        
        $message = 'Booking confirmed successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get user's registered vehicles
$userId = $_SESSION['user_id'];
$vehiclesStmt = $db->prepare("SELECT vehicle_ID, vehicle_type, vehicle_model, license_plate, grant_status FROM Vehicle WHERE user_ID = ? ORDER BY grant_status DESC, created_at DESC");
$vehiclesStmt->execute([$userId]);
$userVehicles = $vehiclesStmt->fetchAll();

// Get filters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedArea = (int)($_GET['area_id'] ?? 0);

// Get all bookable areas (Student parking only)
$areas = $db->query("SELECT * FROM ParkingLot WHERE is_booking_lot = 1 AND parkingLot_type = 'Student' ORDER BY parkingLot_name")->fetchAll();

// Get available spaces
$whereClause = '';
$params = [':date' => $selectedDate];

if ($selectedArea) {
    $whereClause = 'AND pl.parkingLot_ID = :area_id';
    $params[':area_id'] = $selectedArea;
}

$stmt = $db->prepare("
    SELECT 
        ps.space_ID,
        ps.space_number,
        pl.parkingLot_name,
        pl.parkingLot_type,
        CASE 
            WHEN b.booking_ID IS NOT NULL THEN 'Occupied'
            ELSE 'Available'
        END as status
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    LEFT JOIN Booking b ON ps.space_ID = b.space_ID 
        AND b.booking_date = :date
        AND b.booking_status IN ('confirmed', 'active')
    WHERE pl.is_booking_lot = 1 AND pl.parkingLot_type = 'Student' $whereClause
    ORDER BY pl.parkingLot_name, ps.space_number
");
$stmt->execute($params);
$spaces = $stmt->fetchAll();

require_once __DIR__ . '/../../layout.php';
renderHeader('Parking Booking');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module03/student/parking_booking.css?v=<?php echo time(); ?>">

<div class="booking-container">
    <h2>Parking Booking</h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Date:</label>
                <input type="date" name="date" value="<?php echo $selectedDate; ?>" min="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="filter-group">
                <label>Parking Area:</label>
                <select name="area_id">
                    <option value="">All Bookable Areas</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['parkingLot_ID']; ?>" 
                            <?php echo $selectedArea == $area['parkingLot_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($area['parkingLot_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
    
    <!-- Available Spaces -->
    <div class="spaces-grid">
        <?php if (empty($spaces)): ?>
        <div class="no-data">
            <i class="fas fa-info-circle"></i>
            <p>No bookable parking spaces found for the selected criteria.</p>
        </div>
        <?php else: ?>
        <?php foreach ($spaces as $space): ?>
        <div class="space-card <?php echo strtolower($space['status']); ?>">
            <div class="space-header">
                <div class="space-number"><?php echo htmlspecialchars($space['space_number']); ?></div>
                <span class="status-badge <?php echo strtolower($space['status']); ?>">
                    <?php echo $space['status']; ?>
                </span>
            </div>
            <div class="space-details">
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($space['parkingLot_name']); ?></p>
                <p><i class="fas fa-tag"></i> <?php echo htmlspecialchars($space['parkingLot_type']); ?></p>
            </div>
            <?php if ($space['status'] === 'Available' && $space['parkingLot_type'] === 'Student'): ?>
            <button class="btn-book" onclick="openBookingModal(<?php echo $space['space_ID']; ?>, '<?php echo htmlspecialchars($space['space_number']); ?>', '<?php echo htmlspecialchars($space['parkingLot_name']); ?>')">
                <i class="fas fa-calendar-check"></i> Book Now
            </button>
            <?php elseif ($space['status'] === 'Available' && $space['parkingLot_type'] !== 'Student'): ?>
            <button class="btn-restricted" disabled>
                <i class="fas fa-ban"></i> Staff Only
            </button>
            <?php else: ?>
            <button class="btn-booked" disabled>
                <i class="fas fa-lock"></i> Occupied
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Booking Modal -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> Confirm Booking</h3>
            <button onclick="closeBookingModal()" class="close-btn">×</button>
        </div>
        <form method="POST" id="bookingForm">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="space_id" id="modal_space_id">
            <input type="hidden" name="booking_date" value="<?php echo $selectedDate; ?>">
            
            <div class="modal-body">
                <div class="booking-info">
                    <p><strong>Space:</strong> <span id="modal_space_number"></span></p>
                    <p><strong>Area:</strong> <span id="modal_parking_area"></span></p>
                    <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($selectedDate)); ?></p>
                </div>
                
                <div class="form-group" style="margin: 20px 0;">
                    <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 8px;">
                        <i class="fas fa-car"></i> Select Vehicle: <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="vehicle_id" id="vehicle_select" required style="width: 100%; padding: 10px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 15px;">
                        <option value="">-- Choose a vehicle --</option>
                        <?php foreach ($userVehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['vehicle_ID']; ?>" 
                                    data-plate="<?php echo htmlspecialchars($vehicle['license_plate']); ?>"
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
                        <?php if (empty($userVehicles)): ?>
                            <option value="" disabled>No vehicles registered</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($userVehicles)): ?>
                        <p style="color: #ef4444; font-size: 13px; margin-top: 5px;">
                            <i class="fas fa-exclamation-circle"></i> Please register a vehicle first.
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="time-selection">
                    <h4 style="margin: 10px 0; color: #374151;"><i class="fas fa-clock"></i> Select Time</h4>
                </div>
                
                <div class="specific-time-inputs">
                    <div class="time-input-group">
                        <label>Start Time: <span style="color: #ef4444;">*</span></label>
                        <input type="time" name="start_time" id="start_time" required>
                    </div>
                    <div class="time-input-group">
                        <label>End Time: <span style="color: #ef4444;">*</span></label>
                        <input type="time" name="end_time" id="end_time" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-confirm"><i class="fas fa-check"></i> Confirm Booking</button>
                <button type="button" onclick="closeBookingModal()" class="btn-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBookingModal(spaceId, spaceNumber, parkingArea) {
    document.getElementById('modal_space_id').value = spaceId;
    document.getElementById('modal_space_number').textContent = spaceNumber;
    document.getElementById('modal_parking_area').textContent = parkingArea;
    document.getElementById('bookingModal').style.display = 'flex';
}

function closeBookingModal() {
    document.getElementById('bookingModal').style.display = 'none';
    document.getElementById('bookingForm').reset();
}

window.onclick = function(event) {
    const modal = document.getElementById('bookingModal');
    if (event.target === modal) {
        closeBookingModal();
    }
}
</script>

<?php renderFooter(); ?>
