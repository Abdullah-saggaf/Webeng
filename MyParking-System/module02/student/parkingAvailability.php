<?php
/**
 * Parking Availability - Student View
 * Module 2 - MyParking System
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require Student role
requireRole(['student']);

$db = getDB();

// Get filters
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedArea = (int)($_GET['area_id'] ?? 0);

// Get all areas for filter
$areas = $db->query("SELECT * FROM ParkingLot ORDER BY parkingLot_name")->fetchAll();

// Get summary counts
$whereClause = '';
$params = [':date' => $selectedDate];

if ($selectedArea) {
    $whereClause = 'AND pl.parkingLot_ID = :area_id';
    $params[':area_id'] = $selectedArea;
}

// Total spaces
$stmt = $db->prepare("
    SELECT COUNT(ps.space_ID) as total
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    WHERE 1=1 $whereClause
");
$stmt->execute($params);
$totalSpaces = $stmt->fetch()['total'];

// Occupied spaces
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT ps.space_ID) as occupied
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    JOIN Booking b ON ps.space_ID = b.space_ID
    WHERE b.booking_date = :date
      AND b.booking_status IN ('confirmed', 'active')
      $whereClause
");
$stmt->execute($params);
$occupiedSpaces = $stmt->fetch()['occupied'];

$availableSpaces = $totalSpaces - $occupiedSpaces;

// Chart data: Spaces used by area
$chartParams = [':date' => $selectedDate];
$chartWhere = '';
if ($selectedArea) {
    $chartWhere = 'WHERE pa.parkingLot_ID = :area_id';
    $chartParams[':area_id'] = $selectedArea;
}

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

require_once __DIR__ . '/../../module01/layout.php';
renderHeader('Parking Availability');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/student/parkingAvailability.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="availability-container">
    <h1 class="page-title">📊 Daily Parking Availability</h1>
    
    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Date:</label>
                <input type="date" name="date" value="<?php echo $selectedDate; ?>">
            </div>
            
            <div class="filter-group">
                <label>Parking Area:</label>
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
            
            <button type="submit" class="btn-search">🔍 Search</button>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="card-icon">🅿️</div>
            <div class="card-content">
                <div class="card-label">Total Spaces</div>
                <div class="card-value"><?php echo $totalSpaces; ?></div>
            </div>
        </div>
        
        <div class="summary-card card-occupied">
            <div class="card-icon">🚗</div>
            <div class="card-content">
                <div class="card-label">Occupied Spaces</div>
                <div class="card-value"><?php echo $occupiedSpaces; ?></div>
            </div>
        </div>
        
        <div class="summary-card card-available">
            <div class="card-icon">✅</div>
            <div class="card-content">
                <div class="card-label">Available Spaces</div>
                <div class="card-value"><?php echo $availableSpaces; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Chart -->
    <div class="chart-card">
        <h3>📈 Occupancy Chart - Spaces Used by Area</h3>
        <div class="chart-container">
            <canvas id="occupancyChart"></canvas>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="info-box">
        <h4>ℹ️ Information</h4>
        <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($selectedDate)); ?></p>
        <p><strong>Last Updated:</strong> <?php echo date('g:i A'); ?></p>
        <p>Real-time parking availability data. Refresh the page to see the latest information.</p>
    </div>
</div>

<script>
const ctx = document.getElementById('occupancyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($chartData, 'parkingLot_name')); ?>,
        datasets: [{
            label: 'Total Spaces',
            data: <?php echo json_encode(array_column($chartData, 'total_spaces')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.6)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2
        }, {
            label: 'Occupied',
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
                beginAtZero: true,
                ticks: {
                    stepSize: 5
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

<?php renderFooter(); ?>
