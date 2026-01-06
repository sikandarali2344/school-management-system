<?php
// Check if user is logged in as student
if (!isset($current_user) || $current_user['role'] != 'student') {
    echo "<div class='error-message'>Access denied. Please login as student.</div>";
    return;
}

// Get student's class
$student_stmt = $pdo->prepare("SELECT class_id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student_data = $student_stmt->fetch();

if (!$student_data) {
    echo "<div class='error-message'>Student profile not found.</div>";
    return;
}

$class_id = $student_data['class_id'];

// Get class info
$class_info_stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
$class_info_stmt->execute([$class_id]);
$class_info = $class_info_stmt->fetch();

if (!$class_info) {
    $class_info = [
        'class_name' => 'Unknown Class',
        'section' => ''
    ];
}

// Get class timetable
$timetable_stmt = $pdo->prepare("
    SELECT t.*, c.course_name, c.course_code, u.name as teacher_name
    FROM timetable t
    LEFT JOIN courses c ON t.course_id = c.id
    LEFT JOIN teachers tr ON t.teacher_id = tr.id
    LEFT JOIN users u ON tr.user_id = u.id
    WHERE t.class_id = ?
    ORDER BY 
        FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
        t.period
");
$timetable_stmt->execute([$class_id]);
$timetable_data = $timetable_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize timetable by day and period
$timetable_grid = [];
foreach ($timetable_data as $entry) {
    $timetable_grid[$entry['day']][$entry['period']] = $entry;
}

// Days and periods
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$periods = range(1, 8);
?>

<div class="card">
    <div class="card-header">
        <h2>My Timetable - <?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['section']); ?></h2>
        <span class="badge badge-primary"><?php echo htmlspecialchars($current_user['name']); ?></span>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Day / Period</th>
                    <?php foreach ($periods as $p): ?>
                        <th>Period <?php echo $p; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $day): ?>
                    <tr>
                        <td><?php echo $day; ?></td>
                        <?php foreach ($periods as $p): ?>
                            <td>
                                <?php
                                if (isset($timetable_grid[$day][$p])) {
                                    $entry = $timetable_grid[$day][$p];
                                    echo htmlspecialchars($entry['course_name']) . "<br>";
                                    echo "<small>Teacher: " . htmlspecialchars($entry['teacher_name'] ?? '-') . "</small>";
                                } else {
                                    echo "-";
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
