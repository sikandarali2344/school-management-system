<?php
// student_fees.php

$student_stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student = $student_stmt->fetch();

if ($student) {
    $fees_stmt = $pdo->prepare("
        SELECT * FROM fees 
        WHERE student_id = ? 
        ORDER BY due_date DESC
    ");
    $fees_stmt->execute([$student['id']]);
    $student_fees = $fees_stmt->fetchAll();
    ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">My Fee Status</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Fee Type</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($student_fees) > 0): ?>
                        <?php 
                        $total_pending = 0;
                        $total_paid = 0;
                        ?>
                        <?php foreach ($student_fees as $fee): ?>
                            <?php
                            if ($fee['status'] == 'pending') {
                                $total_pending += $fee['amount'];
                            } else {
                                $total_paid += $fee['amount'];
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                <td><span class="currency"><?php echo $settings['currency'] . number_format($fee['amount'], 2); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $fee['status'] == 'paid' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($fee['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $fee['payment_date'] ? date('M j, Y', strtotime($fee['payment_date'])) : '-'; ?></td>
                                <td><?php echo $fee['payment_method'] ?: '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                No fee records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Fee Summary</h2>
        </div>
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $settings['currency'] . number_format($total_paid, 2); ?></h3>
                    <p>Total Paid</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--danger);">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $settings['currency'] . number_format($total_pending, 2); ?></h3>
                    <p>Total Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary);">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $settings['currency'] . number_format($total_paid + $total_pending, 2); ?></h3>
                    <p>Total Fees</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>