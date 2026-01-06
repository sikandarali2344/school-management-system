<?php
// admin_exams.php - Exam Management for Admin

// Handle exam creation
if (isset($_POST['add_exam'])) {
    $exam_name = $_POST['exam_name'];
    $exam_type = $_POST['exam_type'];
    $academic_year = $_POST['academic_year'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $description = $_POST['description'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO exams (exam_name, exam_type, academic_year, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$exam_name, $exam_type, $academic_year, $start_date, $end_date, $description]);
        $exam_id = $pdo->lastInsertId();
        
        // Add subjects for this exam
        if (isset($_POST['subject_name']) && is_array($_POST['subject_name'])) {
            for ($i = 0; $i < count($_POST['subject_name']); $i++) {
                if (!empty($_POST['subject_name'][$i])) {
                    $stmt = $pdo->prepare("INSERT INTO exam_subjects (exam_id, subject_name, total_marks, passing_marks, exam_date, exam_time) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $exam_id,
                        $_POST['subject_name'][$i],
                        $_POST['total_marks'][$i],
                        $_POST['passing_marks'][$i],
                        $_POST['exam_date'][$i],
                        $_POST['exam_time'][$i]
                    ]);
                }
            }
        }
        
        $success = "Exam created successfully!";
    } catch(PDOException $e) {
        $error = "Error creating exam: " . $e->getMessage();
    }
}

// Handle exam deletion
if (isset($_GET['delete_exam'])) {
    $exam_id = $_GET['delete_exam'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->execute([$exam_id]);
        $success = "Exam deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting exam: " . $e->getMessage();
    }
}

// Get all exams
$stmt = $pdo->query("SELECT * FROM exams ORDER BY start_date DESC");
$exams = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Exam Management</h2>
        <button class="btn btn-primary" onclick="openAddExamModal()">
            <i class="fas fa-plus"></i> Create New Exam
        </button>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Exam Name</th>
                    <th>Type</th>
                    <th>Academic Year</th>
                    <th>Dates</th>
                    <th>Subjects</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($exams) > 0): ?>
                    <?php foreach ($exams as $exam): 
                        // Get subjects count for this exam
                        $sub_stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_subjects WHERE exam_id = ?");
                        $sub_stmt->execute([$exam['id']]);
                        $subject_count = $sub_stmt->fetchColumn();
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($exam['description']); ?></small>
                            </td>
                            <td><?php echo ucfirst(str_replace('-', ' ', $exam['exam_type'])); ?></td>
                            <td><?php echo htmlspecialchars($exam['academic_year']); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($exam['start_date'])); ?> -<br>
                                <?php echo date('M d, Y', strtotime($exam['end_date'])); ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $subject_count; ?> Subjects</span>
                            </td>
                            <td>
                                <?php
                                $status_class = [
                                    'upcoming' => 'warning',
                                    'ongoing' => 'success', 
                                    'completed' => 'info',
                                    'cancelled' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $status_class[$exam['status']]; ?>">
                                    <?php echo ucfirst($exam['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?page=exam_details&id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn btn-warning btn-sm" onclick="editExam(<?php echo $exam['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?page=exams&delete_exam=<?php echo $exam['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this exam? All related data will be deleted.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            No exams found. <a href="javascript:void(0)" onclick="openAddExamModal()">Create first exam</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Exam Modal -->
<div id="addExamModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close" onclick="closeAddExamModal()">&times;</span>
        <h2>Create New Exam</h2>
        <form method="POST" id="examForm">
            <div class="form-row">
                <div class="form-group">
                    <label>Exam Name *</label>
                    <input type="text" name="exam_name" class="form-control" placeholder="e.g., Annual Examination 2024" required>
                </div>
                <div class="form-group">
                    <label>Exam Type *</label>
                    <select name="exam_type" class="form-control" required>
                        <option value="mid-term">Mid-Term</option>
                        <option value="final">Final Examination</option>
                        <option value="unit-test">Unit Test</option>
                        <option value="quiz">Quiz</option>
                        <option value="assignment">Assignment</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="e.g., 2024-2025" required>
                </div>
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Exam description..."></textarea>
            </div>
            
            <h3 style="margin: 20px 0 10px 0;">Exam Subjects</h3>
            <div id="subjectsContainer">
                <div class="subject-row form-row">
                    <div class="form-group">
                        <label>Subject Name</label>
                        <input type="text" name="subject_name[]" class="form-control" placeholder="e.g., Mathematics">
                    </div>
                    <div class="form-group">
                        <label>Total Marks</label>
                        <input type="number" name="total_marks[]" class="form-control" placeholder="100">
                    </div>
                    <div class="form-group">
                        <label>Passing Marks</label>
                        <input type="number" name="passing_marks[]" class="form-control" placeholder="33">
                    </div>
                    <div class="form-group">
                        <label>Exam Date</label>
                        <input type="date" name="exam_date[]" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Exam Time</label>
                        <input type="time" name="exam_time[]" class="form-control">
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary btn-sm" onclick="addSubjectRow()">
                <i class="fas fa-plus"></i> Add Another Subject
            </button>
            
            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="submit" name="add_exam" class="btn btn-success">
                    <i class="fas fa-save"></i> Create Exam
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeAddExamModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Exam Modal Functions
function openAddExamModal() {
    document.getElementById('addExamModal').style.display = 'block';
    // Set default dates
    var today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="start_date"]').value = today;
    document.querySelector('input[name="end_date"]').value = today;
}

function closeAddExamModal() {
    document.getElementById('addExamModal').style.display = 'none';
}

function addSubjectRow() {
    var container = document.getElementById('subjectsContainer');
    var newRow = document.createElement('div');
    newRow.className = 'subject-row form-row';
    newRow.innerHTML = `
        <div class="form-group">
            <label>Subject Name</label>
            <input type="text" name="subject_name[]" class="form-control" placeholder="e.g., Mathematics">
        </div>
        <div class="form-group">
            <label>Total Marks</label>
            <input type="number" name="total_marks[]" class="form-control" placeholder="100">
        </div>
        <div class="form-group">
            <label>Passing Marks</label>
            <input type="number" name="passing_marks[]" class="form-control" placeholder="33">
        </div>
        <div class="form-group">
            <label>Exam Date</label>
            <input type="date" name="exam_date[]" class="form-control">
        </div>
        <div class="form-group">
            <label>Exam Time</label>
            <input type="time" name="exam_time[]" class="form-control">
        </div>
    `;
    container.appendChild(newRow);
}

function editExam(examId) {
    alert('Edit feature for exam ID: ' + examId + ' will be implemented soon!');
}

// Close modal when clicking outside
window.onclick = function(event) {
    var examModal = document.getElementById('addExamModal');
    if (event.target == examModal) {
        closeAddExamModal();
    }
}
</script>