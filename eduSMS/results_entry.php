<?php
// results_entry.php - Enter Exam Results

// Get all exams
$exams_stmt = $pdo->query("SELECT * FROM exams WHERE status = 'completed' OR status = 'ongoing' ORDER BY start_date DESC");
$exams = $exams_stmt->fetchAll();

$selected_exam = $_GET['exam_id'] ?? '';
$selected_class = $_GET['class_id'] ?? '';

// Handle result submission
if (isset($_POST['save_results'])) {
    $exam_id = $_POST['exam_id'];
    $class_id = $_POST['class_id'];
    $subject_name = $_POST['subject_name'];
    
    try {
        // Delete existing results for this exam+class+subject
        $delete_stmt = $pdo->prepare("
            DELETE er FROM exam_results er 
            JOIN students s ON er.student_id = s.id 
            WHERE er.exam_id = ? AND er.subject_name = ? AND s.class_id = ?
        ");
        $delete_stmt->execute([$exam_id, $subject_name, $class_id]);
        
        // Insert new results
        if (isset($_POST['marks'])) {
            foreach ($_POST['marks'] as $student_id => $marks_obtained) {
                if (!empty($marks_obtained)) {
                    // Get total marks for this subject
                    $total_stmt = $pdo->prepare("SELECT total_marks FROM exam_subjects WHERE exam_id = ? AND subject_name = ?");
                    $total_stmt->execute([$exam_id, $subject_name]);
                    $subject_data = $total_stmt->fetch();
                    $total_marks = $subject_data['total_marks'] ?? 100;
                    
                    // Calculate percentage
                    $percentage = ($marks_obtained / $total_marks) * 100;
                    
                    // Determine grade
                    $grade_stmt = $pdo->prepare("SELECT grade_name FROM grade_system WHERE ? BETWEEN min_marks AND max_marks");
                    $grade_stmt->execute([$percentage]);
                    $grade_data = $grade_stmt->fetch();
                    $grade = $grade_data['grade_name'] ?? 'F';
                    
                    // Insert result
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO exam_results (student_id, exam_id, subject_name, marks_obtained, total_marks, grade) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->execute([$student_id, $exam_id, $subject_name, $marks_obtained, $total_marks, $grade]);
                }
            }
        }
        
        $success = "Results saved successfully!";
    } catch(PDOException $e) {
        $error = "Error saving results: " . $e->getMessage();
    }
}

// Get students for selected class
$students = [];
$subjects = [];
if ($selected_exam && $selected_class) {
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
    
    // Get subjects for this exam
    $subjects_stmt = $pdo->prepare("SELECT subject_name FROM exam_subjects WHERE exam_id = ?");
    $subjects_stmt->execute([$selected_exam]);
    $subjects = $subjects_stmt->fetchAll();
    
    // Get existing results
    $existing_results = [];
    if (isset($_GET['subject'])) {
        $result_stmt = $pdo->prepare("
            SELECT student_id, marks_obtained 
            FROM exam_results 
            WHERE exam_id = ? AND subject_name = ?
        ");
        $result_stmt->execute([$selected_exam, $_GET['subject']]);
        $result_data = $result_stmt->fetchAll();
        foreach ($result_data as $row) {
            $existing_results[$row['student_id']] = $row['marks_obtained'];
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Enter Exam Results</h2>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Filter Form -->
    <form method="GET" action="" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="results">
        
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
                    <?php 
                    $classes_stmt = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY class_name, section");
                    $all_classes = $classes_stmt->fetchAll();
                    foreach ($all_classes as $class): ?>
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
                        <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>" <?php echo isset($_GET['subject']) && $_GET['subject'] == $subject['subject_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </form>
    
    <?php if ($selected_exam && $selected_class && isset($_GET['subject']) && count($students) > 0): ?>
    <form method="POST">
        <input type="hidden" name="exam_id" value="<?php echo $selected_exam; ?>">
        <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
        <input type="hidden" name="subject_name" value="<?php echo htmlspecialchars($_GET['subject']); ?>">
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Roll No.</th>
                        <th>Student Name</th>
                        <th>Marks Obtained</th>
                        <th>Total Marks</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get total marks for this subject
                    $total_marks = 100;
                    if (isset($_GET['subject'])) {
                        $total_stmt = $pdo->prepare("SELECT total_marks FROM exam_subjects WHERE exam_id = ? AND subject_name = ?");
                        $total_stmt->execute([$selected_exam, $_GET['subject']]);
                        $subject_data = $total_stmt->fetch();
                        $total_marks = $subject_data['total_marks'] ?? 100;
                    }
                    
                    foreach ($students as $student): 
                        $existing_mark = $existing_results[$student['id']] ?? '';
                        $percentage = $existing_mark ? ($existing_mark / $total_marks) * 100 : 0;
                        
                        // Get grade
                        $grade = 'F';
                        if ($existing_mark) {
                            $grade_stmt = $pdo->prepare("SELECT grade_name FROM grade_system WHERE ? BETWEEN min_marks AND max_marks");
                            $grade_stmt->execute([$percentage]);
                            $grade_data = $grade_stmt->fetch();
                            $grade = $grade_data['grade_name'] ?? 'F';
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td>
                                <input type="number" name="marks[<?php echo $student['id']; ?>]" 
                                       class="form-control" value="<?php echo $existing_mark; ?>"
                                       min="0" max="<?php echo $total_marks; ?>" step="0.01">
                            </td>
                            <td><?php echo $total_marks; ?></td>
                            <td><?php echo $existing_mark ? number_format($percentage, 2) . '%' : '-'; ?></td>
                            <td>
                                <?php if ($existing_mark): ?>
                                    <span class="badge badge-<?php echo $grade == 'F' ? 'danger' : 'success'; ?>">
                                        <?php echo $grade; ?>
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
        
        <div style="margin-top: 20px; text-align: center;">
            <button type="submit" name="save_results" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> Save Results
            </button>
        </div>
    </form>
    <?php elseif ($selected_exam && $selected_class && !isset($_GET['subject'])): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-book fa-3x" style="margin-bottom: 20px;"></i>
            <p>Please select a subject to enter marks.</p>
        </div>
    <?php elseif ($selected_exam && $selected_class && count($students) == 0): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-users fa-3x" style="margin-bottom: 20px;"></i>
            <p>No students found in this class.</p>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-clipboard-check fa-3x" style="margin-bottom: 20px;"></i>
            <p>Please select an exam, class and subject to enter marks.</p>
        </div>
    <?php endif; ?>
</div>