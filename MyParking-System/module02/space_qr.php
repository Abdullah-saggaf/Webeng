<?php
/**
 * Space QR Code Display
 * Module 2 - MyParking System
 * 
 * PURPOSE: Generate and display QR code for a parking space
 * QR code encodes URL to space_qr_view.php?space_id=X
 * WORKFLOW: Admin prints this QR → Stick on physical parking space → Students scan to view space info
 * PUBLIC PAGE: No login required (used for printing/display)
 */

// Include database configuration
require_once __DIR__ . '/../database/db_config.php';

/**
 * Auto-detect the correct project base URL
 * IMPORTANT: For QR codes, ALWAYS use IP address instead of localhost
 * Reason: Mobile phones cannot access localhost URLs (they refer to the phone itself)
 */
function detectProjectBaseUrl(): string {
    // Determine HTTP or HTTPS
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    
    // Replace localhost with actual network IP for mobile accessibility
    if ($host === 'localhost' || $host === '127.0.0.1' || 
        strpos($host, 'localhost:') === 0 || 
        strpos($host, '127.0.0.1:') === 0 || 
        strpos($host, '[::1]') === 0) {
        
        // Default to localhost if IP detection fails
        $serverIP = '127.0.0.1';
        
        // Detect actual network IP (exclude VirtualBox adapters)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // On Windows, use ipconfig to find WiFi adapter IP
            $ipconfig = shell_exec('ipconfig');
            
            // Look for valid IPv4 addresses (not localhost, not VirtualBox, not APIPA)
            if (preg_match_all('/IPv4 Address[.\s]*:\s*([0-9.]+)/', $ipconfig, $matches)) {
                foreach ($matches[1] as $ip) {
                    // Skip: 127.0.0.1 (localhost), 192.168.56.x (VirtualBox), 169.254.x.x (APIPA)
                    if ($ip !== '127.0.0.1' && 
                        !preg_match('/^192\.168\.56\./', $ip) && 
                        !preg_match('/^169\.254\./', $ip)) {
                        $serverIP = $ip; // Use first valid IP found
                        break;
                    }
                }
            }
        } else {
            // On Linux/Mac, use hostname or gethostbyname
            $serverIP = gethostbyname(gethostname());
        }
        
        $host = $serverIP; // Replace localhost with detected IP
    }
    
    // Build base path (remove /module02 if present)
    $scriptName = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $root = preg_replace('#/module02$#', '', $scriptName);
    
    // Remove /Webeng prefix if present
    $root = preg_replace('#^/Webeng#', '', $root);

    return $scheme . '://' . $host . $root;
}

// Get base URL (with IP address for mobile scanning)
$baseUrl = detectProjectBaseUrl();

// Get space_id from URL parameter
$space_id = (int)($_GET['space_id'] ?? 0);

// VALIDATION: Ensure space_id is valid
if ($space_id < 1) {
    // Display error if invalid ID provided
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Invalid Space ID</h2>
        <p>Please provide a valid parking space ID.</p>
        </div>');
}

// Establish database connection
$db = getDB();

// Fetch parking space details
// JOIN ParkingSpace with ParkingLot to get area information
// FK: ps.parkingLot_ID → pl.parkingLot_ID
$stmt = $db->prepare("
    SELECT ps.*, pl.parkingLot_name, pl.parkingLot_type 
    FROM ParkingSpace ps 
    JOIN ParkingLot pl ON ps.parkingLot_ID=pl.parkingLot_ID 
    WHERE ps.space_ID=?
");
$stmt->execute([$space_id]);
$space = $stmt->fetch();

// If space not found, display error
if (!$space) {
    die('<div style="padding: 40px; text-align: center; font-family: Arial;">
        <h2 style="color: #ef4444;">❌ Space Not Found</h2>
        <p>The parking space you are looking for does not exist.</p>
        </div>');
}

// Build verification URL: Points to space_qr_view.php with space_id parameter
// This URL is encoded into the QR code
$base = detectProjectBaseUrl();
$verification_url = $base . '/module02/space_qr_view.php?space_id=' . $space_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?php echo htmlspecialchars($space['space_number']); ?></title>
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn i {
            font-size: 16px;
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
        <div class="space-number"><i class="fas fa-parking"></i> <?php echo htmlspecialchars($space['space_number']); ?></div>
        <div class="area-info"><?php echo htmlspecialchars($space['parkingLot_name']); ?></div>
        <div class="area-type"><?php echo htmlspecialchars($space['parkingLot_type']); ?></div>
        
        <div id="qrcode-space-<?= $space_id ?>"></div>
        
        <div style="font-size: 11px; color: #9ca3af; margin-top: 15px;">
            <i class="fas fa-mobile-alt"></i> Scan with phone camera to view space details
        </div>
        
        <div class="url-display">
            <i class="fas fa-link"></i> <?php echo htmlspecialchars($verification_url); ?>
        </div>
        
        <div class="buttons">
            <a href="<?= htmlspecialchars($verification_url) ?>" class="btn btn-primary" target="_blank">
                <i class="fas fa-mobile-alt"></i> Open Space Info
            </a>
            <button onclick="copyToClipboard('<?= htmlspecialchars($verification_url, ENT_QUOTES) ?>')" class="btn btn-success">
                <i class="fas fa-clipboard"></i> Copy URL
            </button>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print QR
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
                alert('✔ URL copied to clipboard!\n\n' + text);
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
            alert('✖ Failed to copy URL');
        }
        document.body.removeChild(textarea);
    }
    </script>
</body>
</html>
