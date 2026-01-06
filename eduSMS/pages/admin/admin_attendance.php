<?php
// Default date
$today = date('Y-m-d');
$selected_date = $_POST['attendance_date'] ?? '';
$selected_class = $_POST['class_id'] ?? '';
$selected_course = $_POST['course_id'] ?? '';

// Get all classes
$classes = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY class_name, section")->fetchAll();

// Get all courses
$courses = $pdo->query("SELECT * FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();

// Fetch attendance summary for admin control
$filters = [];
$sql = "
    SELECT 
        ar.attendance_date,
        ar.class_id,
        ar.course_id,
        c.class_name,
        c.section,
        co.course_name,
        COUNT(ar.id) as total_records,
        SUM(CASE WHEN ar.status='present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN ar.status='absent' THEN 1 ELSE 0 END) as absent_count,
        u.name as recorded_by
    FROM attendance_records ar
    JOIN classes c ON ar.class_id = c.id
    LEFT JOIN courses co ON ar.course_id = co.id
    JOIN users u ON ar.recorded_by = u.id
    WHERE 1=1
";

// Apply filters
$params = [];

if ($selected_date !== '') {
    $sql .= " AND ar.attendance_date = ? ";
    $params[] = $selected_date;
}

if ($selected_class !== '') {
    $sql .= " AND ar.class_id = ? ";
    $params[] = $selected_class;
}

if ($selected_course !== '') {
    $sql .= " AND ar.course_id = ? ";
    $params[] = $selected_course;
}

$sql .= " GROUP BY ar.attendance_date, ar.class_id, ar.course_id ORDER BY ar.attendance_date DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance_summary = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Admin Attendance Control Panel</h2>
    </div>

    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Select Date</label>
                <input type="date" name="attendance_date" value="<?= $selected_date ?>" class="form-control">
            </div>

            <div class="form-group">
                <label>Select Class</label>
                <select name="class_id" class="form-control">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['id']; ?>" <?= $selected_class == $class['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($class['class_name'] . " - " . $class['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Course</label>
                <select name="course_id" class="form-control">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id']; ?>" <?= $selected_course == $course['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <button class="btn btn-primary" style="margin-top: 22px;">Filter</button>
            </div>
        </div>
    </form>
</div>

<!-- Attendance Summary -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Attendance Summary</h2>
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
                    <th>Total</th>
                    <th>Recorded By</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($attendance_summary) > 0): ?>
                    <?php foreach ($attendance_summary as $record): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                            <td><?= htmlspecialchars($record['class_name'] . " - " . $record['section']); ?></td>
                            <td><?= htmlspecialchars($record['course_name'] ?? 'All'); ?></td>
                            <td><span class="badge badge-success"><?= $record['present_count']; ?></span></td>
                            <td><span class="badge badge-danger"><?= $record['absent_count']; ?></span></td>
                            <td><span class="badge badge-primary"><?= $record['total_records']; ?></span></td>
                            <td><?= htmlspecialchars($record['recorded_by']); ?></td>

                            <td>
                                <a href="?page=edit_attendance&date=<?= $record['attendance_date']; ?>&class=<?= $record['class_id']; ?>&course=<?= $record['course_id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <a href="?page=attendance&delete_attendance=<?= $record['attendance_date']; ?>&class=<?= $record['class_id']; ?>&course=<?= $record['course_id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Delete ALL attendance for this date, class & course?');">
                                   <i class="fas fa-trash"></i> Delete
                               </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:20px;">No attendance found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
