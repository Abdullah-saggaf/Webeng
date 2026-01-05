<?php
/**
 * Database Configuration & Functions
 * MyParking Management System
 */

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// ============================================
// Database Configuration
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'parking_management');

// ============================================
// QR Code Base URL Configuration (Module 3)
// ============================================
// IMPORTANT: Configure this for QR codes to work on mobile phones
//
// Structure: http://YOUR_IP:PORT/PROJECT_FOLDER
//
// YOUR_IP: Your PC's local network IP (find with: ipconfig in cmd)
//          Example: 192.168.0.77, 192.168.1.100, etc.
//
// PORT: Only add if NOT using default port 80
//       Default Apache: :80 (omit this, just use http://IP)
//       Custom port: :3000, :8080, etc.
//       Examples:
//         - http://192.168.0.77 (default port 80)
//         - http://192.168.0.77:3000 (custom port)
//
// PROJECT_FOLDER: Path from htdocs to your project
//                 For XAMPP: c:\xampp\htdocs\FOLDER\PROJECT
//                 URL becomes: http://IP/FOLDER/PROJECT
//                 Example: /Webeng/MyParking-System
//
// WHY 404 "Not Found" happens:
// 1. Wrong IP - phone can't reach server
// 2. Wrong PORT - server listening on different port
// 3. Wrong FOLDER - Apache looks in wrong directory
// 4. Missing file - the PHP file doesn't exist at that path
//
// HOW TO FIX:
// 1. Find your IP: Run 'ipconfig' in cmd, look for IPv4 Address
// 2. Check port: Apache usually uses 80 (http) or 443 (https)
// 3. Verify folder: Your project is in c:\xampp\htdocs\Webeng\MyParking-System
//    So URL folder is: /Webeng/MyParking-System
// 4. Test URL in browser: http://192.168.0.77/Webeng/MyParking-System/module03/student/parking_session.php
//
// Current Configuration:
define('QR_BASE_URL', 'http://192.168.0.77/Webeng/MyParking-System');

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

function setVehicleStatus($vehicle_id, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE Vehicle SET grant_status = :status WHERE vehicle_ID = :vehicle_id");
    return $stmt->execute([
        ':vehicle_id' => $vehicle_id,
        ':status' => $status
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

/**
 * Generate a unique QR code for tickets (Module 04)
 * @return string 32-character unique code
 */
function generateTicketQrCode() {
    return bin2hex(random_bytes(16)); // 32 characters
}

/**
 * Ensure ticket has a QR code, generate if missing (Module 04)
 * @param int $ticketId
 * @return string QR code value
 */
function ensureTicketHasQrCode($ticketId) {
    $db = getDB();
    
    // Check if ticket already has QR code
    $sql = "SELECT qr_code_value FROM Ticket WHERE ticket_ID = :ticket_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':ticket_id' => $ticketId]);
    $result = $stmt->fetch();
    
    if (!$result) {
        throw new Exception("Ticket not found");
    }
    
    // If QR code exists and not empty, return it
    if (!empty($result['qr_code_value'])) {
        return $result['qr_code_value'];
    }
    
    // Generate unique QR code
    $maxAttempts = 10;
    $attempts = 0;
    
    do {
        $qrCode = generateTicketQrCode();
        
        // Check if unique
        $checkSql = "SELECT COUNT(*) as count FROM Ticket WHERE qr_code_value = :qr_code";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':qr_code' => $qrCode]);
        $exists = $checkStmt->fetch()['count'] > 0;
        
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    if ($exists) {
        throw new Exception("Failed to generate unique QR code");
    }
    
    // Update ticket with QR code
    $updateSql = "UPDATE Ticket SET qr_code_value = :qr_code WHERE ticket_ID = :ticket_id";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([
        ':qr_code' => $qrCode,
        ':ticket_id' => $ticketId
    ]);
    
    return $qrCode;
}

/**
 * Create a new ticket (Module 04 compliant)
 * @return array ['ticket_id' => int, 'qr_code_value' => string]
 */
function createTicket($vehicleId, $userId, $violationId, $description, $issuedAt = null) {
    $db = getDB();
    
    // Generate unique QR code
    $maxAttempts = 10;
    $attempts = 0;
    
    do {
        $qrCode = generateTicketQrCode();
        
        // Check if unique
        $checkSql = "SELECT COUNT(*) as count FROM Ticket WHERE qr_code_value = :qr_code";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([':qr_code' => $qrCode]);
        $exists = $checkStmt->fetch()['count'] > 0;
        
        $attempts++;
    } while ($exists && $attempts < $maxAttempts);
    
    if ($exists) {
        throw new Exception("Failed to generate unique QR code");
    }
    
    // Insert ticket with 'Completed' status and QR code
    $sql = "INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description, qr_code_value) 
            VALUES (:vehicle_id, :user_id, :violation_id, 'Completed', :issued_at, :description, :qr_code)";
    $stmt = $db->prepare($sql);
    $success = $stmt->execute([
        ':vehicle_id' => $vehicleId,
        ':user_id' => $userId,
        ':violation_id' => $violationId,
        ':issued_at' => $issuedAt ?? date('Y-m-d H:i:s'),
        ':description' => $description,
        ':qr_code' => $qrCode
    ]);
    
    if (!$success) {
        throw new Exception("Failed to create ticket");
    }
    
    $ticketId = (int)$db->lastInsertId();
    
    // Recalculate user points
    recalculateUserPoints($userId);
    
    return [
        'ticket_id' => $ticketId,
        'qr_code_value' => $qrCode
    ];
}

function getTicketsByUser($userId) {
    $db = getDB();
    $sql = "SELECT 
                t.*,
                v.license_plate,
                v.vehicle_type,
                vl.violation_type,
                vl.violation_points
            FROM Ticket t
            LEFT JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
            INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
            WHERE t.user_ID = :user_id
            ORDER BY t.issued_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Update ticket status (Module 04 compliant - only Completed/Cancelled)
 * @param int $ticketId
 * @param string $newStatus 'Completed' or 'Cancelled'
 * @return bool
 */
function updateTicketStatus($ticketId, $newStatus) {
    $db = getDB();
    
    // Only allow 'Completed' or 'Cancelled'
    if (!in_array($newStatus, ['Completed', 'Cancelled'])) {
        throw new Exception("Invalid ticket status. Only 'Completed' or 'Cancelled' are allowed.");
    }
    
    // Get user_ID for this ticket first
    $getUserSql = "SELECT user_ID FROM Ticket WHERE ticket_ID = :ticket_id";
    $getUserStmt = $db->prepare($getUserSql);
    $getUserStmt->execute([':ticket_id' => $ticketId]);
    $ticket = $getUserStmt->fetch();
    
    if (!$ticket) {
        return false;
    }
    
    // Update status
    $sql = "UPDATE Ticket SET ticket_status = :status WHERE ticket_ID = :ticket_id";
    $stmt = $db->prepare($sql);
    $success = $stmt->execute([
        ':status' => $newStatus,
        ':ticket_id' => $ticketId
    ]);
    
    // Recalculate points after status change
    if ($success) {
        recalculateUserPoints($ticket['user_ID']);
    }
    
    return $success;
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
// Module 04: QR Code & Points Management Functions
// ============================================

/**
 * Get ticket details by QR code for public view (Module 04)
 * @param string $code QR code value
 * @return array|false Ticket details with all joined data
 */
function getTicketDetailsByQrCode($code) {
    $db = getDB();
    $sql = "SELECT 
                t.ticket_ID,
                t.ticket_status,
                t.issued_at,
                t.description,
                t.qr_code_value,
                u.user_ID,
                u.username,
                u.email,
                v.vehicle_ID,
                v.license_plate,
                v.vehicle_type,
                v.vehicle_model,
                vl.violation_ID,
                vl.violation_type,
                vl.violation_points
            FROM Ticket t
            INNER JOIN User u ON t.user_ID = u.user_ID
            LEFT JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
            INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
            WHERE t.qr_code_value = :code
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':code' => $code]);
    return $stmt->fetch();
}

/**
 * Get ticket details by ticket ID (Module 04)
 * @param int $ticketId
 * @return array|false Ticket details with all joined data
 */
function getTicketDetailsById($ticketId) {
    $db = getDB();
    $sql = "SELECT 
                t.ticket_ID,
                t.ticket_status,
                t.issued_at,
                t.description,
                t.qr_code_value,
                u.user_ID,
                u.username,
                u.email,
                v.vehicle_ID,
                v.license_plate,
                v.vehicle_type,
                v.vehicle_model,
                vl.violation_ID,
                vl.violation_type,
                vl.violation_points
            FROM Ticket t
            INNER JOIN User u ON t.user_ID = u.user_ID
            LEFT JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
            INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
            WHERE t.ticket_ID = :ticket_id
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':ticket_id' => $ticketId]);
    return $stmt->fetch();
}

