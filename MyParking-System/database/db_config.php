<?php
/**
 * Database Configuration & Functions
 * MyParking Management System
 */

// ============================================
// Database Configuration
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'parking_management');

// ============================================
// Database Connection
// ============================================
function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// ============================================
// User Functions
// ============================================

function createUser($user_id, $username, $email, $phone, $password, $user_type) {
    $db = getDB();
    $sql = "INSERT INTO User (user_ID, username, email, phone_number, password, user_type) 
            VALUES (:user_id, :username, :email, :phone, :password, :user_type)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':user_id' => $user_id,
        ':username' => $username,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':user_type' => $user_type
    ]);
}

function getUserById($user_id) {
    $db = getDB();
    $sql = "SELECT * FROM User WHERE user_ID = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetch();
}

function getUserByEmail($email) {
    $db = getDB();
    $sql = "SELECT * FROM User WHERE email = :email";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
}

function verifyUserLogin($email_or_username, $password) {
    $db = getDB();
    $sql = "SELECT * FROM User WHERE email = :identifier OR username = :identifier2";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':identifier' => $email_or_username,
        ':identifier2' => $email_or_username
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }

    return false;
}

function getUsers(array $filters = []) {
    $db = getDB();
    $sql = "SELECT user_ID, username, email, phone_number, user_type, created_at, updated_at FROM User WHERE 1=1";
    $params = [];

    if (!empty($filters['user_type'])) {
        $sql .= " AND user_type = :user_type";
        $params[':user_type'] = $filters['user_type'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (username LIKE :search OR email LIKE :search OR user_ID LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateUser($user_id, array $fields) {
    $db = getDB();

    $allowed = ['username', 'email', 'phone_number', 'user_type', 'password'];
    $setParts = [];
    $params = [':user_id' => $user_id];

    foreach ($allowed as $column) {
        if (!isset($fields[$column])) {
            continue;
        }

        $placeholder = ':' . $column;
        $value = $column === 'password' ? password_hash($fields[$column], PASSWORD_DEFAULT) : $fields[$column];

        $setParts[] = "$column = $placeholder";
        $params[$placeholder] = $value;
    }

    if (empty($setParts)) {
        return false;
    }

    $sql = "UPDATE User SET " . implode(', ', $setParts) . " WHERE user_ID = :user_id";
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

function deleteUser($user_id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM User WHERE user_ID = :user_id");
    return $stmt->execute([':user_id' => $user_id]);
}

// ============================================
// Vehicle Functions
// ============================================

function addVehicle($user_id, $vehicle_type, $vehicle_model, $license_plate, $grant_document_path) {
    $db = getDB();
    $sql = "INSERT INTO Vehicle (user_ID, vehicle_type, vehicle_model, license_plate, grant_document, grant_status) 
            VALUES (:user_id, :vehicle_type, :vehicle_model, :license_plate, :grant_document, 'Pending')";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':user_id' => $user_id,
        ':vehicle_type' => $vehicle_type,
        ':vehicle_model' => $vehicle_model,
        ':license_plate' => $license_plate,
        ':grant_document' => $grant_document_path
    ]);
}

function getVehiclesByUser($user_id) {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE user_ID = :user_id ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function getVehicleById($vehicle_id) {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE vehicle_ID = :vehicle_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':vehicle_id' => $vehicle_id]);
    return $stmt->fetch();
}

function getVehicleForUser($vehicle_id, $user_id) {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE vehicle_ID = :vehicle_id AND user_ID = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':vehicle_id' => $vehicle_id,
        ':user_id' => $user_id
    ]);
    return $stmt->fetch();
}

