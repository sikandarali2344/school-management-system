<?php
session_start();

$host = 'localhost';
$dbname = 'edumanage';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get school settings
$settings_stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $settings_stmt->fetch() ?: ['school_name' => 'EduManage', 'currency' => '₹'];

// Handle settings update
if (isset($_POST['update_settings'])) {
    $school_name = $_POST['school_name'];
    $currency = $_POST['currency'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (id, school_name, currency) VALUES (1, ?, ?) 
                              ON DUPLICATE KEY UPDATE school_name = ?, currency = ?");
        $stmt->execute([$school_name, $currency, $school_name, $currency]);
        $settings['school_name'] = $school_name;
        $settings['currency'] = $currency;
        $success = "Settings updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// ========== STUDENT/TEACHER/COURSE/CLASS/ATTENDANCE MANAGEMENT CODE START ==========

// Handle student actions
if (isset($_POST['add_student'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $class_id = $_POST['class_id'];
    $parent_name = $_POST['parent_name'];
    $parent_contact = $_POST['parent_contact'];
    $address = $_POST['address'];
    
    try {
        // Generate username from name
        $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
        
        // First create user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'student', ?, ?)");
        $stmt->execute([$username, 'password123', $name, $email]);
        $user_id = $pdo->lastInsertId();
        
        // Then create student record
        $stmt = $pdo->prepare("INSERT INTO students (user_id, class_id, parent_name, parent_contact, email, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $class_id, $parent_name, $parent_contact, $email, $phone, $address]);
        
        $success = "Student added successfully! Username: " . $username;
    } catch(PDOException $e) {
        $error = "Error adding student: " . $e->getMessage();
    }
}

// Handle student deletion
if (isset($_GET['delete_student'])) {
    $student_id = $_GET['delete_student'];
    
    try {
        // Get user_id first
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Delete from students table
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            
            // Delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$student['user_id']]);
            
            $success = "Student deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error deleting student: " . $e->getMessage();
    }
}

// Handle teacher actions
if (isset($_POST['add_teacher'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $subject = $_POST['subject'];
    $classes = $_POST['classes'];
    $qualification = $_POST['qualification'];
    
    try {
        // Generate username from name
        $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
        
        // First create user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'teacher', ?, ?)");
        $stmt->execute([$username, 'password123', $name, $email]);
        $user_id = $pdo->lastInsertId();
        
        // Then create teacher record
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, subject, classes, qualification, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $subject, $classes, $qualification, $phone]);
        
        $success = "Teacher added successfully! Username: " . $username;
    } catch(PDOException $e) {
        $error = "Error adding teacher: " . $e->getMessage();
    }
}

// Handle teacher deletion
if (isset($_GET['delete_teacher'])) {
    $teacher_id = $_GET['delete_teacher'];
    
    try {
        // Get user_id first
        $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
        $stmt->execute([$teacher_id]);
        $teacher = $stmt->fetch();
        
        if ($teacher) {
            // Delete from teachers table
            $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            
            // Delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$teacher['user_id']]);
            
            $success = "Teacher deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error deleting teacher: " . $e->getMessage();
    }
}

// Handle course actions
if (isset($_POST['add_course'])) {
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $department = $_POST['department'];
    $credits = $_POST['credits'];
    $teacher_id = $_POST['teacher_id'];
    $description = $_POST['description'];
    $duration = $_POST['duration'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, department, credits, teacher_id, description, duration) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course_code, $course_name, $department, $credits, $teacher_id, $description, $duration]);
        
        $success = "Course added successfully!";
    } catch(PDOException $e) {
        $error = "Error adding course: " . $e->getMessage();
    }
}

// Handle course deletion
if (isset($_GET['delete_course'])) {
    $course_id = $_GET['delete_course'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        
        $success = "Course deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting course: " . $e->getMessage();
    }
}

// Handle class actions
if (isset($_POST['add_class'])) {
    $class_name = $_POST['class_name'];
    $section = $_POST['section'];
    $class_teacher_id = $_POST['class_teacher_id'];
    $room_number = $_POST['room_number'];
    $capacity = $_POST['capacity'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO classes (class_name, section, class_teacher_id, room_number, capacity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$class_name, $section, $class_teacher_id, $room_number, $capacity]);
        
        $success = "Class added successfully!";
    } catch(PDOException $e) {
        $error = "Error adding class: " . $e->getMessage();
    }
}

// Handle class deletion
if (isset($_GET['delete_class'])) {
    $class_id = $_GET['delete_class'];
    
    try {
        // Check if class has students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $student_count = $stmt->fetchColumn();
        
        if ($student_count > 0) {
            $error = "Cannot delete class. There are students enrolled in this class.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $success = "Class deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error deleting class: " . $e->getMessage();
    }
}

// Handle attendance actions
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ========== ADD ATTENDANCE HANDLING HERE ==========
// Handle attendance actions (MUST be before session start and user check)
if (isset($_POST['take_attendance']) && isset($_SESSION['user_id'])) {
    $class_id = $_POST['class_id'];
    $course_id = $_POST['course_id'];
    $attendance_date = $_POST['attendance_date'];
    $recorded_by = $_SESSION['user_id'];
    
    try {
        // Delete existing attendance for same class, course and date
        $stmt = $pdo->prepare("DELETE FROM attendance_records WHERE class_id = ? AND course_id = ? AND attendance_date = ?");
        $stmt->execute([$class_id, $course_id, $attendance_date]);
        
        // Insert new attendance records
        foreach ($_POST['attendance'] as $student_id => $status) {
            $remarks = $_POST['remarks'][$student_id] ?? '';
            
            $stmt = $pdo->prepare("INSERT INTO attendance_records (student_id, class_id, course_id, attendance_date, status, remarks, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $class_id, $course_id, $attendance_date, $status, $remarks, $recorded_by]);
        }
        
        $success = "Attendance recorded successfully!";
    } catch(PDOException $e) {
        $error = "Error recording attendance: " . $e->getMessage();
    }
}
// ========== END ATTENDANCE HANDLING ==========

// Get school settings
$settings_stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $settings_stmt->fetch() ?: ['school_name' => 'EduManage', 'currency' => '₹'];

// Handle settings update
if (isset($_POST['update_settings'])) {
    // ... existing code ...
}



// Handle attendance deletion
if (isset($_GET['delete_attendance'])) {
    $attendance_id = $_GET['delete_attendance'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM attendance_records WHERE id = ?");
        $stmt->execute([$attendance_id]);
        
        $success = "Attendance record deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting attendance record: " . $e->getMessage();
    }

}


// ========== MANAGEMENT CODE END ==========

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Simple password check for demo
        if ($password === 'password123') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
            header('Location: index.php');
            exit();
        } else {
            $error = "Invalid password! Use: password123";
        }
    } else {
        $error = "User not found!";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get current user if logged in
$current_user = null;
if (isset($_SESSION['user_id'])) {
    $current_user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'name' => $_SESSION['name']
    ];
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['school_name']; ?> | School Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
:root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --danger: #f72585;
    --warning: #f8961e;
    --info: #4895ef;
    --light: #f8f9fa;
    --dark: #212529;
    --admin: #7209b7;
    --teacher: #3a0ca3;
    --student: #4361ee;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.container {
    width: 100%;
    max-width: 1200px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 90vh;
}

header {
    background: var(--primary);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.5rem;
    font-weight: 700;
}

.logo i {
    font-size: 2rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-weight: bold;
}

.main-content {
    display: flex;
    flex: 1;
}

.sidebar {
    width: 250px;
    background: var(--dark);
    color: white;
    padding: 20px 0;
}

.sidebar-menu {
    list-style: none;
}

.sidebar-item {
    padding: 15px 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.sidebar-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar-item.active {
    background: var(--primary);
    border-left: 5px solid var(--success);
}

.content {
    flex: 1;
    padding: 30px;
    background: #f5f7fb;
    overflow-y: auto;
}

.page-title {
    margin-bottom: 25px;
    color: var(--dark);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title h1 {
    font-size: 1.8rem;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-info h3 {
    font-size: 1.8rem;
    margin-bottom: 5px;
}

.stat-info p {
    color: #6c757d;
    font-size: 0.9rem;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    margin-bottom: 25px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.card-title {
    font-size: 1.3rem;
    color: var(--dark);
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

th {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark);
}

tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-success {
    background: #e8f5e9;
    color: #2e7d32;
}

.badge-warning {
    background: #fff3e0;
    color: #ef6c00;
}

.badge-danger {
    background: #ffebee;
    color: #c62828;
}

.badge-primary {
    background: #e3f2fd;
    color: #1565c0;
}

.badge-info {
    background: #e0f2f1;
    color: #00796b;
}

.btn {
    padding: 8px 15px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-info {
    background: var(--info);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: white;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.8rem;
}

/* Classical Login Page Styles */
.login-wrapper {
    position: relative;
    width: 100%;
    max-width: 450px;
    z-index: 10;
}

.bubble-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
}

.bubble {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    animation: float 15s infinite linear;
}

@keyframes float {
    0% {
        transform: translateY(100vh) translateX(0) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 0.3;
    }
    90% {
        opacity: 0.3;
    }
    100% {
        transform: translateY(-100px) translateX(100px) rotate(360deg);
        opacity: 0;
    }
}

.login-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 50px 40px;
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.5);
    max-width: 450px;
    width: 100%;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255, 255, 255, 0.8);
    position: relative;
    overflow: hidden;
}

.login-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary), var(--secondary), var(--primary));
    background-size: 200% 100%;
    animation: shimmer 3s infinite linear;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.login-container:hover {
    transform: translateY(-8px);
    box-shadow: 
        0 30px 60px rgba(0, 0, 0, 0.15),
        0 0 0 1px rgba(255, 255, 255, 0.6);
}

.login-header {
    text-align: center;
    margin-bottom: 40px;
}

.login-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    box-shadow: 
        0 10px 30px rgba(67, 97, 238, 0.4),
        inset 0 2px 0 rgba(255, 255, 255, 0.3);
    position: relative;
}

