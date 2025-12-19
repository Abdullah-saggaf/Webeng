-- ============================================
-- Module 01 Test Data
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
-- Test Credentials Summary
-- ============================================
-- All passwords: password123
--
-- STUDENTS:
-- Email: ahmad.ali@student.uitm.edu.my | Username: Ahmad Ali | ID: S001
-- Email: siti.nur@student.uitm.edu.my | Username: Siti Nurhaliza | ID: S002
-- Email: kumar.rajan@student.uitm.edu.my | Username: Kumar Rajan | ID: S003
-- Email: fatimah.ib@student.uitm.edu.my | Username: Fatimah Ibrahim | ID: S004
-- Email: lee.wei@student.uitm.edu.my | Username: Lee Wei Ming | ID: S005
--
-- FK STAFF (ADMIN):
-- Email: hassan.abd@uitm.edu.my | Username: Dr. Hassan Abdullah | ID: FK001
-- Email: aminah.yusof@uitm.edu.my | Username: Prof. Aminah Yusof | ID: FK002
--
-- SAFETY STAFF:
-- Email: razak.ismail@uitm.edu.my | Username: Encik Razak Ismail | ID: SF001
-- Email: zarina.ahmad@uitm.edu.my | Username: Puan Zarina Ahmad | ID: SF002
-- ============================================
