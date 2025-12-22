<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['student']);

$user = currentUser();
$userId = $user['user_id'];

// Get user's total demerit points
$totalPoints = getUserTotalPoints($userId);

// Get recent summons with points
$tickets = getTicketsByUser($userId);

// Calculate points threshold for warning
$warningThreshold = 50;
$showWarning = $totalPoints >= $warningThreshold;

require_once __DIR__ . '/../../layout.php';
renderHeader('Demerit Points');
?>

<div class="card">
    <h2><i class="fas fa-exclamation-triangle"></i> My Demerit Points</h2>
    <p>Track your traffic violation demerit points</p>
    
    <!-- Total Points Display -->
    <div style="margin: 25px 0;">
        <div style="text-align: center; padding: 40px; background: linear-gradient(135deg, <?php echo $showWarning ? '#fee2e2' : '#f0f9ff'; ?> 0%, <?php echo $showWarning ? '#fef3c7' : '#dbeafe'; ?> 100%); border-radius: 15px; border: 2px solid <?php echo $showWarning ? '#fbbf24' : '#60a5fa'; ?>;">
            <i class="fas fa-tachometer-alt" style="font-size: 48px; color: <?php echo $showWarning ? '#dc2626' : '#2563eb'; ?>; margin-bottom: 15px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #374151; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">
                Total Demerit Points
            </h3>
            <div style="font-size: 72px; font-weight: 800; color: <?php echo $showWarning ? '#dc2626' : '#2563eb'; ?>; line-height: 1; margin: 15px 0;">
                <?php echo $totalPoints; ?>
            </div>
            <div style="font-size: 14px; color: #6b7280; margin-top: 10px;">
                <?php if ($totalPoints === 0): ?>
                    <span style="color: #059669; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Perfect! No demerit points
                    </span>
                <?php elseif ($totalPoints < 20): ?>
                    <span style="color: #059669; font-weight: 600;">
                        <i class="fas fa-thumbs-up"></i> Low demerit points - Keep it up!
                    </span>
                <?php elseif ($totalPoints < 50): ?>
                    <span style="color: #d97706; font-weight: 600;">
                        <i class="fas fa-exclamation-circle"></i> Moderate demerit points - Drive carefully
                    </span>
                <?php else: ?>
                    <span style="color: #dc2626; font-weight: 600;">
                        <i class="fas fa-exclamation-triangle"></i> High demerit points - Action required!
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- High Demerit Warning (Alternative Flow A3) -->
    <?php if ($showWarning): ?>
        <div style="background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%); border-left: 5px solid #dc2626; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);">
            <div style="display: flex; align-items: start; gap: 15px;">
                <div style="background: #dc2626; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                </div>
                <div>
                    <h3 style="margin: 0 0 10px 0; color: #991b1b; font-size: 18px;">
                        ⚠️ High Demerit Points Warning
                    </h3>
                    <p style="margin: 0 0 10px 0; color: #78350f; font-size: 14px; line-height: 1.6;">
                        Your demerit points have reached <strong><?php echo $totalPoints; ?> points</strong>, which exceeds the warning threshold of <?php echo $warningThreshold; ?> points.
                    </p>
                    <div style="background: rgba(255, 255, 255, 0.7); padding: 15px; border-radius: 8px; margin-top: 12px;">
                        <p style="margin: 0 0 8px 0; color: #92400e; font-weight: 600; font-size: 14px;">
                            <i class="fas fa-info-circle"></i> Possible Consequences:
                        </p>
                        <ul style="margin: 0; padding-left: 20px; color: #78350f; font-size: 13px; line-height: 1.8;">
                            <li>Suspension of parking privileges</li>
                            <li>Mandatory attendance at traffic safety workshop</li>
                            <li>Vehicle grant renewal may be affected</li>
                            <li>Disciplinary action if points continue to accumulate</li>
                        </ul>
                    </div>
                    <div style="margin-top: 15px; padding: 12px; background: rgba(220, 38, 38, 0.1); border-radius: 6px;">
                        <p style="margin: 0; color: #991b1b; font-size: 13px; font-weight: 600;">
                            <i class="fas fa-phone"></i> Action Required: Please contact the Safety Management Unit immediately to discuss your demerit points status.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Points Information -->
    <div style="background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb; margin: 20px 0;">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
            <i class="fas fa-info-circle"></i> Understanding Demerit Points
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #059669; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px;">0-19 Points</div>
                <div style="font-size: 13px; color: #6b7280;">Good standing - No action required</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #d97706; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px;">20-49 Points</div>
                <div style="font-size: 13px; color: #6b7280;">Warning - Exercise caution</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #dc2626; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px;">50+ Points</div>
                <div style="font-size: 13px; color: #6b7280;">High risk - Action required</div>
            </div>
        </div>
    </div>
    
    <!-- Recent Summons with Points -->
    <?php if (!empty($tickets)): ?>
        <div style="margin-top: 25px;">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #374151;">
                <i class="fas fa-list"></i> Recent Summons Contributing to Points
            </h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Date</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Violation</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px;">Vehicle Plate</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px;">Points Added</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Show latest 10 tickets
                        $recentTickets = array_slice($tickets, 0, 10);
                        foreach ($recentTickets as $ticket): 
                        ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 12px; font-size: 13px; color: #6b7280;">
                                    <?php echo date('M d, Y', strtotime($ticket['issued_at'])); ?>
                                </td>
                                <td style="padding: 12px; color: #374151; font-size: 14px;">
                                    <?php echo htmlspecialchars($ticket['violation_type']); ?>
                                </td>
                                <td style="padding: 12px; font-weight: 600; color: #374151;">
                                    <?php echo htmlspecialchars($ticket['license_plate']); ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <span style="background: #fee2e2; color: #b91c1c; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 14px;">
                                        +<?php echo htmlspecialchars($ticket['violation_points']); ?>
                                    </span>
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
                                    <span style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 12px;">
                                        <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($tickets) > 10): ?>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="<?php echo APP_BASE_PATH . '/module04/student/my_summons.php'; ?>" 
                       class="btn" style="display: inline-flex; text-decoration: none;">
                        <i class="fas fa-list"></i> View All Summons
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: #f0fdf4; border-radius: 10px; margin-top: 20px; border: 2px solid #86efac;">
            <i class="fas fa-medal" style="font-size: 48px; color: #059669; margin-bottom: 15px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #059669; font-size: 20px;">Perfect Record!</h3>
            <p style="margin: 0; color: #047857; font-size: 15px;">
                You have no traffic violations. Keep driving safely!
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Additional Information -->
    <div style="margin-top: 25px; padding: 15px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
        <div style="display: flex; align-items: start; gap: 10px;">
            <i class="fas fa-lightbulb" style="color: #2563eb; font-size: 20px; margin-top: 2px;"></i>
            <div>
                <p style="margin: 0 0 8px 0; color: #1e40af; font-weight: 600; font-size: 14px;">
                    How to Reduce Demerit Points:
                </p>
                <ul style="margin: 0; padding-left: 20px; color: #1e3a8a; font-size: 13px; line-height: 1.8;">
                    <li>Attend traffic safety workshops (points reduction may be offered)</li>
                    <li>Maintain a clean record for 6 months (automatic point reduction)</li>
                    <li>Pay all outstanding fines promptly</li>
                    <li>Follow all campus traffic rules and regulations</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