function updateVehicle($vehicle_id, $user_id, $fields) {
    $db = getDB();
    $allowed = ['vehicle_type', 'vehicle_model', 'license_plate', 'grant_document', 'grant_status', 'rejection_reason'];
    $setParts = [];
    $params = [
        ':vehicle_id' => $vehicle_id,
        ':user_id' => $user_id
    ];

    foreach ($allowed as $column) {
        if (!isset($fields[$column])) {
            continue;
        }

        $placeholder = ':' . $column;
        $setParts[] = "$column = $placeholder";
        $params[$placeholder] = $fields[$column];
    }

    if (empty($setParts)) {
        return false;
    }

    $sql = "UPDATE Vehicle SET " . implode(', ', $setParts) . " WHERE vehicle_ID = :vehicle_id AND user_ID = :user_id";
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

function deleteVehicle($vehicle_id, $user_id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM Vehicle WHERE vehicle_ID = :vehicle_id AND user_ID = :user_id");
    return $stmt->execute([
        ':vehicle_id' => $vehicle_id,
        ':user_id' => $user_id
    ]);
}

function getPendingVehicles(array $filters = []) {
    $db = getDB();
    $sql = "SELECT v.*, u.username, u.user_ID AS owner_id, u.email 
            FROM Vehicle v
            JOIN User u ON v.user_ID = u.user_ID
            WHERE v.grant_status = 'Pending'";
    $params = [];

    if (!empty($filters['license_plate'])) {
        $sql .= " AND v.license_plate LIKE :plate";
        $params[':plate'] = '%' . $filters['license_plate'] . '%';
    }

    if (!empty($filters['user_ID'])) {
        $sql .= " AND v.user_ID = :user_id";
        $params[':user_id'] = $filters['user_ID'];
    }

    $sql .= " ORDER BY v.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function setVehicleStatus($vehicle_id, $status, $reason = null) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE Vehicle SET grant_status = :status, rejection_reason = :reason WHERE vehicle_ID = :vehicle_id");
    return $stmt->execute([
        ':vehicle_id' => $vehicle_id,
        ':status' => $status,
        ':reason' => $reason
    ]);
}

function getApprovedVehicles() {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE grant_status = 'Approved' ORDER BY created_at DESC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

function getRejectedVehicles() {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE grant_status = 'Rejected' ORDER BY created_at DESC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

// ============================================
// Booking Functions
// ============================================

function createBooking($space_id, $vehicle_id, $booking_date, $start_time, $end_time, $qr_code) {
    $db = getDB();
    $sql = "INSERT INTO Booking (space_ID, vehicle_ID, booking_date, start_time, end_time, booking_status, qr_code_value) 
            VALUES (:space_id, :vehicle_id, :booking_date, :start_time, :end_time, 'Scheduled', :qr_code)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':space_id' => $space_id,
        ':vehicle_id' => $vehicle_id,
        ':booking_date' => $booking_date,
        ':start_time' => $start_time,
        ':end_time' => $end_time,
        ':qr_code' => $qr_code
    ]);
}

function getActiveBookings() {
    $db = getDB();
    $sql = "SELECT * FROM active_bookings ORDER BY booking_date DESC, start_time DESC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

function getBookingsByUser($user_id) {
    $db = getDB();
    $sql = "SELECT b.*, ps.space_number, pl.parkingLot_name, v.license_plate
            FROM Booking b
            JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
            JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
            JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
            WHERE v.user_ID = :user_id
            ORDER BY b.booking_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function updateBookingStatus($booking_id, $status) {
    $db = getDB();
    $sql = "UPDATE Booking SET booking_status = :status WHERE booking_ID = :booking_id";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':booking_id' => $booking_id,
        ':status' => $status
    ]);
}

// ============================================
// Parking Space Functions
// ============================================

function getAvailableSpaces($parking_lot_id, $date, $start_time, $end_time) {
    $db = getDB();
    $sql = "SELECT ps.* 
            FROM ParkingSpace ps
            WHERE ps.parkingLot_ID = :parking_lot_id
            AND ps.space_ID NOT IN (
                SELECT space_ID FROM Booking 
                WHERE booking_date = :date 
                AND booking_status IN ('Active', 'Scheduled')
                AND (
                    (start_time <= :start_time AND end_time > :start_time) OR
                    (start_time < :end_time AND end_time >= :end_time) OR
                    (start_time >= :start_time AND end_time <= :end_time)
                )
            )";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':parking_lot_id' => $parking_lot_id,
        ':date' => $date,
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ]);
    return $stmt->fetchAll();
}

