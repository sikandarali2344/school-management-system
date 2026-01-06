<?php
// Check if user is logged in as admin
if (!isset($current_user) || $current_user['role'] != 'admin') {
    echo "<div class='error-message'>Access denied. Please login as admin.</div>";
    return;
}

// Handle fee structure actions
if (isset($_POST['add_fee_structure'])) {
    $class_id = $_POST['class_id'];
    $academic_year = $_POST['academic_year'];
    $tuition_fee = $_POST['tuition_fee'];
    $transport_fee = $_POST['transport_fee'] ?? 0;
    $hostel_fee = $_POST['hostel_fee'] ?? 0;
    $lab_fee = $_POST['lab_fee'] ?? 0;
    $sports_fee = $_POST['sports_fee'] ?? 0;
    $misc_fee = $_POST['misc_fee'] ?? 0;
    $due_date = $_POST['due_date'];
    $description = $_POST['description'];
    
    // Calculate total fee
    $total_fee = $tuition_fee + $transport_fee + $hostel_fee + $lab_fee + $sports_fee + $misc_fee;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO fee_structures (class_id, academic_year, tuition_fee, transport_fee, hostel_fee, lab_fee, sports_fee, misc_fee, total_fee, due_date, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $academic_year, $tuition_fee, $transport_fee, $hostel_fee, $lab_fee, $sports_fee, $misc_fee, $total_fee, $due_date, $description]);
        
        $success = "Fee structure added successfully!";
    } catch(PDOException $e) {
        $error = "Error adding fee structure: " . $e->getMessage();
    }
}

// Handle fee structure update
if (isset($_POST['update_fee_structure'])) {
    $fee_id = $_POST['fee_id'];
    $class_id = $_POST['class_id'];
    $academic_year = $_POST['academic_year'];
    $tuition_fee = $_POST['tuition_fee'];
    $transport_fee = $_POST['transport_fee'] ?? 0;
    $hostel_fee = $_POST['hostel_fee'] ?? 0;
    $lab_fee = $_POST['lab_fee'] ?? 0;
    $sports_fee = $_POST['sports_fee'] ?? 0;
    $misc_fee = $_POST['misc_fee'] ?? 0;
    $due_date = $_POST['due_date'];
    $description = $_POST['description'];
    
    // Calculate total fee
    $total_fee = $tuition_fee + $transport_fee + $hostel_fee + $lab_fee + $sports_fee + $misc_fee;
    
    try {
        $stmt = $pdo->prepare("UPDATE fee_structures SET class_id = ?, academic_year = ?, tuition_fee = ?, transport_fee = ?, hostel_fee = ?, lab_fee = ?, sports_fee = ?, misc_fee = ?, total_fee = ?, due_date = ?, description = ? WHERE id = ?");
        $stmt->execute([$class_id, $academic_year, $tuition_fee, $transport_fee, $hostel_fee, $lab_fee, $sports_fee, $misc_fee, $total_fee, $due_date, $description, $fee_id]);
        
        $success = "Fee structure updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating fee structure: " . $e->getMessage();
    }
}

