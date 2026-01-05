<?php
require_once __DIR__ . '/module01/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/database/db_config.php';

// Require safety_staff role
requireRole(['safety_staff']);

$user = currentUser();

// Get vehicle statistics
$pendingVehicles = getPendingVehicles();
$approvedVehicles = getApprovedVehicles();
$rejectedVehicles = getRejectedVehicles();

$stats = [
    'pending' => count($pendingVehicles),
    'approved' => count($approvedVehicles),
    'rejected' => count($rejectedVehicles),
    'total_approvals' => count($approvedVehicles) + count($pendingVehicles) + count($rejectedVehicles)
];

// Get vehicle type breakdown
$db = getDB();
$typeQuery = $db->query("
    SELECT vehicle_type, COUNT(*) as count 
    FROM Vehicle 
    GROUP BY vehicle_type
");
$vehicleTypes = $typeQuery->fetchAll(PDO::FETCH_ASSOC);

// Get status breakdown with percentages
$statusQuery = $db->query("
    SELECT grant_status, COUNT(*) as count 
    FROM Vehicle 
    GROUP BY grant_status
");
$vehicleStatuses = $statusQuery->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Safety Staff Dashboard');
?>

<div class="card">
    <h2>Safety Staff Dashboard</h2>
    <p>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
    <p style="color: #6b7280; font-size: 14px;">Monitor vehicle approvals and system statistics in real-time.</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid" style="margin-top: 24px;">
    <div class="stat-card pending">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3>Pending Approvals</h3>
            <p class="stat-number"><?php echo $stats['pending']; ?></p>
        </div>
    </div>
    <div class="stat-card approved">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3>Approved</h3>
            <p class="stat-number"><?php echo $stats['approved']; ?></p>
        </div>
    </div>
    <div class="stat-card rejected">
        <div class="stat-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-content">
            <h3>Rejected</h3>
            <p class="stat-number"><?php echo $stats['rejected']; ?></p>
        </div>
    </div>
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="fas fa-car"></i>
        </div>
        <div class="stat-content">
            <h3>Total Processed</h3>
            <p class="stat-number"><?php echo $stats['total_approvals']; ?></p>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid" style="margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
    <div class="card">
        <h3 style="margin-bottom: 20px;">Vehicle Status Distribution</h3>
        <div class="chart-container">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
    <div class="card">
        <h3 style="margin-bottom: 20px;">Vehicle Types</h3>
        <div class="chart-container">
            <canvas id="typeChart"></canvas>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2>Pending Vehicle Approvals</h2>
        <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="btn">View All</a>
    </div>
    
    <?php if ($pendingVehicles): ?>
        <table>
            <thead>
                <tr>
                    <th>License Plate</th>
                    <th>Student</th>
                    <th>Vehicle Type</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $displayVehicles = array_slice($pendingVehicles, 0, 5); // Show first 5
                foreach ($displayVehicles as $vehicle): 
                    $student = getUserById($vehicle['user_ID']);
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                        <td><?php echo htmlspecialchars($student['username'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                        <td>
                            <a href="<?php echo appUrl('/safety/vehicle-approvals.php?id=' . $vehicle['vehicle_ID']); ?>" class="btn" style="font-size: 12px; padding: 6px 10px;">Review</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($pendingVehicles) > 5): ?>
            <p style="text-align: center; margin-top: 12px; color: #6b7280;">
                <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>">View all <?php echo count($pendingVehicles); ?> pending approvals →</a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="text-align: center; color: #6b7280; padding: 20px;">No pending vehicle approvals. All caught up!</p>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 24px;">
    <h2>Quick Actions</h2>
    <div class="actions">
        <a href="<?php echo APP_BASE_PATH . '/module02/admin/parkingDashboard.php'; ?>" class="btn">Parking Dashboard</a>
        <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="btn">Go to Approvals</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Vehicle Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($s) { return "'" . $s['grant_status'] . "'"; }, $vehicleStatuses)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($vehicleStatuses, 'count')); ?>],
            backgroundColor: [
                '#f59e0b',  // Pending - amber
                '#10b981',  // Approved - green
                '#ef4444'   // Rejected - red
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 13
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Vehicle Type Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeChart = new Chart(typeCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($t) { return "'" . $t['vehicle_type'] . "'"; }, $vehicleTypes)); ?>],
        datasets: [{
            label: 'Number of Vehicles',
            data: [<?php echo implode(',', array_column($vehicleTypes, 'count')); ?>],
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Vehicles: ${context.parsed.y}`;
                    }
                }
            }
        }
    }
});
</script>

<?php renderFooter(); ?>