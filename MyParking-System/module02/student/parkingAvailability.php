<?php
/**
 * Parking Availability - Student View
 * Module 2 - MyParking System
 * 
 * PURPOSE: Display real-time parking availability for students
 * Shows: Total/Occupied/Available spaces, Occupancy chart by area (filtered by date and area)
 * Students use this to check parking availability before making bookings
 */

// Include authentication and database modules
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// AUTHORIZATION: Only students can access this page
requireRole(['student']);

// Establish database connection
$db = getDB();

/* ==================== FILTERS ==================== */
// Get filter parameters from URL
$selectedDate = $_GET['date'] ?? date('Y-m-d'); // Default to today
$selectedArea = (int)($_GET['area_id'] ?? 0); // 0 = All areas

// Get all parking areas for filter dropdown (only Student parking areas)
$areas = $db->query("SELECT * FROM ParkingLot WHERE parkingLot_type = 'Student' ORDER BY parkingLot_name")->fetchAll();

/* ==================== SUMMARY COUNTS ==================== */
// Build WHERE clause and params for occupied spaces query (needs date parameter)
$whereClause = '';
$params = [':date' => $selectedDate];

if ($selectedArea) {
    // Filter by specific area if selected
    $whereClause = 'AND pl.parkingLot_ID = :area_id';
    $params[':area_id'] = $selectedArea;
}

// Build WHERE clause and params for total spaces query (no date needed)
$totalWhere = '';
$totalParams = [];
if ($selectedArea) {
    $totalWhere = 'AND pl.parkingLot_ID = :area_id';
    $totalParams[':area_id'] = $selectedArea;
}

