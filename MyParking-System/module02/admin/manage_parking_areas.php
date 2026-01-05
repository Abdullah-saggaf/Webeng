<?php
/**
 * Manage Parking Areas - Admin View
 * Module 2 - MyParking System
 * 
 * PURPOSE: CRUD operations for parking areas (ParkingLot table)
 * Admin can: Create, Read, Update, Delete parking areas
 * Features: Toggle booking availability (lock/unlock), Close spaces for events, Pagination, Search
 */

// Include authentication and database modules
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// AUTHORIZATION: Only FK Staff (admin) can manage parking areas
// Redirects to login if user is not admin
requireRole(['fk_staff']);

// Establish database connection
$db = getDB();

// Variables to store feedback messages after form submission
$message = '';
$messageType = ''; // 'success' or 'error'

/* ==================== HANDLE POST ACTIONS (Form Submissions) ==================== */
// Check if form was submitted via POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the action type from hidden input field
    $action = $_POST['action'] ?? '';
    
    /* ----- ACTION: Create New Parking Area ----- */
    if ($action === 'create') {
        // Retrieve and sanitize form inputs
        $name = trim($_POST['area_name'] ?? ''); // Remove whitespace
        $type = $_POST['area_type'] ?? ''; // e.g., Student, Staff, Visitor
        $isBooking = isset($_POST['is_booking']) ? 1 : 0; // Checkbox: 1 if checked, 0 otherwise
        $capacity = (int)($_POST['capacity'] ?? 0); // Cast to integer for safety
        
        // Validate inputs before database insertion
        if ($name && $type && $capacity > 0) {
            try {
                // Check total capacity limit (200 spaces maximum)
                $stmt = $db->query("SELECT SUM(capacity) as total FROM ParkingLot");
                $currentTotal = $stmt->fetch()['total'] ?? 0;
                
                if (($currentTotal + $capacity) > 200) {
                    $remaining = 200 - $currentTotal;
                    $message = "Cannot add area! Total capacity limit is 200 spaces. Currently: {$currentTotal}, Available: {$remaining}";
                    $messageType = 'error';
                } else {
                    // PREPARED STATEMENT: Prevents SQL injection by using placeholders (?)
                    // INSERT INTO ParkingLot table with 4 fields
                    $stmt = $db->prepare("INSERT INTO ParkingLot (parkingLot_name, parkingLot_type, is_booking_lot, capacity) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $type, $isBooking, $capacity]);
                    $message = "Parking area created successfully!";
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                // Catch database errors (e.g., duplicate names, constraint violations)
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    /* ----- ACTION: Update Existing Parking Area ----- */
    elseif ($action === 'update') {
        // Retrieve inputs including area_id to identify which record to update
        $id = (int)($_POST['area_id'] ?? 0);
        $name = trim($_POST['area_name'] ?? '');
        $type = $_POST['area_type'] ?? '';
        $isBooking = isset($_POST['is_booking']) ? 1 : 0;
        $capacity = (int)($_POST['capacity'] ?? 0);
        
        // Validate inputs
        if ($id && $name && $type && $capacity > 0) {
            try {
                // Check total capacity limit excluding current area
                $stmt = $db->prepare("SELECT SUM(capacity) as total FROM ParkingLot WHERE parkingLot_ID != ?");
                $stmt->execute([$id]);
                $currentTotal = $stmt->fetch()['total'] ?? 0;
                
                if (($currentTotal + $capacity) > 200) {
                    $remaining = 200 - $currentTotal;
                    $message = "Cannot update! Total capacity limit is 200 spaces. Current total (excluding this area): {$currentTotal}, Available: {$remaining}";
                    $messageType = 'error';
                } else {
                    // UPDATE ParkingLot WHERE parkingLot_ID matches
                    $stmt = $db->prepare("UPDATE ParkingLot SET parkingLot_name=?, parkingLot_type=?, is_booking_lot=?, capacity=? WHERE parkingLot_ID=?");
                    $stmt->execute([$name, $type, $isBooking, $capacity, $id]);
                    $message = "Parking area updated successfully!";
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    /* ----- ACTION: Delete Parking Area ----- */
    elseif ($action === 'delete') {
        $id = (int)($_POST['area_id'] ?? 0);
        
        // BUSINESS RULE: Cannot delete area if there are any bookings for spaces in this area
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt 
            FROM Booking b
            JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
            WHERE ps.parkingLot_ID = ?
        ");
        $stmt->execute([$id]);
        $bookingCount = $stmt->fetch()['cnt'];
        
        if ($bookingCount > 0) {
            $message = "Cannot delete area! There are {$bookingCount} booking(s) associated with spaces in this area. Delete all bookings first.";
            $messageType = 'error';
        } else {
            // CASCADE DELETE: Delete all parking spaces in this area first, then delete the area
            try {
                // Begin transaction for atomic operation
                $db->beginTransaction();
                
                // First, delete all parking spaces in this area
                $stmt = $db->prepare("DELETE FROM ParkingSpace WHERE parkingLot_ID=?");
                $stmt->execute([$id]);
                $deletedSpaces = $stmt->rowCount();
                
                // Then delete the parking area
                $stmt = $db->prepare("DELETE FROM ParkingLot WHERE parkingLot_ID=?");
                $stmt->execute([$id]);
                
                // Commit transaction
                $db->commit();
                
                if ($deletedSpaces > 0) {
                    $message = "Parking area deleted successfully! ({$deletedSpaces} parking spaces also removed)";
                } else {
                    $message = "Parking area deleted successfully!";
                }
                $messageType = 'success';
            } catch (PDOException $e) {
                // Rollback on error
                $db->rollBack();
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    /* ----- ACTION: Close Spaces for Event ----- */
    elseif ($action === 'close_spaces') {
        $areaId = (int)($_POST['area_id'] ?? 0);
        $spacesToClose = (int)($_POST['spaces_to_close'] ?? 0);
        $reason = trim($_POST['closure_reason'] ?? '');
        $startDatetime = $_POST['closure_start'] ?? '';
        $endDatetime = $_POST['closure_end'] ?? '';
        
        if ($areaId && $spacesToClose > 0 && $reason && $startDatetime && $endDatetime) {
            try {
                // Check if spaces_to_close doesn't exceed capacity
                $stmt = $db->prepare("SELECT capacity, parkingLot_name FROM ParkingLot WHERE parkingLot_ID = ?");
                $stmt->execute([$areaId]);
                $area = $stmt->fetch();
                
                if ($spacesToClose > $area['capacity']) {
                    $message = "Cannot close {$spacesToClose} spaces! Area capacity is only {$area['capacity']}.";
                    $messageType = 'error';
                } else {
                    // Store closure info as JSON in remarks
                    $closureData = json_encode([
                        'area_id' => $areaId,
                        'area_name' => $area['parkingLot_name'],
                        'spaces_closed' => $spacesToClose,
                        'reason' => $reason,
                        'start' => $startDatetime,
                        'end' => $endDatetime,
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    // Insert closure record into ParkingLog (NULL booking_ID for administrative events)
                    $stmt = $db->prepare("
                        INSERT INTO ParkingLog (booking_ID, event_time, event_type, remarks)
                        VALUES (NULL, NOW(), 'CLOSURE_START', ?)
                    ");
                    $stmt->execute([$closureData]);
                    
                    $message = "Successfully closed {$spacesToClose} spaces for event!";
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    /* ----- ACTION: End Space Closure ----- */
    elseif ($action === 'end_closure') {
        $logId = (int)($_POST['log_id'] ?? 0);
        
        if ($logId) {
            try {
                // Mark closure as ended
                $stmt = $db->prepare("
                    INSERT INTO ParkingLog (booking_ID, event_time, event_type, remarks)
                    SELECT NULL, NOW(), 'CLOSURE_END', CONCAT('Ended closure: ', log_ID)
                    FROM ParkingLog WHERE log_ID = ?
                ");
                $stmt->execute([$logId]);
                
                $message = "Space closure ended successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    /* ----- ACTION: Toggle Lock/Unlock (Booking Availability) ----- */
    elseif ($action === 'toggle_lock') {
        $id = (int)($_POST['area_id'] ?? 0);
        $currentStatus = (int)($_POST['current_status'] ?? 0); // Current is_booking_lot value
        $newStatus = $currentStatus ? 0 : 1; // Toggle: 1→0 or 0→1
        
        // BUSINESS RULE: is_booking_lot = 1 means bookable/unlocked, 0 means locked for events
        if ($id) {
            try {
                $stmt = $db->prepare("UPDATE ParkingLot SET is_booking_lot=? WHERE parkingLot_ID=?");
                $stmt->execute([$newStatus, $id]);
                $statusText = $newStatus ? "unlocked and available" : "locked for events";
                $message = "Parking area $statusText successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    

}
/* ==================== END OF POST ACTIONS ==================== */

/* ==================== PAGINATION & SEARCH SETUP ==================== */
// Get pagination parameters from URL (for table display)
$limit = (int)($_GET['entries'] ?? 10); // Number of entries per page (default 10)
$page = (int)($_GET['page'] ?? 1); // Current page number (default 1)
$offset = ($page - 1) * $limit; // Calculate SQL OFFSET (e.g., page 2 with limit 10 → offset 10)
$search = $_GET['search'] ?? ''; // Search keyword from search form

// Build WHERE clause dynamically based on search input
$whereClause = '';
$params = []; // Array to hold prepared statement parameters
if ($search) {
    // LIKE operator for partial name matching (case-insensitive in MySQL)
    $whereClause = "WHERE parkingLot_name LIKE ?";
    $params[] = "%$search%"; // Wrap search term with % wildcards
}

// Get total count of matching records (for pagination calculation)
$countSql = "SELECT COUNT(*) as total FROM ParkingLot $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit); // Calculate total pages (round up)

// Fetch parking areas for current page
// ORDER BY parkingLot_name: Sort alphabetically by area name
// LIMIT/OFFSET: Pagination (e.g., LIMIT 10 OFFSET 20 = rows 21-30)
$sql = "SELECT parkingLot_ID, parkingLot_name, parkingLot_type, is_booking_lot, capacity 
        FROM ParkingLot $whereClause ORDER BY parkingLot_name LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$areas = $stmt->fetchAll(); // Fetch all results as associative array

// Get active closures from ParkingLog
$activeClosures = [];
$closureStmt = $db->query("
    SELECT log_ID, remarks, event_time
    FROM ParkingLog
    WHERE event_type = 'CLOSURE_START'
    AND log_ID NOT IN (
        SELECT CAST(SUBSTRING_INDEX(remarks, ': ', -1) AS UNSIGNED)
        FROM ParkingLog
        WHERE event_type = 'CLOSURE_END'
    )
    ORDER BY event_time DESC
");
$closureLogs = $closureStmt->fetchAll();
foreach ($closureLogs as $log) {
    $data = json_decode($log['remarks'], true);
    if ($data && isset($data['area_id']) && strtotime($data['end']) > time()) {
        $activeClosures[$data['area_id']] = [
            'log_id' => $log['log_ID'],
            'spaces_closed' => $data['spaces_closed'],
            'reason' => $data['reason'],
            'start' => $data['start'],
            'end' => $data['end']
        ];
    }
}

// Load main layout wrapper (header, sidebar, navigation from layout.php)
require_once __DIR__ . '/../../layout.php';
renderHeader('Manage Parking Areas'); // Sets page title
?>

<!-- Link to external CSS for styling this page -->
<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/manage_parking_areas.css?v=<?php echo time(); ?>">

<div class="areas-container">
    <h2>Parking Area Management</h2>
    
    <!-- ==================== PARKING AREA MAP DISPLAY ==================== -->
    <!-- Visual representation of parking areas for reference -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #374151; font-size: 18px;"><i class="fas fa-map-marked-alt"></i> Parking Area Layout</h3>
        <div style="text-align: center; background: #f9fafb; padding: 20px; border-radius: 6px;">
            <!-- Campus parking map image (SVG format) -->
            <img src="../../images/A1.svg.svg" alt="Parking Area Map" style="max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
    </div>
    
    <!-- Success/Error Message Alert (shown after form submission) -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); // Escape output to prevent XSS ?>
    </div>
    <?php endif; ?>
    
    <!-- ==================== CONTROLS BAR ==================== -->
    <!-- Contains pagination dropdown, search form, and Add Area button -->
    <div class="controls-bar">
        <!-- Left side: Entries per page dropdown -->
        <div class="controls-left">
            <label for="entries">Show entries:</label>
            <!-- When changed, calls JavaScript to reload page with new limit -->
            <select id="entries" onchange="changeEntries(this.value)">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
            </select>
        </div>
        
        <!-- Right side: Search form + Add Area button -->
        <div class="controls-right">
            <!-- Search form submits via GET to filter parking areas by name -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by area name..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
            </form>
            <!-- Button to open Create modal (opens via JavaScript) -->
            <button onclick="openCreateModal()" class="btn-add"><i class="fas fa-plus"></i> Add Area</button>
        </div>
    </div>
    
    <!-- ==================== PARKING AREAS TABLE ==================== -->
    <!-- Displays all parking areas with their details and action buttons -->
    <div class="table-wrapper">
        <table class="areas-table">
            <thead>
                <tr>
                    <th>No</th> <!-- Row number (with pagination offset) -->
                    <th>Area Name</th> <!-- ParkingLot.parkingLot_name -->
                    <th>Area Type</th> <!-- Student/Staff/Visitor/VIP/General -->
                    <th>Booking/General</th> <!-- is_booking_lot: 1=Available, 0=Locked -->
                    <th>Capacity</th> <!-- Total capacity minus closed_spaces -->
                    <th>Total Spaces</th> <!-- Count of child ParkingSpace records -->
                    <th>Actions</th> <!-- Buttons: Lock/Unlock, Close Spaces, Edit, Delete -->
                </tr>
            </thead>
            <tbody>
                <!-- Show "No data" message if no parking areas found -->
                <?php if (empty($areas)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 40px;">No parking areas found</td></tr>
                <?php else: ?>
                <!-- Loop through each parking area and display as table row -->
                <?php foreach ($areas as $index => $area): 
                    // For each area, count how many spaces it has (FK: ParkingSpace.parkingLot_ID)
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ParkingSpace WHERE parkingLot_ID=?");
                    $stmt->execute([$area['parkingLot_ID']]);
                    $spaceCount = $stmt->fetch()['cnt'];
                ?>
                <tr>
                    <!-- Row number (accounts for pagination offset) -->
                    <td><?php echo $offset + $index + 1; ?></td>
                    <!-- Area name -->
                    <td class="area-name"><?php echo htmlspecialchars($area['parkingLot_name']); ?></td>
                    <!-- Area type -->
                    <td><?php echo htmlspecialchars($area['parkingLot_type']); ?></td>
                    <!-- Booking status badge (color-coded) -->
                    <td>
                        <span class="badge badge-<?php echo $area['is_booking_lot'] ? 'booking' : 'locked'; ?>">
                            <?php echo $area['is_booking_lot'] ? '<i class="fas fa-unlock"></i> Available' : '<i class="fas fa-lock"></i> Locked'; ?>
                        </span>
                    </td>
                    <!-- Capacity -->
                    <td>
                        <?php echo $area['capacity']; ?>
                    </td>
                    <!-- Total spaces (child records count) -->
                    <td><?php echo $spaceCount; ?></td>
                    <!-- Action buttons column -->
                    <td class="actions">
                        <!-- Close Spaces button -->
                        <?php if (isset($activeClosures[$area['parkingLot_ID']])): 
                            $closure = $activeClosures[$area['parkingLot_ID']];
                        ?>
                        <div style="margin-bottom: 8px; padding: 8px; background: #fef3c7; border-radius: 6px; font-size: 12px;">
                            <strong>🚧 <?php echo $closure['spaces_closed']; ?> spaces closed</strong><br>
                            <em><?php echo htmlspecialchars($closure['reason']); ?></em><br>
                            Until: <?php echo date('M d, Y H:i', strtotime($closure['end'])); ?>
                            <form method="POST" style="display: inline; margin-top: 4px;">
                                <input type="hidden" name="action" value="end_closure">
                                <input type="hidden" name="log_id" value="<?php echo $closure['log_id']; ?>">
                                <button type="submit" class="btn-unlock" style="font-size: 11px; padding: 4px 8px;">
                                    <i class="fas fa-check"></i> End Closure
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <button onclick='openCloseSpacesModal(<?php echo json_encode($area); ?>)' 
                                class="btn-lock" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-calendar-times"></i> Close Spaces
                        </button>
                        <?php endif; ?>
                        
                        <!-- Lock/Unlock button (toggles is_booking_lot) -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_lock">
                            <input type="hidden" name="area_id" value="<?php echo $area['parkingLot_ID']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $area['is_booking_lot']; ?>">
                            <button type="submit" class="btn-lock <?php echo $area['is_booking_lot'] ? 'btn-do-lock' : 'btn-unlock'; ?>">
                                <i class="fas fa-<?php echo $area['is_booking_lot'] ? 'lock' : 'unlock'; ?>"></i> 
                                <?php echo $area['is_booking_lot'] ? 'Lock' : 'Unlock'; ?>
                            </button>
                        </form>
                        <!-- Edit button (opens modal with current data) -->
                        <button onclick='openEditModal(<?php echo json_encode($area); ?>)' 
                                class="btn-edit"><i class="fas fa-edit"></i> Edit</button>
                        <!-- Delete button (with confirmation prompt) -->
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Delete this parking area? (Only if no spaces exist)')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="area_id" value="<?php echo $area['parkingLot_ID']; ?>">
                            <button type="submit" class="btn-delete"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- ==================== PAGINATION ==================== -->
    <!-- Only show pagination if there are multiple pages -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <!-- Generate page links (1, 2, 3...) -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <!-- Each link preserves entries and search parameters -->
        <a href="?page=<?php echo $i; ?>&entries=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" 
           class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== CREATE MODAL ==================== -->
<!-- Modal popup form for creating new parking area -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Parking Area</h3>
            <button onclick="closeModal('createModal')" class="close-btn">×</button>
        </div>
        <!-- Form submits via POST with action='create' -->
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Area Name <span class="required">*</span></label>
                <input type="text" name="area_name" required>
            </div>
            
            <div class="form-group">
                <label>Area Type <span class="required">*</span></label>
                <!-- Dropdown with predefined area types -->
                <select name="area_type" required>
                    <option value="">Select Type</option>
                    <option value="Student">Student</option>
                    <option value="Staff">Staff</option>
                    <option value="Visitor">Visitor</option>
                    <option value="VIP">VIP</option>
                    <option value="General">General</option>
                </select>
            </div>
            
            <div class="form-group">
                <!-- Checkbox: If checked, area is bookable (is_booking_lot=1) -->
                <label>
                    Bookable Area
                    <input type="checkbox" name="is_booking">
                </label>
            </div>
            
            <div class="form-group">
                <label>Capacity <span class="required">*</span></label>
                <input type="number" name="capacity" min="1" required>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">💾 Create</button>
                <button type="button" onclick="closeModal('createModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Parking Area</h3>
            <button onclick="closeModal('editModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="area_id" id="edit_area_id">
            
            <div class="form-group">
                <label>Area Name <span class="required">*</span></label>
                <input type="text" name="area_name" id="edit_area_name" required>
            </div>
            
            <div class="form-group">
                <label>Area Type <span class="required">*</span></label>
                <select name="area_type" id="edit_area_type" required>
                    <option value="Student">Student</option>
                    <option value="Staff">Staff</option>
                    <option value="Visitor">Visitor</option>
                    <option value="VIP">VIP</option>
                    <option value="General">General</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    Bookable Area
                    <input type="checkbox" name="is_booking" id="edit_is_booking">
                </label>
            </div>
            
            <div class="form-group">
                <label>Capacity <span class="required">*</span></label>
                <input type="number" name="capacity" id="edit_capacity" min="1" required>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">💾 Update</button>
                <button type="button" onclick="closeModal('editModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Close Spaces Modal -->
<div id="closeSpacesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-ban"></i> Close Parking Spaces for Event</h3>
            <button onclick="closeModal('closeSpacesModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="close_spaces">
            <input type="hidden" name="area_id" id="close_area_id">
            
            <div class="form-group">
                <label>Area <span class="required">*</span></label>
                <input type="text" id="close_area_name" disabled style="background: #f5f5f5;">
            </div>
            
            <div class="form-group">
                <label>Number of Spaces to Close <span class="required">*</span></label>
                <input type="number" name="spaces_to_close" id="close_spaces_to_close" min="1" required>
                <small>Available spaces: <span id="close_available_spaces"></span></small>
            </div>
            
            <div class="form-group">
                <label>Reason for Closure <span class="required">*</span></label>
                <textarea name="closure_reason" id="close_reason" rows="3" required placeholder="e.g., University event, Maintenance, etc."></textarea>
            </div>
            
            <div class="form-group">
                <label>Closure Start <span class="required">*</span></label>
                <input type="datetime-local" name="closure_start" id="close_start" required>
            </div>
            
            <div class="form-group">
                <label>Closure End <span class="required">*</span></label>
                <input type="datetime-local" name="closure_end" id="close_end" required>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary">🔒 Close Spaces</button>
                <button type="button" onclick="closeModal('closeSpacesModal')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ==================== JAVASCRIPT FUNCTIONS ==================== */

/**
 * Change entries per page and reload with new limit
 * Resets to page 1 when entries changed
 */
function changeEntries(value) {
    const url = new URL(window.location);
    url.searchParams.set('entries', value);
    url.searchParams.set('page', 1); // Reset to first page
    window.location = url;
}

/**
 * Open Create modal (show form to add new area)
 */
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

/**
 * Open Edit modal and populate form with current area data
 * @param {Object} area - Parking area object from PHP (JSON encoded)
 */
function openEditModal(area) {
    // Fill form fields with existing data
    document.getElementById('edit_area_id').value = area.parkingLot_ID;
    document.getElementById('edit_area_name').value = area.parkingLot_name;
    document.getElementById('edit_area_type').value = area.parkingLot_type;
    document.getElementById('edit_is_booking').checked = area.is_booking_lot == 1;
    document.getElementById('edit_capacity').value = area.capacity;
    document.getElementById('editModal').style.display = 'flex';
}

/**
 * Open Close Spaces modal and populate with area data
 * @param {Object} area - Parking area object from PHP (JSON encoded)
 */
function openCloseSpacesModal(area) {
    // Fill form fields with area data
    document.getElementById('close_area_id').value = area.parkingLot_ID;
    document.getElementById('close_area_name').value = area.parkingLot_name;
    document.getElementById('close_spaces_to_close').max = area.capacity;
    document.getElementById('close_available_spaces').textContent = area.capacity;
    
    // Set default start time to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('close_start').value = now.toISOString().slice(0, 16);
    
    // Set default end time to 24 hours from now
    const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    document.getElementById('close_end').value = tomorrow.toISOString().slice(0, 16);
    
    document.getElementById('closeSpacesModal').style.display = 'flex';
}

/**
 * Close modal by ID
 * @param {string} modalId - ID of modal element to hide
 */
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

/**
 * Close modal when clicking outside modal content (on overlay)
 */
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php 
// Render footer from layout.php
renderFooter(); 
?>
