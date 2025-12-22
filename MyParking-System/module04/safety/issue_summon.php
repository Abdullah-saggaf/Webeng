<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['safety_staff']);

$success_message = '';
$error_message = '';
$violations = getAllViolations();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_summon'])) {
    $licensePlate = trim($_POST['license_plate'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $violationId = $_POST['violation_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($licensePlate) && empty($studentId)) {
        $error_message = "Please enter either a license plate or student ID.";
    } elseif (empty($violationId)) {
        $error_message = "Please select a violation type.";
    } else {
        // Find vehicle
        $vehicle = null;
        if (!empty($licensePlate)) {
            $vehicle = getVehicleByPlate($licensePlate);
        } elseif (!empty($studentId)) {
            $vehicle = getVehicleByUserId($studentId);
        }
        
        if (!$vehicle) {
            $error_message = "Vehicle or student not found. Please check the plate number or student ID.";
        } else {
            // Get violation details to add points
            $violationDetails = null;
            foreach ($violations as $v) {
                if ($v['violation_ID'] == $violationId) {
                    $violationDetails = $v;
                    break;
                }
            }
            
            if ($violationDetails) {
                try {
                    // Create ticket
                    if (createTicket($vehicle['vehicle_ID'], $vehicle['user_ID'], $violationId, $description)) {
                        // Add points to user
                        addUserPoints($vehicle['user_ID'], $violationDetails['violation_points']);
                        
                        $success_message = "Summon created successfully! Ticket issued to " . htmlspecialchars($vehicle['user_ID']) . 
                                         " for " . htmlspecialchars($violationDetails['violation_type']) . 
                                         ". " . $violationDetails['violation_points'] . " demerit points added.";
                        
                        // Clear form
                        $licensePlate = '';
                        $studentId = '';
                        $description = '';
                    } else {
                        $error_message = "Failed to create summon. Please try again.";
                    }
                } catch (Exception $e) {
                    $error_message = "Error creating summon: " . $e->getMessage();
                }
            } else {
                $error_message = "Invalid violation selected.";
            }
        }
    }
}

require_once __DIR__ . '/../../layout.php';
renderHeader('Issue Summon');
?>

<div class="card">
    <h2><i class="fas fa-edit"></i> Issue Traffic Summon</h2>
    <p>Create a new traffic summon for a violation</p>
    
    <?php if (!empty($success_message)): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin: 15px 0; border: 1px solid #6ee7b7;">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin: 15px 0; border: 1px solid #fecaca;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" style="margin-top: 20px;">
        <div style="display: grid; gap: 20px;">
            <!-- Vehicle Identification -->
            <div style="background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                    <i class="fas fa-car"></i> Vehicle Identification
                </h3>
                <p style="margin: 0 0 15px 0; font-size: 13px; color: #6b7280;">
                    Enter either the license plate OR the student ID (one is required)
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">
                            License Plate Number
                        </label>
                        <input type="text" name="license_plate" 
                               value="<?php echo isset($licensePlate) ? htmlspecialchars($licensePlate) : ''; ?>"
                               placeholder="e.g., ABC1234"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    </div>
                    
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">
                            Student ID
                        </label>
                        <input type="text" name="student_id" 
                               value="<?php echo isset($studentId) ? htmlspecialchars($studentId) : ''; ?>"
                               placeholder="e.g., CD22001"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    </div>
                </div>
            </div>
            
            <!-- Violation Details -->
            <div style="background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                    <i class="fas fa-exclamation-circle"></i> Violation Details
                </h3>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">
                        Violation Type <span style="color: #dc2626;">*</span>
                    </label>
                    <select name="violation_id" required
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                        <option value="">-- Select Violation --</option>
                        <?php foreach ($violations as $violation): ?>
                            <option value="<?php echo htmlspecialchars($violation['violation_ID']); ?>">
                                <?php echo htmlspecialchars($violation['violation_type']); ?> 
                                (<?php echo $violation['violation_points']; ?> points, RM <?php echo number_format($violation['fine_amount'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; color: #374151;">
                        Description / Remarks
                    </label>
                    <textarea name="description" rows="4"
                              placeholder="Enter additional details about the violation (optional)"
                              style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical;"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 25px; display: flex; gap: 10px;">
            <button type="submit" name="create_summon" class="btn" 
                    style="background: linear-gradient(135deg, #059669 0%, #047857 100%); flex: 1;">
                <i class="fas fa-plus-circle"></i> Create Summon
            </button>
            <a href="<?php echo APP_BASE_PATH . '/module04/safety/traffic_summons.php'; ?>" 
               class="btn" 
               style="background: #6b7280; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fas fa-list"></i> View All Summons
            </a>
        </div>
    </form>
</div>

<!-- Violation Reference Card -->
<div class="card" style="margin-top: 20px;">
    <h3 style="margin: 0 0 15px 0; font-size: 18px;">
        <i class="fas fa-info-circle"></i> Violation Types Reference
    </h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                    <th style="padding: 10px; text-align: left; font-weight: 600;">Violation Type</th>
                    <th style="padding: 10px; text-align: center; font-weight: 600;">Demerit Points</th>
                    <th style="padding: 10px; text-align: right; font-weight: 600;">Fine Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($violations as $violation): ?>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px;"><?php echo htmlspecialchars($violation['violation_type']); ?></td>
                        <td style="padding: 10px; text-align: center;">
                            <span style="background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 6px; font-weight: 600;">
                                <?php echo htmlspecialchars($violation['violation_points']); ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: right; font-weight: 600;">
                            <?php echo number_format($violation['fine_amount'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