// Count total spaces in booking areas (only Student parking areas)
// FK: ps.parkingLot_ID → pl.parkingLot_ID
$stmt = $db->prepare("
    SELECT COUNT(ps.space_ID) as total
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    WHERE pl.is_booking_lot = 1 
      AND pl.parkingLot_type = 'Student'
      $totalWhere
");
$stmt->execute($totalParams);
$totalSpaces = $stmt->fetch()['total'];

// Count occupied spaces (with confirmed/active bookings for selected date)
// Multiple JOINs: ps → pl, ps ← b (Booking)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT ps.space_ID) as occupied
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    JOIN Booking b ON ps.space_ID = b.space_ID
    WHERE b.booking_date = :date
      AND b.booking_status IN ('confirmed', 'active')
      AND pl.is_booking_lot = 1
      AND pl.parkingLot_type = 'Student'
      $whereClause
");
$stmt->execute($params);
$occupiedSpaces = $stmt->fetch()['occupied'];

// Calculate available spaces
$availableSpaces = $totalSpaces - $occupiedSpaces;

/* ==================== CHART DATA: Spaces Used by Area ==================== */
// Get occupancy data for each parking area (for bar chart visualization)

// Build dynamic WHERE clause for chart query
$chartParams = [':date' => $selectedDate];
$chartConditions = ["pa.parkingLot_type = 'Student'"];
if ($selectedArea) {
    $chartConditions[] = 'pa.parkingLot_ID = :area_id';
    $chartParams[':area_id'] = $selectedArea;
}
$chartWhere = 'WHERE ' . implode(' AND ', $chartConditions);

// Query: For each area, count total spaces and occupied spaces (only Student parking areas)
// LEFT JOIN ensures areas with no bookings still appear (occupied_count = 0)
$stmt = $db->prepare("
    SELECT 
        pa.parkingLot_name,
        COUNT(ps.space_ID) as total_spaces,
        COUNT(CASE WHEN b.booking_ID IS NOT NULL THEN 1 END) as occupied_count
    FROM ParkingLot pa
    LEFT JOIN ParkingSpace ps ON pa.parkingLot_ID = ps.parkingLot_ID
    LEFT JOIN Booking b ON ps.space_ID = b.space_ID 
        AND b.booking_date = :date
        AND b.booking_status IN ('confirmed', 'active')
    $chartWhere
    GROUP BY pa.parkingLot_ID, pa.parkingLot_name
    ORDER BY pa.parkingLot_name
");
$stmt->execute($chartParams);
$chartData = $stmt->fetchAll();

// Load layout wrapper
require_once __DIR__ . '/../../layout.php';
renderHeader('Parking Availability');
?>

<!-- External CSS and Chart.js library -->
<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/student/parkingAvailability.css?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Main container -->
<div class="availability-container">
    <h2>Daily Parking Availability</h2>
    
    <!-- ==================== FILTERS BAR ==================== -->
    <!-- Form to filter by date and parking area (submits via GET) -->
    <div class="filters-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Date:</label>
                <!-- Date picker to select specific date -->
                <input type="date" name="date" value="<?php echo $selectedDate; ?>">
            </div>
            
            <div class="filter-group">
                <label>Parking Area:</label>
                <!-- Dropdown to filter by specific area or show all -->
                <select name="area_id">
                    <option value="">All Areas</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['parkingLot_ID']; ?>" 
                            <?php echo $selectedArea == $area['parkingLot_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($area['parkingLot_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Submit button to apply filters -->
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
    
    <!-- ==================== SUMMARY CARDS ==================== -->
    <!-- Display 3 key metrics: Total, Occupied, Available spaces -->
    <div class="summary-grid">
        <!-- Card 1: Total Spaces -->
        <div class="summary-card">
            <div class="card-icon"><i class="fas fa-parking"></i></div>
            <div class="card-content">
                <div class="card-label">Total Spaces</div>
                <div class="card-value"><?php echo $totalSpaces; ?></div>
            </div>
        </div>
        
        <!-- Card 2: Occupied Spaces (red color) -->
        <div class="summary-card card-occupied">
            <div class="card-icon"><i class="fas fa-car"></i></div>
            <div class="card-content">
                <div class="card-label">Occupied Spaces</div>
                <div class="card-value"><?php echo $occupiedSpaces; ?></div>
            </div>
        </div>
        
        <!-- Card 3: Available Spaces (green color) -->
        <div class="summary-card card-available">
            <div class="card-icon"><i class="fas fa-check-circle"></i></div>
            <div class="card-content">
                <div class="card-label">Available Spaces</div>
                <div class="card-value"><?php echo $availableSpaces; ?></div>
            </div>
        </div>
    </div>
    
    <!-- ==================== OCCUPANCY CHART ==================== -->
    <!-- Bar chart showing total vs occupied spaces by parking area -->
    <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Occupancy Chart - Spaces Used by Area</h3>
        <div class="chart-container">
            <!-- Canvas element for Chart.js rendering -->
            <canvas id="occupancyChart"></canvas>
        </div>
    </div>
    
    <!-- ==================== INFO BOX ==================== -->
    <!-- Additional information about the data -->
    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> Information</h4>
        <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($selectedDate)); ?></p>
        <p><strong>Last Updated:</strong> <?php echo date('g:i A'); ?></p>
        <p>Real-time parking availability data. Refresh the page to see the latest information.</p>
    </div>
</div>

<script>
/* ==================== CHART.JS CONFIGURATION ==================== */
// Bar chart comparing total spaces vs occupied spaces for each area
const ctx = document.getElementById('occupancyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar', // Bar chart type
    data: {
        // X-axis: Parking area names from PHP
        labels: <?php echo json_encode(array_column($chartData, 'parkingLot_name')); ?>,
        datasets: [{
            label: 'Total Spaces', // Blue bars
            data: <?php echo json_encode(array_column($chartData, 'total_spaces')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.6)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2
        }, {
            label: 'Occupied', // Red bars
            data: <?php echo json_encode(array_column($chartData, 'occupied_count')); ?>,
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true, // Y-axis starts at 0
                ticks: {
                    stepSize: 5 // Increment by 5
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: false
            }
        }
    }
});
</script>

<?php 
// Render footer
renderFooter(); 
?>