// Handle fee structure deletion
if (isset($_GET['delete_fee_structure'])) {
    $fee_id = $_GET['delete_fee_structure'];
    
    try {
        // Check if there are any fee payments linked to this structure
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM fee_payments WHERE fee_structure_id = ?");
        $check_stmt->execute([$fee_id]);
        $payment_count = $check_stmt->fetchColumn();
        
        if ($payment_count > 0) {
            $error = "Cannot delete fee structure. There are fee payments linked to this structure.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
            $stmt->execute([$fee_id]);
            $success = "Fee structure deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error deleting fee structure: " . $e->getMessage();
    }
}

// Get all classes
$classes_stmt = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY class_name, section");
$classes = $classes_stmt->fetchAll();

// Get all fee structures with class names
$fee_structures_stmt = $pdo->query("
    SELECT fs.*, c.class_name, c.section 
    FROM fee_structures fs 
    JOIN classes c ON fs.class_id = c.id 
    ORDER BY fs.academic_year DESC, c.class_name, c.section
");
$fee_structures = $fee_structures_stmt->fetchAll();

// Get current academic year (assuming April-March cycle)
$current_year = date('Y');
$current_month = date('m');
$academic_year = ($current_month >= 4) ? $current_year . '-' . ($current_year + 1) : ($current_year - 1) . '-' . $current_year;
?>

<?php if (isset($success)): ?>
    <div class="success-message"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="error-message"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Fee Structures Management Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Fee Structures Management</h2>
        <button class="btn btn-primary" onclick="openAddFeeModal()">
            <i class="fas fa-plus"></i> Add New Fee Structure
        </button>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Academic Year</th>
                    <th>Tuition Fee</th>
                    <th>Transport</th>
                    <th>Hostel</th>
                    <th>Total Fee</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($fee_structures) > 0): ?>
                    <?php foreach ($fee_structures as $fee): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($fee['class_name'] . ' - ' . $fee['section']); ?></strong>
                                <?php if (!empty($fee['description'])): ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($fee['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                            <td><strong><?php echo $settings['currency'] . number_format($fee['tuition_fee'], 2); ?></strong></td>
                            <td><?php echo $settings['currency'] . number_format($fee['transport_fee'], 2); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['hostel_fee'], 2); ?></td>
                            <td>
                                <span class="badge badge-primary">
                                    <strong><?php echo $settings['currency'] . number_format($fee['total_fee'], 2); ?></strong>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $fee['status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($fee['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editFeeStructure(<?php echo $fee['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-info btn-sm" onclick="viewFeeDetails(<?php echo $fee['id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <a href="?page=fees&delete_fee_structure=<?php echo $fee['id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Are you sure you want to delete this fee structure?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            No fee structures found. <a href="javascript:void(0)" onclick="openAddFeeModal()">Add first fee structure</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Fee Summary Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Fee Summary by Class</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Academic Year</th>
                    <th>Tuition Fee</th>
                    <th>Transport Fee</th>
                    <th>Hostel Fee</th>
                    <th>Lab Fee</th>
                    <th>Sports Fee</th>
                    <th>Misc Fee</th>
                    <th>Total Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($fee_structures) > 0): ?>
                    <?php foreach ($fee_structures as $fee): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($fee['class_name'] . ' - ' . $fee['section']); ?></strong></td>
                            <td><?php echo htmlspecialchars($fee['academic_year']); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['tuition_fee'], 2); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['transport_fee'], 2); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['hostel_fee'], 2); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['lab_fee'], 2); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['sports_fee'], 2); ?></td>
                            <td><?php echo $settings['currency'] . number_format($fee['misc_fee'], 2); ?></td>
                            <td><strong><?php echo $settings['currency'] . number_format($fee['total_fee'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            No fee structures available.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Fee Structure Modal -->
<div id="addFeeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddFeeModal()">&times;</span>
        <h2>Add New Fee Structure</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Class *</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>">
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" value="<?php echo $academic_year; ?>" placeholder="e.g., 2024-2025" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tuition Fee *</label>
                    <input type="number" name="tuition_fee" class="form-control" min="0" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Transport Fee</label>
                    <input type="number" name="transport_fee" class="form-control" min="0" step="0.01" placeholder="0.00" value="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Hostel Fee</label>
                    <input type="number" name="hostel_fee" class="form-control" min="0" step="0.01" placeholder="0.00" value="0">
                </div>
                <div class="form-group">
                    <label>Lab Fee</label>
                    <input type="number" name="lab_fee" class="form-control" min="0" step="0.01" placeholder="0.00" value="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Sports Fee</label>
                    <input type="number" name="sports_fee" class="form-control" min="0" step="0.01" placeholder="0.00" value="0">
                </div>
                <div class="form-group">
                    <label>Miscellaneous Fee</label>
                    <input type="number" name="misc_fee" class="form-control" min="0" step="0.01" placeholder="0.00" value="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Due Date *</label>
                    <input type="date" name="due_date" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Fee structure description..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="add_fee_structure" class="btn btn-success">
                    <i class="fas fa-save"></i> Add Fee Structure
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeAddFeeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Fee Modal Functions
function openAddFeeModal() {
    document.getElementById('addFeeModal').style.display = 'block';
}

function closeAddFeeModal() {
    document.getElementById('addFeeModal').style.display = 'none';
}

function editFeeStructure(feeId) {
    alert('Edit feature for fee structure ID: ' + feeId + ' will be implemented soon!');
    // You can implement AJAX to load and edit fee structure
}

function viewFeeDetails(feeId) {
    alert('View details for fee structure ID: ' + feeId + ' will be implemented soon!');
    // You can implement modal to show detailed fee breakdown
}

// Close modal when clicking outside
window.onclick = function(event) {
    var feeModal = document.getElementById('addFeeModal');
    if (event.target == feeModal) {
        closeAddFeeModal();
    }
}
</script>