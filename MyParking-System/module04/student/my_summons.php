<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['student']);

$user = currentUser();
$userId = $user['user_id'];

// Get student's tickets
$tickets = getTicketsByUser($userId);

require_once __DIR__ . '/../../layout.php';
renderHeader('My Summons');
?>

<div class="card">
    <h2><i class="fas fa-file-invoice"></i> My Traffic Summons</h2>
    <p>View all traffic summons issued to your vehicles</p>
    
    <?php if (empty($tickets)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 10px; margin-top: 20px;">
            <i class="fas fa-smile" style="font-size: 64px; color: #10b981; margin-bottom: 20px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #374151; font-size: 20px;">Great News!</h3>
            <p style="margin: 0; color: #6b7280; font-size: 16px;">
                You have no traffic summons. Keep up the good driving!
            </p>
        </div>
    <?php else: ?>
        <!-- Statistics Summary -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
            <?php
            $totalSummons = count($tickets);
            $unpaidCount = 0;
            $paidCount = 0;
            $totalFines = 0;
            $unpaidFines = 0;
            
            foreach ($tickets as $ticket) {
                $totalFines += $ticket['fine_amount'];
                if ($ticket['ticket_status'] === 'Unpaid') {
                    $unpaidCount++;
                    $unpaidFines += $ticket['fine_amount'];
                } elseif ($ticket['ticket_status'] === 'Paid') {
                    $paidCount++;
                }
            }
            ?>
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
                <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Total Summons</div>
                <div style="font-size: 28px; font-weight: 700;"><?php echo $totalSummons; ?></div>
            </div>
            
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
                <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Unpaid</div>
                <div style="font-size: 28px; font-weight: 700;"><?php echo $unpaidCount; ?></div>
            </div>
            
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
                <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Total Fines</div>
                <div style="font-size: 28px; font-weight: 700;">RM <?php echo number_format($totalFines, 2); ?></div>
            </div>
            
            <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 10px;">
                <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Unpaid Fines</div>
                <div style="font-size: 28px; font-weight: 700;">RM <?php echo number_format($unpaidFines, 2); ?></div>
            </div>
        </div>
        
        <?php if ($unpaidCount > 0): ?>
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <div style="display: flex; align-items: start; gap: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #d97706; font-size: 20px; margin-top: 2px;"></i>
                    <div>
                        <strong style="color: #92400e;">Attention Required</strong>
                        <p style="margin: 5px 0 0 0; color: #78350f; font-size: 14px;">
                            You have <?php echo $unpaidCount; ?> unpaid summon(s) totaling RM <?php echo number_format($unpaidFines, 2); ?>. 
                            Please settle your fines as soon as possible.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Summons Table -->
        <div style="overflow-x: auto; margin-top: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Ticket ID</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Vehicle Plate</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Violation</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px;">Points</th>
                        <th style="padding: 12px; text-align: right; font-weight: 600; font-size: 13px;">Fine (RM)</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px;">Status</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Issued Date</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 12px; font-weight: 600; color: #374151;">
                                #<?php echo htmlspecialchars($ticket['ticket_ID']); ?>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 600; color: #374151;">
                                    <?php echo htmlspecialchars($ticket['license_plate']); ?>
                                </div>
                                <small style="color: #6b7280; font-size: 12px;">
                                    <?php echo htmlspecialchars($ticket['vehicle_type']); ?>
                                </small>
                            </td>
                            <td style="padding: 12px; color: #374151;">
                                <?php echo htmlspecialchars($ticket['violation_type']); ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <span style="background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px;">
                                    <?php echo htmlspecialchars($ticket['violation_points']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; text-align: right; font-weight: 600; font-size: 14px; color: #374151;">
                                <?php echo number_format($ticket['fine_amount'], 2); ?>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <?php
                                $statusColor = '#6b7280';
                                $statusBg = '#f3f4f6';
                                if ($ticket['ticket_status'] === 'Paid') {
                                    $statusColor = '#059669';
                                    $statusBg = '#d1fae5';
                                } elseif ($ticket['ticket_status'] === 'Cancelled') {
                                    $statusColor = '#dc2626';
                                    $statusBg = '#fee2e2';
                                } elseif ($ticket['ticket_status'] === 'Unpaid') {
                                    $statusColor = '#d97706';
                                    $statusBg = '#fef3c7';
                                }
                                ?>
                                <span style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; padding: 5px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; white-space: nowrap;">
                                    <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; color: #6b7280; font-size: 13px;">
                                <?php echo date('M d, Y', strtotime($ticket['issued_at'])); ?>
                                <br>
                                <small style="color: #9ca3af;">
                                    <?php echo date('h:i A', strtotime($ticket['issued_at'])); ?>
                                </small>
                            </td>
                            <td style="padding: 12px; color: #6b7280; font-size: 13px; max-width: 200px;">
                                <?php 
                                $desc = htmlspecialchars($ticket['description']);
                                echo !empty($desc) ? $desc : '<em style="color: #9ca3af;">No description</em>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: #3b82f6;"></i>
                <p style="margin: 0; color: #6b7280; font-size: 13px;">
                    <strong>Note:</strong> Please contact the Safety Management Unit if you have any questions about your summons 
                    or need to arrange payment. All fines must be settled before graduation or vehicle grant renewal.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
