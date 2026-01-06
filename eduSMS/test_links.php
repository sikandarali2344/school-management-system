<!DOCTYPE html>
<html>
<head>
    <title>Test Links - Certificate System</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #0a3d62; }
        .link-box { 
            background: #f8f9fa; 
            padding: 20px; 
            margin: 15px 0; 
            border-left: 4px solid #0a3d62;
        }
        .link-box a { 
            display: block; 
            padding: 10px; 
            background: white; 
            margin: 5px 0; 
            text-decoration: none; 
            color: #0a3d62;
            border: 1px solid #ddd;
        }
        .link-box a:hover { background: #e9ecef; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Certificate System Test Links</h1>
        
        <div class="link-box">
            <h3>Main Pages:</h3>
            <a href="student_list.php">1. Student List</a>
            <a href="enroll_student.php">2. Enroll New Student</a>
        </div>
        
        <div class="link-box">
            <h3>Direct Certificate Links (Need ID):</h3>
            <a href="admin_certificate.php?id=1">admin_certificate.php?id=1</a>
            <a href="admin_certificate.php?id=2">admin_certificate.php?id=2</a>
            <a href="admin_certificate.php?id=3">admin_certificate.php?id=3</a>
        </div>
        
        <div class="link-box">
            <h3>Error Test:</h3>
            <a href="admin_certificate.php">admin_certificate.php (No ID - will show error)</a>
        </div>
        
        <div style="margin-top:30px; padding:20px; background:#ffeaa7;">
            <h3>Instructions:</h3>
            <ol>
                <li>First, enroll a student using "Enroll New Student"</li>
                <li>Then go to "Student List" to see all students</li>
                <li>Click "View Certificate" for any student</li>
                <li>Or use direct links like admin_certificate.php?id=1</li>
            </ol>
        </div>
    </div>
</body>
</html>