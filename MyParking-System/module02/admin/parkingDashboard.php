<?php
/**
 * Parking Dashboard - Admin View
 * Module 2 - MyParking System
 * 
 * PURPOSE: Display real-time parking metrics and visualizations for administrators
 * Shows KPIs (total areas, spaces, booking/general spaces) and 2 charts:
 * - Chart 1: Capacity vs Occupancy by parking area (for a selected date)
 * - Chart 2: Daily occupied spaces trend (last 7 days)
 */

// Include authentication module to verify user is logged in
require_once __DIR__ . '/../../module01/auth.php';

// Include database configuration to connect to MySQL database
require_once __DIR__ . '/../../database/db_config.php';

// AUTHORIZATION: Allow both FK Staff (admin) and Safety Staff to access this dashboard
// Redirects to login page if user role does not match
requireRole(['fk_staff', 'safety_staff']);

// Establish database connection using PDO
$db = getDB();

// Get selected date from URL query parameter (default to today if not provided)
// Used to filter booking/occupancy data for Chart 1
$selectedDate = $_GET['date'] ?? date('Y-m-d');

/* ==================== KPI CALCULATIONS ==================== */

// KPI 1: Total Parking Areas (count all rows in ParkingLot table)
$stmt = $db->query("SELECT COUNT(*) as total FROM ParkingLot");
$totalAreas = $stmt->fetch()['total'];

// KPI 2: Total Parking Spaces (count all rows in ParkingSpace table)
$stmt = $db->query("SELECT COUNT(*) as total FROM ParkingSpace");
$totalSpaces = $stmt->fetch()['total'];

