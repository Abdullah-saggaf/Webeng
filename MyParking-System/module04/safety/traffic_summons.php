<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['safety_staff']);

$success_message = '';
$error_message = '';
$tickets = [];
$searchQuery = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticketId = $_POST['ticket_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    
    if (!empty($ticketId) && in_array($newStatus, ['Unpaid', 'Paid'])) {
        if (updateTicketStatus($ticketId, $newStatus)) {
            $success_message = "Ticket status updated to $newStatus successfully.";
        } else {
            $error_message = "Failed to update ticket status.";
        }
    } else {
        $error_message = "Invalid ticket or status.";
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticketId = $_POST['ticket_id'] ?? '';
    
    if (!empty($ticketId)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM Ticket WHERE ticket_ID = ?");
            if ($stmt->execute([$ticketId])) {
                $success_message = "Ticket deleted successfully.";
            } else {
                $error_message = "Failed to delete ticket.";
            }
        } catch (Exception $e) {
            $error_message = "Error deleting ticket: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid ticket ID.";
    }
}

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchQuery = trim($_POST['search_query'] ?? '');
    if (!empty($searchQuery)) {
        $tickets = searchTickets($searchQuery);
    } else {
        $tickets = getLatestTickets(20);
    }
} else {
    // Default: show latest 20 tickets
    $tickets = getLatestTickets(20);
}

require_once __DIR__ . '/../../layout.php';
renderHeader('Traffic Summons');
?>

<div class="card">
    <h2><i class="fas fa-exclamation-circle"></i> Traffic Summons Management</h2>
    <p>Search and manage traffic summons for violations</p>
    
    <?php if (!empty($success_message)): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin: 10px 0; border: 1px solid #6ee7b7;">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin: 10px 0; border: 1px solid #fecaca;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Search Form -->
    <form method="POST" style="margin: 20px 0;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="search_query" placeholder="Search by plate number, student ID, or username..." 
                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                   style="flex: 1; min-width: 250px; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            <button type="submit" name="search" class="btn">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
    </form>
    
    <!-- Results Table -->
    <?php if (empty($tickets)): ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No summons found. <?php echo !empty($searchQuery) ? 'Try a different search term.' : 'No tickets issued yet.'; ?></p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Ticket ID</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Student</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Plate</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Violation</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Points</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Fine (RM)</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Status</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Issued At</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 12px;"><?php echo htmlspecialchars($ticket['ticket_ID']); ?></td>
                            <td style="padding: 12px;">
                                <div><?php echo htmlspecialchars($ticket['username']); ?></div>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($ticket['user_ID']); ?></small>
                            </td>
                            <td style="padding: 12px; font-weight: 600;"><?php echo htmlspecialchars($ticket['license_plate']); ?></td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($ticket['violation_type']); ?></td>
                            <td style="padding: 12px; text-align: center;">
                                <span style="background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 6px; font-weight: 600;">
                                    <?php echo htmlspecialchars($ticket['violation_points']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-weight: 600;"><?php echo number_format($ticket['fine_amount'], 2); ?></td>
                            <td style="padding: 12px;">
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
                                <span style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px;">
                                    <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 13px;"><?php echo date('M d, Y H:i', strtotime($ticket['issued_at'])); ?></td>
                            <td style="padding: 12px;">
                                <form method="POST" style="display: inline-flex; gap: 5px; align-items: center;">
                                    <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['ticket_ID']); ?>">
                                    <select name="new_status" style="padding: 6px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px;">
                                        <option value="Unpaid" <?php echo $ticket['ticket_status'] === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                        <option value="Paid" <?php echo $ticket['ticket_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                    </select>
                                    <button type="submit" name="update_status" style="padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                        Update
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ticket? This action cannot be undone.');">
                                    <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['ticket_ID']); ?>">
                                    <button type="submit" name="delete_ticket" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; margin-left: 5px;">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top: 15px; color: #6b7280; font-size: 14px;">
            Showing <?php echo count($tickets); ?> ticket(s)
        </div>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
