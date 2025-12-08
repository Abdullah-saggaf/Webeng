# Parking Management System Database

## Overview
This database system manages a comprehensive parking management solution including users, vehicles, parking lots, bookings, tickets, and violations.

## Database Setup Instructions

### Prerequisites
- MySQL 5.7+ or MariaDB 10.2+
- PHP 7.4+ with PDO extension
- phpMyAdmin or MySQL command line access

### Installation Steps

#### Option 1: Using phpMyAdmin
1. Open phpMyAdmin in your browser (usually at `http://localhost/phpmyadmin`)
2. Click on "New" to create a new database
3. Name it `parking_management`
4. Set collation to `utf8mb4_unicode_ci`
5. Click "Create"
6. Select the newly created database
7. Go to the "Import" tab
8. Choose `schema.sql` file and click "Go"
9. After successful import, import `sample_data.sql` for test data

#### Option 2: Using MySQL Command Line
```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE parking_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import the schema
mysql -u root -p parking_management < schema.sql

# Import sample data (optional)
mysql -u root -p parking_management < sample_data.sql
```

#### Option 3: Using PowerShell (Windows)
```powershell
# Navigate to the database folder
cd c:\Users\thsjv\Webeng\database

# Create database and import schema
mysql -u root -p -e "CREATE DATABASE parking_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p parking_management < schema.sql
mysql -u root -p parking_management < sample_data.sql
```

### Database Configuration

Edit `db_config.php` to match your MySQL settings:
```php
define('DB_HOST', 'localhost');     // Your MySQL host
define('DB_NAME', 'parking_management'); // Database name
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
```

## Database Structure

### Tables

1. **User** - Stores user information (students, faculty, staff, visitors)
2. **Vehicle** - Stores vehicle information linked to users
3. **ParkingLot** - Stores parking lot information
4. **ParkingSpace** - Individual parking spaces within lots
5. **Booking** - Parking space reservations
6. **Violation** - Types of parking violations
7. **Ticket** - Issued parking tickets
8. **ParkingLog** - Event logs for bookings
9. **User_points** - Violation points tracking

## Usage Examples

### Include Database Configuration in PHP Files
```php
<?php
require_once 'database/db_config.php';
require_once 'database/db_functions.php';

// Example: Get user by ID
$user = getUserById('U001');

// Example: Get active bookings
$bookings = getActiveBookings();

// Example: Create a new booking
$qr_code = 'BOOKING_QR_' . uniqid();
createBooking(1, 1, '2025-12-09', '08:00:00', '17:00:00', $qr_code);
?>
```

## Sample Data

The `sample_data.sql` file includes:
- 8 sample users (admin, students, faculty, staff, visitor)
- 7 sample vehicles
- 6 parking lots
- 20 parking spaces
- 7 bookings
- 7 violation types
- 4 sample tickets
- Parking logs and user points

### Default Admin Credentials (for testing)
- **User ID:** U001
- **Email:** admin@parking.com
- **Password:** admin123 (hashed in database)

## Security Notes

⚠️ **Important Security Considerations:**
1. Change the default database password before deployment
2. Use environment variables for sensitive data in production
3. Enable SSL for database connections in production
4. Never commit `db_config.php` with real credentials to version control
5. Use prepared statements (already implemented in `db_functions.php`)
6. Implement proper password hashing (already using `password_hash()`)

## Maintenance

### Backup Database
```bash
mysqldump -u root -p parking_management > backup_$(date +%Y%m%d).sql
```

### Reset Database
```bash
mysql -u root -p parking_management < schema.sql
```

## Support

For issues or questions, please refer to the project documentation or contact the development team.

---
**Last Updated:** December 8, 2025
