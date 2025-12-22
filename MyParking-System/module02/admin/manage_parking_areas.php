<?php
/**
 * Manage Parking Areas - Admin View
 * Module 2 - MyParking System
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require FK Staff role (admin)
requireRole(['fk_staff']);

$db = getDB();
$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['area_name'] ?? '');
        $type = $_POST['area_type'] ?? '';
        $isBooking = isset($_POST['is_booking']) ? 1 : 0;
        $capacity = (int)($_POST['capacity'] ?? 0);
        
        if ($name && $type && $capacity > 0) {
            try {
                $stmt = $db->prepare("INSERT INTO ParkingLot (parkingLot_name, parkingLot_type, is_booking_lot, capacity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $type, $isBooking, $capacity]);
                $message = "Parking area created successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    elseif ($action === 'update') {
        $id = (int)($_POST['area_id'] ?? 0);
        $name = trim($_POST['area_name'] ?? '');
        $type = $_POST['area_type'] ?? '';
        $isBooking = isset($_POST['is_booking']) ? 1 : 0;
        $capacity = (int)($_POST['capacity'] ?? 0);
        
        if ($id && $name && $type && $capacity > 0) {
            try {
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
    
    elseif ($action === 'delete') {
        $id = (int)($_POST['area_id'] ?? 0);
        
        // Check if area has spaces
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ParkingSpace WHERE parkingLot_ID=?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['cnt'];
        
        if ($count > 0) {
            $message = "Cannot delete area with existing parking spaces!";
            $messageType = 'error';
        } else {
            try {
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
}

// Pagination & Search
$limit = (int)($_GET['entries'] ?? 10);
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

// Build query
$whereClause = '';
$params = [];
if ($search) {
    $whereClause = "WHERE parkingLot_name LIKE ?";
    $params[] = "%$search%";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM ParkingLot $whereClause";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalRecords = $stmt->fetch()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get areas
$sql = "SELECT * FROM ParkingLot $whereClause ORDER BY parkingLot_name LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$areas = $stmt->fetchAll();

require_once __DIR__ . '/../../module01/layout.php';
renderHeader('Manage Parking Areas');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/manage_parking_areas.css">

<div class="areas-container">
    <h1 class="page-title">🏢 Parking Area Management</h1>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Controls -->
    <div class="controls-bar">
        <div class="controls-left">
            <label for="entries">Show entries:</label>
            <select id="entries" onchange="changeEntries(this.value)">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
            </select>
        </div>
        
        <div class="controls-right">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by area name..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search">🔍 Search</button>
            </form>
            <button onclick="openCreateModal()" class="btn-add">➕ Add Area</button>
        </div>
    </div>
    
    <!-- Table -->
    <div class="table-wrapper">
        <table class="areas-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Area Name</th>
                    <th>Area Type</th>
                    <th>Booking/General</th>
                    <th>Capacity</th>
                    <th>Total Spaces</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($areas)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 40px;">No parking areas found</td></tr>
                <?php else: ?>
                <?php foreach ($areas as $index => $area): 
                    // Get space count
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM ParkingSpace WHERE parkingLot_ID=?");
                    $stmt->execute([$area['parkingLot_ID']]);
                    $spaceCount = $stmt->fetch()['cnt'];
                ?>
                <tr>
                    <td><?php echo $offset + $index + 1; ?></td>
                    <td class="area-name"><?php echo htmlspecialchars($area['parkingLot_name']); ?></td>
                    <td><?php echo htmlspecialchars($area['parkingLot_type']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $area['is_booking_lot'] ? 'booking' : 'general'; ?>">
                            <?php echo $area['is_booking_lot'] ? '📅 Bookable' : '🚗 General'; ?>
                        </span>
                    </td>
                    <td><?php echo $area['capacity']; ?></td>
                    <td><?php echo $spaceCount; ?></td>
                    <td class="actions">
                        <button onclick='openEditModal(<?php echo json_encode($area); ?>)' 
                                class="btn-edit">✏️ Edit</button>
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('Delete this parking area? (Only if no spaces exist)')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="area_id" value="<?php echo $area['parkingLot_ID']; ?>">
                            <button type="submit" class="btn-delete">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&entries=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" 
           class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>➕ Add New Parking Area</h3>
            <button onclick="closeModal('createModal')" class="close-btn">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Area Name <span class="required">*</span></label>
                <input type="text" name="area_name" required>
            </div>
            
            <div class="form-group">
                <label>Area Type <span class="required">*</span></label>
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
            <h3>✏️ Edit Parking Area</h3>
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

<script>
function changeEntries(value) {
    const url = new URL(window.location);
    url.searchParams.set('entries', value);
    url.searchParams.set('page', 1);
    window.location = url;
}

function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function openEditModal(area) {
    document.getElementById('edit_area_id').value = area.parkingLot_ID;
    document.getElementById('edit_area_name').value = area.parkingLot_name;
    document.getElementById('edit_area_type').value = area.parkingLot_type;
    document.getElementById('edit_is_booking').checked = area.is_booking_lot == 1;
    document.getElementById('edit_capacity').value = area.capacity;
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php renderFooter(); ?>
