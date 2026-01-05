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

// Determine enforcement status based on Table A
function getEnforcementStatus($points) {
    if ($points == 0) {
        return [
            'level' => 0,
            'label' => 'No Action Required',
            'description' => 'Clean record - no enforcement action needed',
            'color' => '#059669',
            'bg' => '#d1fae5',
            'icon' => 'check-circle'
        ];
    } elseif ($points >= 1 && $points <= 19) {
        return [
            'level' => 1,
            'label' => 'Warning Issued',
            'description' => 'Warning given - please follow campus traffic regulations',
            'color' => '#d97706',
            'bg' => '#fef3c7',
            'icon' => 'exclamation-circle'
        ];
    } elseif ($points >= 20 && $points <= 49) {
        return [
            'level' => 2,
            'label' => 'Permission Revoked - 1 Semester',
            'description' => 'Parking permission revoked for 1 semester',
            'color' => '#dc2626',
            'bg' => '#fee2e2',
            'icon' => 'exclamation-triangle'
        ];
    } elseif ($points >= 50 && $points <= 79) {
        return [
            'level' => 3,
            'label' => 'Permission Revoked - 2 Semesters',
            'description' => 'Parking permission revoked for 2 semesters',
            'color' => '#b91c1c',
            'bg' => '#fecaca',
            'icon' => 'ban'
        ];
    } else { // >= 80
        return [
            'level' => 4,
            'label' => 'Permission Revoked - Entire Study Duration',
            'description' => 'Parking permission revoked for whole study duration',
            'color' => '#991b1b',
            'bg' => '#fee2e2',
            'icon' => 'times-circle'
        ];
    }
}

$enforcement = getEnforcementStatus($totalPoints);

require_once __DIR__ . '/../../layout.php';
renderHeader('Demerit Points');
?>

