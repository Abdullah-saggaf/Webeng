<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan QR Code - Parking Space</title>
    <!-- html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    <?php
    // Embed the auto-detect function for use in JavaScript
    function detectProjectBaseUrl(): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'];
        $doc    = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/');

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

        if ($root === null) {
            $scriptDir = rtrim(str_replace('\\','/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
            $root = $scriptDir;
        }

        return $scheme . '://' . $host . $root;
    }
    
    $baseUrl = detectProjectBaseUrl();
    ?>
    const PROJECT_BASE_URL = "<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>";
    </script>
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
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .scanner-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }
        
        #reader {
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        #result {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            margin-top: 15px;
            display: none;
        }
        
        #result.success {
            display: block;
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
        }
        
        #result.error {
            display: block;
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .instructions {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .instructions h3 {
            font-size: 16px;
            color: #92400e;
            margin-bottom: 8px;
        }
        
        .instructions ul {
            margin-left: 20px;
            color: #78350f;
            font-size: 14px;
        }
        
        .instructions li {
            margin: 5px 0;
        }
        
        #status {
            text-align: center;
            padding: 10px;
            font-size: 14px;
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Scan Parking Space QR</h1>
            <p>Point your camera at a parking space QR code</p>
        </div>
        
        <div class="scanner-card">
            <div class="instructions">
                <h3>📋 How to scan:</h3>
                <ul>
                    <li>Allow camera access when prompted</li>
                    <li>Point camera at QR code</li>
                    <li>Hold steady until it scans</li>
                    <li>You'll be redirected to space info automatically</li>
                </ul>
            </div>
            
            <div id="status">Initializing camera...</div>
            <div id="reader"></div>
            <div id="result"></div>
            
            <div class="controls">
                <button onclick="window.location.href='../space_qr_view.php'" class="btn btn-secondary">
                    ← Back to QR List
                </button>
            </div>
        </div>
    </div>
    
    <script>
    let html5QrcodeScanner = null;
    
    function onScanSuccess(decodedText, decodedResult) {
        console.log(`Scan result: ${decodedText}`, decodedResult);
        
        const resultDiv = document.getElementById('result');
        
        // Check if it's a full URL (starts with http)
        if (decodedText.startsWith('http://') || decodedText.startsWith('https://')) {
            resultDiv.className = 'success';
            resultDiv.innerHTML = '✅ QR Code Detected!<br>Redirecting to space info...';
            
            // Stop scanning
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
            
            // Redirect after short delay
            setTimeout(() => {
                window.location.href = decodedText;
            }, 1000);
        }
        // Check if it contains SPACE: prefix (alternative format)
        else if (decodedText.includes('SPACE:')) {
            const match = decodedText.match(/SPACE:(\d+)/);
            if (match && match[1]) {
                const spaceId = match[1];
                const targetUrl = PROJECT_BASE_URL + '/module02/pageSpaceInfo.php?space_id=' + spaceId;
                
                resultDiv.className = 'success';
                resultDiv.innerHTML = '✅ Space ' + spaceId + ' detected!<br>Redirecting...';
                
                // Stop scanning
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear();
                }
                
                // Redirect
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 1000);
            } else {
                resultDiv.className = 'error';
                resultDiv.innerHTML = '❌ Invalid SPACE format';
            }
        }
        // Check if it's just a space ID number
        else if (/^\d+$/.test(decodedText)) {
            const spaceId = decodedText;
            const targetUrl = PROJECT_BASE_URL + '/module02/pageSpaceInfo.php?space_id=' + spaceId;
            
            resultDiv.className = 'success';
            resultDiv.innerHTML = '✅ Space ' + spaceId + ' detected!<br>Redirecting...';
            
            // Stop scanning
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
            
            // Redirect
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 1000);
        }
        else {
            resultDiv.className = 'error';
            resultDiv.innerHTML = '❌ Not a valid parking space QR code<br>' + decodedText.substring(0, 50);
        }
    }
    
    function onScanError(errorMessage) {
        // Ignore scan errors (they happen constantly while scanning)
        // console.log(`Scan error: ${errorMessage}`);
    }
    
    // Initialize scanner
    document.addEventListener('DOMContentLoaded', function() {
        const statusDiv = document.getElementById('status');
        
        try {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { 
                    fps: 10,
                    qrbox: {width: 250, height: 250},
                    aspectRatio: 1.0,
                    disableFlip: false
                },
                /* verbose= */ false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanError);
            statusDiv.textContent = '📷 Camera ready - Position QR code in view';
            statusDiv.style.color = '#10b981';
        } catch (error) {
            statusDiv.textContent = '❌ Error: ' + error.message;
            statusDiv.style.color = '#ef4444';
            console.error('Scanner initialization error:', error);
        }
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
    });
    </script>
</body>
</html>
