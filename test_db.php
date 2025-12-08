<?php
require_once 'database/db_config.php';
require_once 'database/db_functions.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 30px;
        }
        .success {
            color: #22c55e;
            padding: 15px;
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            margin: 15px 0;
            border-radius: 5px;
        }
        .error {
            color: #ef4444;
            padding: 15px;
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            margin: 15px 0;
            border-radius: 5px;
        }
        .info {
            padding: 15px;
            background: #f8fafc;
            border-radius: 5px;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .back-btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🗄️ Database Connection Test</h1>";

try {
    // Test connection
    $db = getDB();
    echo "<div class='success'>✓ Database connection successful!</div>";
    
    // Test User table
    $stmt = $db->query("SELECT COUNT(*) as count FROM User");
    $userCount = $stmt->fetch();
    echo "<div class='info'><strong>Users in database:</strong> " . $userCount['count'] . "</div>";
    
    // Test active bookings
    $bookings = getActiveBookings();
    echo "<div class='info'><strong>Active bookings:</strong> " . count($bookings) . "</div>";
    
    // Test ParkingLot table
    $stmt = $db->query("SELECT COUNT(*) as count FROM ParkingLot");
    $lotCount = $stmt->fetch();
    echo "<div class='info'><strong>Parking lots:</strong> " . $lotCount['count'] . "</div>";
    
    // Test Vehicle table
    $stmt = $db->query("SELECT COUNT(*) as count FROM Vehicle");
    $vehicleCount = $stmt->fetch();
    echo "<div class='info'><strong>Registered vehicles:</strong> " . $vehicleCount['count'] . "</div>";
    
    // Show sample users
    echo "<h2>Sample Users</h2>";
    $stmt = $db->query("SELECT user_id, name, email, user_type FROM User LIMIT 5");
    echo "<table>
            <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Type</th>
            </tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>
                <td>" . htmlspecialchars($row['user_id']) . "</td>
                <td>" . htmlspecialchars($row['name']) . "</td>
                <td>" . htmlspecialchars($row['email']) . "</td>
                <td>" . htmlspecialchars($row['user_type']) . "</td>
              </tr>";
    }
    echo "</table>";
    
    // Show parking lots
    echo "<h2>Parking Lots</h2>";
    $stmt = $db->query("SELECT lot_name, location, total_capacity FROM ParkingLot");
    echo "<table>
            <tr>
                <th>Lot Name</th>
                <th>Location</th>
                <th>Total Capacity</th>
            </tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>
                <td>" . htmlspecialchars($row['lot_name']) . "</td>
                <td>" . htmlspecialchars($row['location']) . "</td>
                <td>" . htmlspecialchars($row['total_capacity']) . "</td>
              </tr>";
    }
    echo "</table>";
    
    echo "<div class='success'>✓ Database is fully operational and ready to use!</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>Make sure XAMPP MySQL is running and database credentials are correct in db_config.php</div>";
}

echo "
        <a href='index.html' class='back-btn'>← Back to Home</a>
    </div>
</body>
</html>";
?>
