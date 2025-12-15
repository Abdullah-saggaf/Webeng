<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../../database/db_functions.php';

requireRole(['fk_staff']);

$statusCounts = getVehicleStatusCounts();
$studentSummary = getStudentVehicleSummary();

renderHeader('Reports');
?>

<div class="card">
    <h2>Vehicle Status Report</h2>
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statusCounts as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['grant_status']); ?></td>
                    <td><?php echo htmlspecialchars($row['total']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Student Vehicle Summary</h2>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Total</th>
                    <th>Approved</th>
                    <th>Pending</th>
                    <th>Rejected</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentSummary as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_ID']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['total_vehicles']); ?></td>
                        <td><?php echo htmlspecialchars($row['approved_count']); ?></td>
                        <td><?php echo htmlspecialchars($row['pending_count']); ?></td>
                        <td><?php echo htmlspecialchars($row['rejected_count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
