<?php
// student_view_certificate.php
session_start();

// Database Connection (same as before)
$host = '127.0.0.1';
$db   = 'edumanage';
$user = 'root'; 
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get student ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Student ID required!");
}
$student_id = (int)$_GET['id'];

// Get student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found!");
}

// Get certificates for this student
$cert_stmt = $pdo->prepare("SELECT * FROM certificates WHERE student_id = ? ORDER BY issue_date DESC");
$cert_stmt->execute([$student_id]);
$certificates = $cert_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Certificate View - <?php echo htmlspecialchars($student['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #0a3d62;
        }
        
        .header h1 {
            color: #0a3d62;
            margin-bottom: 10px;
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        .certificate-section {
            margin-top: 30px;
        }
        
        .certificate-actions {
            text-align: center;
            margin: 30px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 0 10px;
            background: #0a3d62;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #1e5799;
        }
        
        .btn-green {
            background: #28a745;
        }
        
        .btn-green:hover {
            background: #218838;
        }
        
        .certificates-list {
            margin-top: 20px;
        }
        
        .certificate-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .certificate-item:hover {
            background: #f8f9fa;
        }
        
        .certificate-date {
            color: #666;
            font-size: 14px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: #0a3d62;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Student Certificate Management</h1>
            <p>View and manage certificates for <?php echo htmlspecialchars($student['name']); ?></p>
        </div>
        
        <div class="student-info">
            <h2>Student Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Course:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['course']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Student ID:</span>
                    <span class="info-value"><?php echo $student['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Admission Date:</span>
                    <span class="info-value"><?php echo !empty($student['admission_date']) ? $student['admission_date'] : 'N/A'; ?></span>
                </div>
            </div>
        </div>
        
        <div class="certificate-actions">
            <a href="admin_certificate.php?id=<?php echo $student_id; ?>" class="btn" target="_blank">
                View Certificate
            </a>
            <a href="generate_certificate.php?id=<?php echo $student_id; ?>" class="btn btn-green">
                Generate PDF Certificate
            </a>
        </div>
        
        <?php if (!empty($certificates)): ?>
        <div class="certificate-section">
            <h2>Generated Certificates</h2>
            <div class="certificates-list">
                <?php foreach ($certificates as $cert): ?>
                <div class="certificate-item">
                    <div>
                        <strong>Certificate #<?php echo $cert['id']; ?></strong>
                        <div class="certificate-date">
                            Issued on: <?php echo date('d-m-Y', strtotime($cert['issue_date'])); ?>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo $cert['file_path']; ?>" target="_blank" class="btn" style="padding:8px 15px; font-size:14px;">
                            Download PDF
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <a href="javascript:history.back()" class="back-link">← Back to Student List</a>
    </div>
</body>
</html>