<div class="card">
    <h2><i class="fas fa-exclamation-triangle"></i> My Demerit Points</h2>
    <p>Track your traffic violation demerit points</p>
    
    <!-- Total Points Display -->
    <div style="margin: 25px 0;">
        <div style="text-align: center; padding: 40px; background: linear-gradient(135deg, <?php echo $enforcement['bg']; ?> 0%, <?php echo $enforcement['bg']; ?> 100%); border-radius: 15px; border: 2px solid <?php echo $enforcement['color']; ?>;">
            <i class="fas fa-tachometer-alt" style="font-size: 48px; color: <?php echo $enforcement['color']; ?>; margin-bottom: 15px;"></i>
            <h3 style="margin: 0 0 10px 0; color: #374151; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">
                Total Demerit Points
            </h3>
            <div style="font-size: 72px; font-weight: 800; color: <?php echo $enforcement['color']; ?>; line-height: 1; margin: 15px 0;">
                <?php echo $totalPoints; ?>
            </div>
        </div>
    </div>
    
    <!-- Enforcement Status Display -->
    <div style="background: <?php echo $enforcement['bg']; ?>; border-left: 5px solid <?php echo $enforcement['color']; ?>; padding: 20px; border-radius: 10px; margin: 20px 0;">
        <div style="display: flex; align-items: start; gap: 15px;">
            <div style="background: <?php echo $enforcement['color']; ?>; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-<?php echo $enforcement['icon']; ?>" style="font-size: 24px;"></i>
            </div>
            <div style="flex: 1;">
                <h3 style="margin: 0 0 10px 0; color: <?php echo $enforcement['color']; ?>; font-size: 18px; font-weight: 700;">
                    <?php echo htmlspecialchars($enforcement['label']); ?>
                </h3>
                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #374151;">
                    <?php echo htmlspecialchars($enforcement['description']); ?>
                </p>
                
                <?php if ($enforcement['level'] >= 2): ?>
                <div style="background: rgba(255, 255, 255, 0.7); padding: 15px; border-radius: 8px; margin-top: 12px;">
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 14px; color: #991b1b;">
                        <i class="fas fa-info-circle"></i> Action Required:
                    </p>
                    <p style="margin: 0; font-size: 13px; line-height: 1.8; color: #78350f;">
                        Please contact the Safety Management Unit immediately to discuss your parking status and enforcement action.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Points Information -->
    <div style="background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb; margin: 20px 0;">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
            <i class="fas fa-info-circle"></i> Understanding Demerit Points
        </h3>
        
        <!-- Violation Types -->
        <div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
            <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #374151; font-weight: 600;">Violation Types & Demerit Points</h4>
            <div style="display: grid; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f9fafb; border-radius: 6px;">
                    <span style="color: #374151; font-size: 13px;">Parking Violation</span>
                    <span style="background: #fee2e2; color: #b91c1c; padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 13px;">10 points</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f9fafb; border-radius: 6px;">
                    <span style="color: #374151; font-size: 13px;">Not Comply with Campus Traffic Regulations</span>
                    <span style="background: #fee2e2; color: #b91c1c; padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 13px;">15 points</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f9fafb; border-radius: 6px;">
                    <span style="color: #374151; font-size: 13px;">Accident Caused</span>
                    <span style="background: #fee2e2; color: #b91c1c; padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 13px;">20 points</span>
                </div>
            </div>
        </div>
        
        <!-- Enforcement Levels -->
        <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #374151; font-weight: 600;">Enforcement Levels (Table A)</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #059669; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px; font-size: 14px;">0 Points</div>
                <div style="font-size: 12px; color: #6b7280; line-height: 1.4;">No action - Clean record</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #d97706; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px; font-size: 14px;">1-19 Points</div>
                <div style="font-size: 12px; color: #6b7280; line-height: 1.4;">Warning given</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #dc2626; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px; font-size: 14px;">20-49 Points</div>
                <div style="font-size: 12px; color: #6b7280; line-height: 1.4;">Revoke for 1 semester</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #b91c1c; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-ban"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px; font-size: 14px;">50-79 Points</div>
                <div style="font-size: 12px; color: #6b7280; line-height: 1.4;">Revoke for 2 semesters</div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                <div style="color: #991b1b; font-size: 24px; margin-bottom: 8px;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div style="font-weight: 600; color: #374151; margin-bottom: 5px; font-size: 14px;">80+ Points</div>
                <div style="font-size: 12px; color: #6b7280; line-height: 1.4;">Revoke for study duration</div>
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
                                    <?php echo !empty($ticket['license_plate']) ? htmlspecialchars($ticket['license_plate']) : '<em style="color: #9ca3af;">Not Registered</em>'; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php if ($ticket['ticket_status'] === 'Completed'): ?>
                                        <span style="background: #fee2e2; color: #b91c1c; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 14px;">
                                            +<?php echo htmlspecialchars($ticket['violation_points']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #f3f4f6; color: #9ca3af; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 14px; text-decoration: line-through;">
                                            <?php echo htmlspecialchars($ticket['violation_points']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php
                                    $statusColor = '#6b7280';
                                    $statusBg = '#f3f4f6';
                                    if ($ticket['ticket_status'] === 'Completed') {
                                        $statusColor = '#059669';
                                        $statusBg = '#d1fae5';
                                    } elseif ($ticket['ticket_status'] === 'Cancelled') {
                                        $statusColor = '#dc2626';
                                        $statusBg = '#fee2e2';
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
                    Important Notes:
                </p>
                <ul style="margin: 0; padding-left: 20px; color: #1e3a8a; font-size: 13px; line-height: 1.8;">
                    <li>Only <strong>Completed</strong> summons contribute to your demerit points</li>
                    <li><strong>Cancelled</strong> summons do not affect your total points</li>
                    <li>Follow all campus traffic rules to maintain a clean record</li>
                    <li>Contact Safety Management Unit for any questions or appeals</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
