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
                // PREPARED STATEMENT: Prevents SQL injection by using placeholders (?)
                // INSERT INTO ParkingLot table with 4 fields
                $stmt = $db->prepare("INSERT INTO ParkingLot (parkingLot_name, parkingLot_type, is_booking_lot, capacity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $type, $isBooking, $capacity]);
                $message = "Parking area created successfully!";
                $messageType = 'success';
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
                // UPDATE ParkingLot WHERE parkingLot_ID matches
                $stmt = $db->prepare("UPDATE ParkingLot SET parkingLot_name=?, parkingLot_type=?, is_booking_lot=?, capacity=? WHERE parkingLot_ID=?");
                $stmt->execute([$name, $type, $isBooking, $capacity, $id]);
                $message = "Parking area updated successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    /* ----- ACTION: Delete Parking Area ----- */
    elseif ($action === 'delete') {
        $id = (int)($_POST['area_id'] ?? 0);
        
        // BUSINESS RULE: Cannot delete area if it has parking spaces
        // Check child records in ParkingSpace table (FK: parkingLot_ID)
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ParkingSpace WHERE parkingLot_ID=?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['cnt'];
        
        if ($count > 0) {
            // Prevent deletion to maintain referential integrity
            $message = "Cannot delete area with existing parking spaces!";
            $messageType = 'error';
        } else {
            try {
                // Safe to delete: No child records exist
                $stmt = $db->prepare("DELETE FROM ParkingLot WHERE parkingLot_ID=?");
                $stmt->execute([$id]);
                $message = "Parking area deleted successfully!";
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
    
    /* ----- ACTION: Close Spaces for Event/Maintenance ----- */
    elseif ($action === 'close_spaces') {
        $id = (int)($_POST['area_id'] ?? 0);
        $closedSpaces = (int)($_POST['closed_spaces'] ?? 0); // Number of spaces to temporarily close
        
        if ($id && $closedSpaces >= 0) {
            // VALIDATION: Get area capacity to ensure closed_spaces doesn't exceed capacity
            $stmt = $db->prepare("SELECT capacity FROM ParkingLot WHERE parkingLot_ID=?");
            $stmt->execute([$id]);
            $area = $stmt->fetch();
            
            // BUSINESS RULE: Cannot close more spaces than total capacity
            if ($closedSpaces > $area['capacity']) {
                $message = "Cannot close more spaces than the area capacity!";
                $messageType = 'error';
            } else {
                try {
                    // Update closed_spaces field (reduces available capacity temporarily)
                    $stmt = $db->prepare("UPDATE ParkingLot SET closed_spaces=? WHERE parkingLot_ID=?");
                    $stmt->execute([$closedSpaces, $id]);
                    $message = "Successfully closed $closedSpaces space(s) for event/maintenance!";
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
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
// COALESCE(closed_spaces, 0): Returns 0 if closed_spaces is NULL
// ORDER BY parkingLot_name: Sort alphabetically by area name
// LIMIT/OFFSET: Pagination (e.g., LIMIT 10 OFFSET 20 = rows 21-30)
$sql = "SELECT parkingLot_ID, parkingLot_name, parkingLot_type, is_booking_lot, capacity, COALESCE(closed_spaces, 0) as closed_spaces FROM ParkingLot $whereClause ORDER BY parkingLot_name LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$areas = $stmt->fetchAll(); // Fetch all results as associative array

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
                    <!-- Available capacity (total - closed spaces) -->
                    <td>
                        <?php 
                        $availableCapacity = $area['capacity'] - $area['closed_spaces'];
                        echo $availableCapacity;
                        // Show closed count if any spaces are closed
                        if ($area['closed_spaces'] > 0) {
                            echo ' <span style="color: #ef4444; font-size: 12px;">(' . $area['closed_spaces'] . ' closed)</span>';
                        }
                        ?>
                    </td>
                    <!-- Total spaces (child records count) -->
                    <td><?php echo $spaceCount; ?></td>
                    <!-- Action buttons column -->
                    <td class="actions">
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
                        <!-- Close Spaces button (opens modal) -->
                        <button onclick='openCloseSpacesModal(<?php echo json_encode($area); ?>)' 
                                class="btn-lock"><i class="fas fa-ban"></i> Close Spaces</button>
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
                    <input type="checkbox" name="is_booking">
                    Bookable Area
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
                    <input type="checkbox" name="is_booking" id="edit_is_booking">
                    Bookable Area
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
            <h3><i class="fas fa-ban"></i> Close Parking Spaces</h3>
            <button onclick="closeModal('closeSpacesModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="close_spaces">
            <input type="hidden" name="area_id" id="close_area_id">
            
            <div class="form-group">
                <label>Area Name</label>
                <input type="text" id="close_area_name" readonly style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>Total Capacity</label>
                <input type="text" id="close_capacity" readonly style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label>Number of Spaces to Close <span class="required">*</span></label>
                <input type="number" name="closed_spaces" id="close_spaces_input" min="0" required>
                <small style="color: #6b7280; display: block; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Close spaces temporarily for events or maintenance. Enter 0 to reopen all spaces.
                </small>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-ban"></i> Update</button>
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
 * @param {Object} area - Parking area object
 */
function openCloseSpacesModal(area) {
    document.getElementById('close_area_id').value = area.parkingLot_ID;
    document.getElementById('close_area_name').value = area.parkingLot_name;
    document.getElementById('close_capacity').value = area.capacity;
    document.getElementById('close_spaces_input').value = area.closed_spaces || 0;
    document.getElementById('close_spaces_input').max = area.capacity; // Set max attribute
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
