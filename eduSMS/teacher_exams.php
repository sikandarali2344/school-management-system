<?php
// teacher_exams.php
// Get teacher ID
$teacher_id = null;
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$current_user['id']]);
$teacher_data = $teacher_stmt->fetch();

if ($teacher_data) {
    $teacher_id = $teacher_data['id'];
    
    // Get exams where teacher is assigned to subjects
    $exams_stmt = $pdo->prepare("
        SELECT DISTINCT e.* 
        FROM exams e 
        JOIN exam_subjects es ON e.id = es.exam_id 
        WHERE EXISTS (
            SELECT 1 FROM courses c 
            WHERE c.teacher_id = ? 
            AND (c.course_name = es.subject_name OR ? = 0)
        )
        AND (e.status = 'ongoing' OR e.status = 'completed')
        ORDER BY e.start_date DESC
    ");
    $exams_stmt->execute([$teacher_id, $teacher_id]);
    $exams = $exams_stmt->fetchAll();
    
    // Get classes teacher teaches
    $classes_stmt = $pdo->prepare("
        SELECT DISTINCT c.* 
        FROM classes c 
        JOIN teachers t ON c.class_teacher_id = t.id 
        WHERE t.id = ? 
        AND c.status = 'active'
        UNION
        SELECT DISTINCT c.* 
        FROM classes c 
        JOIN students s ON c.id = s.class_id 
        WHERE EXISTS (
            SELECT 1 FROM courses cs 
            WHERE cs.teacher_id = ? 
            AND cs.id IN (SELECT course_id FROM student_courses WHERE student_id = s.id)
        )
        AND c.status = 'active'
    ");
    $classes_stmt->execute([$teacher_id, $teacher_id]);
    $classes = $classes_stmt->fetchAll();
    
    $selected_exam = $_GET['exam_id'] ?? '';
    $selected_class = $_GET['class_id'] ?? '';
    $selected_subject = $_GET['subject'] ?? '';
    
    // Get subjects taught by this teacher for selected exam
    $subjects = [];
    if ($selected_exam) {
        $subjects_stmt = $pdo->prepare("
            SELECT DISTINCT es.subject_name 
            FROM exam_subjects es 
            JOIN courses c ON (es.subject_name = c.course_name OR c.teacher_id = ?)
            WHERE es.exam_id = ? 
            AND c.teacher_id = ?
        ");
        $subjects_stmt->execute([$teacher_id, $selected_exam, $teacher_id]);
        $subjects = $subjects_stmt->fetchAll();
    }
    
    // Handle result submission
    if (isset($_POST['save_marks'])) {
        $exam_id = $_POST['exam_id'];
        $class_id = $_POST['class_id'];
        $subject_name = $_POST['subject_name'];
        
        try {
            // Get total marks for this subject
            $total_stmt = $pdo->prepare("SELECT total_marks, passing_marks FROM exam_subjects WHERE exam_id = ? AND subject_name = ?");
            $total_stmt->execute([$exam_id, $subject_name]);
            $subject_data = $total_stmt->fetch();
            $total_marks = $subject_data['total_marks'] ?? 100;
            $passing_marks = $subject_data['passing_marks'] ?? 33;
            
            // Process each student's marks
            foreach ($_POST['marks'] as $student_id => $marks_obtained) {
                if ($marks_obtained !== '') {
                    $marks_obtained = floatval($marks_obtained);
                    
                    // Calculate percentage and grade
                    $percentage = ($marks_obtained / $total_marks) * 100;
                    
                    $grade_stmt = $pdo->prepare("SELECT grade_name FROM grade_system WHERE ? BETWEEN min_marks AND max_marks");
                    $grade_stmt->execute([$percentage]);
                    $grade_data = $grade_stmt->fetch();
                    $grade = $grade_data['grade_name'] ?? 'F';
                    
                    // Check if result already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM exam_results WHERE student_id = ? AND exam_id = ? AND subject_name = ?");
                    $check_stmt->execute([$student_id, $exam_id, $subject_name]);
                    
                    if ($check_stmt->fetch()) {
                        // Update existing
                        $update_stmt = $pdo->prepare("
                            UPDATE exam_results 
                            SET marks_obtained = ?, total_marks = ?, grade = ?, created_at = NOW() 
                            WHERE student_id = ? AND exam_id = ? AND subject_name = ?
                        ");
                        $update_stmt->execute([$marks_obtained, $total_marks, $grade, $student_id, $exam_id, $subject_name]);
                    } else {
                        // Insert new
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO exam_results (student_id, exam_id, subject_name, marks_obtained, total_marks, grade) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([$student_id, $exam_id, $subject_name, $marks_obtained, $total_marks, $grade]);
                    }
                }
            }
            
            $success = "Marks saved successfully!";
        } catch(PDOException $e) {
            $error = "Error saving marks: " . $e->getMessage();
        }
    }
    
    // Get students for selected class
    $students = [];
    $existing_marks = [];
    if ($selected_exam && $selected_class && $selected_subject) {
        // Get students
        $students_stmt = $pdo->prepare("
            SELECT s.id, u.name, s.roll_number 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.class_id = ? AND s.status = 'active' 
            ORDER BY s.roll_number
        ");
        $students_stmt->execute([$selected_class]);
        $students = $students_stmt->fetchAll();
        
        // Get existing marks
        $marks_stmt = $pdo->prepare("
            SELECT student_id, marks_obtained 
            FROM exam_results 
            WHERE exam_id = ? AND subject_name = ?
        ");
        $marks_stmt->execute([$selected_exam, $selected_subject]);
        $marks_data = $marks_stmt->fetchAll();
        
        foreach ($marks_data as $mark) {
            $existing_marks[$mark['student_id']] = $mark['marks_obtained'];
        }
    }
} else {
    $exams = [];
    $classes = [];
    $subjects = [];
    $students = [];
    $existing_marks = [];
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Enter Exam Marks</h2>
        <small>Enter marks for your subjects</small>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($teacher_id): ?>
        
        <!-- Selection Form -->
        <form method="GET" action="" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="teacher_exams">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Select Exam</label>
                    <select name="exam_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Select Exam</option>
                        <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam == $exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name'] . ' (' . $exam['academic_year'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($selected_exam): ?>
                <div class="form-group">
                    <label>Select Class</label>
                    <select name="class_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if ($selected_exam && $selected_class && count($subjects) > 0): ?>
                <div class="form-group">
                    <label>Select Subject</label>
                    <select name="subject" class="form-control" onchange="this.form.submit()">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>" <?php echo $selected_subject == $subject['subject_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($selected_exam && $selected_class && $selected_subject): 
            // Get subject details
            $subject_stmt = $pdo->prepare("SELECT total_marks, passing_marks FROM exam_subjects WHERE exam_id = ? AND subject_name = ?");
            $subject_stmt->execute([$selected_exam, $selected_subject]);
            $subject_details = $subject_stmt->fetch();
            $total_marks = $subject_details['total_marks'] ?? 100;
            $passing_marks = $subject_details['passing_marks'] ?? 33;
            
            // Get exam details
            $exam_stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
            $exam_stmt->execute([$selected_exam]);
            $exam_details = $exam_stmt->fetch();
            
            // Get class details
            $class_stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
            $class_stmt->execute([$selected_class]);
            $class_details = $class_stmt->fetch();
        ?>
            
            <!-- Marks Entry Form -->
            <div class="card" style="margin-bottom: 20px; background: #f8f9fa;">
                <div class="card-header">
                    <h3 class="card-title">Enter Marks</h3>
                </div>
                <div style="padding: 20px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Exam</label>
                            <p><strong><?php echo htmlspecialchars($exam_details['exam_name']); ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Class</label>
                            <p><strong><?php echo htmlspecialchars($class_details['class_name'] . ' - ' . $class_details['section']); ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Subject</label>
                            <p><strong><?php echo htmlspecialchars($selected_subject); ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Total Marks</label>
                            <p><strong><?php echo $total_marks; ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Passing Marks</label>
                            <p><strong><?php echo $passing_marks; ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (count($students) > 0): ?>
            <form method="POST">
                <input type="hidden" name="exam_id" value="<?php echo $selected_exam; ?>">
                <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($selected_subject); ?>">
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Roll No.</th>
                                <th>Student Name</th>
                                <th>Marks Obtained (0 - <?php echo $total_marks; ?>)</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): 
                                $existing_mark = $existing_marks[$student['id']] ?? '';
                                $percentage = $existing_mark ? ($existing_mark / $total_marks) * 100 : 0;
                                
                                // Calculate grade
                                $grade = 'F';
                                if ($existing_mark) {
                                    $grade_stmt = $pdo->prepare("SELECT grade_name FROM grade_system WHERE ? BETWEEN min_marks AND max_marks");
                                    $grade_stmt->execute([$percentage]);
                                    $grade_data = $grade_stmt->fetch();
                                    $grade = $grade_data['grade_name'] ?? 'F';
                                }
                                
                                $is_passed = $existing_mark >= $passing_marks;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td>
                                        <input type="number" name="marks[<?php echo $student['id']; ?>]" 
                                               class="form-control" value="<?php echo $existing_mark; ?>"
                                               min="0" max="<?php echo $total_marks; ?>" step="0.01"
                                               style="width: 120px;">
                                    </td>
                                    <td>
                                        <?php if ($existing_mark): ?>
                                            <?php echo number_format($percentage, 2); ?>%
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($existing_mark): ?>
                                            <span class="badge badge-<?php echo $grade == 'F' ? 'danger' : 'success'; ?>">
                                                <?php echo $grade; ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($existing_mark): ?>
                                            <span class="badge badge-<?php echo $is_passed ? 'success' : 'danger'; ?>">
                                                <?php echo $is_passed ? 'Pass' : 'Fail'; ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Statistics -->
                <?php
                $entered_count = 0;
                $passed_count = 0;
                $total_entered = 0;
                
                foreach ($students as $student) {
                    if (isset($existing_marks[$student['id']])) {
                        $entered_count++;
                        $total_entered += $existing_marks[$student['id']];
                        if ($existing_marks[$student['id']] >= $passing_marks) {
                            $passed_count++;
                        }
                    }
                }
                
                $average = $entered_count > 0 ? $total_entered / $entered_count : 0;
                $pass_percentage = $entered_count > 0 ? ($passed_count / $entered_count) * 100 : 0;
                ?>
                
                <div class="card" style="margin: 20px 0; background: #e8f5e9;">
                    <div class="card-header">
                        <h4>Summary</h4>
                    </div>
                    <div class="form-row" style="padding: 15px;">
                        <div class="form-group">
                            <label>Total Students</label>
                            <p><strong><?php echo count($students); ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Marks Entered</label>
                            <p><strong><?php echo $entered_count; ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Students Passed</label>
                            <p><strong><?php echo $passed_count; ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Average Marks</label>
                            <p><strong><?php echo number_format($average, 2); ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Pass Percentage</label>
                            <p><strong><?php echo number_format($pass_percentage, 2); ?>%</strong></p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="submit" name="save_marks" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Save All Marks
                    </button>
                    <button type="button" class="btn btn-info btn-lg" onclick="fillPassingMarks()" style="margin-left: 10px;">
                        <i class="fas fa-magic"></i> Auto-Fill Passing Marks
                    </button>
                    <button type="button" class="btn btn-warning btn-lg" onclick="clearAllMarks()" style="margin-left: 10px;">
                        <i class="fas fa-eraser"></i> Clear All
                    </button>
                </div>
            </form>
            
            <script>
            function fillPassingMarks() {
                var passingMarks = <?php echo $passing_marks; ?>;
                var inputs = document.querySelectorAll('input[name^="marks["]');
                inputs.forEach(function(input) {
                    if (input.value === '') {
                        input.value = passingMarks;
                    }
                });
            }
            
            function clearAllMarks() {
                if (confirm('Are you sure you want to clear all marks?')) {
                    var inputs = document.querySelectorAll('input[name^="marks["]');
                    inputs.forEach(function(input) {
                        input.value = '';
                    });
                }
            }
            </script>
            
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-users fa-3x" style="margin-bottom: 20px;"></i>
                    <p>No students found in this class.</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($selected_exam && $selected_class && !$selected_subject): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-book fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select a subject to enter marks.</p>
                <p>Available subjects: <?php echo count($subjects); ?></p>
            </div>
        <?php elseif ($selected_exam && !$selected_class): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-school fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select a class to enter marks.</p>
                <p>You teach <?php echo count($classes); ?> class(es).</p>
            </div>
        <?php elseif (!$selected_exam): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-file-alt fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select an exam to enter marks.</p>
                <p>You have <?php echo count($exams); ?> active exam(s).</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-chalkboard-teacher fa-3x" style="margin-bottom: 20px;"></i>
            <p>Teacher profile not found.</p>
            <p>Please contact administrator if this is an error.</p>
        </div>
    <?php endif; ?>
</div>