function getParkingLotAvailability() {
    $db = getDB();
    $sql = "SELECT * FROM parking_space_availability";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

// ============================================
// Ticket Functions
// ============================================

function createTicket($vehicleId, $userId, $violationId, $description, $issuedAt = null) {
    $db = getDB();
    $sql = "INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description) 
            VALUES (:vehicle_id, :user_id, :violation_id, 'Unpaid', :issued_at, :description)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':vehicle_id' => $vehicleId,
        ':user_id' => $userId,
        ':violation_id' => $violationId,
        ':issued_at' => $issuedAt ?? date('Y-m-d H:i:s'),
        ':description' => $description
    ]);
}

function getTicketsByUser($userId) {
    $db = getDB();
    $sql = "SELECT 
                t.*,
                v.license_plate,
                v.vehicle_type,
                vl.violation_type,
                vl.violation_points,
                vl.fine_amount
            FROM Ticket t
            INNER JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
            INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
            WHERE t.user_ID = :user_id
            ORDER BY t.issued_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function updateTicketStatus($ticketId, $newStatus) {
    $db = getDB();
    $sql = "UPDATE Ticket SET ticket_status = :status WHERE ticket_ID = :ticket_id";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':status' => $newStatus,
        ':ticket_id' => $ticketId
    ]);
}

// ============================================
// Parking Log Functions
// ============================================

function addParkingLog($booking_id, $event_type, $remarks = null) {
    $db = getDB();
    $sql = "INSERT INTO ParkingLog (booking_ID, event_type, remarks) 
            VALUES (:booking_id, :event_type, :remarks)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':booking_id' => $booking_id,
        ':event_type' => $event_type,
        ':remarks' => $remarks
    ]);
}

function getParkingLogsByBooking($booking_id) {
    $db = getDB();
    $sql = "SELECT * FROM ParkingLog WHERE booking_ID = :booking_id ORDER BY event_time DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':booking_id' => $booking_id]);
    return $stmt->fetchAll();
}

// ============================================
// User Points Functions
// ============================================

function updateUserPoints($user_id, $points) {
    $db = getDB();
    $sql = "INSERT INTO User_points (user_ID, total_points) 
            VALUES (:user_id, :points) 
            ON DUPLICATE KEY UPDATE total_points = total_points + :points";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':user_id' => $user_id,
        ':points' => $points
    ]);
}

function getUserPoints($user_id) {
    $db = getDB();
    $sql = "SELECT total_points FROM User_points WHERE user_ID = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $result = $stmt->fetch();
    return $result ? $result['total_points'] : 0;
}

// ============================================
// Reporting Helpers (Module 01)
// ============================================

