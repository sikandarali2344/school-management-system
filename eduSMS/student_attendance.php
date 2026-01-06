<?php
// Get student's class and attendance records
$student_id = null;
$student_stmt = $pdo->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student_data = $student_stmt->fetch();

if ($student_data) {
    $student_id = $student_data['id'];
    $class_id = $student_data['class_id'];
    
    // Get student's class information
    $class_stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
    $class_stmt->execute([$class_id]);
    $class_info = $class_stmt->fetch();
    
    // Get student's attendance records
    $attendance_stmt = $pdo->prepare("
        SELECT 
            ar.attendance_date,
            ar.status,
            ar.remarks,
            co.course_name,
            u.name as recorded_by
        FROM attendance_records ar
        LEFT JOIN courses co ON ar.course_id = co.id
        JOIN users u ON ar.recorded_by = u.id
        WHERE ar.student_id = ?
        ORDER BY ar.attendance_date DESC
        LIMIT 30
    ");
    $attendance_stmt->execute([$student_id]);
    $attendance_records = $attendance_stmt->fetchAll();
    
    // Calculate attendance summary
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days
        FROM attendance_records 
        WHERE student_id = ?
    ");
    $summary_stmt->execute([$student_id]);
    $attendance_summary = $summary_stmt->fetch();
    
    $attendance_percentage = $attendance_summary['total_days'] > 0 ? 
        round(($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100, 2) : 0;
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Attendance</h2>
    </div>
    
    <?php if ($student_data): ?>
        <!-- Attendance Summary -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $attendance_summary['present_days']; ?></h3>
                    <p>Present Days</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--danger);">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $attendance_summary['absent_days']; ?></h3>
                    <p>Absent Days</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $attendance_summary['late_days']; ?></h3>
                    <p>Late Days</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--info);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $attendance_percentage; ?>%</h3>
                    <p>Attendance Percentage</p>
                </div>
            </div>
        </div>

        <!-- Class Information -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 class="card-title">Class Information</h3>
            </div>
            <div style="padding: 15px;">
                <p><strong>Class:</strong> <?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['section']); ?></p>
                <p><strong>Total Attendance Days:</strong> <?php echo $attendance_summary['total_days']; ?></p>
            </div>
        </div>

        <!-- Attendance Details -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 class="card-title">Attendance Details</h3>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendance_records) > 0): ?>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_name'] ?? 'All Courses'); ?></td>
                                    <td>
                                        <?php 
                                        $status_badge = [
                                            'present' => 'badge-success',
                                            'absent' => 'badge-danger', 
                                            'late' => 'badge-warning',
                                            'half_day' => 'badge-info'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_badge[$record['status']] ?? 'badge-primary'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['remarks']); ?></td>
                                    <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">
                                    No attendance records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-user-graduate fa-3x" style="margin-bottom: 20px;"></i>
            <p>Student information not found.</p>
        </div>
    <?php endif; ?>
</div>