.login-logo::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    animation: ripple 2s ease-out infinite;
}

@keyframes ripple {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}

.login-header h1 {
    color: var(--dark);
    font-size: 2.3rem;
    margin-bottom: 8px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-header p {
    color: var(--text-light);
    font-size: 1rem;
    font-weight: 500;
}

.role-selector {
    display: flex;
    margin-bottom: 30px;
    border-radius: 15px;
    overflow: hidden;
    border: 2px solid rgba(67, 97, 238, 0.1);
    background: rgba(67, 97, 238, 0.05);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
}

.role-option {
    flex: 1;
    padding: 16px 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 600;
    color: var(--text-light);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    position: relative;
    overflow: hidden;
    background: transparent;
}

.role-option i {
    font-size: 1.3rem;
    transition: all 0.3s ease;
}

.role-option.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    transform: scale(1.02);
    box-shadow: 
        0 5px 15px rgba(67, 97, 238, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.role-option:not(.active):hover {
    color: var(--primary);
    background: rgba(67, 97, 238, 0.1);
    transform: translateY(-2px);
}

.role-option.active i {
    transform: scale(1.2);
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.form-group {
    margin-bottom: 25px;
}

.input-with-icon {
    position: relative;
    transition: all 0.3s ease;
}

.input-with-icon:focus-within {
    transform: translateY(-2px);
}

.input-with-icon i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
    transition: all 0.3s ease;
    z-index: 2;
    font-size: 1.1rem;
}

.form-control {
    width: 100%;
    padding: 16px 18px 16px 50px;
    border: 2px solid rgba(67, 97, 238, 0.2);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(255, 255, 255, 0.9);
    color: var(--dark);
    font-weight: 500;
    box-shadow: 
        inset 0 2px 4px rgba(0, 0, 0, 0.05),
        0 2px 0 rgba(255, 255, 255, 0.5);
}

.form-control::placeholder {
    color: var(--text-light);
    font-weight: 400;
}

.form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 
        0 0 0 4px rgba(67, 97, 238, 0.15),
        inset 0 2px 4px rgba(0, 0, 0, 0.05),
        0 2px 0 rgba(255, 255, 255, 0.5);
    background: white;
    transform: translateY(-2px);
}

.form-control:focus + i {
    color: var(--primary);
    transform: translateY(-50%) scale(1.1);
}

.login-btn {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    background-size: 200% 200%;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 
        0 10px 30px rgba(67, 97, 238, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.login-btn:hover {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
    background-size: 200% 200%;
    transform: translateY(-3px);
    box-shadow: 
        0 15px 40px rgba(67, 97, 238, 0.6),
        0 0 20px rgba(67, 97, 238, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
    letter-spacing: 1px;
}

.login-btn:active {
    transform: translateY(-1px);
    box-shadow: 
        0 8px 25px rgba(67, 97, 238, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

.login-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(255, 255, 255, 0.4), 
        transparent);
    transition: left 0.6s;
}

.login-btn:hover::before {
    left: 100%;
}

.login-footer {
    margin-top: 30px;
    text-align: center;
}

.login-footer p {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-bottom: 20px;
    font-weight: 500;
}

.login-footer strong {
    color: var(--primary);
    font-weight: 700;
}

.features {
    display: flex;
    justify-content: center;
    gap: 25px;
}

.feature {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    color: var(--text-light);
    font-size: 0.8rem;
    transition: all 0.3s ease;
    padding: 10px;
    border-radius: 10px;
    background: rgba(67, 97, 238, 0.05);
}

.feature i {
    font-size: 1.1rem;
    color: var(--primary);
    transition: all 0.3s ease;
}

.feature:hover {
    color: var(--primary);
    transform: translateY(-3px);
    background: rgba(67, 97, 238, 0.1);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
}

.feature:hover i {
    color: var(--secondary);
    transform: scale(1.2);
}

.error-message {
    background: linear-gradient(135deg, var(--danger), #ff6b9d);
    color: white;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 25px;
    border-left: 4px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 5px 20px rgba(247, 37, 133, 0.3);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: shake 0.6s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}

.error-message i {
    font-size: 1.3rem;
    flex-shrink: 0;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-8px); }
    75% { transform: translateX(8px); }
}

.success-message {
    background: linear-gradient(135deg, var(--success), #56d4e8);
    color: white;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 25px;
    border-left: 4px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 5px 20px rgba(76, 201, 240, 0.3);
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: 10px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    max-height: 90vh;
    overflow-y: auto;
}

.close {
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
}

.close:hover {
    color: #000;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        margin: 10% auto;
        padding: 20px;
    }
    
    .login-container {
        padding: 40px 30px;
        margin: 20px;
    }
    
    .login-header h1 {
        font-size: 2rem;
    }
    
    .role-option {
        padding: 14px 10px;
        font-size: 0.9rem;
    }
    
    .features {
        flex-direction: column;
        gap: 15px;
    }
}

@media (max-width: 480px) {
    .login-container {
        padding: 35px 25px;
    }
    
    .login-header h1 {
        font-size: 1.8rem;
    }
    
    .login-logo {
        width: 70px;
        height: 70px;
        font-size: 1.8rem;
    }
}
        
    </style>
</head>
<body>
    <?php if (!$current_user): ?>
    <!-- Login Page -->
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-graduation-cap"></i> <?php echo $settings['school_name']; ?></h1>
            <p>School Management System</p>
    </div>


        
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="role-selector">
                <div class="role-option active" data-role="student">Student</div>
                <div class="role-option" data-role="teacher">Teacher</div>
                <div class="role-option" data-role="admin">Admin</div>
                <input type="hidden" name="role" id="selectedRole" value="student">
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter password: password123" required>
            </div>
            
            <button type="submit" name="login" class="login-btn">Login</button>
            <p style="margin-top: 15px; font-size: 0.9rem; color: #6c757d;">
         
            </p>
        </form>
    </div>
    <?php else: ?>
    <!-- Dashboard -->
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span><?php echo $settings['school_name']; ?></span>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($current_user['name'], 0, 2); ?></div>
                <div>
                    <div><?php echo $current_user['name']; ?></div>
                    <div style="font-size: 0.8rem;"><?php echo ucfirst($current_user['role']); ?></div>
                </div>
                <a href="?logout=1" class="btn btn-danger" style="margin-left: 15px;">Logout</a>
            </div>
        </header>
        
        <div class="main-content">
            <nav class="sidebar">
                <ul class="sidebar-menu">
                    <?php
                    // Define menu items based on role
                    $menu_items = [];
                    
                    if ($current_user['role'] == 'admin') {
                        $menu_items = [
                            'dashboard' => ['Dashboard', 'fas fa-tachometer-alt'],
                            'students' => ['Students', 'fas fa-user-graduate'],
                            'teachers' => ['Teachers', 'fas fa-chalkboard-teacher'],
                            'classes' => ['Classes', 'fas fa-school'],
                            'courses' => ['Courses', 'fas fa-book'],
                            'timetable' => ['Timetable', 'fas fa-calendar-alt'],
                            'attendance' => ['Attendance', 'fas fa-clipboard-check'],
                                // NEW MENU ITEMS FOR EXAMS
        'exams' => ['Exams', 'fas fa-file-alt'],
        'results' => ['Results', 'fas fa-chart-line'],
        'marksheets' => ['Marksheets', 'fas fa-file-pdf'],
                            'fees' => ['Fees', 'fas fa-money-check'],
                          'certificates' => ['Certificates', 'fas fa-certificate'],

                            'settings' => ['Settings', 'fas fa-cogs']
                        ];
                    } elseif ($current_user['role'] == 'teacher') {
                        $menu_items = [
                            'dashboard' => ['Dashboard', 'fas fa-tachometer-alt'],
                            'students' => ['My Students', 'fas fa-user-graduate'],
                             'courses' => ['My Courses', 'fas fa-book'],
                            'timetable' => ['My Timetable', 'fas fa-calendar-alt'], 
                            'attendance' => ['Attendance', 'fas fa-clipboard-check'],
                            'grades' => ['Grades', 'fas fa-award'],
                             'teacher_exams' => ['Exam Marks', 'fas fa-edit'],
        'teacher_results' => ['View Results', 'fas fa-chart-bar']
                        ];
                    } else {
                        $menu_items = [
                            'dashboard' => ['Dashboard', 'fas fa-tachometer-alt'],
                            'courses' => ['My Courses', 'fas fa-book'],  
                            'timetable' => ['My Timetable', 'fas fa-calendar-alt'], 
                            'my_results' => ['My Results', 'fas fa-award'],
        'my_marksheets' => ['My Marksheets', 'fas fa-file-download'],
        
                            'grades' => ['My Grades', 'fas fa-award'],
                            'attendance' => ['My Attendance', 'fas fa-clipboard-check'],
                            'my_certificates' => [' My Certificates', 'fas fa-award'],

                        ];
                    }
                    
                    foreach ($menu_items as $key => $value) {
                        $active = ($page == $key) ? 'active' : '';
                        echo "<li class='sidebar-item $active'>
                                <a href='?page=$key' style='color: white; text-decoration: none; display: flex; align-items: center; gap: 15px;'>
                                    <i class='{$value[1]}'></i>
                                    <span>{$value[0]}</span>
                                </a>
                              </li>";
                    }
                    ?>
                </ul>
            </nav>
            
            <main class="content">
                <div class="page-title">
                    <h1><?php echo $menu_items[$page][0] ?? 'Dashboard'; ?></h1>
                    <div><?php echo date('l, F j, Y'); ?></div>
                </div>
                
                <div id="dashboardContent">
                    <?php
                    // Display content based on page
                    switch ($page) {
                        case 'dashboard':
                            include 'dashboard_content.php';
                            break;
                        case 'students':
                            // Get all students with class names
                            $stmt = $pdo->query("
                                SELECT s.*, u.username, u.name as full_name, c.class_name, c.section
                                FROM students s 
                                JOIN users u ON s.user_id = u.id 
                                JOIN classes c ON s.class_id = c.id
                                ORDER BY s.id DESC
                            ");
                            $students = $stmt->fetchAll();
                            
                            // Get all classes for dropdown
                            $classes_stmt = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY class_name, section");
                            $all_classes = $classes_stmt->fetchAll();
                            ?>
                            
                            <?php if (isset($success)): ?>
                                <div class="success-message"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($error)): ?>
                                <div class="error-message"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title">Student Management</h2>
                                    <button class="btn btn-primary" onclick="openAddStudentModal()">
                                        <i class="fas fa-plus"></i> Add New Student
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Class</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Parent</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($students) > 0): ?>
                                                <?php foreach ($students as $student): ?>
                                                    <tr>
                                                        <td>#STU-<?php echo $student['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['username']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                                        <td>
                                                            <small>
                                                                <?php echo htmlspecialchars($student['parent_name']); ?><br>
                                                                <?php echo htmlspecialchars($student['parent_contact']); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-primary btn-sm" onclick="editStudent(<?php echo $student['id']; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <a href="?page=students&delete_student=<?php echo $student['id']; ?>" 
                                                               class="btn btn-danger btn-sm" 
                                                               onclick="return confirm('Are you sure you want to delete this student?')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                                        No students found. <a href="javascript:void(0)" onclick="openAddStudentModal()">Add first student</a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Add Student Modal -->
                            <div id="addStudentModal" class="modal">
                                <div class="modal-content">
                                    <span class="close" onclick="closeAddStudentModal()">&times;</span>
                                    <h2>Add New Student</h2>
                                    <form method="POST">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Full Name *</label>
                                                <input type="text" name="name" class="form-control" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Email *</label>
                                                <input type="email" name="email" class="form-control" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Phone</label>
                                                <input type="tel" name="phone" class="form-control">
                                            </div>
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
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Parent Name *</label>
                                                <input type="text" name="parent_name" class="form-control" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Parent Contact *</label>
                                                <input type="text" name="parent_contact" class="form-control" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Address</label>
                                            <textarea name="address" class="form-control" rows="3"></textarea>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                                            <button type="submit" name="add_student" class="btn btn-success">
                                                <i class="fas fa-save"></i> Add Student
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="closeAddStudentModal()">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php
case 'teachers':
    // Get all teachers
    $stmt = $pdo->query("
        SELECT t.*, u.username, u.name as full_name, u.email 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.id DESC
    ");
    $teachers = $stmt->fetchAll();
    
    // Get all classes for dropdown (NEW CODE)
    $classes_stmt = $pdo->query("
        SELECT DISTINCT CONCAT(class_name, ' - ', section) as class_display, 
               id, class_name, section 
        FROM classes 
        WHERE status = 'active' 
        ORDER BY class_name, section
    ");
    $all_classes = $classes_stmt->fetchAll();
    ?>
    
    <?php if (isset($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Teacher Management</h2>
            <button class="btn btn-primary" onclick="openAddTeacherModal()">
                <i class="fas fa-plus"></i> Add New Teacher
            </button>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Subject</th>
                        <th>Classes</th>
                        <th>Email</th>
                        <th>Qualification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($teachers) > 0): ?>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td>#TCH-<?php echo $teacher['id']; ?></td>
                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['classes']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['qualification']); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editTeacher(<?php echo $teacher['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?page=teachers&delete_teacher=<?php echo $teacher['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this teacher?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                No teachers found. <a href="javascript:void(0)" onclick="openAddTeacherModal()">Add first teacher</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div id="addTeacherModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddTeacherModal()">&times;</span>
            <h2>Add New Teacher</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g., Mathematics" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Classes *</label>
                        <!-- CHANGED: Text input to dropdown -->
                        <select name="classes[]" class="form-control" multiple required style="height: 100px;">
                            <option value="">Select Classes</option>
                            <?php foreach ($all_classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['class_display']); ?>">
                                    <?php echo htmlspecialchars($class['class_display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl to select multiple classes</small>
                    </div>
                    <div class="form-group">
                        <label>Qualification</label>
                        <input type="text" name="qualification" class="form-control" placeholder="e.g., M.Sc, B.Ed">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_teacher" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Teacher
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddTeacherModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
    break;
                        case 'classes':
                            // Get all classes with teacher names and student counts
                            $stmt = $pdo->query("
                                SELECT c.*, u.name as teacher_name, 
                                       (SELECT COUNT(*) FROM students WHERE class_id = c.id) as student_count
                                FROM classes c 
                                LEFT JOIN teachers t ON c.class_teacher_id = t.id 
                                LEFT JOIN users u ON t.user_id = u.id 
                                ORDER BY c.class_name, c.section
                            ");
                            $classes = $stmt->fetchAll();
                            
                            // Get all teachers for dropdown
                            $teachers_stmt = $pdo->query("
                                SELECT t.id, u.name 
                                FROM teachers t 
                                JOIN users u ON t.user_id = u.id 
                                WHERE t.status = 'active'
                            ");
                            $teachers = $teachers_stmt->fetchAll();
                            ?>
                            
                            <?php if (isset($success)): ?>
                                <div class="success-message"><?php echo $success; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($error)): ?>
                                <div class="error-message"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title">Class Management</h2>
                                    <button class="btn btn-primary" onclick="openAddClassModal()">
                                        <i class="fas fa-plus"></i> Add New Class
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Class Name</th>
                                                <th>Section</th>
                                                <th>Class Teacher</th>
                                                <th>Room</th>
                                                <th>Capacity</th>
                                                <th>Students</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($classes) > 0): ?>
                                                <?php foreach ($classes as $class): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($class['section']); ?></td>
                                                        <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                                        <td><?php echo htmlspecialchars($class['room_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($class['capacity']); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $class['student_count'] > 0 ? 'success' : 'warning'; ?>">
                                                                <?php echo $class['student_count']; ?> students
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $class['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                                <?php echo ucfirst($class['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-primary btn-sm" onclick="editClass(<?php echo $class['id']; ?>)">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <a href="?page=classes&delete_class=<?php echo $class['id']; ?>" 
                                                               class="btn btn-danger btn-sm" 
                                                               onclick="return confirm('Are you sure you want to delete this class?')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                                        No classes found. <a href="javascript:void(0)" onclick="openAddClassModal()">Add first class</a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Add Class Modal -->
                            <div id="addClassModal" class="modal">
                                <div class="modal-content">
                                    <span class="close" onclick="closeAddClassModal()">&times;</span>
                                    <h2>Add New Class</h2>
                                    <form method="POST">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Class Name *</label>
                                                <input type="text" name="class_name" class="form-control" placeholder="e.g., 10" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Section *</label>
                                                <input type="text" name="section" class="form-control" placeholder="e.g., A" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Class Teacher</label>
                                                <select name="class_teacher_id" class="form-control">
                                                    <option value="">Select Class Teacher</option>
                                                    <?php foreach ($teachers as $teacher): ?>
                                                        <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Room Number</label>
                                                <input type="text" name="room_number" class="form-control" placeholder="e.g., Room 101">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Capacity *</label>
                                                <input type="number" name="capacity" class="form-control" min="1" max="100" value="30" required>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                                            <button type="submit" name="add_class" class="btn btn-success">
                                                <i class="fas fa-save"></i> Add Class
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="closeAddClassModal()">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php
                            break;   

    case 'courses':

// Permissions
$can_manage = ($current_user['role'] === 'admin'); // Only admin can manage

// Backend protection from manual delete/edit URL access
if (isset($_GET['delete_course']) && !$can_manage) {
    echo "<div class='error-message'>Access Denied. Only admin can delete courses.</div>";
    break;
}

// Get all courses with teacher names
$stmt = $pdo->query("
    SELECT c.*, u.name as teacher_name 
    FROM courses c 
    LEFT JOIN teachers t ON c.teacher_id = t.id 
    LEFT JOIN users u ON t.user_id = u.id 
    ORDER BY c.id DESC
");
$courses = $stmt->fetchAll();

// Get all teachers for dropdown
$teachers_stmt = $pdo->query("
    SELECT t.id, u.name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'active'
");
$teachers = $teachers_stmt->fetchAll();
?>

<?php if (isset($success)): ?>
    <div class="success-message"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="error-message"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Course Management</h2>

        <?php if ($can_manage): ?>
        <button class="btn btn-primary" onclick="openAddCourseModal()">
            <i class="fas fa-plus"></i> Add New Course
        </button>
        <?php endif; ?>
    </div>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Department</th>
                    <th>Credits</th>
                    <th>Teacher</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($courses) > 0): ?>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                            
                            <td>
                                <div><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></div>
                                <small style="color: #666;"><?php echo htmlspecialchars($course['description']); ?></small>
                            </td>

                            <td><?php echo htmlspecialchars($course['department']); ?></td>
                            <td><?php echo htmlspecialchars($course['credits']); ?></td>
                            <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'Not Assigned'); ?></td>
                            <td><?php echo htmlspecialchars($course['duration']); ?></td>

                            <td>
                                <span class="badge badge-<?php echo $course['status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($can_manage): ?>
                                    <button class="btn btn-primary btn-sm" onclick="editCourse(<?php echo $course['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>

                                    <a href="?page=courses&delete_course=<?php echo $course['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this course?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <span style="color:#666;font-size:13px;">View Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            No courses found. 
                            <?php if ($can_manage): ?>
                                <a href="javascript:void(0)" onclick="openAddCourseModal()">Add first course</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($can_manage): ?>
<!-- Add Course Modal -->
<div id="addCourseModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddCourseModal()">&times;</span>
        <h2>Add New Course</h2>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Course Code *</label>
                    <input type="text" name="course_code" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Course Name *</label>
                    <input type="text" name="course_name" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" class="form-control" required>
                        <option value="">Select Department</option>
                        <option value="Science">Science</option>
                        <option value="Arts">Arts</option>
                        <option value="Commerce">Commerce</option>
                        <option value="Technology">Technology</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Languages">Languages</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Credits *</label>
                    <input type="number" name="credits" class="form-control" min="1" max="10" value="3" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" class="form-control">
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Duration</label>
                    <input type="text" name="duration" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="add_course" class="btn btn-success">
                    <i class="fas fa-save"></i> Add Course
                </button>

                <button type="button" class="btn btn-secondary" onclick="closeAddCourseModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>

        </form>
    </div>
</div>
<?php endif; ?>

<?php
break;

                      case 'attendance':
    // Handle different views based on role
    if ($current_user['role'] == 'admin') {
        // Admin Attendance View
        include __DIR__ . "/pages/admin/admin_attendance.php";
    } elseif ($current_user['role'] == 'teacher') {
        // Teacher Attendance View
        ?>
        <?php
        // Get teacher's assigned classes
        $teacher_id = null;
        $teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $teacher_stmt->execute([$current_user['id']]);
        $teacher_data = $teacher_stmt->fetch();

        if ($teacher_data) {
            $teacher_id = $teacher_data['id'];
            
            // Get classes where teacher is class teacher
            $classes_stmt = $pdo->prepare("
                SELECT c.* 
                FROM classes c 
                WHERE c.class_teacher_id = ? AND c.status = 'active'
            ");
            $classes_stmt->execute([$teacher_id]);
            $classes = $classes_stmt->fetchAll();

            // Get courses taught by this teacher
            $courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? AND status = 'active'");
            $courses_stmt->execute([$teacher_id]);
            $courses = $courses_stmt->fetchAll();
        } else {
            $classes = [];
            $courses = [];
            echo "<div class='error-message'>Teacher profile not found.</div>";
        }

        // Get today's date for default
        $today = date('Y-m-d');
        $selected_date = $_POST['attendance_date'] ?? $today;
        $selected_class = $_POST['class_id'] ?? '';
        $selected_course = $_POST['course_id'] ?? '';

        // Get attendance records if class is selected
        $attendance_records = [];
        $students = [];

        if ($selected_class && $selected_date && $teacher_id) {
            // Get students for selected class
            $students_stmt = $pdo->prepare("
                SELECT s.id, u.name, s.class_id 
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.class_id = ? AND s.status = 'active'
                ORDER BY u.name
            ");
            $students_stmt->execute([$selected_class]);
            $students = $students_stmt->fetchAll();
            
            // Get existing attendance records for the selected date and class
            $attendance_stmt = $pdo->prepare("
                SELECT student_id, status, remarks 
                FROM attendance_records 
                WHERE class_id = ? AND attendance_date = ? AND course_id = ?
            ");
            $attendance_stmt->execute([$selected_class, $selected_date, $selected_course]);
            $attendance_data = $attendance_stmt->fetchAll();
            foreach ($attendance_data as $record) {
                $attendance_records[$record['student_id']] = $record;
            }
        }

        // Get teacher's attendance records summary
        if ($teacher_id) {
            $summary_stmt = $pdo->prepare("
                SELECT 
                    ar.attendance_date,
                    c.class_name,
                    c.section,
                    co.course_name,
                    COUNT(ar.id) as total_records,
                    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count
                FROM attendance_records ar
                JOIN classes c ON ar.class_id = c.id
                LEFT JOIN courses co ON ar.course_id = co.id
                WHERE ar.recorded_by = ?
                GROUP BY ar.attendance_date, ar.class_id, ar.course_id
                ORDER BY ar.attendance_date DESC
                LIMIT 10
            ");
            $summary_stmt->execute([$current_user['id']]);
            $attendance_summary = $summary_stmt->fetchAll();

            // Get attendance summary for current month
            $current_month = date('Y-m');
            $monthly_summary_stmt = $pdo->prepare("
                SELECT 
                    c.class_name,
                    c.section,
                    co.course_name,
                    COUNT(DISTINCT ar.attendance_date) as total_days,
                    COUNT(ar.id) as total_records,
                    SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN ar.status = 'half_day' THEN 1 ELSE 0 END) as half_day_count
                FROM attendance_records ar
                JOIN classes c ON ar.class_id = c.id
                LEFT JOIN courses co ON ar.course_id = co.id
                WHERE ar.recorded_by = ? AND DATE_FORMAT(ar.attendance_date, '%Y-%m') = ?
                GROUP BY ar.class_id, ar.course_id
            ");
            $monthly_summary_stmt->execute([$current_user['id'], $current_month]);
            $monthly_summary = $monthly_summary_stmt->fetchAll();
        } else {
            $attendance_summary = [];
            $monthly_summary = [];
        }
        ?>

        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($teacher_id && count($classes) > 0): ?>

        <!-- Monthly Summary Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Monthly Attendance Summary (<?php echo date('F Y'); ?>)</h2>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Course</th>
                            <th>Total Days</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Half Day</th>
                            <th>Total Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($monthly_summary) > 0): ?>
                            <?php foreach ($monthly_summary as $summary): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['class_name'] . ' - ' . $summary['section']); ?></td>
                                    <td><?php echo htmlspecialchars($summary['course_name'] ?? 'All'); ?></td>
                                    <td><?php echo $summary['total_days']; ?></td>
                                    <td>
                                        <span class="badge badge-success"><?php echo $summary['present_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-danger"><?php echo $summary['absent_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning"><?php echo $summary['late_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $summary['half_day_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $summary['total_records']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">
                                    No attendance records found for this month.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Take Attendance Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Take Attendance</h2>
            </div>
            
            <form method="POST" id="attendanceForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Select Date *</label>
                        <input type="date" name="attendance_date" class="form-control" value="<?php echo $selected_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Select Class *</label>
                        <select name="class_id" class="form-control" required onchange="this.form.submit()">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Course</label>
                        <select name="course_id" class="form-control" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo $selected_course == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <?php if (count($students) > 0): ?>
                    <div class="table-responsive" style="margin-top: 20px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>#STU-<?php echo $student['id']; ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td>
                                            <select name="attendance[<?php echo $student['id']; ?>]" class="form-control" required>
                                                <option value="present" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'present') ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'absent') ? 'selected' : ''; ?>>Absent</option>
                                                <option value="late" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'late') ? 'selected' : ''; ?>>Late</option>
                                                <option value="half_day" <?php echo (isset($attendance_records[$student['id']]) && $attendance_records[$student['id']]['status'] == 'half_day') ? 'selected' : ''; ?>>Half Day</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="remarks[<?php echo $student['id']; ?>]" class="form-control" placeholder="Remarks (optional)" value="<?php echo isset($attendance_records[$student['id']]) ? htmlspecialchars($attendance_records[$student['id']]['remarks']) : ''; ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button type="submit" name="take_attendance" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                <?php elseif ($selected_class): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-users fa-3x" style="margin-bottom: 20px;"></i>
                        <p>No students found in this class.</p>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-calendar-check fa-3x" style="margin-bottom: 20px;"></i>
                        <p>Please select a class to take attendance.</p>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Teacher's Daily Attendance Summary Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Recent Daily Attendance Records</h2>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Course</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Half Day</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendance_summary) > 0): ?>
                            <?php foreach ($attendance_summary as $record): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['class_name'] . ' - ' . $record['section']); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_name'] ?? 'All'); ?></td>
                                    <td>
                                        <span class="badge badge-success"><?php echo $record['present_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-danger"><?php echo $record['absent_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning"><?php echo $record['late_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $record['half_day_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $record['total_records']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">
                                    No attendance records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Teacher Attendance</h2>
                </div>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-chalkboard-teacher fa-3x" style="margin-bottom: 20px;"></i>
                    <p>No classes assigned to you or teacher profile not found.</p>
                    <p>Please contact administrator to assign classes.</p>
                </div>
            </div>
        <?php endif; ?>
        <?php
    } else {
        // Student Attendance View
        include 'student_attendance.php';
    }
    break;

                     case 'fees':
    if ($current_user['role'] == 'admin') {
        include 'fees_management.php';
    } else {
        echo "<div class='error-message'>Access denied. Only administrators can manage fees.</div>";
    }
                            break;
                        case 'settings':
                            // Only admin can access settings
                            if ($current_user['role'] == 'admin') {
                                ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h2 class="card-title">System Settings</h2>
                                    </div>
                                    
                                    <?php if (isset($success)): ?>
                                        <div class="success-message"><?php echo $success; ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($error)): ?>
                                        <div class="error-message"><?php echo $error; ?></div>
                                    <?php endif; ?>
                                    
                                    <form method="POST">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>School Name *</label>
                                                <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($settings['school_name']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Currency *</label>
                                                <select name="currency" class="form-control" required>
                                                    <option value="₹" <?php echo $settings['currency'] == '₹' ? 'selected' : ''; ?>>Indian Rupee (₹)</option>
                                                    <option value="₨" <?php echo $settings['currency'] == '₨' ? 'selected' : ''; ?>>Pakistani Rupee (₨)</option>
                                                    <option value="$" <?php echo $settings['currency'] == '$' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                                    <option value="€" <?php echo $settings['currency'] == '€' ? 'selected' : ''; ?>>Euro (€)</option>
                                                    <option value="£" <?php echo $settings['currency'] == '£' ? 'selected' : ''; ?>>British Pound (£)</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <button type="submit" name="update_settings" class="btn btn-success">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php
                            } else {
                                echo "<div class='error-message'>Access denied. Only administrators can access settings.</div>";
                            }
                            break;
   case 'grades':
    if ($current_user['role'] == 'admin') {
        // Admin grade management (you can create this later)
        echo "<div class='card'><h2>Grade Management</h2><p>Admin grade management will be implemented here.</p></div>";
    } elseif ($current_user['role'] == 'teacher') {
        include 'grades_management.php';
    } else {
        include 'student_grades.php';
    }
    break;
    case 'timetable':
    if ($current_user['role'] == 'admin') {
        include __DIR__ . "/pages/admin/admin_timetable.php";
    } elseif ($current_user['role'] == 'teacher') {
        include 'teacher_timetable.php';
    } else {
        include 'student_timetable.php';
    }
    break;
    case 'certificates':
    if ($current_user['role'] == 'admin') {
        include 'admin_certificates.php';
    } else {
        include 'student_certificate_view.php';
    }
    break;
    // Line 809 ke baad add karein:
// Exam System Cases
case 'exams':
    if ($current_user['role'] == 'admin') {
        include 'admin_exam.php';
    } else {
        echo "<div class='error-message'>Access denied. Only administrators can manage exams.</div>";
    }
    break;

case 'results':
    if ($current_user['role'] == 'admin') {
        include 'results_entry.php';
    } else {
        echo "<div class='error-message'>Access denied. Only administrators can enter results.</div>";
    }
    break;

case 'marksheets':
    if ($current_user['role'] == 'admin') {
        // Marksheet upload system (you can create this later)
        echo "<div class='card'><h2>Marksheet Management</h2><p>Upload and manage student marksheets.</p></div>";
    } else {
        echo "<div class='error-message'>Access denied. Only administrators can manage marksheets.</div>";
    }
    break;

case 'my_results':
    if ($current_user['role'] == 'student') {
        include 'student_results.php';
    } else {
        echo "<div class='error-message'>Access denied. This page is for students only.</div>";
    }
    break;

case 'my_marksheets':
    if ($current_user['role'] == 'student') {
        include 'student_marksheets.php';
    } else {
        echo "<div class='error-message'>Access denied. This page is for students only.</div>";
    }
    break;

case 'teacher_exams':
    if ($current_user['role'] == 'teacher') {
        include 'teacher_exams.php';
    } else {
        echo "<div class='error-message'>Access denied. This page is for teachers only.</div>";
    }
    break;

case 'teacher_results':
    if ($current_user['role'] == 'teacher') {
        include 'teacher_results_view.php';
    } else {
        echo "<div class='error-message'>Access denied. This page is for teachers only.</div>";
    }
    break;


                        default:
                            include 'dashboard_content.php';
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Role selector functionality
        document.addEventListener('DOMContentLoaded', function() {
            const roleOptions = document.querySelectorAll('.role-option');
            const selectedRole = document.getElementById('selectedRole');
            
            if (roleOptions.length > 0) {
                roleOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        roleOptions.forEach(opt => opt.classList.remove('active'));
                        this.classList.add('active');
                        if (selectedRole) {
                            selectedRole.value = this.getAttribute('data-role');
                        }
                    });
                });
            }
        });

        // Student Modal Functions
        function openAddStudentModal() {
            document.getElementById('addStudentModal').style.display = 'block';
        }

        function closeAddStudentModal() {
            document.getElementById('addStudentModal').style.display = 'none';
        }

        function editStudent(studentId) {
            alert('Edit feature for student ID: ' + studentId + ' will be implemented soon!');
        }

        // Teacher Modal Functions
        function openAddTeacherModal() {
            document.getElementById('addTeacherModal').style.display = 'block';
        }

        function closeAddTeacherModal() {
            document.getElementById('addTeacherModal').style.display = 'none';
        }

        function editTeacher(teacherId) {
            alert('Edit feature for teacher ID: ' + teacherId + ' will be implemented soon!');
        }

        // Course Modal Functions
        function openAddCourseModal() {
            document.getElementById('addCourseModal').style.display = 'block';
        }

        function closeAddCourseModal() {
            document.getElementById('addCourseModal').style.display = 'none';
        }

        function editCourse(courseId) {
            alert('Edit feature for course ID: ' + courseId + ' will be implemented soon!');
        }

        // Class Modal Functions
        function openAddClassModal() {
            document.getElementById('addClassModal').style.display = 'block';
        }

        function closeAddClassModal() {
            document.getElementById('addClassModal').style.display = 'none';
        }

        function editClass(classId) {
            alert('Edit feature for class ID: ' + classId + ' will be implemented soon!');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var studentModal = document.getElementById('addStudentModal');
            var teacherModal = document.getElementById('addTeacherModal');
            var courseModal = document.getElementById('addCourseModal');
            var classModal = document.getElementById('addClassModal');
            
            if (event.target == studentModal) {
                closeAddStudentModal();
            }
            if (event.target == teacherModal) {
                closeAddTeacherModal();
            }
            if (event.target == courseModal) {
                closeAddCourseModal();
            }
            if (event.target == classModal) {
                closeAddClassModal();
            }
        }
    </script>
</body>
</html>