<?php
// Include database connection
require_once 'config.php'; // Assuming you have config.php with $pdo connection

// Start session if needed
session_start();

// Check if student ID exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Student ID required!");
}

$student_db_id = intval($_GET['id']);

// Fetch student data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_db_id]);
$stu = $stmt->fetch();

if (!$stu) {
    die("Student not found!");
}

// Set variables
$student_name = htmlspecialchars($stu['name']);
$course = htmlspecialchars($stu['course']);
$photo = (!empty($stu['photo']) && file_exists($stu['photo'])) ? $stu['photo'] : 'uploads/default.png';
$issue_date = date('d-m-Y');

// Ensure certificates folder exists
if (!file_exists('certificates')) {
    mkdir('certificates', 0777, true);
}

// Ensure tmp folder exists for mPDF
if (!file_exists('tmp')) {
    mkdir('tmp', 0777, true);
}

// Check if mPDF is installed
try {
    require_once __DIR__ . '/vendor/autoload.php';
} catch (Exception $e) {
    die("mPDF library not found. Please install it using: composer require mpdf/mpdf");
}

// Create mPDF instance
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_header' => 10,
    'margin_footer' => 10,
    'tempDir' => __DIR__ . '/tmp'
]);

// HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .certificate { 
            border: 15px solid #0a3d62; 
            padding: 40px; 
            text-align: center;
            background: #fff;
        }
        .header { margin-bottom: 30px; }
        .school-name { 
            font-size: 32px; 
            color: #0a3d62; 
            font-weight: bold;
            margin-bottom: 10px;
        }
        .title { 
            font-size: 28px; 
            color: #1e3799;
            margin: 20px 0;
            text-decoration: underline;
        }
        .student-info { 
            margin: 30px 0; 
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .student-name { 
            font-size: 26px; 
            font-weight: bold;
            color: #000;
            margin: 15px 0;
        }
        .course { 
            font-size: 20px; 
            color: #333;
            margin: 10px 0;
        }
        .date { 
            font-size: 18px; 
            color: #666;
            margin: 20px 0;
        }
        .footer { 
            margin-top: 50px; 
            border-top: 2px solid #000;
            padding-top: 20px;
        }
        .signature { 
            font-size: 18px; 
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="school-name">Mahrang Public School</div>
            <div class="title">Certificate of Completion</div>
        </div>
        
        <div class="student-info">
            <p>This is to certify that</p>
            <div class="student-name">' . $student_name . '</div>
            <p>has successfully completed the course</p>
            <div class="course">' . $course . '</div>
            <div class="date">Date: ' . $issue_date . '</div>
        </div>
        
        <div class="footer">
            <div class="signature">Principal</div>
            <div>Mahrang Public School</div>
        </div>
    </div>
</body>
</html>';

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Generate filename
$filename = 'certificates/certificate_' . $stu['id'] . '_' . time() . '.pdf';

// Output PDF to file
$mpdf->Output($filename, \Mpdf\Output\Destination::FILE);

// Save to database
$insert = $pdo->prepare("INSERT INTO certificates (student_id, file_path, issue_date) VALUES (?, ?, ?)");
$insert->execute([
    $stu['id'],
    $filename,
    date('Y-m-d')
]);

echo "<h2 style='text-align:center; color:green;'>Certificate Generated Successfully!</h2>";
echo "<div style='text-align:center; margin-top:20px;'>";
echo "<a href='$filename' target='_blank' style='padding:10px 20px; background:#0a3d62; color:white; text-decoration:none; border-radius:5px;'>Download PDF</a><br><br>";
echo "<a href='admin_certificate.php?id=" . $stu['id'] . "' style='color:#0a3d62;'>← Back to Certificate View</a>";
echo "</div>";
?>