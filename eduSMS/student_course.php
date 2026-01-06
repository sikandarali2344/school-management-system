<?php
// student_courses.php - Student's view of courses only

// Get student's enrolled courses
$student_stmt = $pdo->prepare("SELECT class_id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student_data = $student_stmt->fetch();

if ($student_data) {
    $class_id = $student_data['class_id'];
    
    // Get courses available for student's class
    // Note: You might need a separate table for class_courses mapping
    // For now, showing all active courses
    $courses_stmt = $pdo->query("
        SELECT c.*, u.name as teacher_name 
        FROM courses c 
        LEFT JOIN teachers t ON c.teacher_id = t.id 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE c.status = 'active'
        ORDER BY c.department, c.course_name
    ");
    $courses = $courses_stmt->fetchAll();
} else {
    $courses = [];
    echo "<div class='error-message'>Student profile not found.</div>";
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
                        <th>Teacher</th>
                        <th>Duration</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($course['department']); ?></td>
                            <td>
                                <span class="badge badge-primary">
                                    <?php echo htmlspecialchars($course['credits']); ?> Credits
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'Not Assigned'); ?></td>
                            <td><?php echo htmlspecialchars($course['duration']); ?></td>
                            <td>
                                <small style="color: #666;">
                                    <?php echo htmlspecialchars($course['description']); ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-book fa-3x" style="margin-bottom: 20px;"></i>
            <p>No courses available for you at the moment.</p>
            <p>Please contact your class teacher or administrator.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Course Details Statistics -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Course Summary</h2>
    </div>
    
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($courses); ?></h3>
                <p>Total Courses</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--info));">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php 
                    $total_credits = array_sum(array_column($courses, 'credits'));
                    echo $total_credits;
                    ?>
                </h3>
                <p>Total Credits</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--danger));">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php
                    $teachers = array_unique(array_column($courses, 'teacher_name'));
                    $teachers = array_filter($teachers); // Remove empty/null values
                    echo count($teachers);
                    ?>
                </h3>
                <p>Different Teachers</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--primary));">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php
                    $departments = array_unique(array_column($courses, 'department'));
                    echo count($departments);
                    ?>
                </h3>
                <p>Departments</p>
            </div>
        </div>
    </div>
    
    <!-- Department-wise breakdown -->
    <div style="margin-top: 20px;">
        <h3 style="margin-bottom: 15px; color: var(--dark);">Courses by Department</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php
            $dept_counts = [];
            foreach ($courses as $course) {
                $dept = $course['department'];
                if (!isset($dept_counts[$dept])) {
                    $dept_counts[$dept] = 0;
                }
                $dept_counts[$dept]++;
            }
            
            foreach ($dept_counts as $dept => $count) {
                echo "
                <div style='background: #f8f9fa; padding: 10px 15px; border-radius: 8px; border-left: 4px solid var(--primary);'>
                    <div style='font-weight: 600; color: var(--dark);'>$dept</div>
                    <div style='font-size: 1.5rem; color: var(--primary);'>$count courses</div>
                </div>";
            }
            ?>
        </div>
    </div>
</div>