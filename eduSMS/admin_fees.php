<?php
// admin_fees.php

// Handle fee payment
if (isset($_POST['pay_fee'])) {
    $fee_id = $_POST['fee_id'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    
    try {
        $stmt = $pdo->prepare("UPDATE fees SET status = 'paid', payment_date = ?, payment_method = ? WHERE id = ?");
        $stmt->execute([$payment_date, $payment_method, $fee_id]);
        $success = "Fee payment recorded successfully!";
    } catch(PDOException $e) {
        $error = "Error recording payment: " . $e->getMessage();
    }
}

// Get all fees
$fees_stmt = $pdo->query("
    SELECT f.*, u.name as student_name, c.class_name, c.section 
    FROM fees f 
    JOIN students s ON f.student_id = s.id 
    JOIN users u ON s.user_id = u.id 
    JOIN classes c ON s.class_id = c.id
    ORDER BY f.due_date DESC
");
$all_fees = $fees_stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Fee Management</h2>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Fee Type</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Payment Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($all_fees) > 0): ?>
                    <?php foreach ($all_fees as $fee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fee['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($fee['class_name'] . ' - ' . $fee['section']); ?></td>
                            <td><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                            <td><span class="currency"><?php echo $settings['currency'] . number_format($fee['amount'], 2); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $fee['status'] == 'paid' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($fee['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $fee['payment_date'] ? date('M j, Y', strtotime($fee['payment_date'])) : '-'; ?></td>
                            <td>
                                <?php if ($fee['status'] == 'pending'): ?>
                                    <button class="btn btn-success btn-sm" onclick="openPayFeeModal(<?php echo $fee['id']; ?>, <?php echo $fee['amount']; ?>)">
                                        <i class="fas fa-money-bill-wave"></i> Mark Paid
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            No fee records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pay Fee Modal -->
<div id="payFeeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePayFeeModal()">&times;</span>
        <h2>Record Fee Payment</h2>
        <form method="POST">
            <input type="hidden" name="fee_id" id="payFeeId">
            <div class="form-row">
                <div class="form-group">
                    <label>Amount</label>
                    <input type="text" id="payAmount" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Payment Method *</label>
                <select name="payment_method" class="form-control" required>
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Online">Online</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="pay_fee" class="btn btn-success">
                    <i class="fas fa-check"></i> Confirm Payment
                </button>
                <button type="button" class="btn btn-secondary" onclick="closePayFeeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayFeeModal(feeId, amount) {
    document.getElementById('payFeeId').value = feeId;
    document.getElementById('payAmount').value = '<?php echo $settings['currency']; ?>' + amount.toFixed(2);
    document.getElementById('payFeeModal').style.display = 'block';
}

function closePayFeeModal() {
    document.getElementById('payFeeModal').style.display = 'none';
}
</script>