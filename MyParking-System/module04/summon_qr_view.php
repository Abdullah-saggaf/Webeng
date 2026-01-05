<?php
/**
 * Traffic Summon Public View (QR Scan Target)
 * Module 04 - MyParking System
 * 
 * PURPOSE: Display summon details after QR scan
 * Shows violation info + demerit points + enforcement status
 * PUBLIC PAGE: No login required (accessible via QR code)
 */

// Get QR code from URL parameter
$code = $_GET['code'] ?? '';

// Validate code
if (empty($code)) {
    die('<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid QR Code</title></head>
    <body style="font-family: Arial; padding: 40px; text-align: center;">
    <h2 style="color: #ef4444;">❌ Invalid QR Code</h2>
    <p>Please scan a valid traffic summon QR code.</p>
    </body></html>');
}

// Include database configuration
require_once __DIR__ . '/../database/db_config.php';

// Fetch ticket details by QR code
try {
    $ticket = getTicketDetailsByQrCode($code);
    
    if (!$ticket) {
        // QR code not found or invalid
        die('<!DOCTYPE html>
        <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Summon</title></head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; 
                     background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                     min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: white; border-radius: 20px; padding: 40px; text-align: center; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="font-size: 60px; margin-bottom: 20px;">🚫</div>
            <h2 style="color: #dc2626; margin-bottom: 15px; font-size: 24px;">Invalid or Expired Summon</h2>
            <p style="color: #6b7280; line-height: 1.6;">This QR code does not match any active traffic summon in our system.</p>
        </div>
        </body></html>');
    }
    
    // Get user's total demerit points
    $total_points = getUserTotalPoints($ticket['user_ID']);
    
    // Determine enforcement status based on total points
    $enforcement = [
        'level' => 0,
        'status' => 'No Action Required',
        'description' => 'Clean record - no enforcement action needed.',
        'color' => '#059669',
        'bg_color' => '#d1fae5',
        'icon' => '✅'
    ];
    
    if ($total_points >= 80) {
        $enforcement = [
            'level' => 4,
            'status' => 'Permission Revoked',
            'description' => 'Parking permission revoked for entire study duration.',
            'color' => '#991b1b',
            'bg_color' => '#fee2e2',
            'icon' => '🚫'
        ];
    } elseif ($total_points >= 50) {
        $enforcement = [
            'level' => 3,
            'status' => 'Severe Violation',
            'description' => 'Parking permission revoked for 2 semesters.',
            'color' => '#b91c1c',
            'bg_color' => '#fecaca',
            'icon' => '⛔'
        ];
    } elseif ($total_points >= 20) {
        $enforcement = [
            'level' => 2,
            'status' => 'Major Violation',
            'description' => 'Parking permission revoked for 1 semester.',
            'color' => '#dc2626',
            'bg_color' => '#fee2e2',
            'icon' => '⚠️'
        ];
    } elseif ($total_points >= 1) {
        $enforcement = [
            'level' => 1,
            'status' => 'Warning Issued',
            'description' => 'You have accumulated demerit points. Please follow campus traffic regulations.',
            'color' => '#d97706',
            'bg_color' => '#fef3c7',
            'icon' => '⚡'
        ];
    }
    
} catch (Exception $e) {
    die('<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title></head>
    <body style="font-family: Arial; padding: 40px; text-align: center;">
    <h2 style="color: #ef4444;">❌ System Error</h2>
    <p>Unable to load summon information.</p>
    <p style="color: #999; font-size: 12px;">' . htmlspecialchars($e->getMessage()) . '</p>
    </body></html>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Summon - <?php echo htmlspecialchars($ticket['user_ID']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #1f2937;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 3px solid #f3f4f6;
        }
        
        .badge-warning {
            display: inline-block;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .summon-title {
            font-size: 28px;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .ticket-id {
            font-size: 14px;
            color: #6b7280;
            font-weight: 600;
        }
        
        .violation-alert {
            background: #fee2e2;
            border: 3px solid #dc2626;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }
        
        .violation-type {
            font-size: 20px;
            color: #991b1b;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        
        .points-display {
            background: #dc2626;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 22px;
            font-weight: 800;
            display: inline-block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
        }
        
        .info-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .info-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .info-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 600;
        }
        
        .total-points-card {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin: 25px 0;
        }
        
        .total-label {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .total-value {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 5px;
        }
        
        .total-subtitle {
            font-size: 14px;
            opacity: 0.7;
        }
        
        .enforcement-card {
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }
        
        .enforcement-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .enforcement-status {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 10px;
        }
        
        .enforcement-description {
            font-size: 15px;
            line-height: 1.6;
            opacity: 0.9;
        }
        
        .description-box {
            background: #f9fafb;
            border-left: 4px solid #6b7280;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .description-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .description-text {
            font-size: 14px;
            color: #374151;
            line-height: 1.6;
        }
        
        .footer-info {
            text-align: center;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }
        
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .summon-title {
                font-size: 24px;
            }
            .total-value {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <!-- Header -->
            <div class="header">
                <div class="badge-warning">
                    <i class="fas fa-exclamation-triangle"></i> Traffic Violation
                </div>
                <div class="summon-title">Traffic Summon Notice</div>
                <div class="ticket-id">Ticket #<?php echo htmlspecialchars($ticket['ticket_ID']); ?></div>
            </div>
            
            <!-- Violation Alert -->
            <div class="violation-alert">
                <div class="violation-type">
                    <?php echo htmlspecialchars($ticket['violation_type']); ?>
                </div>
                <div class="points-display">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo htmlspecialchars($ticket['violation_points']); ?> Demerit Points
                </div>
            </div>
            
            <!-- Student & Vehicle Info -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Student ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($ticket['user_ID']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Student Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($ticket['username']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">License Plate</div>
                    <div class="info-value"><?php echo !empty($ticket['license_plate']) ? htmlspecialchars($ticket['license_plate']) : '<em style="color: #9ca3af;">Not Registered</em>'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Vehicle Type</div>
                    <div class="info-value"><?php echo !empty($ticket['vehicle_type']) ? htmlspecialchars($ticket['vehicle_type']) : '<em style="color: #9ca3af;">N/A</em>'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Issue Date</div>
                    <div class="info-value"><?php echo date('d M Y', strtotime($ticket['issued_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value" style="<?php 
                        if ($ticket['ticket_status'] === 'Completed') {
                            echo 'color: #059669;';
                        } elseif ($ticket['ticket_status'] === 'Cancelled') {
                            echo 'color: #dc2626; text-decoration: line-through;';
                        }
                    ?>">
                        <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($ticket['description'])): ?>
            <!-- Description -->
            <div class="description-box">
                <div class="description-label">Violation Details</div>
                <div class="description-text"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Total Demerit Points -->
        <div class="card">
            <div class="total-points-card">
                <div class="total-label">Your Total Demerit Points</div>
                <div class="total-value"><?php echo htmlspecialchars($total_points); ?></div>
                <div class="total-subtitle">Accumulated Points</div>
            </div>
        </div>
        
        <!-- Enforcement Status -->
        <div class="card">
            <div class="enforcement-card" style="background: <?php echo $enforcement['bg_color']; ?>; color: <?php echo $enforcement['color']; ?>; text-align: center;">
                <div class="enforcement-icon"><?php echo $enforcement['icon']; ?></div>
                <div class="enforcement-status"><?php echo htmlspecialchars($enforcement['status']); ?></div>
                <div class="enforcement-description"><?php echo htmlspecialchars($enforcement['description']); ?></div>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="card">
            <div class="footer-info">
                <strong><i class="fas fa-info-circle"></i> Enforcement Policy</strong><br>
                <div style="margin-top: 10px; text-align: left;">
                    • <strong>0 points:</strong> No action - clean record<br>
                    • <strong>1-19 points:</strong> Warning given<br>
                    • <strong>20-49 points:</strong> Permission revoked for 1 semester<br>
                    • <strong>50-79 points:</strong> Permission revoked for 2 semesters<br>
                    • <strong>80+ points:</strong> Permission revoked for entire study duration
                </div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    For inquiries, contact Campus Safety Office
                </div>
            </div>
        </div>
    </div>
</body>
</html>
