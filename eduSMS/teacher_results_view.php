<?php
// teacher_results_view.php
// Get teacher ID
$teacher_id = null;
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$current_user['id']]);
$teacher_data = $teacher_stmt->fetch();

if ($teacher_data) {
    $teacher_id = $teacher_data['id'];
    
    // Get exams where teacher has entered marks
    $exams_stmt = $pdo->prepare("
        SELECT DISTINCT e.* 
        FROM exams e 
        JOIN exam_subjects es ON e.id = es.exam_id 
        JOIN courses c ON (es.subject_name = c.course_name OR c.teacher_id = ?)
        WHERE c.teacher_id = ?
        ORDER BY e.start_date DESC
    ");
    $exams_stmt->execute([$teacher_id, $teacher_id]);
    $exams = $exams_stmt->fetchAll();
    
    $selected_exam = $_GET['exam_id'] ?? '';
    $selected_class = $_GET['class_id'] ?? '';
    
    // Get classes for selected exam
    $classes = [];
    if ($selected_exam) {
        $classes_stmt = $pdo->prepare("
            SELECT DISTINCT c.* 
            FROM classes c 
            JOIN students s ON c.id = s.class_id 
            JOIN exam_results er ON s.id = er.student_id 
            JOIN exam_subjects es ON er.exam_id = es.exam_id AND er.subject_name = es.subject_name
            JOIN courses cs ON (es.subject_name = cs.course_name OR cs.teacher_id = ?)
            WHERE er.exam_id = ? 
            AND cs.teacher_id = ?
            AND c.status = 'active'
            ORDER BY c.class_name, c.section
        ");
        $classes_stmt->execute([$teacher_id, $selected_exam, $teacher_id]);
        $classes = $classes_stmt->fetchAll();
    }
    
    // Get results data
    $results_summary = [];
    $student_results = [];
    $class_stats = [];
    
    if ($selected_exam && $selected_class) {
        // Get subjects taught by teacher for this exam
        $subjects_stmt = $pdo->prepare("
            SELECT DISTINCT er.subject_name 
            FROM exam_results er 
            JOIN exam_subjects es ON er.exam_id = es.exam_id AND er.subject_name = es.subject_name
            JOIN courses c ON (es.subject_name = c.course_name OR c.teacher_id = ?)
            WHERE er.exam_id = ? 
            AND c.teacher_id = ?
            ORDER BY er.subject_name
        ");
        $subjects_stmt->execute([$teacher_id, $selected_exam, $teacher_id]);
        $subjects = $subjects_stmt->fetchAll();
        
        // Get students in class
        $students_stmt = $pdo->prepare("
            SELECT s.id, u.name, s.roll_number 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.class_id = ? AND s.status = 'active' 
            ORDER BY s.roll_number
        ");
        $students_stmt->execute([$selected_class]);
        $students = $students_stmt->fetchAll();
        
        // Get all results for these students and subjects
        $student_ids = array_column($students, 'id');
        if (!empty($student_ids)) {
            $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
            $subject_names = array_column($subjects, 'subject_name');
            $subject_placeholders = str_repeat('?,', count($subject_names) - 1) . '?';
            
            $results_stmt = $pdo->prepare("
                SELECT er.*, es.total_marks, es.passing_marks 
                FROM exam_results er 
                LEFT JOIN exam_subjects es ON er.exam_id = es.exam_id AND er.subject_name = es.subject_name
                WHERE er.student_id IN ($placeholders) 
                AND er.exam_id = ? 
                AND er.subject_name IN ($subject_placeholders)
            ");
            $params = array_merge($student_ids, [$selected_exam], $subject_names);
            $results_stmt->execute($params);
            $all_results = $results_stmt->fetchAll();
            
            // Organize results by student
            foreach ($students as $student) {
                $student_results[$student['id']] = [
                    'info' => $student,
                    'subjects' => [],
                    'total' => 0,
                    'percentage' => 0,
                    'grade' => 'F',
                    'status' => 'Fail'
                ];
            }
            
            foreach ($all_results as $result) {
                $student_results[$result['student_id']]['subjects'][$result['subject_name']] = $result;
                $student_results[$result['student_id']]['total'] += $result['marks_obtained'];
            }
            
            // Calculate statistics for each student
            $total_possible_marks = 0;
            foreach ($students as $student) {
                $student_total = $student_results[$student['id']]['total'];
                
                // Calculate total possible marks
                $possible_stmt = $pdo->prepare("
                    SELECT SUM(total_marks) as total_possible 
                    FROM exam_subjects 
                    WHERE exam_id = ? 
                    AND subject_name IN ($subject_placeholders)
                ");
                $possible_stmt->execute(array_merge([$selected_exam], $subject_names));
                $possible_data = $possible_stmt->fetch();
                $total_possible_marks = $possible_data['total_possible'] ?? 100 * count($subjects);
                
                $percentage = $total_possible_marks > 0 ? ($student_total / $total_possible_marks) * 100 : 0;
                $student_results[$student['id']]['percentage'] = $percentage;
                
                // Get grade
                $grade_stmt = $pdo->prepare("SELECT grade_name FROM grade_system WHERE ? BETWEEN min_marks AND max_marks");
                $grade_stmt->execute([$percentage]);
                $grade_data = $grade_stmt->fetch();
                $student_results[$student['id']]['grade'] = $grade_data['grade_name'] ?? 'F';
                
                // Check if passed all subjects
                $passed_all = true;
                foreach ($subjects as $subject) {
                    $mark = $student_results[$student['id']]['subjects'][$subject['subject_name']]['marks_obtained'] ?? 0;
                    $passing = $student_results[$student['id']]['subjects'][$subject['subject_name']]['passing_marks'] ?? 33;
                    if ($mark < $passing) {
                        $passed_all = false;
                        break;
                    }
                }
                $student_results[$student['id']]['status'] = $passed_all ? 'Pass' : 'Fail';
            }
            
            // Calculate class statistics
            $class_stats = [
                'total_students' => count($students),
                'passed_students' => 0,
                'failed_students' => 0,
                'average_percentage' => 0,
                'top_percentage' => 0,
                'subject_averages' => []
            ];
            
            $total_percentage = 0;
            foreach ($student_results as $result) {
                if ($result['status'] == 'Pass') {
                    $class_stats['passed_students']++;
                } else {
                    $class_stats['failed_students']++;
                }
                $total_percentage += $result['percentage'];
                $class_stats['top_percentage'] = max($class_stats['top_percentage'], $result['percentage']);
            }
            
            $class_stats['average_percentage'] = count($students) > 0 ? $total_percentage / count($students) : 0;
            
            // Calculate subject averages
            foreach ($subjects as $subject) {
                $subject_total = 0;
                $subject_count = 0;
                foreach ($student_results as $result) {
                    if (isset($result['subjects'][$subject['subject_name']])) {
                        $subject_total += $result['subjects'][$subject['subject_name']]['marks_obtained'];
                        $subject_count++;
                    }
                }
                $class_stats['subject_averages'][$subject['subject_name']] = $subject_count > 0 ? $subject_total / $subject_count : 0;
            }
        }
    }
} else {
    $exams = [];
    $classes = [];
    $student_results = [];
    $class_stats = [];
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">View Exam Results</h2>
        <small>View results of your students</small>
    </div>
    
    <?php if ($teacher_id): ?>
        
        <!-- Selection Form -->
        <form method="GET" action="" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="teacher_results">
            
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
            </div>
        </form>
        
        <?php if ($selected_exam && $selected_class): 
            // Get exam and class details
            $exam_stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
            $exam_stmt->execute([$selected_exam]);
            $exam_details = $exam_stmt->fetch();
            
            $class_stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
            $class_stmt->execute([$selected_class]);
            $class_details = $class_stmt->fetch();
            
            // Get subjects
            $subjects_stmt = $pdo->prepare("
                SELECT DISTINCT er.subject_name 
                FROM exam_results er 
                JOIN courses c ON (er.subject_name = c.course_name OR c.teacher_id = ?)
                WHERE er.exam_id = ? 
                AND c.teacher_id = ?
                ORDER BY er.subject_name
            ");
            $subjects_stmt->execute([$teacher_id, $selected_exam, $teacher_id]);
            $subjects = $subjects_stmt->fetchAll();
        ?>
            
            <!-- Class Statistics -->
            <div class="card" style="margin-bottom: 20px; background: #f8f9fa;">
                <div class="card-header">
                    <h3 class="card-title">Class Performance Summary</h3>
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
                            <label>Total Students</label>
                            <p><strong><?php echo $class_stats['total_students']; ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Passed</label>
                            <p><strong><?php echo $class_stats['passed_students']; ?></strong></p>
                        </div>
                        <div class="form-group">
                            <label>Failed</label>
                            <p><strong><?php echo $class_stats['failed_students']; ?></strong></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Average Percentage</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;">
                                    <div style="background: var(--success); height: 100%; width: <?php echo min($class_stats['average_percentage'], 100); ?>%;"></div>
                                </div>
                                <span><strong><?php echo number_format($class_stats['average_percentage'], 2); ?>%</strong></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Top Score</label>
                            <p><strong><?php echo number_format($class_stats['top_percentage'], 2); ?>%</strong></p>
                        </div>
                        <div class="form-group">
                            <label>Pass Percentage</label>
                            <p><strong>
                                <?php echo $class_stats['total_students'] > 0 ? 
                                    number_format(($class_stats['passed_students'] / $class_stats['total_students']) * 100, 2) : 0; ?>%
                            </strong></p>
                        </div>
                    </div>
                    
                    <!-- Subject-wise Averages -->
                    <?php if (!empty($class_stats['subject_averages'])): ?>
                    <div style="margin-top: 20px;">
                        <h4>Subject-wise Averages</h4>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                            <?php foreach ($class_stats['subject_averages'] as $subject_name => $average): 
                                // Get total marks for this subject
                                $total_stmt = $pdo->prepare("SELECT total_marks FROM exam_subjects WHERE exam_id = ? AND subject_name = ?");
                                $total_stmt->execute([$selected_exam, $subject_name]);
                                $subject_total = $total_stmt->fetchColumn() ?? 100;
                                
                                $percentage = ($average / $subject_total) * 100;
                            ?>
                                <div style="flex: 1; min-width: 150px; background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($subject_name); ?></div>
                                    <div style="font-weight: bold; margin: 5px 0;"><?php echo number_format($average, 2); ?>/<?php echo $subject_total; ?></div>
                                    <div style="background: #e9ecef; height: 6px; border-radius: 3px; overflow: hidden;">
                                        <div style="background: var(--info); height: 100%; width: <?php echo min($percentage, 100); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Detailed Results Table -->
            <?php if (!empty($student_results)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Student-wise Results</h3>
                    <button class="btn btn-primary btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="resultsTable">
                        <thead>
                            <tr>
                                <th>Roll No.</th>
                                <th>Student Name</th>
                                <?php foreach ($subjects as $subject): ?>
                                    <th><?php echo htmlspecialchars($subject['subject_name']); ?></th>
                                <?php endforeach; ?>
                                <th>Total</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_results as $student_id => $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['info']['roll_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($result['info']['name']); ?></td>
                                    
                                    <?php foreach ($subjects as $subject): 
                                        $subject_result = $result['subjects'][$subject['subject_name']] ?? null;
                                    ?>
                                        <td>
                                            <?php if ($subject_result): 
                                                $is_passed = $subject_result['marks_obtained'] >= $subject_result['passing_marks'];
                                            ?>
                                                <span style="color: <?php echo $is_passed ? 'green' : 'red'; ?>; font-weight: bold;">
                                                    <?php echo number_format($subject_result['marks_obtained'], 2); ?>
                                                </span>
                                                /<?php echo $subject_result['total_marks']; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <td><strong><?php echo number_format($result['total'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $result['percentage'] >= 50 ? 'success' : ($result['percentage'] >= 33 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($result['percentage'], 2); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $result['grade'] == 'F' ? 'danger' : 'success'; ?>">
                                            <?php echo $result['grade']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $result['status'] == 'Pass' ? 'success' : 'danger'; ?>">
                                            <?php echo $result['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <script>
            function exportToExcel() {
                var table = document.getElementById('resultsTable');
                var html = table.outerHTML;
                
                // Create a blob and download link
                var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'Exam_Results_<?php echo $class_details['class_name'] . '_' . $class_details['section'] . '_' . $exam_details['exam_name']; ?>.xls';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
            </script>
            
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-clipboard-list fa-3x" style="margin-bottom: 20px;"></i>
                    <p>No results found for this class.</p>
                    <p>Marks may not have been entered yet.</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($selected_exam && !$selected_class): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-school fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select a class to view results.</p>
            </div>
        <?php elseif (!$selected_exam): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-file-alt fa-3x" style="margin-bottom: 20px;"></i>
                <p>Please select an exam to view results.</p>
                <p>You have <?php echo count($exams); ?> exam(s) with results.</p>
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