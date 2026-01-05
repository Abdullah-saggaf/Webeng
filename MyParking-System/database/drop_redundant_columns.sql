-- ============================================
-- Migration: Remove Redundant Columns from Booking Table
-- Date: January 5, 2026
-- Purpose: Remove actual_vehicle_id and actual_plate_number 
--          as they duplicate data from Vehicle table
-- ============================================

USE parking_management;

-- Drop the foreign key constraint first
ALTER TABLE Booking 
DROP FOREIGN KEY IF EXISTS `Booking_ibfk_3`;

-- Now drop the redundant columns
ALTER TABLE Booking 
DROP COLUMN IF EXISTS actual_vehicle_id,
DROP COLUMN IF EXISTS actual_plate_number;

-- Verify the changes
DESCRIBE Booking;
