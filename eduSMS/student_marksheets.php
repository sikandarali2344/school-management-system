<?php
// student_marksheets.php
// Get current student ID
$student_id = null;
$student_stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$student_stmt->execute([$current_user['id']]);
$student_data = $student_stmt->fetch();

if ($student_data) {
    $student_id = $student_data['id'];
    
    // Get all marksheets for this student
    $marksheets_stmt = $pdo->prepare("
        SELECT m.*, e.exam_name, e.exam_type, e.academic_year, 
               u.name as uploaded_by_name, m.upload_date
        FROM marksheets m
        JOIN exams e ON m.exam_id = e.id
        LEFT JOIN users u ON m.uploaded_by = u.id
        WHERE m.student_id = ? 
        ORDER BY m.upload_date DESC
    ");
    $marksheets_stmt->execute([$student_id]);
    $marksheets = $marksheets_stmt->fetchAll();
    
    // Get all exams student has appeared in
    $exams_stmt = $pdo->prepare("
        SELECT DISTINCT e.* 
        FROM exams e 
        JOIN exam_results er ON e.id = er.exam_id 
        WHERE er.student_id = ? 
        ORDER BY e.start_date DESC
    ");
    $exams_stmt->execute([$student_id]);
    $available_exams = $exams_stmt->fetchAll();
} else {
    $marksheets = [];
    $available_exams = [];
}

// Handle marksheet download request
if (isset($_GET['download_marksheet'])) {
    $marksheet_id = $_GET['download_marksheet'];
    
    $stmt = $pdo->prepare("SELECT file_path, file_name FROM marksheets WHERE id = ? AND student_id = ?");
    $stmt->execute([$marksheet_id, $student_id]);
    $file_data = $stmt->fetch();
    
    if ($file_data && file_exists($file_data['file_path'])) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_data['file_name'] . '"');
        readfile($file_data['file_path']);
        exit;
    } else {
        $error = "Marksheet file not found!";
    }
}
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Marksheets</h2>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($student_id): ?>
        
        <!-- Available Marksheets -->
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h3 class="card-title">Available Marksheets</h3>
                <small>Download your marksheets in PDF format</small>
            </div>
            
            <?php if (count($marksheets) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Exam Name</th>
                                <th>Type</th>
                                <th>Academic Year</th>
                                <th>Upload Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marksheets as $marksheet): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($marksheet['exam_name']); ?></strong><br>
                                        <small>Uploaded by: <?php echo htmlspecialchars($marksheet['uploaded_by_name'] ?? 'System'); ?></small>
                                    </td>
                                    <td><?php echo ucfirst(str_replace('-', ' ', $marksheet['exam_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($marksheet['academic_year']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($marksheet['upload_date'])); ?></td>
                                    <td>
                                        <?php
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_badge[$marksheet['status']]; ?>">
                                            <?php echo ucfirst($marksheet['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($marksheet['status'] == 'approved' && !empty($marksheet['file_path'])): ?>
                                            <a href="?page=my_marksheets&download_marksheet=<?php echo $marksheet['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        <?php elseif ($marksheet['status'] == 'pending'): ?>
                                            <span class="btn btn-warning btn-sm" disabled>
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-ban"></i> Unavailable
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($marksheet['file_path']) && $marksheet['status'] == 'approved'): ?>
                                            <button class="btn btn-info btn-sm" onclick="previewMarksheet(<?php echo $marksheet['id']; ?>)">
                                                <i class="fas fa-eye"></i> Preview
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-file-pdf fa-3x" style="margin-bottom: 20px;"></i>
                    <p>No marksheets available for download.</p>
                    <p>Marksheets will appear here once uploaded and approved by administration.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Request New Marksheet (Optional) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Marksheet Status & Information</h3>
            </div>
            <div style="padding: 20px;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Exams Appeared In</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 5px; min-height: 100px;">
                            <?php if (count($available_exams) > 0): ?>
                                <ul>
                                    <?php foreach ($available_exams as $exam): 
                                        // Check if marksheet exists for this exam
                                        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM marksheets WHERE student_id = ? AND exam_id = ?");
                                        $check_stmt->execute([$student_id, $exam['id']]);
                                        $has_marksheet = $check_stmt->fetchColumn() > 0;
                                    ?>
                                        <li style="margin-bottom: 8px;">
                                            <strong><?php echo htmlspecialchars($exam['exam_name']); ?></strong>
                                            (<?php echo $exam['academic_year']; ?>)
                                            <?php if ($has_marksheet): ?>
                                                <span class="badge badge-success" style="margin-left: 10px;">Marksheet Available</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning" style="margin-left: 10px;">Processing</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p style="color: #666; text-align: center; padding: 20px;">
                                    No exam records found.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Instructions</label>
                    <div style="padding: 15px; background: #e8f5e9; border-radius: 5px; border-left: 4px solid #4caf50;">
                        <ul style="margin: 0; padding-left: 20px;">
                            <li>Marksheets are usually uploaded within 7-10 days after exam results declaration</li>
                            <li>Only approved marksheets can be downloaded</li>
                            <li>Downloaded marksheets are official documents</li>
                            <li>For any issues, contact the examination department</li>
                            <li>Keep downloaded marksheets safely for future reference</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Marksheet Preview Modal -->
        <div id="marksheetPreviewModal" class="modal">
            <div class="modal-content" style="max-width: 90%; height: 90%;">
                <span class="close" onclick="closeMarksheetPreview()">&times;</span>
                <h2>Marksheet Preview</h2>
                <div id="previewContent" style="height: calc(100% - 100px);">
                    <iframe id="marksheetFrame" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
            </div>
        </div>
        
        <script>
        function previewMarksheet(marksheetId) {
            document.getElementById('marksheetPreviewModal').style.display = 'block';
            document.getElementById('marksheetFrame').src = '?page=my_marksheets&download_marksheet=' + marksheetId;
        }
        
        function closeMarksheetPreview() {
            document.getElementById('marksheetPreviewModal').style.display = 'none';
            document.getElementById('marksheetFrame').src = '';
        }
        </script>
        
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-user-graduate fa-3x" style="margin-bottom: 20px;"></i>
            <p>Student profile not found.</p>
            <p>Please contact administrator if this is an error.</p>
        </div>
    <?php endif; ?>
</div>