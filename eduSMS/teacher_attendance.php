<?php
// Check if user is logged in
if (!isset($current_user) || $current_user['role'] != 'teacher') {
    echo "<div class='error-message'>Access denied. Please login as teacher.</div>";
    return;
}

// Get teacher's assigned classes
$teacher_id = null;
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$current_user['id']]);
$teacher_data = $teacher_stmt->fetch();

if ($teacher_data) {
    $teacher_id = $teacher_data['id'];
    
    // Get classes where teacher is class teacher
    $classes_stmt = $pdo->prepare("
        SELECT c.* 
        FROM classes c 
        WHERE c.class_teacher_id = ? AND c.status = 'active'
    ");
    $classes_stmt->execute([$teacher_id]);
    $classes = $classes_stmt->fetchAll();

    // Get courses taught by this teacher
    $courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? AND status = 'active'");
    $courses_stmt->execute([$teacher_id]);
    $courses = $courses_stmt->fetchAll();
} else {
    $classes = [];
    $courses = [];
    echo "<div class='error-message'>Teacher profile not found.</div>";
}

// Get today's date for default
$today = date('Y-m-d');
$selected_date = $_POST['attendance_date'] ?? $today;
$selected_class = $_POST['class_id'] ?? '';
$selected_course = $_POST['course_id'] ?? '';

// Get attendance records if class is selected
$attendance_records = [];
$students = [];

if ($selected_class && $selected_date && $teacher_id) {
    // Get students for selected class
    $students_stmt = $pdo->prepare("
        SELECT s.id, u.name, s.class_id 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.class_id = ? AND s.status = 'active'
        ORDER BY u.name
    ");
    $students_stmt->execute([$selected_class]);
    $students = $students_stmt->fetchAll();
    
    // Get existing attendance records for the selected date and class
    $attendance_stmt = $pdo->prepare("
        SELECT student_id, status, remarks 
        FROM attendance_records 
        WHERE class_id = ? AND attendance_date = ? AND course_id = ?
    ");
    $attendance_stmt->execute([$selected_class, $selected_date, $selected_course]);
    $attendance_data = $attendance_stmt->fetchAll();
    foreach ($attendance_data as $record) {
        $attendance_records[$record['student_id']] = $record;
    }
}

// Get teacher's attendance records summary
if ($teacher_id) {
    $summary_stmt = $pdo->prepare("
        SELECT 
            ar.attendance_date,
            c.class_name,
            c.section,
            co.course_name,
            COUNT(ar.id) as total_records,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count
        FROM attendance_records ar
        JOIN classes c ON ar.class_id = c.id
        LEFT JOIN courses co ON ar.course_id = co.id
        WHERE ar.recorded_by = ?
        GROUP BY ar.attendance_date, ar.class_id, ar.course_id
        ORDER BY ar.attendance_date DESC
        LIMIT 10
    ");
    $summary_stmt->execute([$current_user['id']]);
    $attendance_summary = $summary_stmt->fetchAll();

    // Get attendance summary for current month
    $current_month = date('Y-m');
    $monthly_summary_stmt = $pdo->prepare("
        SELECT 
            c.class_name,
            c.section,
            co.course_name,
            COUNT(DISTINCT ar.attendance_date) as total_days,
            COUNT(ar.id) as total_records,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count
        FROM attendance_records ar
        JOIN classes c ON ar.class_id = c.id
        LEFT JOIN courses co ON ar.course_id = co.id
        WHERE ar.recorded_by = ? AND DATE_FORMAT(ar.attendance_date, '%Y-%m') = ?
        GROUP BY ar.class_id, ar.course_id
    ");
    $monthly_summary_stmt->execute([$current_user['id'], $current_month]);
    $monthly_summary = $monthly_summary_stmt->fetchAll();
} else {
    $attendance_summary = [];
    $monthly_summary = [];
}
?>

<?php if (isset($success)): ?>
    <div class="success-message"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="error-message"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($teacher_id && count($classes) > 0): ?>

<!-- Monthly Summary Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Monthly Attendance Summary (<?php echo date('F Y'); ?>)</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Course</th>
                    <th>Total Days</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Late</th>
                    <th>Half Day</th>
                    <th>Total Records</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($monthly_summary) > 0): ?>
                    <?php foreach ($monthly_summary as $summary): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($summary['class_name'] . ' - ' . $summary['section']); ?></td>
                            <td><?php echo htmlspecialchars($summary['course_name'] ?? 'All'); ?></td>
                            <td><?php echo $summary['total_days']; ?></td>
                            <td>
                                <span class="badge badge-success"><?php echo $summary['present_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-danger"><?php echo $summary['absent_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-warning"><?php echo $summary['late_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $summary['half_day_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?php echo $summary['total_records']; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            No attendance records found for this month.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Take Attendance Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Take Attendance (Teacher)</h2>
    </div>
    
    <form method="POST" id="attendanceForm">
        <div class="form-row">
            <div class="form-group">
                <label>Select Date *</label>
                <input type="date" name="attendance_date" class="form-control" value="<?php echo $selected_date; ?>" required>
            </div>
            <div class="form-group">
                <label>Select Class *</label>
                <select name="class_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Select Course</label>
                <select name="course_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if (count($students) > 0): ?>
            <div class="table-responsive" style="margin-top: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>#STU-<?php echo $student['id']; ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td>
                                    <select name="attendance[<?php echo $student['id']; ?>]" class="form-control" required>
                                        <option value="present" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'present') ? 'selected' : ''; ?>>Present</option>
                                        <option value="absent" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'absent') ? 'selected' : ''; ?>>Absent</option>
                                        <option value="late" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'late') ? 'selected' : ''; ?>>Late</option>
                                        <option value="half_day" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'half_day') ? 'selected' : ''; ?>>Half Day</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?php echo $student['id']; ?>]" class="form-control" placeholder="Remarks" value="<?php echo isset($attendance_records[$student['id']]) ? htmlspecialchars($attendance_records[$student['id']]['remarks']) : ''; ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <button type="submit" name="take_attendance" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
        <?php elseif ($selected_class): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-users fa-3x" style="margin-bottom: 20px;"></i>
                <p>No students found in this class.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-calendar-check fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select a class to take attendance.</p>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Teacher's Daily Attendance Summary Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Recent Daily Attendance Records</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Class</th>
                    <th>Course</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Late</th>
                    <th>Half Day</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($attendance_summary) > 0): ?>
                    <?php foreach ($attendance_summary as $record): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['class_name'] . ' - ' . $record['section']); ?></td>
                            <td><?php echo htmlspecialchars($record['course_name'] ?? 'All'); ?></td>
                            <td>
                                <span class="badge badge-success"><?php echo $record['present_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-danger"><?php echo $record['absent_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-warning"><?php echo $record['late_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $record['half_day_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?php echo $record['total_records']; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            No attendance records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Teacher Attendance</h2>
        </div>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-chalkboard-teacher fa-3x" style="margin-bottom: 20px;"></i>
            <p>No classes assigned to you or teacher profile not found.</p>
            <p>Please contact administrator to assign classes.</p>
        </div>
    </div>
<?php endif; ?>