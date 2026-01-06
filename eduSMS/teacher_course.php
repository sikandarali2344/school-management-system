<?php
// teacher_courses.php - Teacher's view of courses

// Get teacher ID from teachers table
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$current_user['id']]);
$teacher_data = $teacher_stmt->fetch();

if ($teacher_data) {
    $teacher_id = $teacher_data['id'];

    // ✅ UPDATED QUERY: Only assigned courses via teacher_courses table
    $courses_stmt = $pdo->prepare("
        SELECT c.*, 
               (
                   SELECT COUNT(DISTINCT s.id)
                   FROM students s
                   JOIN classes cl ON s.class_id = cl.id
                   JOIN class_courses cc ON cc.class_id = cl.id
                   WHERE cc.course_id = c.id
               ) AS student_count
        FROM courses c
        JOIN teacher_courses tc ON tc.course_id = c.id
        WHERE tc.teacher_id = ? AND c.status = 'active'
        ORDER BY c.course_name
    ");
    $courses_stmt->execute([$teacher_id]);
    $courses = $courses_stmt->fetchAll();

} else {
    $courses = [];
    echo "<div class='error-message'>Teacher profile not found.</div>";
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Courses</h2>
    </div>

    <?php if (count($courses) > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Department</th>
                        <th>Credits</th>
                        <th>Duration</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($course['course_code']); ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($course['course_name']); ?></strong><br>
                                <small><?= htmlspecialchars($course['description']); ?></small>
                            </td>
                            <td><?= htmlspecialchars($course['department']); ?></td>
                            <td><?= htmlspecialchars($course['credits']); ?></td>
                            <td><?= htmlspecialchars($course['duration']); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?= (int)$course['student_count']; ?> students
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:40px; color:#666;">
            <i class="fas fa-chalkboard-teacher fa-3x"></i>
            <p>No courses assigned to you.</p>
        </div>
    <?php endif; ?>
</div>
