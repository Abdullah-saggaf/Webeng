<?php
/**
 * View & Manage Bookings - Admin View
 * Module 3 - MyParking System
 * 
 * Features:
 * 1. View & Manage Bookings
 * 2. Booking Reports & Dashboard  
 * 3. Active Parking Monitoring
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require Admin role
requireRole(['fk_staff']);

$db = getDB();
$message = '';
$messageType = '';

// Auto-complete expired active bookings
$currentTime = date('Y-m-d H:i:s');
$completeStmt = $db->prepare("
    UPDATE Booking 
    SET booking_status = 'completed',
        session_ended_at = actual_end_time
    WHERE booking_status = 'active' 
    AND actual_end_time IS NOT NULL 
    AND actual_end_time > '1000-01-01 00:00:00'
    AND actual_end_time < ?
");
$completedCount = $completeStmt->execute([$currentTime]) ? $completeStmt->rowCount() : 0;

if ($completedCount > 0) {
    $message = "$completedCount parking session(s) automatically completed.";
    $messageType = 'success';
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    
    if ($action === 'cancel_booking' && $bookingId) {
        try {
            // Delete booking from database
            $stmt = $db->prepare("DELETE FROM Booking WHERE booking_ID = ?");
            $stmt->execute([$bookingId]);
            $message = 'Booking cancelled and deleted successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_status' && $bookingId) {
        $newStatus = $_POST['new_status'] ?? '';
        try {
            $stmt = $db->prepare("UPDATE Booking SET booking_status = ? WHERE booking_ID = ?");
            $stmt->execute([$newStatus, $bookingId]);
            $message = 'Booking status updated successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$filterStudent = $_GET['student'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterArea = $_GET['area'] ?? '';
$activeTab = $_GET['tab'] ?? 'manage';

require_once __DIR__ . '/../../layout.php';
renderHeader('View & Manage Bookings');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/manage_parking_areas.css?v=<?php echo time(); ?>">

<style>
.booking-container {
    padding: 20px;
}

.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
}

.tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    color: #6b7280;
    transition: all 0.2s;
}

.tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.tab:hover {
    color: #3b82f6;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.filter-bar {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    min-height: 18px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-card h3 {
    color: #6b7280;
    font-size: 13px;
    font-weight: 500;
    margin: 0 0 8px 0;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
}

.stat-card.blue { border-left: 4px solid #3b82f6; }
.stat-card.green { border-left: 4px solid #10b981; }
.stat-card.yellow { border-left: 4px solid #f59e0b; }
.stat-card.red { border-left: 4px solid #ef4444; }

.table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.bookings-table {
    width: 100%;
    border-collapse: collapse;
}

.bookings-table th {
    background: #f9fafb;
    padding: 12px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
}

.bookings-table td {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
    color: #1f2937;
}

.bookings-table tr:hover {
    background: #f9fafb;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-confirmed { background: #dbeafe; color: #1e40af; }
.status-active { background: #d1fae5; color: #065f46; }
.status-completed { background: #e5e7eb; color: #374151; }
.status-cancelled { background: #fee2e2; color: #991b1b; }

.btn-action {
    padding: 6px 12px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    margin-right: 5px;
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.btn-cancel {
    background: #ef4444;
    color: white;
}

.btn-update {
    background: #f59e0b;
    color: white;
}

.report-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.report-section h3 {
    margin: 0 0 15px 0;
    color: #1f2937;
}

.active-sessions {
    display: grid;
    gap: 15px;
}

.session-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.session-info {
    display: flex;
    gap: 20px;
}

.session-detail {
    display: flex;
    flex-direction: column;
}

.session-label {
    font-size: 12px;
    color: #6b7280;
}

.session-value {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}
</style>

<div class="booking-container">
    <h2 style="margin-bottom: 20px; color: #1f2937;">
        <i class="fas fa-calendar-check"></i> View & Manage Bookings
    </h2>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab <?php echo $activeTab === 'manage' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=manage'">
            <i class="fas fa-list"></i> Manage Bookings
        </button>
        <button class="tab <?php echo $activeTab === 'reports' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=reports'">
            <i class="fas fa-chart-bar"></i> Reports & Dashboard
        </button>
        <button class="tab <?php echo $activeTab === 'monitoring' ? 'active' : ''; ?>" 
                onclick="location.href='?tab=monitoring'">
            <i class="fas fa-eye"></i> Active Monitoring
        </button>
    </div>
    
    <!-- Tab 1: Manage Bookings -->
    <div class="tab-content <?php echo $activeTab === 'manage' ? 'active' : ''; ?>">
        <!-- Filters -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Status</label>
                <select id="filter-status" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="confirmed" <?php echo $filterStatus === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date</label>
                <input type="date" id="filter-date" value="<?php echo $filterDate; ?>" onchange="applyFilters()">
            </div>
            
            <div class="filter-group">
                <label>Student ID</label>
                <input type="text" id="filter-student" placeholder="e.g., S001" 
                       value="<?php echo htmlspecialchars($filterStudent); ?>" onchange="applyFilters()">
            </div>
        </div>
        
        <!-- Bookings Table -->
        <div class="table-container">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Space</th>
                        <th>Area</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Build query with filters
                    $whereConditions = [];
                    $params = [];
                    
                    if ($filterStatus) {
                        $whereConditions[] = "b.booking_status = ?";
                        $params[] = $filterStatus;
                    }
                    
                    if ($filterDate) {
                        $whereConditions[] = "b.booking_date = ?";
                        $params[] = $filterDate;
                    }
                    
                    if ($filterStudent) {
                        $whereConditions[] = "u.user_ID LIKE ?";
                        $params[] = "%$filterStudent%";
                    }
                    
                    // Exclude cancelled bookings by default (they should be deleted, but filter just in case)
                    $whereConditions[] = "b.booking_status != 'cancelled'";
                    
                    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "WHERE b.booking_status != 'cancelled'";
                    
                    $sql = "
                        SELECT 
                            b.*,
                            ps.space_number,
                            pl.parkingLot_name,
                            u.user_ID,
                            u.username,
                            v.license_plate as booked_plate,
                            v.vehicle_model,
                            v.vehicle_type,
                            b.actual_plate_number
                        FROM Booking b
                        JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
                        JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
                        JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                        JOIN User u ON v.user_ID = u.user_ID
                        $whereClause
                        ORDER BY b.booking_date DESC, b.booking_ID DESC
                        LIMIT 100
                    ";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $bookings = $stmt->fetchAll();
                    
                    if (empty($bookings)):
                    ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">
                            No bookings found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td>#<?php echo $booking['booking_ID']; ?></td>
                        <td>
                            <div><?php echo htmlspecialchars($booking['username']); ?></div>
                            <div style="font-size: 11px; color: #6b7280;"><?php echo $booking['user_ID']; ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($booking['space_number']); ?></td>
                        <td><?php echo htmlspecialchars($booking['parkingLot_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                        <td>
                            <?php 
                            // Show the times that were selected during booking
                            if ($booking['start_time'] && $booking['end_time']) {
                                echo date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($booking['vehicle_model']); ?></div>
                            <div style="font-size: 11px; color: #6b7280;">
                                <?php 
                                echo htmlspecialchars($booking['actual_plate_number'] ?: $booking['booked_plate']); 
                                ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                                <?php echo ucfirst($booking['booking_status']); ?>
                            </span>
                        </td>
                        <td>
                            <button onclick='viewBookingDetails(<?php echo json_encode($booking); ?>)' 
                                    class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($booking['booking_status'] === 'completed'): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Delete this completed booking?')">
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                                <button type="submit" class="btn-action btn-cancel">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                            <?php elseif ($booking['booking_status'] !== 'cancelled'): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Cancel this booking?')">
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_ID']; ?>">
                                <button type="submit" class="btn-action btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tab 2: Reports & Dashboard -->
    <div class="tab-content <?php echo $activeTab === 'reports' ? 'active' : ''; ?>">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php
            // Get statistics
            $stats = [];
            
            // Total bookings
            $stmt = $db->query("SELECT COUNT(*) as count FROM Booking");
            $stats['total'] = $stmt->fetch()['count'];
            
            // Bookings by status
            $stmt = $db->query("SELECT booking_status, COUNT(*) as count FROM Booking GROUP BY booking_status");
            while ($row = $stmt->fetch()) {
                $stats[$row['booking_status']] = $row['count'];
            }
            ?>
            
            <div class="stat-card blue">
                <h3>Total Bookings</h3>
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card green">
                <h3>Active Sessions</h3>
                <div class="stat-value"><?php echo $stats['active'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card yellow">
                <h3>Confirmed</h3>
                <div class="stat-value"><?php echo $stats['confirmed'] ?? 0; ?></div>
            </div>
            
            <div class="stat-card red">
                <h3>Completed</h3>
                <div class="stat-value"><?php echo $stats['completed'] ?? 0; ?></div>
            </div>
        </div>
        
        <!-- Report 1: Bookings by Status -->
        <div class="report-section">
            <h3><i class="fas fa-chart-pie"></i> Bookings by Status</h3>
            <div class="table-container">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $db->query("SELECT booking_status, COUNT(*) as count FROM Booking GROUP BY booking_status");
                        $statusCounts = $stmt->fetchAll();
                        $total = array_sum(array_column($statusCounts, 'count'));
                        
                        foreach ($statusCounts as $row):
                            $percentage = $total > 0 ? round(($row['count'] / $total) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <span class="status-badge status-<?php echo $row['booking_status']; ?>">
                                    <?php echo ucfirst($row['booking_status']); ?>
                                </span>
                            </td>
                            <td><?php echo $row['count']; ?></td>
                            <td><?php echo $percentage; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Report 2: Booking History with Details -->
        <div class="report-section">
            <h3><i class="fas fa-history"></i> Recent Booking History (Last 50)</h3>
            <div class="table-container">
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Vehicle</th>
                            <th>Parking Area</th>
                            <th>Space</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "
                            SELECT 
                                b.booking_ID,
                                u.username,
                                u.user_ID,
                                v.license_plate,
                                v.vehicle_type,
                                v.vehicle_model,
                                pl.parkingLot_name,
                                ps.space_number,
                                b.booking_date,
                                b.start_time,
                                b.end_time,
                                b.booking_status,
                                b.session_started_at,
                                b.session_ended_at
                            FROM Booking b
                            JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                            JOIN User u ON v.user_ID = u.user_ID
                            JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
                            JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
                            ORDER BY b.created_at DESC
                            LIMIT 50
                        ";
                        
                        $stmt = $db->query($sql);
                        $history = $stmt->fetchAll();
                        
                        foreach ($history as $record):
                            $duration = '';
                            if ($record['session_started_at'] && $record['session_ended_at']) {
                                $start = new DateTime($record['session_started_at']);
                                $end = new DateTime($record['session_ended_at']);
                                $diff = $start->diff($end);
                                $duration = $diff->format('%hh %im');
                            }
                        ?>
                        <tr>
                            <td>#<?php echo $record['booking_ID']; ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($record['username']); ?></div>
                                <div style="font-size: 11px; color: #6b7280;"><?php echo $record['user_ID']; ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($record['vehicle_type']); ?></div>
                                <div style="font-size: 11px; color: #6b7280;"><?php echo htmlspecialchars($record['license_plate']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($record['parkingLot_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['space_number']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($record['booking_date'])); ?></td>
                            <td><?php echo $duration ?: 'N/A'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $record['booking_status']; ?>">
                                    <?php echo ucfirst($record['booking_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Tab 3: Active Monitoring -->
    <div class="tab-content <?php echo $activeTab === 'monitoring' ? 'active' : ''; ?>">
        <h3 style="margin-bottom: 20px; color: #1f2937;">
            <i class="fas fa-broadcast-tower"></i> Currently Active Parking Sessions
        </h3>
        
        <div class="active-sessions">
            <?php
            $sql = "
                SELECT 
                    b.*,
                    u.username,
                    u.user_ID,
                    ps.space_number,
                    pl.parkingLot_name,
                    b.actual_plate_number,
                    v.vehicle_type,
                    v.vehicle_model
                FROM Booking b
                JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
                JOIN User u ON v.user_ID = u.user_ID
                JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
                JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
                WHERE b.booking_status = 'active'
                ORDER BY b.session_started_at DESC
            ";
            
            $stmt = $db->query($sql);
            $activeSessions = $stmt->fetchAll();
            
            if (empty($activeSessions)):
            ?>
            <div style="background: white; padding: 40px; text-align: center; border-radius: 8px; color: #6b7280;">
                <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No active parking sessions at the moment</p>
            </div>
            <?php else: ?>
            <?php foreach ($activeSessions as $session): 
                $startTime = new DateTime($session['session_started_at']);
                $now = new DateTime();
                $elapsed = $startTime->diff($now);
            ?>
            <div class="session-card">
                <div class="session-info">
                    <div class="session-detail">
                        <span class="session-label">Space</span>
                        <span class="session-value"><?php echo htmlspecialchars($session['space_number']); ?></span>
                    </div>
                    
                    <div class="session-detail">
                        <span class="session-label">Area</span>
                        <span class="session-value"><?php echo htmlspecialchars($session['parkingLot_name']); ?></span>
                    </div>
                    
                    <div class="session-detail">
                        <span class="session-label">Student</span>
                        <span class="session-value">
                            <?php echo htmlspecialchars($session['username']); ?>
                            <small style="color: #6b7280;">(<?php echo $session['user_ID']; ?>)</small>
                        </span>
                    </div>
                    
                    <div class="session-detail">
                        <span class="session-label">Vehicle</span>
                        <span class="session-value"><?php echo htmlspecialchars($session['actual_plate_number']); ?></span>
                    </div>
                    
                    <div class="session-detail">
                        <span class="session-label">Started</span>
                        <span class="session-value"><?php echo $startTime->format('g:i A'); ?></span>
                    </div>
                    
                    <div class="session-detail">
                        <span class="session-label">Elapsed</span>
                        <span class="session-value"><?php echo $elapsed->format('%h:%I'); ?></span>
                    </div>
                </div>
                
                <div>
                    <span class="status-badge status-active">ACTIVE</span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Space Occupancy Summary -->
        <div class="stats-grid" style="margin-top: 30px;">
            <?php
            $sql = "
                SELECT 
                    pl.parkingLot_name,
                    COUNT(ps.space_ID) as total_spaces,
                    COUNT(CASE WHEN b.booking_status = 'active' THEN 1 END) as occupied_spaces
                FROM ParkingLot pl
                LEFT JOIN ParkingSpace ps ON pl.parkingLot_ID = ps.parkingLot_ID
                LEFT JOIN Booking b ON ps.space_ID = b.space_ID 
                    AND b.booking_date = CURDATE()
                    AND b.booking_status = 'active'
                WHERE pl.parkingLot_type = 'Student'
                GROUP BY pl.parkingLot_ID
                ORDER BY pl.parkingLot_name
            ";
            
            $stmt = $db->query($sql);
            $occupancy = $stmt->fetchAll();
            
            foreach ($occupancy as $area):
                $available = $area['total_spaces'] - $area['occupied_spaces'];
                $occupancyRate = $area['total_spaces'] > 0 ? round(($area['occupied_spaces'] / $area['total_spaces']) * 100) : 0;
                $cardClass = $occupancyRate > 80 ? 'red' : ($occupancyRate > 50 ? 'yellow' : 'green');
            ?>
            <div class="stat-card <?php echo $cardClass; ?>">
                <h3><?php echo htmlspecialchars($area['parkingLot_name']); ?></h3>
                <div class="stat-value"><?php echo $available; ?> / <?php echo $area['total_spaces']; ?></div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                    Available / Total (<?php echo $occupancyRate; ?>% occupied)
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="detailsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Booking Details</h3>
            <button onclick="closeDetailsModal()" class="close-btn">×</button>
        </div>
        <div id="modalBody" style="padding: 20px;">
            <!-- Details will be populated by JavaScript -->
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const status = document.getElementById('filter-status').value;
    const date = document.getElementById('filter-date').value;
    const student = document.getElementById('filter-student').value;
    
    const params = new URLSearchParams();
    params.set('tab', 'manage');
    if (status) params.set('status', status);
    if (date) params.set('date', date);
    if (student) params.set('student', student);
    
    window.location.href = '?' + params.toString();
}

function viewBookingDetails(booking) {
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('modalBody');
    
    // Calculate session duration if available
    let sessionDuration = 'N/A';
    if (booking.actual_start_time && booking.actual_end_time) {
        const start = new Date(booking.actual_start_time);
        const end = new Date(booking.actual_end_time);
        const diffMs = end - start;
        const diffMins = Math.floor(diffMs / 60000);
        const hours = Math.floor(diffMins / 60);
        const minutes = diffMins % 60;
        
        if (hours > 0 && minutes > 0) {
            sessionDuration = `${hours} hour${hours > 1 ? 's' : ''} ${minutes} minute${minutes > 1 ? 's' : ''}`;
        } else if (hours > 0) {
            sessionDuration = `${hours} hour${hours > 1 ? 's' : ''}`;
        } else {
            sessionDuration = `${minutes} minute${minutes > 1 ? 's' : ''}`;
        }
    }
    
    body.innerHTML = `
        <div style="display: grid; gap: 15px;">
            <div><strong>Booking ID:</strong> #${booking.booking_ID}</div>
            <div><strong>Student:</strong> ${booking.username} (${booking.user_ID})</div>
            <div><strong>Space:</strong> ${booking.space_number}</div>
            <div><strong>Area:</strong> ${booking.parkingLot_name}</div>
            <div><strong>Date:</strong> ${booking.booking_date}</div>
            <div><strong>Reserved Time:</strong> ${booking.start_time} - ${booking.end_time}</div>
            <div><strong>Vehicle Plate:</strong> ${booking.actual_plate_number || booking.booked_plate}</div>
            <div><strong>Status:</strong> <span class="status-badge status-${booking.booking_status}">${booking.booking_status.toUpperCase()}</span></div>
            ${booking.actual_start_time ? `<div><strong>Session Start Time:</strong> ${new Date(booking.actual_start_time).toLocaleString('en-MY', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })}</div>` : ''}
            ${booking.actual_start_time && booking.actual_end_time ? `<div><strong>Session Duration:</strong> ${sessionDuration}</div>` : ''}
            ${booking.actual_end_time ? `<div><strong>Session End Time:</strong> ${new Date(booking.actual_end_time).toLocaleString('en-MY', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })}</div>` : ''}
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target === modal) {
        closeDetailsModal();
    }
}
</script>

<?php require_once __DIR__ . '/../../layout.php'; renderFooter(); ?>
