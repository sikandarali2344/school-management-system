<?php
// Get stats for admin dashboard
$student_count = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
$teacher_count = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
$course_count = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$class_count = $pdo->query("SELECT COUNT(DISTINCT class) FROM students")->fetchColumn();
?>
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $student_count; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--teacher);">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $teacher_count; ?></h3>
            <p>Total Teachers</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success);">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $course_count; ?></h3>
            <p>Courses</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning);">
            <i class="fas fa-school"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $class_count; ?></h3>
            <p>Classes</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Students</h2>
        <a href="?page=students" class="btn btn-primary">View All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT s.*, u.name, u.email FROM students s JOIN users u ON s.user_id = u.id ORDER BY s.id DESC LIMIT 5");
                while ($student = $stmt->fetch()) {
                    echo "<tr>
                            <td>#STU-{$student['id']}</td>
                            <td>{$student['name']}</td>
                            <td>{$student['class']}</td>
                            <td>{$student['parent_contact']}</td>
                            <td><span class='badge badge-success'>{$student['status']}</span></td>
                            <td>
                                <a href='?page=students&edit={$student['id']}' class='btn btn-primary btn-sm'>Edit</a>
                                <a href='?action=delete_student&id={$student['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                            </td>
                        </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>