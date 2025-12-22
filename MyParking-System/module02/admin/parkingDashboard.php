<?php
/**
 * Parking Dashboard - Admin View
 * Module 2 - MyParking System
 */

require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';

// Require FK Staff role (admin)
requireRole(['fk_staff']);

$db = getDB();

// Get selected date (default today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// KPI 1: Total Parking Areas
$stmt = $db->query("SELECT COUNT(*) as total FROM ParkingLot");
$totalAreas = $stmt->fetch()['total'];

// KPI 2: Total Parking Spaces
$stmt = $db->query("SELECT COUNT(*) as total FROM ParkingSpace");
$totalSpaces = $stmt->fetch()['total'];

// KPI 3: Spaces in Booking Areas
$stmt = $db->query("
    SELECT COUNT(ps.space_ID) as total 
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    WHERE pl.is_booking_lot = 1
");
$bookingSpaces = $stmt->fetch()['total'];

// KPI 4: Spaces in General Areas
$stmt = $db->query("
    SELECT COUNT(ps.space_ID) as total 
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    WHERE pl.is_booking_lot = 0
");
$generalSpaces = $stmt->fetch()['total'];

// Chart 1 Data: Capacity & Occupancy by Area (selected date)
$stmt = $db->prepare("
    SELECT 
        pl.parkingLot_name,
        pl.capacity,
        COUNT(ps.space_ID) as total_spaces,
        COUNT(CASE WHEN b.booking_ID IS NOT NULL THEN 1 END) as occupied
    FROM ParkingLot pl
    LEFT JOIN ParkingSpace ps ON pl.parkingLot_ID = ps.parkingLot_ID
    LEFT JOIN Booking b ON ps.space_ID = b.space_ID 
        AND b.booking_date = :date
        AND b.booking_status IN ('confirmed', 'active')
    GROUP BY pl.parkingLot_ID, pl.parkingLot_name, pl.capacity
    ORDER BY pl.parkingLot_name
");
$stmt->execute([':date' => $selectedDate]);
$capacityData = $stmt->fetchAll();

// Chart 2 Data: Daily Occupied Spaces (last 7 days)
$stmt = $db->query("
    SELECT 
        b.booking_date,
        COUNT(DISTINCT b.space_ID) as occupied
    FROM Booking b
    WHERE b.booking_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
        AND b.booking_status IN ('confirmed', 'active')
    GROUP BY b.booking_date
    ORDER BY b.booking_date
");
$dailyData = $stmt->fetchAll();

// Fill missing dates with 0
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last7Days[$date] = 0;
}
foreach ($dailyData as $row) {
    $last7Days[$row['booking_date']] = $row['occupied'];
}

require_once __DIR__ . '/../../module01/layout.php';
renderHeader('Parking Dashboard');
?>

<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/parkingDashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-container">
    <h1 class="page-title">🅿️ Parking Dashboard</h1>
    
    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon">🏢</div>
            <div class="kpi-content">
                <div class="kpi-label">Total Parking Areas</div>
                <div class="kpi-value"><?php echo $totalAreas; ?></div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon">🅿️</div>
            <div class="kpi-content">
                <div class="kpi-label">Total Parking Spaces</div>
                <div class="kpi-value"><?php echo $totalSpaces; ?></div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon">📅</div>
            <div class="kpi-content">
                <div class="kpi-label">Spaces in Booking Areas</div>
                <div class="kpi-value"><?php echo $bookingSpaces; ?></div>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon">🚗</div>
            <div class="kpi-content">
                <div class="kpi-label">Spaces in General Areas</div>
                <div class="kpi-value"><?php echo $generalSpaces; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="charts-row">
        <!-- Left Chart: Capacity & Occupancy by Area -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>📊 Capacity & Occupancy by Area</h3>
                <div class="date-filter">
                    <label for="chartDate">Date:</label>
                    <input type="date" id="chartDate" value="<?php echo $selectedDate; ?>" 
                           onchange="window.location.href='?date='+this.value">
                </div>
            </div>
            <div class="chart-container">
                <canvas id="capacityChart"></canvas>
            </div>
        </div>
        
        <!-- Right Chart: Daily Occupied Spaces -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>📈 Daily Occupied Spaces</h3>
                <p class="chart-subtitle">Last 7 Days</p>
            </div>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Chart 1: Capacity & Occupancy by Area (Bar Chart)
const capacityCtx = document.getElementById('capacityChart').getContext('2d');
new Chart(capacityCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($capacityData, 'parkingLot_name')); ?>,
        datasets: [{
            label: 'Capacity',
            data: <?php echo json_encode(array_column($capacityData, 'capacity')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.6)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2
        }, {
            label: 'Occupied',
            data: <?php echo json_encode(array_column($capacityData, 'occupied')); ?>,
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
                    stepSize: 10
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

// Chart 2: Daily Occupied Spaces (Line Chart)
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_keys($last7Days)); ?>,
        datasets: [{
            label: 'Occupied Spaces',
            data: <?php echo json_encode(array_values($last7Days)); ?>,
            fill: true,
            backgroundColor: 'rgba(16, 185, 129, 0.2)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: 'rgba(16, 185, 129, 1)'
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
