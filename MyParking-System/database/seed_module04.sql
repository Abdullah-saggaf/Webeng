-- ============================================
-- Module 4: Traffic Summons & Demerit Points
-- Test Data Seed File
-- ============================================
-- This file populates Violation, Ticket, and User_points tables
-- Safe to run multiple times (idempotent)
-- ============================================

USE parking_management;

-- ============================================
-- 1. SEED VIOLATIONS
-- ============================================
-- Insert violation types only if they don't already exist
-- Using NOT EXISTS to prevent duplicates

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Illegal Parking', 10, 50
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Illegal Parking');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Parking in Reserved Area', 15, 80
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Parking in Reserved Area');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Blocking Emergency Exit', 25, 150
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Blocking Emergency Exit');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Expired Parking Pass', 5, 30
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Expired Parking Pass');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Speeding in Campus', 20, 100
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Speeding in Campus');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'No Parking Permit', 12, 60
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'No Parking Permit');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Double Parking', 8, 40
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Double Parking');

INSERT INTO Violation (violation_type, violation_points, fine_amount)
SELECT 'Parking on Grass/Walkway', 10, 50
WHERE NOT EXISTS (SELECT 1 FROM Violation WHERE violation_type = 'Parking on Grass/Walkway');

-- ============================================
-- 2. SEED TICKETS
-- ============================================
-- Insert tickets only if they don't already exist
-- Using unique description as identifier to prevent duplicates

-- Ticket 1: S001 - Illegal Parking
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 1, 'S001', 
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Illegal Parking' LIMIT 1),
    'Unpaid',
    DATE_SUB(NOW(), INTERVAL 5 DAY),
    'Parked in no-parking zone near library'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Parked in no-parking zone near library'
);

-- Ticket 2: S002 - Parking in Reserved Area
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 3, 'S002',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Parking in Reserved Area' LIMIT 1),
    'Paid',
    DATE_SUB(NOW(), INTERVAL 12 DAY),
    'Vehicle parked in staff reserved lot'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Vehicle parked in staff reserved lot'
);

-- Ticket 3: S001 - Speeding in Campus (Second violation for S001)
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 1, 'S001',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Speeding in Campus' LIMIT 1),
    'Unpaid',
    DATE_SUB(NOW(), INTERVAL 3 DAY),
    'Exceeded 30km/h speed limit near admin building'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Exceeded 30km/h speed limit near admin building'
);

-- Ticket 4: S003 - No Parking Permit
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 5, 'S003',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'No Parking Permit' LIMIT 1),
    'Unpaid',
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    'Motorcycle without valid parking sticker'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Motorcycle without valid parking sticker'
);

-- Ticket 5: S004 - Double Parking
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 7, 'S004',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Double Parking' LIMIT 1),
    'Cancelled',
    DATE_SUB(NOW(), INTERVAL 15 DAY),
    'Double parked blocking another vehicle - appeal approved'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Double parked blocking another vehicle - appeal approved'
);

-- Ticket 6: S005 - Expired Parking Pass
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 9, 'S005',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Expired Parking Pass' LIMIT 1),
    'Paid',
    DATE_SUB(NOW(), INTERVAL 20 DAY),
    'Parking pass expired 2 weeks ago'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Parking pass expired 2 weeks ago'
);

-- Ticket 7: S002 - Blocking Emergency Exit (Second violation for S002)
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 3, 'S002',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Blocking Emergency Exit' LIMIT 1),
    'Unpaid',
    DATE_SUB(NOW(), INTERVAL 1 DAY),
    'Vehicle blocking fire exit at faculty building'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Vehicle blocking fire exit at faculty building'
);

-- Ticket 8: S003 - Parking on Grass/Walkway (Second violation for S003)
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 5, 'S003',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Parking on Grass/Walkway' LIMIT 1),
    'Unpaid',
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    'Motorcycle parked on pedestrian walkway'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Motorcycle parked on pedestrian walkway'
);

-- Ticket 9: S004 - Illegal Parking (Second violation for S004)
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 6, 'S004',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'Illegal Parking' LIMIT 1),
    'Paid',
    DATE_SUB(NOW(), INTERVAL 10 DAY),
    'Parked in disabled parking without permit'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Parked in disabled parking without permit'
);

-- Ticket 10: S001 - No Parking Permit (Third violation for S001 - will trigger high warning)
INSERT INTO Ticket (vehicle_ID, user_ID, violation_ID, ticket_status, issued_at, description)
SELECT 2, 'S001',
    (SELECT violation_ID FROM Violation WHERE violation_type = 'No Parking Permit' LIMIT 1),
    'Unpaid',
    DATE_SUB(NOW(), INTERVAL 1 DAY),
    'Motorcycle parked without valid permit'
