<div class="card">
    <div class="card-header">
        <h2 class="card-title">Student Management</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addStudentForm').style.display='block'">Add New Student</button>
    </div>
    
    <!-- Add Student Form -->
    <div id="addStudentForm" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3>Add New Student</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Class</label>
                    <input type="text" name="class" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Parent Name</label>
                    <input type="text" name="parent_name" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Parent Contact</label>
                <input type="text" name="parent_contact" class="form-control" required>
            </div>
            <button type="submit" name="add_student" class="btn btn-success">Add Student</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addStudentForm').style.display='none'">Cancel</button>
        </form>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Parent Name</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT s.*, u.name, u.email FROM students s JOIN users u ON s.user_id = u.id");
                while ($student = $stmt->fetch()) {
                    echo "<tr>
                            <td>#STU-{$student['id']}</td>
                            <td>{$student['name']}</td>
                            <td>{$student['class']}</td>
                            <td>{$student['parent_name']}</td>
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