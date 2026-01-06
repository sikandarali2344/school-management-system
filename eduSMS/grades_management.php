<?php
// Check if user is logged in as teacher
if (!isset($current_user) || $current_user['role'] != 'teacher') {
    echo "<div class='error-message'>Access denied. Please login as teacher.</div>";
    return;
}

// Define academic year FIRST - before any usage
$current_year = date('Y');
$current_month = date('m');
$academic_year = ($current_month >= 4) ? $current_year . '-' . ($current_year + 1) : ($current_year - 1) . '-' . $current_year;

// Get teacher's ID and assigned classes/courses
$teacher_id = null;
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$current_user['id']]);
$teacher_data = $teacher_stmt->fetch();

if ($teacher_data) {
    $teacher_id = $teacher_data['id'];
    
    // Get courses taught by this teacher
    $courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? AND status = 'active'");
    $courses_stmt->execute([$teacher_id]);
    $courses = $courses_stmt->fetchAll();
} else {
    $courses = [];
    echo "<div class='error-message'>Teacher profile not found.</div>";
}

// Handle grade submission
if (isset($_POST['add_grade'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $class_id = $_POST['class_id'];
    $exam_type = $_POST['exam_type'];
    $marks_obtained = $_POST['marks_obtained'];
    $total_marks = $_POST['total_marks'];
    $exam_date = $_POST['exam_date'];
    $remarks = $_POST['remarks'];
    
    // Calculate percentage and grade
    $percentage = ($marks_obtained / $total_marks) * 100;
    
    // Get grade based on percentage
    $grade_stmt = $pdo->prepare("SELECT grade, grade_point FROM grade_scale WHERE ? BETWEEN min_percentage AND max_percentage");
    $grade_stmt->execute([$percentage]);
    $grade_data = $grade_stmt->fetch();
    
    $grade = $grade_data['grade'] ?? 'F';
    $grade_point = $grade_data['grade_point'] ?? 0.0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, class_id, teacher_id, academic_year, exam_type, marks_obtained, total_marks, grade, grade_point, remarks, exam_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $course_id, $class_id, $teacher_id, $academic_year, $exam_type, $marks_obtained, $total_marks, $grade, $grade_point, $remarks, $exam_date, $current_user['id']]);
        
        $success = "Grade added successfully!";
    } catch(PDOException $e) {
        $error = "Error adding grade: " . $e->getMessage();
    }
}

// Handle bulk grade submission
if (isset($_POST['add_bulk_grades'])) {
    $course_id = $_POST['course_id'];
    $class_id = $_POST['class_id'];
    $exam_type = $_POST['exam_type'];
    $total_marks = $_POST['total_marks'];
    $exam_date = $_POST['exam_date'];
    
    $success_count = 0;
    $error_count = 0;
    $error_messages = [];
    
    foreach ($_POST['marks'] as $student_id => $marks_obtained) {
        if (!empty($marks_obtained) && $marks_obtained >= 0) {
            $percentage = ($marks_obtained / $total_marks) * 100;
            
            // Get grade based on percentage
            $grade_stmt = $pdo->prepare("SELECT grade, grade_point FROM grade_scale WHERE ? BETWEEN min_percentage AND max_percentage");
            $grade_stmt->execute([$percentage]);
            $grade_data = $grade_stmt->fetch();
            
            $grade = $grade_data['grade'] ?? 'F';
            $grade_point = $grade_data['grade_point'] ?? 0.0;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, class_id, teacher_id, academic_year, exam_type, marks_obtained, total_marks, grade, grade_point, exam_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $course_id, $class_id, $teacher_id, $academic_year, $exam_type, $marks_obtained, $total_marks, $grade, $grade_point, $exam_date, $current_user['id']]);
                $success_count++;
            } catch(PDOException $e) {
                $error_count++;
                $error_messages[] = "Student ID $student_id: " . $e->getMessage();
            }
        }
    }
    
    if ($success_count > 0) {
        $success = "Successfully added grades for $success_count students!";
    }
    if ($error_count > 0) {
        $error = "Failed to add grades for $error_count students. " . implode(", ", $error_messages);
    }
}

// Get students for selected class/course
$students = [];
$selected_course = $_POST['course_id'] ?? '';
$selected_class = $_POST['class_id'] ?? '';

