<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['safety_staff']);

/**
 * Helper function to get public-accessible base URL
 * If accessed via localhost, tries to use LAN IP for phone scanning
 */
function getPublicBaseUrl() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // If localhost, try to get LAN IP
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $ip = gethostbyname(gethostname());
        if (!empty($ip) && $ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $host = $ip;
        }
    }
    return 'http://' . $host . '/Webeng/MyParking-System';
}

$success_message = '';
$error_message = '';
$tickets = [];
$searchQuery = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticketId = $_POST['ticket_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    
    if (!empty($ticketId) && in_array($newStatus, ['Completed', 'Cancelled'])) {
        try {
            if (updateTicketStatus($ticketId, $newStatus)) {
                $success_message = "Ticket status updated to $newStatus successfully. Points recalculated.";
            } else {
                $error_message = "Failed to update ticket status.";
            }
        } catch (Exception $e) {
            $error_message = "Error updating status: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid ticket or status. Only 'Completed' or 'Cancelled' are allowed.";
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticketId = $_POST['ticket_id'] ?? '';
    
    if (!empty($ticketId)) {
        try {
            if (deleteTicketAndRecalc($ticketId)) {
                $success_message = "Ticket deleted successfully. User points recalculated.";
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
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Status</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Issued At</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Description</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <?php
                        // Ensure ticket has QR code
                        $qrCode = $ticket['qr_code_value'] ?? null;
                        if (empty($qrCode)) {
                            $qrCode = ensureTicketHasQrCode($ticket['ticket_ID']);
                        }
                        
                        // Build scan URL with LAN IP for phone accessibility
                        $scanUrl = getPublicBaseUrl() . '/module04/summon_qr_view.php?code=' . urlencode($qrCode);
                        ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 12px;"><?php echo htmlspecialchars($ticket['ticket_ID']); ?></td>
                            <td style="padding: 12px;">
                                <div><?php echo htmlspecialchars($ticket['username']); ?></div>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($ticket['user_ID']); ?></small>
                            </td>
                            <td style="padding: 12px; font-weight: 600;">
                                <?php echo !empty($ticket['license_plate']) ? htmlspecialchars($ticket['license_plate']) : '<em style="color: #9ca3af;">Unregistered</em>'; ?>
                            </td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($ticket['violation_type']); ?></td>
                            <td style="padding: 12px; text-align: center;">
                                <span style="background: #fee2e2; color: #b91c1c; padding: 4px 8px; border-radius: 6px; font-weight: 600;">
                                    <?php echo htmlspecialchars($ticket['violation_points']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                // Status badge colors: Completed=green, Cancelled=red
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
                                <span style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 13px;">
                                    <?php echo htmlspecialchars($ticket['ticket_status']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 13px;"><?php echo date('M d, Y h:i A', strtotime($ticket['issued_at'])); ?></td>
                            <td style="padding: 12px; color: #6b7280; font-size: 13px; max-width: 200px;">
                                <?php 
                                $desc = htmlspecialchars($ticket['description'] ?? '');
                                echo !empty($desc) ? $desc : '<em style="color: #9ca3af;">No description</em>';
                                ?>
                            </td>
                            <td style="padding: 12px;">
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <!-- Status Update Form -->
                                    <form method="POST" style="display: flex; gap: 5px; align-items: center;">
                                        <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['ticket_ID']); ?>">
                                        <select name="new_status" style="padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; background: white; min-width: 110px;">
                                            <option value="Completed" <?php echo $ticket['ticket_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?php echo $ticket['ticket_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" style="padding: 6px 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: opacity 0.2s; display: inline-flex; align-items: center; justify-content: center;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                            Update
                                        </button>
                                    </form>
                                    
                                    <!-- Action Buttons -->
                                    <div style="display: flex; gap: 5px;">
                                        <!-- QR Code Button - Opens Modal -->
                                        <button type="button" 
                                                class="qr-modal-trigger"
                                                data-ticket-id="<?php echo htmlspecialchars($ticket['ticket_ID']); ?>"
                                                data-student-id="<?php echo htmlspecialchars($ticket['user_ID']); ?>"
                                                data-student-name="<?php echo htmlspecialchars($ticket['username']); ?>"
                                                data-plate="<?php echo !empty($ticket['license_plate']) ? htmlspecialchars($ticket['license_plate']) : 'Unregistered Vehicle'; ?>"
                                                data-violation="<?php echo htmlspecialchars($ticket['violation_type']); ?>"
                                                data-points="<?php echo htmlspecialchars($ticket['violation_points']); ?>"
                                                data-status="<?php echo htmlspecialchars($ticket['ticket_status']); ?>"
                                                data-issued="<?php echo date('M d, Y h:i A', strtotime($ticket['issued_at'])); ?>"
                                                data-scan-url="<?php echo htmlspecialchars($scanUrl); ?>"
                                                style="padding: 6px 12px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fas fa-qrcode"></i> QR
                                        </button>
                                        
                                        <!-- Delete Button -->
                                        <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this ticket? Points will be recalculated.');">
                                            <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticket['ticket_ID']); ?>">
                                            <button type="submit" name="delete_ticket" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
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

<!-- QR Preview Modal -->
<div id="qrModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 15px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 50px rgba(0,0,0,0.3); position: relative;">
        <!-- Close Button -->
        <button id="closeModal" style="position: absolute; top: 15px; right: 15px; background: #ef4444; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 20px; font-weight: bold; z-index: 10; display: flex; align-items: center; justify-content: center; line-height: 1; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);" onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 12px rgba(239, 68, 68, 0.5)';" onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)'; this.style.boxShadow='0 2px 8px rgba(239, 68, 68, 0.3)';">
            ×
        </button>
        
        <!-- Modal Header -->
        <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 25px 20px; border-radius: 15px 15px 0 0; text-align: center;">
            <h3 style="margin: 0 0 5px 0; font-size: 22px;">
                <i class="fas fa-qrcode"></i> Traffic Summon QR Code
            </h3>
            <p style="margin: 0; font-size: 14px; opacity: 0.9;" id="modalTicketId">Ticket #123</p>
        </div>
        
        <!-- Modal Body -->
        <div style="padding: 25px;">
            <!-- Ticket Information -->
            <div style="background: #f9fafb; border-radius: 10px; padding: 15px; margin-bottom: 20px; border: 1px solid #e5e7eb;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 14px;">
                    <div>
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Student ID</div>
                        <div style="font-weight: 600; color: #374151;" id="modalStudentId">-</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Student Name</div>
                        <div style="font-weight: 600; color: #374151;" id="modalStudentName">-</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Vehicle Plate</div>
                        <div style="font-weight: 600; color: #374151;" id="modalPlate">-</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Status</div>
                        <div style="font-weight: 600;" id="modalStatus">-</div>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Violation</div>
                        <div style="font-weight: 600; color: #374151;" id="modalViolation">-</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Demerit Points</div>
                        <div style="font-weight: 700; color: #dc2626;" id="modalPoints">-</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 12px; margin-bottom: 3px;">Issued Date</div>
                        <div style="font-weight: 600; color: #374151;" id="modalIssued">-</div>
                    </div>
                </div>
            </div>
            
            <!-- QR Code Display -->
            <div style="background: white; border: 3px solid #059669; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px;">
                <div id="qrCodeContainer" style="display: inline-block; padding: 10px;"></div>
                <div style="margin-top: 15px; font-size: 13px; color: #6b7280;">
                    Scan with phone to view summon details
                </div>
            </div>
            
            <!-- Scan URL Display -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                <div style="font-size: 11px; color: #1e40af; font-weight: 600; margin-bottom: 5px;">SCAN URL:</div>
                <div id="scanUrlDisplay" style="font-size: 11px; color: #3b82f6; word-break: break-all; font-family: monospace; line-height: 1.6;">
                    -
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a id="openLinkBtn" href="#" target="_blank" style="flex: 1; min-width: 150px; padding: 12px 20px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-external-link-alt"></i> Open Link
                </a>
                <button id="copyLinkBtn" type="button" style="padding: 12px 20px; background: #6b7280; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QRCode.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
// Modal elements
const modal = document.getElementById('qrModal');
const closeBtn = document.getElementById('closeModal');
const copyBtn = document.getElementById('copyLinkBtn');
const qrContainer = document.getElementById('qrCodeContainer');
const openLinkBtn = document.getElementById('openLinkBtn');

// QR trigger buttons
const qrTriggers = document.querySelectorAll('.qr-modal-trigger');

// Open modal and generate QR
qrTriggers.forEach(btn => {
    btn.addEventListener('click', function() {
        // Get data from button
        const ticketId = this.dataset.ticketId;
        const studentId = this.dataset.studentId;
        const studentName = this.dataset.studentName;
        const plate = this.dataset.plate;
        const violation = this.dataset.violation;
        const points = this.dataset.points;
        const status = this.dataset.status;
        const issued = this.dataset.issued;
        const scanUrl = this.dataset.scanUrl;
        
        // Populate modal fields
        document.getElementById('modalTicketId').textContent = 'Ticket #' + ticketId;
        document.getElementById('modalStudentId').textContent = studentId;
        document.getElementById('modalStudentName').textContent = studentName;
        document.getElementById('modalPlate').textContent = plate;
        document.getElementById('modalViolation').textContent = violation;
        document.getElementById('modalPoints').textContent = points + ' points';
        document.getElementById('modalStatus').textContent = status;
        document.getElementById('modalIssued').textContent = issued;
        document.getElementById('scanUrlDisplay').textContent = scanUrl;
        
        // Set status color
        const statusEl = document.getElementById('modalStatus');
        if (status === 'Completed') {
            statusEl.style.color = '#059669';
        } else if (status === 'Cancelled') {
            statusEl.style.color = '#dc2626';
        } else {
            statusEl.style.color = '#6b7280';
        }
        
        // Set open link button href
        openLinkBtn.href = scanUrl;
        
        // Clear previous QR code
        qrContainer.innerHTML = '';
        
        // Generate new QR code
        try {
            new QRCode(qrContainer, {
                text: scanUrl,
                width: 220,
                height: 220,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        } catch (e) {
            qrContainer.innerHTML = '<div style="color: #ef4444; padding: 20px;">Error generating QR code</div>';
        }
        
        // Show modal
        modal.style.display = 'flex';
    });
});

// Close modal on close button click
closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
});

// Close modal on outside click
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display === 'flex') {
        modal.style.display = 'none';
    }
});

// Copy link functionality
copyBtn.addEventListener('click', function() {
    const scanUrl = document.getElementById('scanUrlDisplay').textContent;
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(scanUrl).then(() => {
            // Visual feedback
            const originalHtml = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            copyBtn.style.background = '#059669';
            
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.style.background = '#6b7280';
            }, 2000);
        }).catch(err => {
            alert('Failed to copy: ' + err);
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = scanUrl;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            const originalHtml = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            copyBtn.style.background = '#059669';
            setTimeout(() => {
                copyBtn.innerHTML = originalHtml;
                copyBtn.style.background = '#6b7280';
            }, 2000);
        } catch (err) {
            alert('Failed to copy');
        }
        document.body.removeChild(textarea);
    }
});
</script>

<?php renderFooter(); ?>
