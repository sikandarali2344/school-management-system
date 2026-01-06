<?php
// Include DB connection
require_once "config.php";

// Fetch classes for dropdown
$classes = $pdo->query("SELECT id, class_name FROM classes")->fetchAll();

// If attendance form submitted
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'];
    $date = $_POST['date'];
    $attendance = $_POST['attendance']; // array: student_id => status

    // Remove old attendance for same class/date (avoid duplicates)
    $del = $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND date = ?");
    $del->execute([$class_id, $date]);

    // Insert new attendance rows
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, status, date) VALUES (?, ?, ?, ?)");

    foreach ($attendance as $student_id => $status) {
        $stmt->execute([$student_id, $class_id, $status, $date]);
    }

    $message = "Attendance successfully saved!";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin | Attendance</title>
    <style>
        body { font-family: Arial; background:#f1f2f6; padding: 20px; }
        .box { background:white; padding:20px; border-radius:8px; max-width:800px; margin:auto; }
        h2 { text-align:center; color:#1e3799; }
        select, input[type=date] {
            width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px;
        }
        table { width:100%; border-collapse: collapse; margin-top:15px; }
        th, td { padding:10px; border-bottom:1px solid #ddd; }
        th { background:#1e3799; color:white; }
        .btn {
            padding:10px 20px; background:#1e3799; color:white; border:none; border-radius:5px;
            cursor:pointer; margin-top:20px; width:100%;
        }
        .btn:hover { background:#0a3d62; }
        .success { background:#2ecc71; padding:10px; color:white; margin-bottom:15px; border-radius:5px; }
    </style>

    <script>
        function loadStudents(classId) {
            if (classId === "") return;

            const xhr = new XMLHttpRequest();
            xhr.open("GET", "fetch_students.php?class_id=" + classId, true);

            xhr.onload = function() {
                document.getElementById("students-area").innerHTML = this.responseText;
            };

            xhr.send();
        }
    </script>
</head>
<body>

<div class="box">
    <h2>Attendance Management</h2>

    <?php if ($message): ?>
        <div class="success"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        
        <label>Select Class:</label>
        <select name="class_id" onchange="loadStudents(this.value)" required>
            <option value="">-- Choose Class --</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo $c['class_name']; ?></option>
            <?php endforeach; ?>
        </select>

        <label>Select Date:</label>
        <input type="date" name="date" required>

        <div id="students-area">
            <!-- Students will load here automatically -->
        </div>

        <button class="btn" type="submit">Save Attendance</button>
    </form>
</div>

</body>
</html>
