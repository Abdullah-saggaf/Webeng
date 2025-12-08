-- ============================================
-- Sample Data for Parking Management System
-- ============================================

-- ============================================
-- 1. Insert Sample Users
-- ============================================
INSERT INTO User (user_ID, username, email, phone_number, password, user_type) VALUES
('U001', 'admin', 'admin@parking.com', '0501234567', 'admin123', 'Admin'),
('U002', 'john_doe', 'john@example.com', '0509876543', 'pass123', 'Student'),
('U003', 'jane_smith', 'jane@example.com', '0507654321', 'pass456', 'Faculty'),
('U004', 'mike_wilson', 'mike@example.com', '0506543210', 'pass789', 'Staff'),
('U005', 'sarah_brown', 'sarah@example.com', '0505432109', 'pass321', 'Student'),
('U006', 'tom_jones', 'tom@example.com', '0504321098', 'pass654', 'Visitor'),
('U007', 'emily_davis', 'emily@example.com', '0503210987', 'pass987', 'Faculty'),
('U008', 'david_lee', 'david@example.com', '0502109876', 'pass246', 'Staff');

-- ============================================
-- 2. Insert Sample Vehicles
-- ============================================
INSERT INTO Vehicle (user_ID, vehicle_type, vehicle_model, license_plate, grant_status) VALUES
('U002', 'Car', 'Toyota Camry 2020', 'ABC1234', 'Approved'),
('U003', 'Car', 'Honda Accord 2021', 'XYZ5678', 'Approved'),
('U004', 'SUV', 'Ford Explorer 2019', 'DEF9012', 'Approved'),
('U005', 'Car', 'Nissan Altima 2022', 'GHI3456', 'Pending'),
('U006', 'Motorcycle', 'Yamaha R1', 'JKL7890', 'Approved'),
('U007', 'Car', 'BMW 530i 2023', 'MNO1357', 'Approved'),
('U008', 'Truck', 'Chevy Silverado', 'PQR2468', 'Approved');

-- ============================================
-- 3. Insert Sample Parking Lots
-- ============================================
INSERT INTO ParkingLot (parkingLot_name, parkingLot_type, is_booking_lot, capacity) VALUES
('North Campus Lot A', 'Student', TRUE, 100),
('South Campus Lot B', 'Faculty', TRUE, 50),
('East Campus Lot C', 'Staff', TRUE, 75),
('West Campus Lot D', 'Visitor', FALSE, 30),
('Central Lot E', 'Mixed', TRUE, 120),
('Library Parking', 'Student', TRUE, 80);

-- ============================================
-- 4. Insert Sample Parking Spaces
-- ============================================
INSERT INTO ParkingSpace (parkingLot_ID, space_number, qr_code_value) VALUES
-- North Campus Lot A (ID: 1)
(1, 'A-001', 'QR_A001_NORTH'),
(1, 'A-002', 'QR_A002_NORTH'),
(1, 'A-003', 'QR_A003_NORTH'),
(1, 'A-004', 'QR_A004_NORTH'),
(1, 'A-005', 'QR_A005_NORTH'),
-- South Campus Lot B (ID: 2)
(2, 'B-001', 'QR_B001_SOUTH'),
(2, 'B-002', 'QR_B002_SOUTH'),
(2, 'B-003', 'QR_B003_SOUTH'),
(2, 'B-004', 'QR_B004_SOUTH'),
-- East Campus Lot C (ID: 3)
(3, 'C-001', 'QR_C001_EAST'),
(3, 'C-002', 'QR_C002_EAST'),
(3, 'C-003', 'QR_C003_EAST'),
-- West Campus Lot D (ID: 4)
(4, 'D-001', 'QR_D001_WEST'),
(4, 'D-002', 'QR_D002_WEST'),
-- Central Lot E (ID: 5)
(5, 'E-001', 'QR_E001_CENTRAL'),
(5, 'E-002', 'QR_E002_CENTRAL'),
(5, 'E-003', 'QR_E003_CENTRAL'),
(5, 'E-004', 'QR_E004_CENTRAL'),
-- Library Parking (ID: 6)
(6, 'F-001', 'QR_F001_LIBRARY'),
(6, 'F-002', 'QR_F002_LIBRARY');

-- ============================================
-- 5. Insert Sample Bookings
-- ============================================
INSERT INTO Booking (space_ID, vehicle_ID, booking_date, start_time, end_time, booking_status, qr_code_value) VALUES
(1, 1, '2025-12-08', '08:00:00', '17:00:00', 'Active', 'BOOKING_QR_001'),
(2, 2, '2025-12-08', '09:00:00', '16:00:00', 'Active', 'BOOKING_QR_002'),
(6, 3, '2025-12-08', '08:30:00', '17:30:00', 'Active', 'BOOKING_QR_003'),
(10, 4, '2025-12-08', '10:00:00', '15:00:00', 'Active', 'BOOKING_QR_004'),
(3, 5, '2025-12-07', '08:00:00', '17:00:00', 'Completed', 'BOOKING_QR_005'),
(15, 6, '2025-12-08', '07:00:00', '19:00:00', 'Active', 'BOOKING_QR_006'),
(4, 7, '2025-12-09', '09:00:00', '18:00:00', 'Scheduled', 'BOOKING_QR_007');

-- ============================================
-- 6. Insert Sample Violations
-- ============================================
INSERT INTO Violation (violation_type, violation_points, fine_amount) VALUES
('Parking in No-Parking Zone', 3, 100),
('Expired Parking Time', 2, 50),
('Parking without Permit', 4, 150),
('Double Parking', 3, 100),
('Blocking Driveway', 5, 200),
('Handicap Violation', 5, 300),
('Fire Lane Violation', 5, 250);

-- ============================================
-- 7. Insert Sample Tickets
-- ============================================
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, description) VALUES
(1, 'U002', 2, 'Unpaid', 'Parking time exceeded by 2 hours'),
(3, 'U004', 1, 'Paid', 'Vehicle parked in restricted zone'),
(5, 'U006', 4, 'Unpaid', 'Motorcycle parked blocking another vehicle'),
(2, 'U003', 2, 'Paid', 'Exceeded allocated time slot');

-- ============================================
-- 8. Insert Sample Parking Logs
-- ============================================
INSERT INTO ParkingLog (booking_ID, event_type, remarks) VALUES
(1, 'Check-In', 'Vehicle entered parking lot'),
(2, 'Check-In', 'Vehicle entered parking lot'),
(3, 'Check-In', 'Vehicle entered parking lot'),
(4, 'Check-In', 'Vehicle entered parking lot'),
(5, 'Check-In', 'Vehicle entered parking lot'),
(5, 'Check-Out', 'Vehicle exited parking lot on time'),
(6, 'Check-In', 'Vehicle entered parking lot');

-- ============================================
-- 9. Insert Sample User Points
-- ============================================
INSERT INTO User_points (user_ID, total_points) VALUES
('U002', 2),
('U003', 0),
('U004', 3),
('U005', 0),
('U006', 3),
('U007', 0),
('U008', 0);

-- ============================================
-- End of Sample Data
-- ============================================
