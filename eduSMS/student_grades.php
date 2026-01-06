<?php
// Check if user is logged in as student
if (!isset($current_user) || $current_user['role'] != 'student') {
    echo "<div class='error-message'>Access denied. Please login as student.</div>";
    return;
}

// Get student's ID
$student_id = null;
$student_stmt = $pdo->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student_data = $student_stmt->fetch();

if ($student_data) {
    $student_id = $student_data['id'];
    $class_id = $student_data['class_id'];
    
    // Get current academic year
    $current_year = date('Y');
    $current_month = date('m');
    $academic_year = ($current_month >= 4) ? $current_year . '-' . ($current_year + 1) : ($current_year - 1) . '-' . $current_year;
    
    // Get all grades for this student
    $grades_stmt = $pdo->prepare("
        SELECT g.*, c.course_name, c.course_code, t.subject as teacher_subject, u.name as teacher_name
        FROM grades g 
        JOIN courses c ON g.course_id = c.id 
        JOIN teachers t ON g.teacher_id = t.id 
        JOIN users u ON t.user_id = u.id 
        WHERE g.student_id = ? AND g.academic_year = ?
        ORDER BY g.exam_date DESC
    ");
    $grades_stmt->execute([$student_id, $academic_year]);
    $grades = $grades_stmt->fetchAll();
    
    // Calculate overall performance
    $total_grade_points = 0;
    $total_courses = 0;
    $latest_grades = [];
    
    foreach ($grades as $grade) {
        $total_grade_points += $grade['grade_point'];
        $total_courses++;
        $latest_grades[$grade['course_id']] = $grade;
    }
    
    $gpa = $total_courses > 0 ? $total_grade_points / $total_courses : 0;
} else {
    $grades = [];
    $gpa = 0;
    echo "<div class='error-message'>Student profile not found.</div>";
}
?>

<!-- Student Performance Summary -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Academic Performance (<?php echo $academic_year; ?>)</h2>
    </div>
    
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary);">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($latest_grades); ?></h3>
                <p>Courses with Grades</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($gpa, 2); ?></h3>
                <p>Current GPA</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--info);">
                <i class="fas fa-award"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($grades); ?></h3>
                <p>Total Assessments</p>
            </div>
        </div>
    </div>
</div>

<!-- Grade Scale Reference -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Grade Scale</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Grade Point</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grade_scale_stmt = $pdo->query("SELECT * FROM grade_scale ORDER BY min_percentage DESC");
                $grade_scale = $grade_scale_stmt->fetchAll();
                ?>
                <?php foreach ($grade_scale as $scale): ?>
                    <tr>
                        <td><?php echo $scale['min_percentage']; ?>% - <?php echo $scale['max_percentage']; ?>%</td>
                        <td><strong><?php echo $scale['grade']; ?></strong></td>
                        <td><?php echo $scale['grade_point']; ?></td>
                        <td><?php echo $scale['description']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Grades -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Recent Grades</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Exam Type</th>
                    <th>Marks</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                    <th>Grade Point</th>
                    <th>Date</th>
                    <th>Teacher</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($grades) > 0): ?>
                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($grade['course_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($grade['course_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($grade['exam_type']); ?></td>
                            <td>
                                <strong><?php echo $grade['marks_obtained']; ?></strong> / <?php echo $grade['total_marks']; ?>
                            </td>
                            <td>
                                <?php echo number_format(($grade['marks_obtained'] / $grade['total_marks']) * 100, 2); ?>%
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $grade['grade'] == 'F' ? 'danger' : 'success'; ?>">
                                    <?php echo $grade['grade']; ?>
                                </span>
                            </td>
                            <td><?php echo $grade['grade_point']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($grade['exam_date'])); ?></td>
                            <td><?php echo htmlspecialchars($grade['teacher_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            <i class="fas fa-clipboard-list fa-2x" style="color: #ccc; margin-bottom: 10px;"></i><br>
                            No grades available yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Course-wise Summary -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Course-wise Performance</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Latest Grade</th>
                    <th>Grade Point</th>
                    <th>Latest Exam</th>
                    <th>Teacher</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($latest_grades) > 0): ?>
                    <?php foreach ($latest_grades as $course_id => $grade): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($grade['course_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($grade['course_code']); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $grade['grade'] == 'F' ? 'danger' : 'success'; ?>">
                                    <?php echo $grade['grade']; ?>
                                </span>
                            </td>
                            <td><?php echo $grade['grade_point']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($grade['exam_type']); ?>
                                <br><small><?php echo date('M j, Y', strtotime($grade['exam_date'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($grade['teacher_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            No course grades available.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div> 