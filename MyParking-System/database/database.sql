-- ============================================
-- MyParking System - Complete Database Setup
-- ============================================
-- This script contains everything needed to set up the database
-- Including: Schema, Test Data, and Module 2 Setup
-- Run this once to create a fresh database
-- ============================================

USE parking_management;

-- ============================================
-- PART 1: DATABASE SCHEMA
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
    email VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NULL,
    password VARCHAR(255) NOT NULL,
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
    capacity INT NOT NULL,
    UNIQUE KEY unique_parking_lot_name (parkingLot_name)
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
    actual_vehicle_id INT NULL,
    actual_plate_number VARCHAR(15) NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    actual_start_time DATETIME NULL,
    actual_end_time DATETIME NULL,
    session_started_at DATETIME NULL,
    session_ended_at DATETIME NULL,
    booking_status VARCHAR(20) NOT NULL,
    qr_code_value VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (space_ID) REFERENCES ParkingSpace(space_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vehicle_ID) REFERENCES Vehicle(vehicle_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (actual_vehicle_id) REFERENCES Vehicle(vehicle_ID) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. Violation Table
-- ============================================
CREATE TABLE Violation (
    violation_ID INT PRIMARY KEY AUTO_INCREMENT,
    violation_type VARCHAR(30) NOT NULL,
    violation_points INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. Ticket Table
-- ============================================
CREATE TABLE Ticket (
    ticket_ID INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_ID INT NULL,
    user_ID VARCHAR(10) NOT NULL,
    violation_ID INT NOT NULL,
    ticket_status VARCHAR(20) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(100) NULL,
    qr_code_value VARCHAR(64) NULL,
    FOREIGN KEY (vehicle_ID) REFERENCES Vehicle(vehicle_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_ID) REFERENCES User(user_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (violation_ID) REFERENCES Violation(violation_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_ticket_qr (qr_code_value)
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
-- PART 2: TEST DATA
-- ============================================

-- Insert Test Users (passwords are all "password123")
INSERT INTO User (user_ID, username, email, phone_number, password, user_type) VALUES
('S001', 'Ahmad Ali', 'ahmad.ali@student.uitm.edu.my', '0123456789', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'student'),
('S002', 'Siti Nurhaliza', 'siti.nur@student.uitm.edu.my', '0198765432', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'student'),
('S003', 'Kumar Rajan', 'kumar.rajan@student.uitm.edu.my', '0134567890', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'student'),
('S004', 'Fatimah Ibrahim', 'fatimah.ib@student.uitm.edu.my', '0167890123', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'student'),
('S005', 'Lee Wei Ming', 'lee.wei@student.uitm.edu.my', '0145678901', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'student'),
('FK001', 'Dr. Hassan Abdullah', 'hassan.abd@uitm.edu.my', '0192345678', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'fk_staff'),
('FK002', 'Prof. Aminah Yusof', 'aminah.yusof@uitm.edu.my', '0193456789', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'fk_staff'),
('SF001', 'Encik Razak Ismail', 'razak.ismail@uitm.edu.my', '0194567890', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'safety_staff'),
('SF002', 'Puan Zarina Ahmad', 'zarina.ahmad@uitm.edu.my', '0195678901', '$2y$10$V/GINlvqCcQ0546JDoNf9.ZlHtOgdE4eO5GWH5kl2O0LEsB/RRVAW', 'safety_staff');

-- Insert Test Vehicles
INSERT INTO Vehicle (user_ID, vehicle_type, vehicle_model, license_plate, grant_document, grant_status) VALUES
('S001', 'Car', 'Perodua Myvi', 'WXY1234', 'storage/grants/grant_S001_sample1.pdf', 'Approved'),
('S001', 'Motorcycle', 'Honda EX5', 'VBK5678', 'storage/grants/grant_S001_sample2.pdf', 'Pending'),
('S002', 'Car', 'Toyota Vios', 'ABC9876', 'storage/grants/grant_S002_sample1.pdf', 'Approved'),
('S002', 'Car', 'Proton Saga', 'JKL5432', 'storage/grants/grant_S002_sample2.pdf', 'Rejected'),
('S003', 'Motorcycle', 'Yamaha Y15ZR', 'MNO7890', 'storage/grants/grant_S003_sample1.pdf', 'Pending'),
('S004', 'Car', 'Perodua Axia', 'PQR3456', 'storage/grants/grant_S004_sample1.pdf', 'Pending'),
('S004', 'Motorcycle', 'Honda Wave', 'STU6789', 'storage/grants/grant_S004_sample2.pdf', 'Approved'),
('S005', 'Car', 'Proton X50', 'VWX2345', 'storage/grants/grant_S005_sample1.pdf', 'Rejected'),
('S005', 'Car', 'Honda City', 'YZA8901', 'storage/grants/grant_S005_sample2.pdf', 'Pending');

-- ============================================
-- PART 3: MODULE 2 SETUP
-- ============================================

-- Add Indexes for Performance
CREATE INDEX idx_parking_lot_type ON ParkingLot(parkingLot_type);
CREATE INDEX idx_space_qr_code ON ParkingSpace(qr_code_value);
CREATE INDEX idx_booking_date_status ON Booking(booking_date, booking_status);
CREATE INDEX idx_booking_space ON Booking(space_ID, booking_date);

-- ============================================
-- Seed Data - Parking Lots (Areas)
-- ============================================

INSERT INTO ParkingLot (parkingLot_name, parkingLot_type, is_booking_lot, capacity) VALUES
('Main Campus Lot A', 'Student', TRUE, 100),
('Main Campus Lot B', 'Staff', TRUE, 50),
('Faculty Building Parking', 'Staff', TRUE, 30),
('Visitor Parking Zone', 'Visitor', FALSE, 25),
('VIP Reserved Area', 'VIP', TRUE, 10),
('Sports Complex Lot', 'General', FALSE, 40),
('Library Parking', 'Student', TRUE, 60),
('Administration Building', 'Staff', TRUE, 20);

-- ============================================
-- Seed Data - Parking Spaces
-- ============================================

-- Main Campus Lot A (Student) - 20 spaces
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
(1, 'A-001', 'MYPARKING:SPACE:1001'),
(1, 'A-002', 'MYPARKING:SPACE:1002'),
(1, 'A-003', 'MYPARKING:SPACE:1003'),
(1, 'A-004', 'MYPARKING:SPACE:1004'),
(1, 'A-005', 'MYPARKING:SPACE:1005'),
(1, 'A-006', 'MYPARKING:SPACE:1006'),
(1, 'A-007', 'MYPARKING:SPACE:1007'),
(1, 'A-008', 'MYPARKING:SPACE:1008'),
(1, 'A-009', 'MYPARKING:SPACE:1009'),
(1, 'A-010', 'MYPARKING:SPACE:1010'),
(1, 'A-011', 'MYPARKING:SPACE:1011'),
(1, 'A-012', 'MYPARKING:SPACE:1012'),
(1, 'A-013', 'MYPARKING:SPACE:1013'),
(1, 'A-014', 'MYPARKING:SPACE:1014'),
(1, 'A-015', 'MYPARKING:SPACE:1015'),
(1, 'A-016', 'MYPARKING:SPACE:1016'),
(1, 'A-017', 'MYPARKING:SPACE:1017'),
(1, 'A-018', 'MYPARKING:SPACE:1018'),
(1, 'A-019', 'MYPARKING:SPACE:1019'),
(1, 'A-020', 'MYPARKING:SPACE:1020');

-- Main Campus Lot B (Staff) - 10 spaces
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
(2, 'B-001', 'MYPARKING:SPACE:2001'),
(2, 'B-002', 'MYPARKING:SPACE:2002'),
(2, 'B-003', 'MYPARKING:SPACE:2003'),
(2, 'B-004', 'MYPARKING:SPACE:2004'),
(2, 'B-005', 'MYPARKING:SPACE:2005'),
(2, 'B-006', 'MYPARKING:SPACE:2006'),
(2, 'B-007', 'MYPARKING:SPACE:2007'),
(2, 'B-008', 'MYPARKING:SPACE:2008'),
(2, 'B-009', 'MYPARKING:SPACE:2009'),
(2, 'B-010', 'MYPARKING:SPACE:2010');

-- Faculty Building Parking (Staff) - 10 spaces
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
(3, 'F-001', 'MYPARKING:SPACE:3001'),
(3, 'F-002', 'MYPARKING:SPACE:3002'),
(3, 'F-003', 'MYPARKING:SPACE:3003'),
(3, 'F-004', 'MYPARKING:SPACE:3004'),
(3, 'F-005', 'MYPARKING:SPACE:3005'),
(3, 'F-006', 'MYPARKING:SPACE:3006'),
(3, 'F-007', 'MYPARKING:SPACE:3007'),
(3, 'F-008', 'MYPARKING:SPACE:3008'),
(3, 'F-009', 'MYPARKING:SPACE:3009'),
(3, 'F-010', 'MYPARKING:SPACE:3010');

-- Visitor Parking Zone - 5 spaces
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
(4, 'V-001', 'MYPARKING:SPACE:4001'),
(4, 'V-002', 'MYPARKING:SPACE:4002'),
(4, 'V-003', 'MYPARKING:SPACE:4003'),
(4, 'V-004', 'MYPARKING:SPACE:4004'),
(4, 'V-005', 'MYPARKING:SPACE:4005');

-- VIP Reserved Area - 3 spaces
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
(5, 'VIP-001', 'MYPARKING:SPACE:5001'),
(5, 'VIP-002', 'MYPARKING:SPACE:5002'),
(5, 'VIP-003', 'MYPARKING:SPACE:5003');

-- Library Parking (Student) - 10 spaces
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
(7, 'L-001', 'MYPARKING:SPACE:7001'),
(7, 'L-002', 'MYPARKING:SPACE:7002'),
(7, 'L-003', 'MYPARKING:SPACE:7003'),
(7, 'L-004', 'MYPARKING:SPACE:7004'),
(7, 'L-005', 'MYPARKING:SPACE:7005'),
(7, 'L-006', 'MYPARKING:SPACE:7006'),
(7, 'L-007', 'MYPARKING:SPACE:7007'),
(7, 'L-008', 'MYPARKING:SPACE:7008'),
(7, 'L-009', 'MYPARKING:SPACE:7009'),
(7, 'L-010', 'MYPARKING:SPACE:7010');

-- ============================================
-- Sample Booking Data (for testing availability)
-- ============================================

INSERT INTO Booking (space_ID, vehicle_ID, booking_date, start_time, end_time, booking_status, qr_code_value) VALUES
-- Some spaces occupied for today
(1, 1, CURDATE(), '08:00:00', '17:00:00', 'confirmed', 'BOOKING:1001:TODAY'),
(2, 2, CURDATE(), '09:00:00', '18:00:00', 'active', 'BOOKING:1002:TODAY'),
(3, 3, CURDATE(), '07:30:00', '16:30:00', 'confirmed', 'BOOKING:1003:TODAY'),
(11, 1, CURDATE(), '08:00:00', '17:00:00', 'confirmed', 'BOOKING:2001:TODAY'),
(21, 2, CURDATE(), '08:30:00', '17:30:00', 'active', 'BOOKING:3001:TODAY'),

-- Future bookings (tomorrow)
(4, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '17:00:00', 'confirmed', 'BOOKING:1004:FUTURE'),
(5, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '18:00:00', 'confirmed', 'BOOKING:1005:FUTURE');

-- ============================================
-- SETUP COMPLETE!
-- ============================================

-- Summary of what was created:
-- ✅ Database schema with all tables
-- ✅ 9 test users (5 students, 2 FK staff, 2 safety staff)
-- ✅ 9 test vehicles with various approval statuses
-- ✅ 8 parking lots/areas
-- ✅ 58 parking spaces with QR codes
-- ✅ 7 sample bookings (5 today, 2 tomorrow)
-- ✅ Performance indexes on key columns
-- ✅ All constraints and foreign keys

-- Test Credentials (all passwords: password123)
-- ============================================
-- FK STAFF (Admin):
--   Email: hassan.abd@uitm.edu.my | ID: FK001
--   Email: aminah.yusof@uitm.edu.my | ID: FK002
--
-- STUDENTS:
--   Email: ahmad.ali@student.uitm.edu.my | ID: S001
--   Email: siti.nur@student.uitm.edu.my | ID: S002
--   Email: kumar.rajan@student.uitm.edu.my | ID: S003
--
-- SAFETY STAFF:
--   Email: razak.ismail@uitm.edu.my | ID: SF001
--   Email: zarina.ahmad@uitm.edu.my | ID: SF002

-- Next Steps:
-- ============================================
-- 1. Create upload folder: uploads/qrcodes/spaces/
-- 2. Set folder permissions for write access
-- 3. Check php.ini for allow_url_fopen = On
-- 4. Login at: /module01/login.php
-- 5. Access Module 2 Dashboard: /module02/dashboard.php
-- 6. Manage parking areas: /module02/fk_staff/parking_areas.php
-- 7. Manage parking spaces: /module02/fk_staff/parking_spaces.php
-- 8. View availability: /module02/shared/availability.php

-- ============================================
-- Verification Queries (Optional)
-- ============================================

-- Check all parking lots
SELECT * FROM ParkingLot;

-- Check parking spaces count by lot
SELECT pl.parkingLot_name, COUNT(ps.space_ID) as total_spaces
FROM ParkingLot pl
LEFT JOIN ParkingSpace ps ON pl.parkingLot_ID = ps.parkingLot_ID
GROUP BY pl.parkingLot_ID
ORDER BY pl.parkingLot_name;

-- Check current availability
SELECT 
    pl.parkingLot_name,
    COUNT(ps.space_ID) as total_spaces,
    COUNT(CASE WHEN b.booking_ID IS NULL THEN 1 END) as available,
    COUNT(CASE WHEN b.booking_ID IS NOT NULL THEN 1 END) as occupied
FROM ParkingLot pl
LEFT JOIN ParkingSpace ps ON pl.parkingLot_ID = ps.parkingLot_ID
LEFT JOIN Booking b ON ps.space_ID = b.space_ID 
    AND b.booking_date = CURDATE()
    AND b.booking_status IN ('confirmed', 'active')
GROUP BY pl.parkingLot_ID
ORDER BY pl.parkingLot_name;
