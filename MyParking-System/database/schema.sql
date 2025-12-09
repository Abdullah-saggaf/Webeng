-- ============================================
-- Parking Management System Database Schema
-- ============================================

USE parking_management;

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
    email VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NULL,
    password VARCHAR(50) NOT NULL,
    user_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Vehicle Table
-- ============================================
CREATE TABLE Vehicle (
    vehicle_ID INT PRIMARY KEY AUTO_INCREMENT,
    user_ID VARCHAR(10) NOT NULL,
    vehicle_type VARCHAR(30) NOT NULL,
    vehicle_model VARCHAR(50) NULL,
    license_plate VARCHAR(15) NOT NULL,
    grant_document LONGBLOB NULL,
    grant_status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES User(user_ID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. ParkingLot Table
-- ============================================
CREATE TABLE ParkingLot (
    parkingLot_ID INT PRIMARY KEY AUTO_INCREMENT,
    parkingLot_name VARCHAR(50) NOT NULL,
    parkingLot_type VARCHAR(30) NOT NULL,
    is_booking_lot BOOLEAN NOT NULL DEFAULT FALSE,
    capacity INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ParkingSpace Table
-- ============================================
CREATE TABLE ParkingSpace (
    space_ID INT PRIMARY KEY AUTO_INCREMENT,
    parkingLot_ID INT NOT NULL,
    space_number VARCHAR(20) NOT NULL,
    qr_code_value VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parkingLot_ID) REFERENCES ParkingLot(parkingLot_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_space (parkingLot_ID, space_number)
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
    qr_code_value VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (space_ID) REFERENCES ParkingSpace(space_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_ID) REFERENCES Vehicle(vehicle_ID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. Violation Table
-- ============================================
CREATE TABLE Violation (
    violation_ID INT PRIMARY KEY AUTO_INCREMENT,
    violation_type VARCHAR(30) NOT NULL,
    violation_points INT NOT NULL,
    fine_amount INT NOT NULL
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
    FOREIGN KEY (violation_ID) REFERENCES Violation(violation_ID) ON DELETE CASCADE ON UPDATE CASCADE
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
    FOREIGN KEY (booking_ID) REFERENCES Booking(booking_ID) ON DELETE CASCADE ON UPDATE CASCADE
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
-- End of Schema
-- ============================================
