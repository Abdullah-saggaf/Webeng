<?php
/**
 * Space QR Code Display
 * Public page - no login required
 */

require_once __DIR__ . '/../database/db_config.php';

// Fallback IP for localhost (change this to your LAN IP)
$FALLBACK_IP = '192.168.0.26';

$spaceId = (int)($_GET['space_id'] ?? 0);

if ($spaceId < 1) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Invalid Space ID</h2>
        <p>Please provide a valid parking space ID.</p>
        </div>');
}

$db = getDB();
$stmt = $db->prepare("
    SELECT ps.*, pl.parkingLot_name, pl.parkingLot_type 
    FROM ParkingSpace ps 
    JOIN ParkingLot pl ON ps.parkingLot_ID=pl.parkingLot_ID 
    WHERE ps.space_ID=?
");
$stmt->execute([$spaceId]);
$space = $stmt->fetch();

if (!$space) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Space Not Found</h2>
        <p>The parking space you are looking for does not exist.</p>
        </div>');
}

// Build URL for QR code
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Replace localhost with fallback IP
if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost:') === 0 || strpos($host, '127.0.0.1:') === 0) {
    $port = '';
    if (strpos($host, ':') !== false) {
        $parts = explode(':', $host);
        $port = ':' . $parts[1];
    }
    $host = $FALLBACK_IP . $port;
}

$infoUrl = $scheme . '://' . $host . '/WebProject/MyParking-System/module02/space_qr_view.php?space_id=' . $spaceId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?php echo htmlspecialchars($space['space_number']); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            max-width: 500px;
            width: 100%;
        }
        .space-number {
            font-size: 36px;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        .area-info {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .area-type {
            display: inline-block;
            padding: 6px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        #qrcode-box {
            display: flex;
            justify-content: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 15px;
        }
        .buttons {
            display: flex;
            gap: 15px;
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
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .qr-card {
                box-shadow: none;
            }
            .buttons {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="qr-card">
        <div class="space-number">🅿️ <?php echo htmlspecialchars($space['space_number']); ?></div>
        <div class="area-info"><?php echo htmlspecialchars($space['parkingLot_name']); ?></div>
        <div class="area-type"><?php echo htmlspecialchars($space['parkingLot_type']); ?></div>
        
        <div id="qrcode-box"></div>
        
        <div style="font-size: 11px; color: #9ca3af; margin-top: 15px;">
            Scan to view space details
        </div>
        
        <div class="buttons">
            <a href="<?php echo htmlspecialchars($infoUrl); ?>" target="_blank" class="btn btn-primary">
                📱 Open Space Info
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ Print QR
            </button>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const url = <?php echo json_encode($infoUrl); ?>;
        const el = document.getElementById("qrcode-box");
        el.innerHTML = "";
        new QRCode(el, {
            text: url,
            width: 260,
            height: 260,
            colorDark: "#4f46e5",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    });
    </script>
</body>
</html>
