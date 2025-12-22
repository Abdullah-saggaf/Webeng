<?php
/**
 * Space QR Code Display
 * Public page - no login required
 */

require_once __DIR__ . '/../database/db_config.php';

/**
 * Auto-detect the correct project base URL
 * For QR codes: ALWAYS use IP address, never localhost
 */
function detectProjectBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    
    // For QR codes: Replace localhost with actual IP address
    if ($host === 'localhost' || $host === '127.0.0.1' || 
        strpos($host, 'localhost:') === 0 || 
        strpos($host, '127.0.0.1:') === 0 || 
        strpos($host, '[::1]') === 0) {
        
        // Try to get server's IP address
        $serverIP = $_SERVER['SERVER_ADDR'] ?? null;
        
        // If SERVER_ADDR is localhost or IPv6, try to find actual network IP
        if (!$serverIP || $serverIP === '127.0.0.1' || $serverIP === '::1') {
            // Use a common local IP range - update this to your actual IP: 192.168.0.24
            $serverIP = '192.168.0.24'; // CHANGE THIS TO YOUR COMPUTER'S IP
        }
        
        // Preserve port if exists
        $port = '';
        if (strpos($host, ':') !== false) {
            $parts = explode(':', $host);
            if (isset($parts[1])) {
                $port = ':' . $parts[1];
            }
        }
        
        $host = $serverIP . $port;
    }
    
    $doc = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/');

    // candidate roots (try both)
    $candidates = [
        '/WebProject/MyParking-System',
        '/MyParking-System'
    ];

    $root = null;
    foreach ($candidates as $c) {
        if (file_exists($doc . $c . '/module02/pageSpaceInfo.php')) {
            $root = $c;
            break;
        }
    }

    // fallback: derive from script location (/.../module02)
    if ($root === null) {
        $scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/'); // ex: /WebProject/MyParking-System/module02
        $root = preg_replace('#/module02$#', '', $scriptDir);
    }

    return $scheme . '://' . $host . $root;
}

$space_id = (int)($_GET['space_id'] ?? 0);

if ($space_id < 1) {
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
$stmt->execute([$space_id]);
$space = $stmt->fetch();

if (!$space) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Space Not Found</h2>
        <p>The parking space you are looking for does not exist.</p>
        </div>');
}

// Build verification URL using auto-detected base
$base = detectProjectBaseUrl();
$verification_url = $base . '/module02/pageSpaceInfo.php?space_id=' . $space_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?php echo htmlspecialchars($space['space_number']); ?></title>
    <!-- QRCode.js library from CDN -->
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
        #qrcode-space-<?= $space_id ?> {
            display: flex;
            justify-content: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 15px;
        }
        .url-display {
            font-size: 10px;
            color: #6b7280;
            margin-top: 15px;
            padding: 10px;
            background: #fef3c7;
            border-radius: 8px;
            word-break: break-all;
            font-family: monospace;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
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
        
        <div id="qrcode-space-<?= $space_id ?>"></div>
        
        <div style="font-size: 11px; color: #9ca3af; margin-top: 15px;">
            📱 Scan with phone camera to view space details
        </div>
        
        <div class="url-display">
            🔗 <?php echo htmlspecialchars($verification_url); ?>
        </div>
        
        <div class="buttons">
            <a href="<?= htmlspecialchars($verification_url) ?>" class="btn btn-primary" target="_blank">
                📱 Open Space Info
            </a>
            <button onclick="copyToClipboard('<?= htmlspecialchars($verification_url, ENT_QUOTES) ?>')" class="btn btn-success">
                📋 Copy URL
            </button>
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ Print QR
            </button>
        </div>
    </div>
    
    <script>
    // Generate QR Code (same pattern as merit system)
    document.addEventListener("DOMContentLoaded", function() {
        const qrElement = document.getElementById("qrcode-space-<?= (int)$space_id ?>");
        
        if (typeof QRCode === 'undefined') {
            qrElement.innerHTML = '<div style="color: red; padding: 20px;">Error: QR Code library failed to load</div>';
            return;
        }
        
        try {
            new QRCode(qrElement, {
                text: "<?= htmlspecialchars($verification_url, ENT_QUOTES) ?>",
                width: 220,
                height: 220,
                colorDark: "#4f46e5",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        } catch (error) {
            qrElement.innerHTML = '<div style="color: red; padding: 20px;">Error: ' + error.message + '</div>';
        }
    });
    
    // Copy URL to clipboard
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('✅ URL copied to clipboard!\n\n' + text);
            }).catch(function(err) {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }
    
    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('✅ URL copied to clipboard!');
        } catch (err) {
            alert('❌ Failed to copy URL');
        }
        document.body.removeChild(textarea);
    }
    </script>
</body>
</html>
