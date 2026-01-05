<?php
/**
 * Update existing bookings with QR tokens
 * Run this once to generate tokens for existing bookings
 */

require_once __DIR__ . '/../database/db_config.php';

$db = getDB();

try {
    // Get all bookings without tokens
    $stmt = $db->query("SELECT booking_ID FROM Booking WHERE qr_token IS NULL OR qr_token = ''");
    $bookings = $stmt->fetchAll();
    
    $updateStmt = $db->prepare("UPDATE Booking SET qr_token = ? WHERE booking_ID = ?");
    
    $count = 0;
    foreach ($bookings as $booking) {
        $token = bin2hex(random_bytes(32));
        $updateStmt->execute([$token, $booking['booking_ID']]);
        $count++;
    }
    
    echo "✅ Successfully generated tokens for $count bookings\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
