<?php
require_once __DIR__ . '/../../module01/auth.php';
require_once __DIR__ . '/../../database/db_config.php';
requireRole(['safety_staff']);

$success_message = '';
$error_message = '';
$created_ticket_id = null;
$created_qr_code = null;
$violations = getModule4ViolationsOnly(); // Only show required 3 violations (10/15/20 points)

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_summon'])) {
    $licensePlate = trim($_POST['license_plate'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $violationId = $_POST['violation_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    
    // Validation: At least one identifier is required
    if (empty($licensePlate) && empty($studentId)) {
        $error_message = "Either License Plate or Student ID is required to issue a summon.";
    } elseif (empty($violationId)) {
        $error_message = "Please select a violation type.";
    } else {
        // Scenario 1: Only Student ID provided (no license plate)
        if (!empty($studentId) && empty($licensePlate)) {
            $student = getUserById($studentId);
            if (!$student) {
                $error_message = "Student not found. Please verify the Student ID and try again.";
            } elseif ($student['user_type'] !== 'student') {
                $error_message = "Invalid Student ID. The ID must belong to a student.";
            } else {
                // Issue to student directly, no vehicle info
                $proceedWithCreation = true;
                $targetUserId = $student['user_ID'];
                $targetVehicleId = null;
            }
        }
        // Scenario 2: License plate provided (with or without student ID)
        else {
            $vehicle = getVehicleByPlate($licensePlate);
            
            if (!$vehicle) {
                // Vehicle NOT registered
                if (empty($studentId)) {
                    $error_message = "Vehicle not registered. Student ID is required to issue summon to the driver.";
                } else {
                    // Validate student exists
                    $student = getUserById($studentId);
                    if (!$student) {
                        $error_message = "Student not found. Please verify the Student ID and try again.";
                    } elseif ($student['user_type'] !== 'student') {
                        $error_message = "Invalid Student ID. The ID must belong to a student.";
                    } else {
                        // Proceed with ticket creation for unregistered vehicle
                        $proceedWithCreation = true;
                        $targetUserId = $student['user_ID'];
                        $targetVehicleId = null;
                    }
                }
            } else {
                // Vehicle IS registered - Use vehicle's owner
                if (!empty($studentId)) {
                    // Verify student ID matches vehicle owner
                    if (strtoupper($studentId) !== strtoupper($vehicle['user_ID'])) {
                        $error_message = "Student ID does not match the registered owner of this vehicle.";
                    } else {
                        $proceedWithCreation = true;
                        $targetUserId = $vehicle['user_ID'];
                        $targetVehicleId = $vehicle['vehicle_ID'];
                    }
                } else {
                    // No student ID provided, use vehicle owner
                    $proceedWithCreation = true;
                    $targetUserId = $vehicle['user_ID'];
                    $targetVehicleId = $vehicle['vehicle_ID'];
                }
            }
        }
        
        // Create ticket if validation passed
        if (isset($proceedWithCreation) && $proceedWithCreation) {
            // Get violation details
            $violationDetails = null;
            foreach ($violations as $v) {
                if ($v['violation_ID'] == $violationId) {
                    $violationDetails = $v;
                    break;
                }
            }
            
            if ($violationDetails) {
                try {
                    // Create ticket (returns array with ticket_id and qr_code_value)
                    // Status is automatically 'Completed' and QR is generated
                    // Points are recalculated automatically
                    $result = createTicket($targetVehicleId, $targetUserId, $violationId, $description);
                    
                    if ($result && isset($result['ticket_id'])) {
                        $created_ticket_id = $result['ticket_id'];
                        $created_qr_code = $result['qr_code_value'];
                        
                        $vehicleStatus = $targetVehicleId ? "registered vehicle" : "unregistered vehicle";
                        $success_message = "Summon created successfully! Ticket #" . $created_ticket_id . 
                                         " issued to " . htmlspecialchars($targetUserId) . 
                                         " (" . $vehicleStatus . ") for " . htmlspecialchars($violationDetails['violation_type']) . 
                                         " (" . $violationDetails['violation_points'] . " demerit points).";
                        
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
        <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #6ee7b7;">
            <div style="display: flex; align-items: start; gap: 10px;">
                <i class="fas fa-check-circle" style="font-size: 20px; margin-top: 2px;"></i>
                <div style="flex: 1;">
                    <div style="font-weight: 600;">
                        <?php echo $success_message; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #fecaca;">
            <div style="display: flex; align-items: start; gap: 10px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 20px; margin-top: 2px;"></i>
                <div style="flex: 1;">
                    <div style="font-weight: 600;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <form method="POST" style="margin-top: 20px;">
        <div style="display: grid; gap: 20px;">
            <!-- Vehicle Identification -->
            <div style="background: #f9fafb; padding: 20px; border-radius: 10px; border: 1px solid #e5e7eb;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                    <i class="fas fa-car"></i> Vehicle Identification
                </h3>
                <div style="background: #fef3c7; border: 1px solid #fbbf24; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; align-items: start; gap: 8px;">
                        <i class="fas fa-info-circle" style="color: #d97706; margin-top: 2px;"></i>
                        <div style="font-size: 13px; color: #78350f;">
                            <strong>Provide at least one identifier:</strong><br>
                            • <strong>License Plate</strong>: For registered vehicles (Student ID optional for verification)<br>
                            • <strong>Student ID</strong>: For unregistered vehicles or when driver is present without vehicle info<br>
                            • Both fields can be filled if available
                        </div>
                    </div>
                </div>
                
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
                               placeholder="e.g., S001"
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
                                (<?php echo $violation['violation_points']; ?> points)
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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
