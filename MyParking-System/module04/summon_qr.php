<?php
/**
 * Traffic Summon QR Code Display
 * Module 04 - MyParking System
 * 
 * PURPOSE: Generate and display QR code for a traffic summon ticket
 * QR code encodes URL to summon_qr_view.php?code=XXX
 * WORKFLOW: Safety staff issues summon → Print QR → Student can scan to view details
 * PUBLIC PAGE: No login required (used for printing/viewing)
 */

// Include database configuration
require_once __DIR__ . '/../database/db_config.php';

// Get ticket_id from URL parameter
$ticket_id = (int)($_GET['ticket_id'] ?? 0);

// VALIDATION: Ensure ticket_id is valid
if ($ticket_id < 1) {
    die('<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Ticket</title></head>
    <body style="font-family: Arial; padding: 40px; text-align: center;">
    <h2 style="color: #ef4444;">❌ Invalid Ticket ID</h2>
    <p>Please provide a valid ticket ID.</p>
    </body></html>');
}

// Fetch ticket details
$ticket = getTicketDetailsById($ticket_id);

// If ticket not found, display error
if (!$ticket) {
    die('<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Not Found</title></head>
    <body style="font-family: Arial; padding: 40px; text-align: center;">
    <h2 style="color: #ef4444;">❌ Ticket Not Found</h2>
    <p>The traffic summon you are looking for does not exist.</p>
    </body></html>');
}

// Ensure ticket has QR code (generate if missing)
try {
    $qr_code = ensureTicketHasQrCode($ticket_id);
} catch (Exception $e) {
    die('<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title></head>
    <body style="font-family: Arial; padding: 40px; text-align: center;">
    <h2 style="color: #ef4444;">❌ Error Generating QR Code</h2>
    <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </body></html>');
}

// Build verification URL - detect LAN IP for phone scanning
function getPublicBaseUrl() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $ip = gethostbyname(gethostname());
        if (!empty($ip) && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $host = $ip;
        }
    }
    return 'http://' . $host . '/Webeng/MyParking-System';
}

$verification_url = getPublicBaseUrl() . '/module04/summon_qr_view.php?code=' . urlencode($qr_code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Summon QR - Ticket #<?php echo $ticket_id; ?></title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- QRCode.js library for client-side QR generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        .ticket-number {
            font-size: 28px;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 10px;
        }
        .title {
            font-size: 20px;
            color: #374151;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .qr-container {
            background: #f9fafb;
            padding: 30px;
            border-radius: 15px;
            margin: 25px 0;
            border: 3px dashed #dc2626;
        }
        #qrcode {
            display: inline-block;
            margin: 0 auto;
        }
        .qr-url {
            margin-top: 15px;
            font-size: 11px;
            color: #6b7280;
            word-break: break-all;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
            text-align: left;
        }
        .info-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        .info-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 15px;
            color: #1f2937;
            font-weight: 600;
        }
        .violation-box {
            background: #fee2e2;
            border: 2px solid #dc2626;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .violation-type {
            font-size: 18px;
            color: #991b1b;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .points-badge {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-cancelled {
            background: #e5e7eb;
            color: #374151;
        }
        .btn-container {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-print {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(5, 150, 105, 0.4);
        }
        .btn-back {
            background: #6b7280;
            color: white;
        }
        .btn-back:hover {
            background: #4b5563;
        }
        @media print {
            body {
                background: white;
            }
            .btn-container {
                display: none;
            }
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="qr-card">
        <div class="ticket-number">
            <i class="fas fa-ticket-alt"></i> Ticket #<?php echo htmlspecialchars($ticket_id); ?>
        </div>
        <div class="title">Traffic Summon QR Code</div>
        
        <!-- Violation Info -->
        <div class="violation-box">
            <div class="violation-type">
                <?php echo htmlspecialchars($ticket['violation_type']); ?>
            </div>
            <div class="points-badge">
                <?php echo htmlspecialchars($ticket['violation_points']); ?> Demerit Points
            </div>
        </div>
        
        <!-- Ticket Details -->
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
                <div class="info-value">
                    <?php echo !empty($ticket['license_plate']) ? htmlspecialchars($ticket['license_plate']) : '<em style="color: #9ca3af;">Not Registered</em>'; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Vehicle Type</div>
                <div class="info-value">
                    <?php echo !empty($ticket['vehicle_type']) ? htmlspecialchars($ticket['vehicle_type']) : '<em style="color: #9ca3af;">N/A</em>'; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Issued Date</div>
                <div class="info-value"><?php echo date('d M Y, h:i A', strtotime($ticket['issued_at'])); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge <?php echo $ticket['ticket_status'] === 'Completed' ? 'status-completed' : 'status-cancelled'; ?>">
                        <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($ticket['description'])): ?>
        <div class="info-item" style="margin: 15px 0; text-align: left;">
            <div class="info-label">Description</div>
            <div class="info-value" style="font-weight: 400; line-height: 1.5;">
                <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- QR Code Display -->
        <div class="qr-container">
            <div id="qrcode"></div>
            <div class="qr-url">
                <strong>Scan URL:</strong><br>
                <?php echo htmlspecialchars($verification_url); ?>
            </div>
        </div>
        
        <div style="font-size: 13px; color: #6b7280; margin-top: 15px;">
            <i class="fas fa-info-circle"></i> Scan this QR code to view summon details and demerit points
        </div>
        
        <!-- Action Buttons -->
        <div class="btn-container">
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print QR Code
            </button>
            <a href="<?php echo APP_BASE_PATH . '/module04/safety/traffic_summons.php'; ?>" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Summons
            </a>
        </div>
    </div>

    <script>
        // Generate QR code using qrcodejs
        new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo addslashes($verification_url); ?>",
            width: 280,
            height: 280,
            colorDark: "#dc2626",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>
