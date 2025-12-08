-- ============================================
-- Parking Management System Database Schema
-- ============================================

-- Drop existing tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS ParkingLog;
DROP TABLE IF EXISTS User_points;
DROP TABLE IF EXISTS Ticket;
DROP TABLE IF EXISTS Booking;
DROP TABLE IF EXISTS ParkingSpace;
DROP TABLE IF EXISTS ParkingLot;
DROP TABLE IF EXISTS Vehicle;
DROP TABLE IF EXISTS Violation;
DROP TABLE IF EXISTS User;

-- ============================================
-- 1. User Table
-- ============================================
CREATE TABLE User (
    user_ID VARCHAR(10) PRIMARY KEY,
    username VARCHAR(20) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NULL,
    password VARCHAR(50) NOT NULL,
    user_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Vehicle Table
-- ============================================
CREATE TABLE Vehicle (
    vehicle_ID INT PRIMARY KEY AUTO_INCREMENT,
    user_ID VARCHAR(10) NOT NULL,
    vehicle_type VARCHAR(30) NOT NULL,
    vehicle_model VARCHAR(50) NULL,
    license_plate VARCHAR(15) NOT NULL UNIQUE,
    grant_document LONGBLOB NULL,
    grant_status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES User(user_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_user_id (user_ID),
    INDEX idx_license_plate (license_plate),
    INDEX idx_grant_status (grant_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. ParkingLot Table
-- ============================================
CREATE TABLE ParkingLot (
    parkingLot_ID INT PRIMARY KEY AUTO_INCREMENT,
    parkingLot_name VARCHAR(50) NOT NULL,
    parkingLot_type VARCHAR(30) NOT NULL,
    is_booking_lot BOOLEAN NOT NULL DEFAULT FALSE,
    capacity INT NOT NULL,
    INDEX idx_lot_type (parkingLot_type),
    INDEX idx_is_booking (is_booking_lot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ParkingSpace Table
-- ============================================
CREATE TABLE ParkingSpace (
    space_ID INT PRIMARY KEY AUTO_INCREMENT,
    parkingLot_ID INT NOT NULL,
    space_number VARCHAR(20) NOT NULL,
    qr_code_value VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parkingLot_ID) REFERENCES ParkingLot(parkingLot_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_space (parkingLot_ID, space_number),
    INDEX idx_lot_id (parkingLot_ID),
    INDEX idx_qr_code (qr_code_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. Booking Table
-- ============================================
CREATE TABLE Booking (
    booking_ID INT PRIMARY KEY AUTO_INCREMENT,
    space_ID INT NOT NULL,
    vehicle_ID INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    booking_status VARCHAR(20) NOT NULL,
    qr_code_value VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (space_ID) REFERENCES ParkingSpace(space_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_ID) REFERENCES Vehicle(vehicle_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_space_id (space_ID),
    INDEX idx_vehicle_id (vehicle_ID),
    INDEX idx_booking_date (booking_date),
    INDEX idx_booking_status (booking_status),
    INDEX idx_qr_code (qr_code_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. Violation Table
-- ============================================
CREATE TABLE Violation (
    violation_ID INT PRIMARY KEY AUTO_INCREMENT,
    violation_type VARCHAR(30) NOT NULL,
    violation_points INT NOT NULL,
    fine_amount INT NOT NULL,
    INDEX idx_violation_type (violation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. Ticket Table
-- ============================================
CREATE TABLE Ticket (
    ticket_ID INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_ID INT NOT NULL,
    user_ID VARCHAR(10) NOT NULL,
    violation_ID INT NOT NULL,
    ticket_status VARCHAR(20) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(100) NULL,
    FOREIGN KEY (vehicle_ID) REFERENCES Vehicle(vehicle_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_ID) REFERENCES User(user_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (violation_ID) REFERENCES Violation(violation_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_vehicle_id (vehicle_ID),
    INDEX idx_user_id (user_ID),
    INDEX idx_violation_id (violation_ID),
    INDEX idx_ticket_status (ticket_status),
    INDEX idx_issued_at (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. ParkingLog Table
-- ============================================
CREATE TABLE ParkingLog (
    log_ID INT PRIMARY KEY AUTO_INCREMENT,
    booking_ID INT NOT NULL,
    event_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_type VARCHAR(20) NOT NULL,
    remarks VARCHAR(100) NULL,
    FOREIGN KEY (booking_ID) REFERENCES Booking(booking_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_booking_id (booking_ID),
    INDEX idx_event_time (event_time),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. User_points Table
-- ============================================
CREATE TABLE User_points (
    user_ID VARCHAR(10) PRIMARY KEY,
    total_points INT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_ID) REFERENCES User(user_ID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Create Views for Common Queries
-- ============================================

-- View for active bookings
CREATE VIEW active_bookings AS
SELECT 
    b.booking_ID,
    b.booking_date,
    b.start_time,
    b.end_time,
    b.booking_status,
    u.username,
    u.email,
    v.license_plate,
    v.vehicle_type,
    ps.space_number,
    pl.parkingLot_name
FROM Booking b
JOIN Vehicle v ON b.vehicle_ID = v.vehicle_ID
JOIN User u ON v.user_ID = u.user_ID
JOIN ParkingSpace ps ON b.space_ID = ps.space_ID
JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
WHERE b.booking_status = 'Active';

-- View for user tickets with violation details
CREATE VIEW user_tickets_detail AS
SELECT 
    t.ticket_ID,
    t.ticket_status,
    t.issued_at,
    t.description,
    u.username,
    u.email,
    v.license_plate,
    v.vehicle_type,
    viol.violation_type,
    viol.violation_points,
    viol.fine_amount
FROM Ticket t
JOIN User u ON t.user_ID = u.user_ID
JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
JOIN Violation viol ON t.violation_ID = viol.violation_ID;

-- View for parking space availability
CREATE VIEW parking_space_availability AS
SELECT 
    pl.parkingLot_ID,
    pl.parkingLot_name,
    pl.parkingLot_type,
    pl.capacity,
    COUNT(DISTINCT ps.space_ID) as total_spaces,
    COUNT(DISTINCT CASE WHEN b.booking_status = 'Active' THEN b.space_ID END) as occupied_spaces,
    COUNT(DISTINCT ps.space_ID) - COUNT(DISTINCT CASE WHEN b.booking_status = 'Active' THEN b.space_ID END) as available_spaces
FROM ParkingLot pl
LEFT JOIN ParkingSpace ps ON pl.parkingLot_ID = ps.parkingLot_ID
LEFT JOIN Booking b ON ps.space_ID = b.space_ID AND b.booking_status = 'Active'
GROUP BY pl.parkingLot_ID, pl.parkingLot_name, pl.parkingLot_type, pl.capacity;

-- ============================================
-- End of Schema
-- ============================================