if ($selected_course && $selected_class) {
    // REMOVED profile_picture from query since column doesn't exist
    $students_stmt = $pdo->prepare("
        SELECT s.id, u.name, s.class_id 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.class_id = ? AND s.status = 'active'
        ORDER BY u.name
    ");
    $students_stmt->execute([$selected_class]);
    $students = $students_stmt->fetchAll();
}

// Get all classes for dropdown
$all_classes_stmt = $pdo->query("SELECT DISTINCT c.id, c.class_name, c.section FROM classes c JOIN students s ON c.id = s.class_id WHERE c.status = 'active' ORDER BY c.class_name, c.section");
$all_classes = $all_classes_stmt->fetchAll();
?>

<?php if (isset($success)): ?>
    <div class="success-message"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="error-message"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($teacher_id && count($courses) > 0): ?>

<!-- Bulk Grade Entry Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Bulk Grade Entry</h2>
    </div>
    
    <form method="POST" id="bulkGradeForm">
        <div class="form-row">
            <div class="form-group">
                <label>Select Course *</label>
                <select name="course_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Select Class *</label>
                <select name="class_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($all_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if ($selected_course && $selected_class && count($students) > 0): ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Exam Type *</label>
                    <select name="exam_type" class="form-control" required>
                        <option value="Midterm">Midterm Exam</option>
                        <option value="Final">Final Exam</option>
                        <option value="Quiz">Quiz</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Project">Project</option>
                        <option value="Practical">Practical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Total Marks *</label>
                    <input type="number" name="total_marks" class="form-control" min="1" max="1000" value="100" required onchange="recalculateAllGrades()">
                </div>
                <div class="form-group">
                    <label>Exam Date *</label>
                    <input type="date" name="exam_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="table-responsive" style="margin-top: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Marks Obtained</th>
                            <th>Percentage</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>#STU-<?php echo $student['id']; ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td>
                                    <input type="number" name="marks[<?php echo $student['id']; ?>]" class="form-control marks-input" min="0" step="0.01" placeholder="0.00" onchange="calculateGrade(this, <?php echo $student['id']; ?>)">
                                </td>
                                <td id="percentage_<?php echo $student['id']; ?>">-</td>
                                <td id="grade_<?php echo $student['id']; ?>">-</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <button type="submit" name="add_bulk_grades" class="btn btn-success btn-lg">
                    <i class="fas fa-save"></i> Save All Grades
                </button>
            </div>
        <?php elseif ($selected_course && $selected_class): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-users fa-3x" style="margin-bottom: 20px;"></i>
                <p>No students found in this class.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-clipboard-list fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select a course and class to enter grades.</p>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Individual Grade Entry Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Individual Grade Entry</h2>
    </div>
    
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Student *</label>
                <select name="student_id" class="form-control" required>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>">
                            <?php echo htmlspecialchars($student['name']); ?> (STU-<?php echo $student['id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Course *</label>
                <select name="course_id" class="form-control" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Class *</label>
                <select name="class_id" class="form-control" required>
                    <option value="">Select Class</option>
                    <?php foreach ($all_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Exam Type *</label>
                <select name="exam_type" class="form-control" required>
                    <option value="Midterm">Midterm Exam</option>
                    <option value="Final">Final Exam</option>
                    <option value="Quiz">Quiz</option>
                    <option value="Assignment">Assignment</option>
                    <option value="Project">Project</option>
                    <option value="Practical">Practical</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Marks Obtained *</label>
                <input type="number" name="marks_obtained" class="form-control" min="0" step="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Total Marks *</label>
                <input type="number" name="total_marks" class="form-control" min="1" max="1000" value="100" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Exam Date *</label>
                <input type="date" name="exam_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Additional remarks..."></textarea>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <button type="submit" name="add_grade" class="btn btn-primary btn-lg">
                <i class="fas fa-plus"></i> Add Grade
            </button>
        </div>
    </form>
</div>

<!-- Recent Grades Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recently Added Grades</h2>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Exam Type</th>
                    <th>Marks</th>
                    <th>Grade</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recent_grades_stmt = $pdo->prepare("
                    SELECT g.*, u.name as student_name, c.course_name, cl.class_name, cl.section 
                    FROM grades g 
                    JOIN students s ON g.student_id = s.id 
                    JOIN users u ON s.user_id = u.id 
                    JOIN courses c ON g.course_id = c.id 
                    JOIN classes cl ON g.class_id = cl.id 
                    WHERE g.teacher_id = ? 
                    ORDER BY g.created_at DESC 
                    LIMIT 10
                ");
                $recent_grades_stmt->execute([$teacher_id]);
                $recent_grades = $recent_grades_stmt->fetchAll();
                ?>
                
                <?php if (count($recent_grades) > 0): ?>
                    <?php foreach ($recent_grades as $grade): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['exam_type']); ?></td>
                            <td>
                                <strong><?php echo $grade['marks_obtained']; ?></strong> / <?php echo $grade['total_marks']; ?>
                                <br><small><?php echo number_format(($grade['marks_obtained'] / $grade['total_marks']) * 100, 2); ?>%</small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $grade['grade'] == 'F' ? 'danger' : 'success'; ?>">
                                    <?php echo $grade['grade']; ?> (<?php echo $grade['grade_point']; ?>)
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($grade['exam_date'])); ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editGrade(<?php echo $grade['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?page=grades&delete_grade=<?php echo $grade['id']; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm('Are you sure you want to delete this grade?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            No grades added yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function calculateGrade(input, studentId) {
    const marks = parseFloat(input.value);
    const totalMarks = parseFloat(document.querySelector('input[name="total_marks"]').value);
    
    if (marks >= 0 && totalMarks > 0) {
        const percentage = (marks / totalMarks) * 100;
        document.getElementById('percentage_' + studentId).textContent = percentage.toFixed(2) + '%';
        
        // Simple grade calculation
        let grade = 'F';
        if (percentage >= 90) grade = 'A+';
        else if (percentage >= 80) grade = 'A';
        else if (percentage >= 75) grade = 'B+';
        else if (percentage >= 70) grade = 'B';
        else if (percentage >= 65) grade = 'C+';
        else if (percentage >= 60) grade = 'C';
        else if (percentage >= 55) grade = 'D+';
        else if (percentage >= 50) grade = 'D';
        
        document.getElementById('grade_' + studentId).textContent = grade;
    }
}

function recalculateAllGrades() {
    // Recalculate all grades when total marks changes
    const marksInputs = document.querySelectorAll('.marks-input');
    marksInputs.forEach(input => {
        const studentId = input.name.match(/\[(\d+)\]/)[1];
        calculateGrade(input, studentId);
    });
}

function editGrade(gradeId) {
    alert('Edit feature for grade ID: ' + gradeId + ' will be implemented soon!');
}
</script>

<?php else: ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Grade Management</h2>
        </div>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-chalkboard-teacher fa-3x" style="margin-bottom: 20px;"></i>
            <p>No courses assigned to you or teacher profile not found.</p>
            <p>Please contact administrator to assign courses.</p>
        </div>
    </div>
<?php endif; ?>