function getVehicleStatusCounts() {
    $db = getDB();
    $sql = "SELECT grant_status, COUNT(*) as total FROM Vehicle GROUP BY grant_status";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

function getStudentVehicleSummary() {
    $db = getDB();
    $sql = "SELECT 
                u.user_ID,
                u.username,
                u.email,
                COUNT(v.vehicle_ID) AS total_vehicles,
                SUM(v.grant_status = 'Approved') AS approved_count,
                SUM(v.grant_status = 'Pending') AS pending_count,
                SUM(v.grant_status = 'Rejected') AS rejected_count
            FROM User u
            LEFT JOIN Vehicle v ON u.user_ID = v.user_ID
            WHERE u.user_type = 'student'
            GROUP BY u.user_ID, u.username, u.email
            ORDER BY total_vehicles DESC, u.username";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

// ============================================
// Module 4: Traffic Summons & Demerit Points Functions
// ============================================

function getAllViolations() {
    $db = getDB();
    $sql = "SELECT * FROM Violation ORDER BY violation_type";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

function searchTickets($query) {
    $db = getDB();
    $sql = "SELECT 
                t.*,
                v.license_plate,
                v.vehicle_type,
                u.user_ID,
                u.username,
                u.email,
                vl.violation_type,
                vl.violation_points,
                vl.fine_amount
            FROM Ticket t
            INNER JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
            INNER JOIN User u ON t.user_ID = u.user_ID
            INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
            WHERE v.license_plate LIKE :query 
               OR u.user_ID LIKE :query 
               OR u.username LIKE :query
            ORDER BY t.issued_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':query' => '%' . $query . '%']);
    return $stmt->fetchAll();
}

function ensureUserPointsRow($userId) {
    $db = getDB();
    $sql = "INSERT IGNORE INTO User_points (user_ID, total_points) VALUES (:user_id, 0)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([':user_id' => $userId]);
}

function addUserPoints($userId, $pointsToAdd) {
    ensureUserPointsRow($userId);
    $db = getDB();
    $sql = "UPDATE User_points SET total_points = total_points + :points WHERE user_ID = :user_id";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':user_id' => $userId,
        ':points' => $pointsToAdd
    ]);
}

function getUserTotalPoints($userId) {
    $db = getDB();
    $sql = "SELECT total_points FROM User_points WHERE user_ID = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $result = $stmt->fetch();
    return $result ? (int)$result['total_points'] : 0;
}

function getDashboardStats($filters = []) {
    $db = getDB();
    
    // Base WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['date_from'])) {
        $where .= " AND t.issued_at >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where .= " AND t.issued_at <= :date_to";
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    if (!empty($filters['violation_id'])) {
        $where .= " AND t.violation_ID = :violation_id";
        $params[':violation_id'] = $filters['violation_id'];
    }
    
    if (!empty($filters['status'])) {
        $where .= " AND t.ticket_status = :status";
        $params[':status'] = $filters['status'];
    }
    
    // Total tickets
    $sql = "SELECT COUNT(*) as total_tickets FROM Ticket t $where";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $totalTickets = $stmt->fetch()['total_tickets'];
    
    // Total points issued
    $sql = "SELECT SUM(v.violation_points) as total_points 
            FROM Ticket t 
            INNER JOIN Violation v ON t.violation_ID = v.violation_ID 
            $where";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $totalPoints = $stmt->fetch()['total_points'] ?? 0;
    
    // Tickets by violation type
    $sql = "SELECT v.violation_type, COUNT(*) as count 
            FROM Ticket t 
            INNER JOIN Violation v ON t.violation_ID = v.violation_ID 
            $where 
            GROUP BY v.violation_type 
            ORDER BY count DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ticketsByViolation = $stmt->fetchAll();
    
    // Tickets by status
    $sql = "SELECT t.ticket_status, COUNT(*) as count 
            FROM Ticket t 
            $where 
            GROUP BY t.ticket_status 
            ORDER BY count DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ticketsByStatus = $stmt->fetchAll();
    
    return [
        'total_tickets' => $totalTickets,
        'total_points_issued' => $totalPoints,
        'tickets_by_violation_type' => $ticketsByViolation,
        'tickets_by_status' => $ticketsByStatus
    ];
}

function getVehicleByPlate($licensePlate) {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE license_plate = :plate LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':plate' => $licensePlate]);
    return $stmt->fetch();
}

function getVehicleByUserId($userId) {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE user_ID = :user_id ORDER BY vehicle_ID DESC LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

function getLatestTickets($limit = 20) {
    $db = getDB();
    $sql = "SELECT 
                t.*,
                v.license_plate,
                v.vehicle_type,
                u.user_ID,
                u.username,
                u.email,
                vl.violation_type,
                vl.violation_points,
                vl.fine_amount
            FROM Ticket t
            INNER JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
            INNER JOIN User u ON t.user_ID = u.user_ID
            INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
            ORDER BY t.issued_at DESC
            LIMIT :limit";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
?>
