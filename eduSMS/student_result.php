<?php
// student_results.php
// Get current student ID
$student_id = null;
$student_stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student_data = $student_stmt->fetch();

if ($student_data) {
    $student_id = $student_data['id'];
    
    // Get student's class
    $class_stmt = $pdo->prepare("SELECT class_id FROM students WHERE id = ?");
    $class_stmt->execute([$student_id]);
    $class_data = $class_stmt->fetch();
    $class_id = $class_data['class_id'] ?? null;
    
    // Get all exams for this student
    $exams_stmt = $pdo->prepare("
        SELECT DISTINCT e.* 
        FROM exams e 
        JOIN exam_results er ON e.id = er.exam_id 
        WHERE er.student_id = ? 
        ORDER BY e.start_date DESC
    ");
    $exams_stmt->execute([$student_id]);
    $exams = $exams_stmt->fetchAll();
    
    $selected_exam = $_GET['exam_id'] ?? ($exams[0]['id'] ?? '');
    
    // Get results for selected exam
    $results = [];
    $exam_stats = [];
    if ($selected_exam) {
        $result_stmt = $pdo->prepare("
            SELECT er.*, es.total_marks, es.passing_marks 
            FROM exam_results er 
            LEFT JOIN exam_subjects es ON er.exam_id = es.exam_id AND er.subject_name = es.subject_name
            WHERE er.student_id = ? AND er.exam_id = ?
            ORDER BY er.subject_name
        ");
        $result_stmt->execute([$student_id, $selected_exam]);
        $results = $result_stmt->fetchAll();
        
        // Calculate statistics
        if (count($results) > 0) {
            $total_marks = 0;
            $obtained_marks = 0;
            $subjects_passed = 0;
            
            foreach ($results as $result) {
                $total_marks += $result['total_marks'];
                $obtained_marks += $result['marks_obtained'];
                if ($result['marks_obtained'] >= $result['passing_marks']) {
                    $subjects_passed++;
                }
            }
            
            $percentage = ($total_marks > 0) ? ($obtained_marks / $total_marks) * 100 : 0;
            $overall_grade = 'F';
            
            $grade_stmt = $pdo->prepare("SELECT grade_name FROM grade_system WHERE ? BETWEEN min_marks AND max_marks");
            $grade_stmt->execute([$percentage]);
            $grade_data = $grade_stmt->fetch();
            $overall_grade = $grade_data['grade_name'] ?? 'F';
            
            $exam_stats = [
                'total_subjects' => count($results),
                'subjects_passed' => $subjects_passed,
                'total_marks' => $total_marks,
                'obtained_marks' => $obtained_marks,
                'percentage' => $percentage,
                'overall_grade' => $overall_grade,
                'status' => ($subjects_passed == count($results)) ? 'Pass' : 'Fail'
            ];
        }
    }
} else {
    $exams = [];
    $results = [];
    $exam_stats = [];
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Exam Results</h2>
    </div>
    
    <?php if ($student_id): ?>
        <!-- Exam Selector -->
        <div style="margin-bottom: 20px;">
            <form method="GET" action="">
                <input type="hidden" name="page" value="my_results">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Exam</label>
                        <select name="exam_id" class="form-control" onchange="this.form.submit()">
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo $selected_exam == $exam['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['exam_name'] . ' (' . date('M Y', strtotime($exam['start_date'])) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($selected_exam && count($results) > 0): 
            $exam_stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
            $exam_stmt->execute([$selected_exam]);
            $exam_details = $exam_stmt->fetch();
        ?>
            <!-- Exam Overview -->
            <div class="card" style="margin-bottom: 20px; background: #f8f9fa;">
                <div class="card-header">
                    <h3 class="card-title"><?php echo htmlspecialchars($exam_details['exam_name']); ?> - Result Summary</h3>
                </div>
                <div class="form-row" style="padding: 20px;">
                    <div class="form-group">
                        <label>Exam Type</label>
                        <p><strong><?php echo ucfirst(str_replace('-', ' ', $exam_details['exam_type'])); ?></strong></p>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <p><strong><?php echo htmlspecialchars($exam_details['academic_year']); ?></strong></p>
                    </div>
                    <div class="form-group">
                        <label>Exam Period</label>
                        <p><strong><?php echo date('M d, Y', strtotime($exam_details['start_date'])); ?> to <?php echo date('M d, Y', strtotime($exam_details['end_date'])); ?></strong></p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-container" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--success);">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($exam_stats['percentage'], 2); ?>%</h3>
                        <p>Overall Percentage</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary);">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $exam_stats['overall_grade']; ?></h3>
                        <p>Overall Grade</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: <?php echo $exam_stats['status'] == 'Pass' ? 'var(--success)' : 'var(--danger)'; ?>;">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $exam_stats['status']; ?></h3>
                        <p>Result Status</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--info);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $exam_stats['subjects_passed']; ?>/<?php echo $exam_stats['total_subjects']; ?></h3>
                        <p>Subjects Passed</p>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Results Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Subject-wise Marks</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Marks Obtained</th>
                                <th>Total Marks</th>
                                <th>Passing Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): 
                                $subject_percentage = ($result['marks_obtained'] / $result['total_marks']) * 100;
                                $is_passed = $result['marks_obtained'] >= $result['passing_marks'];
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($result['subject_name']); ?></strong></td>
                                    <td><?php echo number_format($result['marks_obtained'], 2); ?></td>
                                    <td><?php echo number_format($result['total_marks'], 2); ?></td>
                                    <td><?php echo number_format($result['passing_marks'], 2); ?></td>
                                    <td><?php echo number_format($subject_percentage, 2); ?>%</td>
                                    <td>
                                        <span class="badge badge-<?php echo $result['grade'] == 'F' ? 'danger' : 'success'; ?>">
                                            <?php echo $result['grade']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $is_passed ? 'success' : 'danger'; ?>">
                                            <?php echo $is_passed ? 'Pass' : 'Fail'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Total Row -->
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td><strong>TOTAL</strong></td>
                                <td><?php echo number_format($exam_stats['obtained_marks'], 2); ?></td>
                                <td><?php echo number_format($exam_stats['total_marks'], 2); ?></td>
                                <td>-</td>
                                <td><?php echo number_format($exam_stats['percentage'], 2); ?>%</td>
                                <td>
                                    <span class="badge badge-<?php echo $exam_stats['overall_grade'] == 'F' ? 'danger' : 'success'; ?>">
                                        <?php echo $exam_stats['overall_grade']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $exam_stats['status'] == 'Pass' ? 'success' : 'danger'; ?>">
                                        <?php echo $exam_stats['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Performance Chart (Simple) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Performance Chart</h3>
                </div>
                <div style="padding: 20px;">
                    <div style="height: 300px; display: flex; align-items: flex-end; gap: 10px; padding: 20px; border: 1px solid #e9ecef; border-radius: 5px;">
                        <?php foreach ($results as $result): 
                            $height = min(($result['marks_obtained'] / $result['total_marks']) * 100, 100);
                        ?>
                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                                <div style="width: 80%; background: <?php echo $result['grade'] == 'F' ? 'var(--danger)' : 'var(--success)'; ?>; 
                                            height: <?php echo $height; ?>%; border-radius: 5px 5px 0 0;">
                                </div>
                                <div style="margin-top: 10px; font-size: 12px; text-align: center;">
                                    <div><?php echo substr($result['subject_name'], 0, 10); ?>...</div>
                                    <div><strong><?php echo number_format($result['marks_obtained'], 1); ?></strong></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($selected_exam): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-clipboard-list fa-3x" style="margin-bottom: 20px;"></i>
                <p>No results found for this exam.</p>
                <p>Results are not yet published or you didn't appear in this exam.</p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-award fa-3x" style="margin-bottom: 20px;"></i>
                <p>Select an exam to view your results.</p>
                <p>You have <?php echo count($exams); ?> exam(s) with results.</p>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-user-graduate fa-3x" style="margin-bottom: 20px;"></i>
            <p>Student profile not found.</p>
            <p>Please contact administrator if this is an error.</p>
        </div>
    <?php endif; ?>
</div>