<?php
/**
 * Database Helper Functions
 * Parking Management System
 */

require_once 'db_config.php';

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
    // Check both email and username
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

// ============================================
// Vehicle Functions
// ============================================

function addVehicle($user_id, $vehicle_type, $vehicle_model, $license_plate, $grant_status) {
    $db = getDB();
    $sql = "INSERT INTO Vehicle (user_ID, vehicle_type, vehicle_model, license_plate, grant_status) 
            VALUES (:user_id, :vehicle_type, :vehicle_model, :license_plate, :grant_status)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':user_id' => $user_id,
        ':vehicle_type' => $vehicle_type,
        ':vehicle_model' => $vehicle_model,
        ':license_plate' => $license_plate,
        ':grant_status' => $grant_status
    ]);
}

function getVehiclesByUser($user_id) {
    $db = getDB();
    $sql = "SELECT * FROM Vehicle WHERE user_ID = :user_id";
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

function createTicket($vehicle_id, $user_id, $violation_id, $description) {
    $db = getDB();
    $sql = "INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, description) 
            VALUES (:vehicle_id, :user_id, :violation_id, 'Unpaid', :description)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':vehicle_id' => $vehicle_id,
        ':user_id' => $user_id,
        ':violation_id' => $violation_id,
        ':description' => $description
    ]);
}

function getTicketsByUser($user_id) {
    $db = getDB();
    $sql = "SELECT * FROM user_tickets_detail WHERE email IN (SELECT email FROM User WHERE user_ID = :user_id)";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function updateTicketStatus($ticket_id, $status) {
    $db = getDB();
    $sql = "UPDATE Ticket SET ticket_status = :status WHERE ticket_ID = :ticket_id";
    $stmt = $db->prepare($sql);
    return $stmt->execute([
        ':ticket_id' => $ticket_id,
        ':status' => $status
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

?>
