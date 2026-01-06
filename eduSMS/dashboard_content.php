<?php
// Get statistics
try {
    // Total Students
    $students_stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $total_students = $students_stmt->fetchColumn();
    
    // Total Teachers
    $teachers_stmt = $pdo->query("SELECT COUNT(*) as total FROM teachers WHERE status = 'active'");
    $total_teachers = $teachers_stmt->fetchColumn();
    
    // Total Classes
    $classes_stmt = $pdo->query("SELECT COUNT(*) as total FROM classes WHERE status = 'active'");
    $total_classes = $classes_stmt->fetchColumn();
    
    // Total Courses
    $courses_stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
    $total_courses = $courses_stmt->fetchColumn();
    
} catch (PDOException $e) {
    $total_students = $total_teachers = $total_classes = $total_courses = 0;
}
?>

<!-- Statistics Cards -->
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--student);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_students; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--teacher);">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_teachers; ?></h3>
            <p>Total Teachers</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary);">
            <i class="fas fa-school"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_classes; ?></h3>
            <p>Total Classes</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--info);">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_courses; ?></h3>
            <p>Total Courses</p>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Activities</h2>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>System Login</td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                    <td><span class="badge badge-success">Success</span></td>
                </tr>
                <tr>
                    <td>Dashboard Access</td>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                    <td><span class="badge badge-success">Active</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Quick Actions</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 20px;">
        <?php if ($current_user['role'] == 'admin'): ?>
            <a href="?page=students" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-user-graduate"></i><br>Manage Students
            </a>
            <a href="?page=teachers" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-chalkboard-teacher"></i><br>Manage Teachers
            </a>
            <a href="?page=classes" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-school"></i><br>Manage Classes
            </a>
            <a href="?page=courses" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-book"></i><br>Manage Courses
            </a>
        <?php elseif ($current_user['role'] == 'teacher'): ?>
            <a href="?page=attendance" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-clipboard-check"></i><br>Take Attendance
            </a>
            <a href="?page=grades" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-award"></i><br>Manage Grades
            </a>
        <?php else: ?>
            <a href="?page=grades" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-award"></i><br>View Grades
            </a>
            <a href="?page=attendance" class="btn btn-primary" style="text-align: center;">
                <i class="fas fa-clipboard-check"></i><br>View Attendance
            </a>
        <?php endif; ?>
    </div>
</div>