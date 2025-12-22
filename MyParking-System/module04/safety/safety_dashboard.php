<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['safety_staff', 'fk_staff']);

$error_message = '';
$stats = null;
$violations = getAllViolations();

// Initialize filter values
$dateFrom = $_POST['date_from'] ?? '';
$dateTo = $_POST['date_to'] ?? '';
$statusFilter = $_POST['status_filter'] ?? '';
$violationFilter = $_POST['violation_filter'] ?? '';

// Build filters array
$filters = [];
if (!empty($dateFrom)) {
    $filters['date_from'] = $dateFrom;
}
if (!empty($dateTo)) {
    $filters['date_to'] = $dateTo;
}
if (!empty($statusFilter)) {
    $filters['status'] = $statusFilter;
}
if (!empty($violationFilter)) {
    $filters['violation_id'] = $violationFilter;
}

// Get dashboard statistics
try {
    $stats = getDashboardStats($filters);
} catch (Exception $e) {
    $error_message = "Data retrieval error. Please try again later.";
}

require_once __DIR__ . '/../../layout.php';
renderHeader('Safety Dashboard');
?>

<div class="card">
    <h2><i class="fas fa-shield-alt"></i> Safety Dashboard - Traffic Reports</h2>
    <p>View traffic summons statistics and reports</p>
    
    <?php if (!empty($error_message)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin: 15px 0; border: 1px solid #fecaca;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Filter Form -->
    <form method="POST" style="background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb; margin: 20px 0;">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
            <i class="fas fa-filter"></i> Filter Reports
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
            </div>
            
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                       style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
            </div>
            
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Status</label>
                <select name="status_filter" 
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                    <option value="">All</option>
                    <option value="Unpaid" <?php echo $statusFilter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="Paid" <?php echo $statusFilter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px;">Violation Type</label>
                <select name="violation_filter" 
                        style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;">
                    <option value="">All</option>
                    <?php foreach ($violations as $violation): ?>
                        <option value="<?php echo htmlspecialchars($violation['violation_ID']); ?>" 
                                <?php echo $violationFilter == $violation['violation_ID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($violation['violation_type']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 15px; display: flex; gap: 10px;">
            <button type="submit" class="btn">
                <i class="fas fa-sync-alt"></i> Apply Filter
            </button>
            <a href="<?php echo APP_BASE_PATH . '/module04/safety/safety_dashboard.php'; ?>" 
               class="btn" style="background: #6b7280; text-decoration: none;">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
    
    <?php if ($stats): ?>
        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 25px 0;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 10px;">
                        <i class="fas fa-file-invoice" style="font-size: 28px;"></i>
                    </div>
                    <div>
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Total Tickets</div>
                        <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($stats['total_tickets']); ?></div>
                    </div>
                </div>
            </div>
            
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 10px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 28px;"></i>
                    </div>
                    <div>
                        <div style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Total Points Issued</div>
                        <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($stats['total_points_issued']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (empty($stats['tickets_by_violation_type']) && empty($stats['tickets_by_status'])): ?>
            <div style="text-align: center; padding: 40px; color: #6b7280; background: #f9fafb; border-radius: 10px; margin: 20px 0;">
                <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                <h3 style="margin: 0 0 10px 0;">No data for selected filters</h3>
                <p style="margin: 0;">Try adjusting your filter criteria or selecting a different date range.</p>
            </div>
        <?php else: ?>
            <!-- Reports Tables -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 25px;">
                <!-- Tickets by Violation Type -->
                <div>
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                        <i class="fas fa-chart-pie"></i> Tickets by Violation Type
                    </h3>
                    <?php if (!empty($stats['tickets_by_violation_type'])): ?>
                        <div style="background: #f9fafb; border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #374151; color: white;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Violation Type</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px;">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['tickets_by_violation_type'] as $item): ?>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 10px; font-size: 13px;"><?php echo htmlspecialchars($item['violation_type']); ?></td>
                                            <td style="padding: 10px; text-align: center; font-weight: 600; font-size: 14px;">
                                                <span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 6px;">
                                                    <?php echo number_format($item['count']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #6b7280; font-style: italic;">No data available</p>
                    <?php endif; ?>
                </div>
                
                <!-- Tickets by Status -->
                <div>
                    <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                        <i class="fas fa-chart-bar"></i> Tickets by Status
                    </h3>
                    <?php if (!empty($stats['tickets_by_status'])): ?>
                        <div style="background: #f9fafb; border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #374151; color: white;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Status</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px;">Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['tickets_by_status'] as $item): ?>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 10px; font-size: 13px;">
                                                <?php
                                                $statusColor = '#6b7280';
                                                $statusBg = '#f3f4f6';
                                                if ($item['ticket_status'] === 'Paid') {
                                                    $statusColor = '#059669';
                                                    $statusBg = '#d1fae5';
                                                } elseif ($item['ticket_status'] === 'Cancelled') {
                                                    $statusColor = '#dc2626';
                                                    $statusBg = '#fee2e2';
                                                } elseif ($item['ticket_status'] === 'Unpaid') {
                                                    $statusColor = '#d97706';
                                                    $statusBg = '#fef3c7';
                                                }
                                                ?>
                                                <span style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; padding: 4px 10px; border-radius: 6px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($item['ticket_status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 10px; text-align: center; font-weight: 600; font-size: 14px;">
                                                <span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 6px;">
                                                    <?php echo number_format($item['count']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #6b7280; font-style: italic;">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