WHERE NOT EXISTS (
    SELECT 1 FROM Ticket WHERE description = 'Motorcycle parked without valid permit'
);

-- ============================================
-- 3. INITIALIZE USER_POINTS
-- ============================================
-- Create user_points rows for all students
-- Using INSERT ... ON DUPLICATE KEY UPDATE for idempotency

INSERT INTO User_points (user_ID, total_points)
VALUES ('S001', 0)
ON DUPLICATE KEY UPDATE user_ID = user_ID;

INSERT INTO User_points (user_ID, total_points)
VALUES ('S002', 0)
ON DUPLICATE KEY UPDATE user_ID = user_ID;

INSERT INTO User_points (user_ID, total_points)
VALUES ('S003', 0)
ON DUPLICATE KEY UPDATE user_ID = user_ID;

INSERT INTO User_points (user_ID, total_points)
VALUES ('S004', 0)
ON DUPLICATE KEY UPDATE user_ID = user_ID;

INSERT INTO User_points (user_ID, total_points)
VALUES ('S005', 0)
ON DUPLICATE KEY UPDATE user_ID = user_ID;

-- ============================================
-- 4. CALCULATE TOTAL POINTS FROM TICKETS
-- ============================================
-- Update User_points.total_points based on actual tickets
-- Only count tickets with status 'Unpaid' or 'Paid' (exclude 'Cancelled')

UPDATE User_points up
INNER JOIN (
    SELECT 
        t.user_ID,
        COALESCE(SUM(v.violation_points), 0) AS calculated_points
    FROM Ticket t
    INNER JOIN Violation v ON t.violation_ID = v.violation_ID
    WHERE t.ticket_status IN ('Unpaid', 'Paid')
    GROUP BY t.user_ID
) AS ticket_points ON up.user_ID = ticket_points.user_ID
SET up.total_points = ticket_points.calculated_points;

-- Reset points to 0 for users with no valid tickets
UPDATE User_points
SET total_points = 0
WHERE user_ID NOT IN (
    SELECT DISTINCT user_ID 
    FROM Ticket 
    WHERE ticket_status IN ('Unpaid', 'Paid')
);

-- ============================================
-- 5. VERIFICATION QUERIES
-- ============================================
-- Display seeded data for verification

SELECT '=== VIOLATIONS ===' AS '';
SELECT * FROM Violation ORDER BY violation_ID;

SELECT '=== TICKETS ===' AS '';
SELECT 
    t.ticket_ID,
    t.user_ID,
    u.username,
    v.license_plate,
    vl.violation_type,
    vl.violation_points,
    vl.fine_amount,
    t.ticket_status,
    DATE_FORMAT(t.issued_at, '%Y-%m-%d %H:%i') AS issued_at,
    t.description
FROM Ticket t
INNER JOIN User u ON t.user_ID = u.user_ID
INNER JOIN Vehicle v ON t.vehicle_ID = v.vehicle_ID
INNER JOIN Violation vl ON t.violation_ID = vl.violation_ID
ORDER BY t.issued_at DESC;

SELECT '=== USER POINTS SUMMARY ===' AS '';
SELECT 
    up.user_ID,
    u.username,
    up.total_points,
    CASE 
        WHEN up.total_points = 0 THEN 'Perfect Record'
        WHEN up.total_points < 20 THEN 'Good Standing'
        WHEN up.total_points < 50 THEN 'Warning'
        ELSE 'High Risk'
    END AS status
FROM User_points up
INNER JOIN User u ON up.user_ID = u.user_ID
ORDER BY up.total_points DESC, up.user_ID;

SELECT '=== SUMMARY STATISTICS ===' AS '';
SELECT 
    COUNT(*) AS total_violations
FROM Violation;

SELECT 
    COUNT(*) AS total_tickets,
    SUM(CASE WHEN ticket_status = 'Unpaid' THEN 1 ELSE 0 END) AS unpaid,
    SUM(CASE WHEN ticket_status = 'Paid' THEN 1 ELSE 0 END) AS paid,
    SUM(CASE WHEN ticket_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
FROM Ticket;

SELECT 
    SUM(total_points) AS total_points_in_system,
    AVG(total_points) AS average_points_per_student,
    MAX(total_points) AS highest_points
FROM User_points;