/**
 * Recalculate user's total demerit points from all Completed tickets (Module 04)
 * @param string $userId
 * @return int Total points
 */
function recalculateUserPoints($userId) {
    $db = getDB();
    
    // Calculate total points from Completed tickets only
    $sql = "SELECT COALESCE(SUM(v.violation_points), 0) AS total
            FROM Ticket t
            INNER JOIN Violation v ON t.violation_ID = v.violation_ID
            WHERE t.user_ID = :user_id AND t.ticket_status = 'Completed'";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $total = (int)$stmt->fetch()['total'];
    
    // Insert or update User_points
    $updateSql = "INSERT INTO User_points (user_ID, total_points) 
                  VALUES (:user_id, :total) 
                  ON DUPLICATE KEY UPDATE total_points = :total";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([
        ':user_id' => $userId,
        ':total' => $total
    ]);
    
    return $total;
}

/**
 * Delete ticket and recalculate points (Module 04)
 * @param int $ticketId
 * @return bool Success
 */
function deleteTicketAndRecalc($ticketId) {
    $db = getDB();
    
    // Get user_ID first
    $sql = "SELECT user_ID FROM Ticket WHERE ticket_ID = :ticket_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':ticket_id' => $ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        return false;
    }
    
    // Delete ticket
    $deleteSql = "DELETE FROM Ticket WHERE ticket_ID = :ticket_id";
    $deleteStmt = $db->prepare($deleteSql);
    $success = $deleteStmt->execute([':ticket_id' => $ticketId]);
    
    // Recalculate points
    if ($success) {
        recalculateUserPoints($ticket['user_ID']);
    }
    
    return $success;
}

/**
 * Get only the 3 required violation types for Module 04
 * @return array Violations ordered by points ascending
 */
function getModule4ViolationsOnly() {
    $db = getDB();
    $sql = "SELECT * FROM Violation 
            WHERE violation_type IN (
                'Parking Violation',
                'Not Comply with Campus Traffic Regulations',
                'Accident Caused'
            )
            ORDER BY violation_points ASC";
    $stmt = $db->query($sql);
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
                vl.violation_points
            FROM Ticket t
            LEFT JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
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
                vl.violation_points
            FROM Ticket t
            LEFT JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
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
