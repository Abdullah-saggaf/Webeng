<?php
require_once __DIR__ . '/module01/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/database/db_config.php';

// Require safety_staff role
requireRole(['safety_staff']);

$user = currentUser();
$db = getDB();

// ============================================
// MODULE 1: Vehicle Grant Approval Data
// ============================================
$pendingVehicles = getPendingVehicles();
$approvedVehicles = getApprovedVehicles();
$rejectedVehicles = getRejectedVehicles();

$module1Stats = [
    'pending' => count($pendingVehicles),
    'approved' => count($approvedVehicles),
    'rejected' => count($rejectedVehicles),
    'total' => count($approvedVehicles) + count($pendingVehicles) + count($rejectedVehicles)
];

// Vehicle type breakdown
$typeQuery = $db->query("
    SELECT vehicle_type, COUNT(*) as count 
    FROM Vehicle 
    GROUP BY vehicle_type
");
$vehicleTypes = $typeQuery->fetchAll(PDO::FETCH_ASSOC);

// Status breakdown
$statusQuery = $db->query("
    SELECT grant_status, COUNT(*) as count 
    FROM Vehicle 
    GROUP BY grant_status
");
$vehicleStatuses = $statusQuery->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// MODULE 4: FK Area Enforcement Insights
// ============================================
$module4Available = false;
$module4Stats = [
    'total_summons' => 0,
    'total_demerit_points' => 0,
    'unpaid_summons' => 0,
    'repeat_offenders' => 0
];
$summonsStatusData = [];
$violationTypeData = [];
$topOffenders = [];

try {
    // Check if Module 4 tables exist
    $tableCheck = $db->query("SHOW TABLES LIKE 'Ticket'");
    if ($tableCheck->rowCount() > 0) {
        $module4Available = true;
        
        // Current month FK area summons
        $currentMonth = date('Y-m');
        $summonQuery = $db->prepare("
            SELECT COUNT(*) as total_summons,
                   SUM(v.violation_points) as total_points
            FROM Ticket t
            JOIN Violation v ON t.violation_ID = v.violation_ID
            WHERE DATE_FORMAT(t.issued_at, '%Y-%m') = :month
        ");
        $summonQuery->execute([':month' => $currentMonth]);
        $summonData = $summonQuery->fetch();
        
        $module4Stats['total_summons'] = $summonData['total_summons'] ?? 0;
        $module4Stats['total_demerit_points'] = $summonData['total_points'] ?? 0;
        
        // Unpaid/active summons
        $unpaidQuery = $db->query("
            SELECT COUNT(*) as unpaid 
            FROM Ticket 
            WHERE ticket_status IN ('Unpaid', 'Active', 'Open')
        ");
        $unpaidData = $unpaidQuery->fetch();
        $module4Stats['unpaid_summons'] = $unpaidData['unpaid'] ?? 0;
        
        // Repeat offenders (users with >1 summons)
        $repeatQuery = $db->query("
            SELECT COUNT(DISTINCT user_ID) as repeat_count
            FROM Ticket
            GROUP BY user_ID
            HAVING COUNT(*) > 1
        ");
        $module4Stats['repeat_offenders'] = $repeatQuery->rowCount();
        
        // Summons by status
        $statusSummonsQuery = $db->query("
            SELECT ticket_status, COUNT(*) as count
            FROM Ticket
            GROUP BY ticket_status
        ");
        $summonsStatusData = $statusSummonsQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // Violation type breakdown
        $violationQuery = $db->query("
            SELECT v.violation_type, COUNT(*) as count
            FROM Ticket t
            JOIN Violation v ON t.violation_ID = v.violation_ID
            GROUP BY v.violation_type
            ORDER BY count DESC
            LIMIT 10
        ");
        $violationTypeData = $violationQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // Top offenders (bonus)
        $topOffendersQuery = $db->query("
            SELECT u.user_ID, u.username,
                   COUNT(t.ticket_ID) as summons_count,
                   SUM(v.violation_points) as total_points
            FROM Ticket t
            JOIN User u ON t.user_ID = u.user_ID
            JOIN Violation v ON t.violation_ID = v.violation_ID
            GROUP BY u.user_ID, u.username
            ORDER BY summons_count DESC, total_points DESC
            LIMIT 5
        ");
        $topOffenders = $topOffendersQuery->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Module 4 not available - safe to continue
    $module4Available = false;
}

renderHeader('Safety Staff Dashboard');
?>

<div class="card">
    <h2>Safety & Security Management Dashboard</h2>
    <p>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
    <p style="color: #6b7280; font-size: 14px;">Unified dashboard combining vehicle approval oversight and FK area enforcement insights.</p>
</div>

<!-- ============================================ -->
<!-- Vehicle Grant Approval Section              -->
<!-- ============================================ -->
<div class="card" style="margin-top: 32px; border-left: 4px solid #667eea;">
    <h2 style="color: #667eea; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-id-card"></i> Vehicle Grant Approval (FK Users)
    </h2>
    <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px;">Monitor and manage vehicle registration approvals for FK community members.</p>

    <!-- Module 1 KPI Cards -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3>Pending Approvals</h3>
                <p class="stat-number"><?php echo $module1Stats['pending']; ?></p>
            </div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3>Approved</h3>
                <p class="stat-number"><?php echo $module1Stats['approved']; ?></p>
            </div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <h3>Rejected</h3>
                <p class="stat-number"><?php echo $module1Stats['rejected']; ?></p>
            </div>
        </div>
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-content">
                <h3>Total Registered</h3>
                <p class="stat-number"><?php echo $module1Stats['total']; ?></p>
            </div>
        </div>
    </div>

    <!-- Module 1 Charts -->
    <div class="grid" style="margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px;">
        <div style="background: #f9fafb; padding: 20px; border-radius: 12px;">
            <h3 style="margin-bottom: 16px; color: #1f2937;">Vehicle Status Distribution</h3>
            <div class="chart-container" style="position: relative; height: 250px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        <div style="background: #f9fafb; padding: 20px; border-radius: 12px;">
            <h3 style="margin-bottom: 16px; color: #1f2937;">Vehicle Types</h3>
            <div class="chart-container" style="position: relative; height: 250px;">
                <canvas id="typeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Pending Approvals Table -->
    <div style="margin-top: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="color: #1f2937;">Recent Pending Vehicle Approvals</h3>
            <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>" class="btn" style="font-size: 13px;">View All Approvals →</a>
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
                    $displayVehicles = array_slice($pendingVehicles, 0, 5);
                    foreach ($displayVehicles as $vehicle): 
                        $student = getUserById($vehicle['user_ID']);
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($vehicle['license_plate']); ?></strong></td>
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
                <p style="text-align: center; margin-top: 12px; color: #6b7280; font-size: 13px;">
                    <a href="<?php echo appUrl('/safety/vehicle-approvals.php'); ?>">+ <?php echo count($pendingVehicles) - 5; ?> more pending approvals</a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p style="text-align: center; color: #6b7280; padding: 20px; background: #f9fafb; border-radius: 8px;">
                <i class="fas fa-check-circle" style="font-size: 24px; color: #10b981; margin-bottom: 8px; display: block;"></i>
                No pending vehicle approvals. All caught up!
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- FK Area Enforcement Section                 -->
<!-- ============================================ -->
<div class="card" style="margin-top: 32px; border-left: 4px solid #f59e0b;">
    <h2 style="color: #f59e0b; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-shield-alt"></i> FK Area Enforcement Insights
    </h2>
    <p style="color: #6b7280; font-size: 14px; margin-bottom: 24px;">Real-time enforcement metrics and violation tracking for FK parking areas.</p>

    <?php if ($module4Available): ?>
        <!-- Module 4 KPI Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
                <div class="stat-icon" style="color: #f59e0b;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Summons (This Month)</h3>
                    <p class="stat-number"><?php echo $module4Stats['total_summons']; ?></p>
                    <p style="font-size: 11px; color: #92400e; margin-top: 4px;">FK Area Only</p>
                </div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);">
                <div class="stat-icon" style="color: #dc2626;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Demerit Points</h3>
                    <p class="stat-number"><?php echo $module4Stats['total_demerit_points']; ?></p>
                    <p style="font-size: 11px; color: #7f1d1d; margin-top: 4px;">FK Area Only</p>
                </div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);">
                <div class="stat-icon" style="color: #2563eb;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <h3>Unpaid/Active Summons</h3>
                    <p class="stat-number"><?php echo $module4Stats['unpaid_summons']; ?></p>
                    <p style="font-size: 11px; color: #1e3a8a; margin-top: 4px;">FK Area Only</p>
                </div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #e9d5ff 0%, #d8b4fe 100%);">
                <div class="stat-icon" style="color: #9333ea;">
                    <i class="fas fa-redo"></i>
                </div>
                <div class="stat-content">
                    <h3>Repeat Offenders</h3>
                    <p class="stat-number"><?php echo $module4Stats['repeat_offenders']; ?></p>
                    <p style="font-size: 11px; color: #581c87; margin-top: 4px;">Users with >1 Summons</p>
                </div>
            </div>
        </div>

        <!-- Module 4 Charts -->
        <div class="grid" style="margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px;">
            <div style="background: #f9fafb; padding: 20px; border-radius: 12px;">
                <h3 style="margin-bottom: 16px; color: #1f2937;">Summons by Status (FK Area)</h3>
                <div class="chart-container" style="position: relative; height: 250px;">
                    <canvas id="summonsStatusChart"></canvas>
                </div>
            </div>
            <div style="background: #f9fafb; padding: 20px; border-radius: 12px;">
                <h3 style="margin-bottom: 16px; color: #1f2937;">Summons by Violation Type (FK Area)</h3>
                <div class="chart-container" style="position: relative; height: 250px;">
                    <canvas id="violationTypeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Offenders Table (Bonus) -->
        <?php if (!empty($topOffenders)): ?>
        <div style="margin-top: 24px;">
            <h3 style="color: #1f2937; margin-bottom: 16px;">Top Offenders (FK Area)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Total Summons</th>
                        <th>Total Demerit Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($topOffenders as $offender): 
                    ?>
                        <tr>
                            <td><strong>#<?php echo $rank++; ?></strong></td>
                            <td><?php echo htmlspecialchars($offender['user_ID']); ?></td>
                            <td><?php echo htmlspecialchars($offender['username']); ?></td>
                            <td><span class="badge rejected"><?php echo $offender['summons_count']; ?></span></td>
                            <td><span class="badge pending"><?php echo $offender['total_points']; ?> pts</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Module 4 Not Available -->
        <div style="text-align: center; padding: 60px 20px; background: #fef3c7; border-radius: 12px;">
            <i class="fas fa-info-circle" style="font-size: 48px; color: #f59e0b; margin-bottom: 16px;"></i>
            <h3 style="color: #92400e; margin-bottom: 8px;">Enforcement Data Not Available Yet</h3>
            <p style="color: #78350f; font-size: 14px;">Enforcement tracking tables (Ticket, Violation) are not configured. This section will display FK area enforcement insights once the system is fully implemented.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ============================================
// MODULE 1 CHARTS
// ============================================

// Chart 1: Vehicle Status Distribution
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($s) { return "'" . $s['grant_status'] . "'"; }, $vehicleStatuses)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($vehicleStatuses, 'count')); ?>],
            backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 12, font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Chart 2: Vehicle Types
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($t) { return "'" . $t['vehicle_type'] . "'"; }, $vehicleTypes)); ?>],
        datasets: [{
            label: 'Vehicles',
            data: [<?php echo implode(',', array_column($vehicleTypes, 'count')); ?>],
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

<?php if ($module4Available): ?>
// ============================================
// MODULE 4 CHARTS
// ============================================

// Chart 3: Summons by Status
const summonsStatusCtx = document.getElementById('summonsStatusChart').getContext('2d');
new Chart(summonsStatusCtx, {
    type: 'pie',
    data: {
        labels: [<?php echo implode(',', array_map(function($s) { return "'" . $s['ticket_status'] . "'"; }, $summonsStatusData)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($summonsStatusData, 'count')); ?>],
            backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#6b7280'],
            borderWidth: 3,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 12, font: { size: 12 } }
            }
        }
    }
});

// Chart 4: Violation Types (Bar Chart)
const violationTypeCtx = document.getElementById('violationTypeChart').getContext('2d');
new Chart(violationTypeCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($v) { return "'" . addslashes($v['violation_type']) . "'"; }, $violationTypeData)); ?>],
        datasets: [{
            label: 'Summons Count',
            data: [<?php echo implode(',', array_column($violationTypeData, 'count')); ?>],
            backgroundColor: 'rgba(245, 158, 11, 0.8)',
            borderColor: 'rgba(245, 158, 11, 1)',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
<?php endif; ?>
</script>

<?php renderFooter(); ?>