// KPI 3: Spaces in Booking Areas
// JOIN ParkingSpace with ParkingLot to filter spaces that belong to booking areas (is_booking_lot = 1)
// FK: ps.parkingLot_ID → pl.parkingLot_ID
$stmt = $db->query("
    SELECT COUNT(ps.space_ID) as total 
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    WHERE pl.is_booking_lot = 1
");
$bookingSpaces = $stmt->fetch()['total'];

// KPI 4: Spaces in General Areas (first-come first-served, non-booking areas)
// Same JOIN as KPI 3 but filter by is_booking_lot = 0
$stmt = $db->query("
    SELECT COUNT(ps.space_ID) as total 
    FROM ParkingSpace ps
    JOIN ParkingLot pl ON ps.parkingLot_ID = pl.parkingLot_ID
    WHERE pl.is_booking_lot = 0
");
$generalSpaces = $stmt->fetch()['total'];

/* ==================== CHART 1 DATA: Capacity & Occupancy by Area ==================== */
// For the selected date, show each parking area's capacity vs occupied spaces
// Uses LEFT JOIN to include areas even if they have no spaces or bookings
// FK relationships: pl.parkingLot_ID ← ps.parkingLot_ID, ps.space_ID ← b.space_ID
// Filters: booking_date matches selected date, status is 'confirmed' or 'active'
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
// PREPARED STATEMENT: Protects against SQL injection by binding :date parameter
$stmt->execute([':date' => $selectedDate]);
$capacityData = $stmt->fetchAll();

/* ==================== CHART 2 DATA: Daily Occupied Spaces (Last 7 Days) ==================== */
// Query distinct occupied spaces for each day in the last 7 days
// Uses DISTINCT to count each space_ID only once per day (even if multiple bookings exist)
// Filters: booking_status is 'confirmed' or 'active'
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

// Fill missing dates with 0 occupied spaces (for days with no bookings)
// Ensures chart displays all 7 days even if some have zero activity
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last7Days[$date] = 0; // Initialize to 0
}
// Populate actual data from database results
foreach ($dailyData as $row) {
    $last7Days[$row['booking_date']] = $row['occupied'];
}

// Load main layout wrapper (header, sidebar, navigation)
require_once __DIR__ . '/../../layout.php';
renderHeader('Parking Dashboard');
?>

<!-- Link to external CSS stylesheet for dashboard styling -->
<link rel="stylesheet" href="<?php echo APP_BASE_PATH; ?>/module02/admin/parkingDashboard.css">

<!-- Chart.js library from CDN for rendering interactive charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Main dashboard container -->
<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="page-title">
            <i class="fas fa-chart-line"></i> Parking Dashboard
        </h1>
        <a href="<?php echo APP_BASE_PATH; ?>/admin.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Admin Panel
        </a>
    </div>
    
    <!-- ==================== KPI CARDS SECTION ==================== -->
    <!-- Displays 4 key performance indicators in a grid layout -->
    <div class="kpi-grid">
        <!-- KPI Card 1: Total number of parking areas in the system -->
        <div class="kpi-card kpi-card-areas">
            <div class="kpi-icon"><i class="fas fa-building"></i></div>
            <div class="kpi-content">
                <div class="kpi-label">Total Parking Areas</div>
                <div class="kpi-value"><?php echo $totalAreas; ?></div>
            </div>
        </div>
        
        <!-- KPI Card 2: Total number of parking spaces across all areas -->
        <div class="kpi-card kpi-card-spaces">
            <div class="kpi-icon"><i class="fas fa-parking"></i></div>
            <div class="kpi-content">
                <div class="kpi-label">Total Parking Spaces</div>
                <div class="kpi-value"><?php echo $totalSpaces; ?></div>
            </div>
        </div>
        
        <!-- KPI Card 3: Spaces in booking areas (require reservation) -->
        <div class="kpi-card kpi-card-booking">
            <div class="kpi-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="kpi-content">
                <div class="kpi-label">Spaces in Booking Areas</div>
                <div class="kpi-value"><?php echo $bookingSpaces; ?></div>
            </div>
        </div>
        
        <!-- KPI Card 4: Spaces in general areas (first-come first-served) -->
        <div class="kpi-card kpi-card-general">
            <div class="kpi-icon"><i class="fas fa-car"></i></div>
            <div class="kpi-content">
                <div class="kpi-label">Spaces in General Areas</div>
                <div class="kpi-value"><?php echo $generalSpaces; ?></div>
            </div>
        </div>
    </div>
    
    <!-- ==================== CHARTS ROW SECTION ==================== -->
    <!-- Contains 2 charts side-by-side for data visualization -->
    <div class="charts-row">
        <!-- Chart 1: Capacity & Occupancy by Area (Bar Chart) -->
        <!-- Shows total capacity vs occupied spaces for each parking area on selected date -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-bar"></i> Capacity & Occupancy by Area</h3>
                <!-- Date filter: When user selects a date, page reloads with new date parameter -->
                <div class="date-filter">
                    <label for="chartDate">Date:</label>
                    <input type="date" id="chartDate" value="<?php echo $selectedDate; ?>" 
                           onchange="window.location.href='?date='+this.value">
                </div>
            </div>
            <div class="chart-container">
                <!-- Canvas element where Chart.js will render the bar chart -->
                <canvas id="capacityChart"></canvas>
            </div>
        </div>
        
        <!-- Chart 2: Daily Occupied Spaces (Line Chart) -->
        <!-- Shows trend of occupied spaces over the last 7 days -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-line"></i> Daily Occupied Spaces</h3>
                <p class="chart-subtitle">Last 7 Days</p>
            </div>
            <div class="chart-container">
                <!-- Canvas element for line chart -->
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
/* ==================== CHART 1: Capacity & Occupancy (Bar Chart) ==================== */
// Chart.js configuration for side-by-side bar chart comparing capacity vs occupied spaces
const capacityCtx = document.getElementById('capacityChart').getContext('2d');
new Chart(capacityCtx, {
    type: 'bar', // Bar chart type
    data: {
        // X-axis labels: Parking area names from PHP array (JSON encoded)
        labels: <?php echo json_encode(array_column($capacityData, 'parkingLot_name')); ?>,
        datasets: [{
            label: 'Capacity', // Blue bars showing total capacity
            data: <?php echo json_encode(array_column($capacityData, 'capacity')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.6)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2
        }, {
            label: 'Occupied', // Red bars showing occupied spaces
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
                beginAtZero: true, // Y-axis starts at 0
                ticks: {
                    stepSize: 10 // Increment Y-axis by 10
                }
            }
        },
        plugins: {
            legend: {
                position: 'top', // Legend at top of chart
            },
            title: {
                display: false
            }
        }
    }
});

/* ==================== CHART 2: Daily Occupied Spaces (Line Chart) ==================== */
// Line chart showing occupancy trend over the last 7 days
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line', // Line chart type
    data: {
        // X-axis: Dates (YYYY-MM-DD format) from PHP associative array keys
        labels: <?php echo json_encode(array_keys($last7Days)); ?>,
        datasets: [{
            label: 'Occupied Spaces', // Green line showing occupied count
            // Y-axis: Number of occupied spaces for each date
            data: <?php echo json_encode(array_values($last7Days)); ?>,
            fill: true, // Fill area under the line
            backgroundColor: 'rgba(16, 185, 129, 0.2)', // Light green fill
            borderColor: 'rgba(16, 185, 129, 1)', // Green line
            borderWidth: 3,
            tension: 0.4, // Smooth curve
            pointRadius: 5, // Size of data point circles
            pointBackgroundColor: 'rgba(16, 185, 129, 1)'
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
// Render footer from layout.php
renderFooter(); 
?>
