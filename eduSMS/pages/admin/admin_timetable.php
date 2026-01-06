<?php
// Check if user is logged in as admin
if (!isset($current_user) || $current_user['role'] != 'admin') {
    echo "<div class='error-message'>Access denied. Only administrators can manage timetable.</div>";
    return;
}

// Handle timetable actions
if (isset($_POST['add_timetable'])) {
    $class_id = $_POST['class_id'];
    $day = $_POST['day'];
    $period = $_POST['period'];
    $course_id = $_POST['course_id'];
    $teacher_id = $_POST['teacher_id'];
    $room = $_POST['room'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $is_break = $_POST['is_break'] ?? 0;
    $is_holiday = $_POST['is_holiday'] ?? 0;
    $break_type = $_POST['break_type'] ?? '';
    $holiday_name = $_POST['holiday_name'] ?? '';
    
    try {
        // Check if slot already exists
        $check_stmt = $pdo->prepare("SELECT id FROM timetable WHERE class_id = ? AND day = ? AND period = ?");
        $check_stmt->execute([$class_id, $day, $period]);
        
        if ($check_stmt->fetch()) {
            // Update existing entry
            $stmt = $pdo->prepare("UPDATE timetable SET course_id = ?, teacher_id = ?, room = ?, start_time = ?, end_time = ?, is_break = ?, is_holiday = ?, break_type = ?, holiday_name = ? WHERE class_id = ? AND day = ? AND period = ?");
            $stmt->execute([$course_id, $teacher_id, $room, $start_time, $end_time, $is_break, $is_holiday, $break_type, $holiday_name, $class_id, $day, $period]);
            $success = "Timetable updated successfully!";
        } else {
            // Insert new entry
            $stmt = $pdo->prepare("INSERT INTO timetable (class_id, day, period, course_id, teacher_id, room, start_time, end_time, is_break, is_holiday, break_type, holiday_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$class_id, $day, $period, $course_id, $teacher_id, $room, $start_time, $end_time, $is_break, $is_holiday, $break_type, $holiday_name]);
            $success = "Timetable entry added successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error managing timetable: " . $e->getMessage();
    }
}

// Handle bulk timetable actions
if (isset($_POST['add_break'])) {
    $class_id = $_POST['class_id'];
    $day = $_POST['day'];
    $break_period = $_POST['break_period'];
    $break_type = $_POST['break_type'];
    
    // Set break time based on period
    $break_times = [
        3 => ['start' => '11:30', 'end' => '12:00'], // Morning break
        5 => ['start' => '13:30', 'end' => '14:00'], // Lunch break
        7 => ['start' => '15:00', 'end' => '15:15']  // Afternoon break
    ];
    
    if (isset($break_times[$break_period])) {
        $start_time = $break_times[$break_period]['start'];
        $end_time = $break_times[$break_period]['end'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO timetable (class_id, day, period, start_time, end_time, is_break, break_type) VALUES (?, ?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE start_time = ?, end_time = ?, is_break = 1, break_type = ?");
            $stmt->execute([$class_id, $day, $break_period, $start_time, $end_time, $break_type, $start_time, $end_time, $break_type]);
            $success = "Break added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding break: " . $e->getMessage();
        }
    }
}

// Handle holiday creation
if (isset($_POST['add_holiday'])) {
    $class_id = $_POST['class_id'];
    $day = $_POST['day'];
    $holiday_name = $_POST['holiday_name'];
    $description = $_POST['description'] ?? '';
    
    try {
        // Mark all periods for that day as holiday
        for ($period = 1; $period <= 7; $period++) {
            $stmt = $pdo->prepare("INSERT INTO timetable (class_id, day, period, is_holiday, holiday_name, description) VALUES (?, ?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE is_holiday = 1, holiday_name = ?, description = ?");
            $stmt->execute([$class_id, $day, $period, $holiday_name, $description, $holiday_name, $description]);
        }
        $success = "Holiday added successfully for $day!";
    } catch(PDOException $e) {
        $error = "Error adding holiday: " . $e->getMessage();
    }
}

// Handle timetable deletion
if (isset($_GET['delete_timetable'])) {
    $timetable_id = $_GET['delete_timetable'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM timetable WHERE id = ?");
        $stmt->execute([$timetable_id]);
        $success = "Timetable entry deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting timetable entry: " . $e->getMessage();
    }
}

// Get all classes, courses, and teachers
$classes_stmt = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY class_name, section");
$classes = $classes_stmt->fetchAll();

$courses_stmt = $pdo->query("SELECT * FROM courses WHERE status = 'active' ORDER BY course_name");
$courses = $courses_stmt->fetchAll();

$teachers_stmt = $pdo->query("SELECT t.*, u.name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.status = 'active' ORDER BY u.name");
$teachers = $teachers_stmt->fetchAll();

// Days of week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Periods - 7 periods with breaks at period 3 and 5
$periods = range(1, 7);

// Break types
$break_types = [
    'morning_break' => 'Morning Break',
    'lunch' => 'Lunch Break',
    'short_break' => 'Short Break'
];

// Selected class for viewing
$selected_class = $_GET['class_id'] ?? ($_POST['class_id'] ?? ($classes[0]['id'] ?? null));
?>

<?php if (isset($success)): ?>
    <div class="success-message"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="error-message"><?php echo $error; ?></div>
<?php endif; ?>

<div class="timetable-container">
    <!-- Class Selection Card -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Timetable Management</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label>Select Class</label>
                            <select name="class_id" class="form-control" onchange="this.form.submit()">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selected_class): ?>
        <?php
        // Get timetable for selected class
        $timetable_stmt = $pdo->prepare("
            SELECT t.*, c.course_name, c.course_code, u.name as teacher_name, cls.class_name, cls.section 
            FROM timetable t 
            LEFT JOIN courses c ON t.course_id = c.id 
            LEFT JOIN teachers tr ON t.teacher_id = tr.id 
            LEFT JOIN users u ON tr.user_id = u.id 
            LEFT JOIN classes cls ON t.class_id = cls.id 
            WHERE t.class_id = ? 
            ORDER BY 
                FIELD(t.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                t.period
        ");
        $timetable_stmt->execute([$selected_class]);
        $timetable_data = $timetable_stmt->fetchAll();
        
        // Organize data by day and period
        $timetable_grid = [];
        foreach ($timetable_data as $entry) {
            $timetable_grid[$entry['day']][$entry['period']] = $entry;
        }
        
        // Get selected class info
        $class_info_stmt = $pdo->prepare("SELECT class_name, section FROM classes WHERE id = ?");
        $class_info_stmt->execute([$selected_class]);
        $class_info = $class_info_stmt->fetch();
        
        // Check for holidays
        $holidays = [];
        foreach ($timetable_data as $entry) {
            if ($entry['is_holiday'] == 1 && !empty($entry['holiday_name'])) {
                $holidays[$entry['day']] = $entry['holiday_name'];
            }
        }
        ?>

        <!-- Control Panel Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <button class="btn btn-warning" onclick="openAddBreakModal()">
                        <i class="fas fa-coffee"></i> Add Break
                    </button>
                    <button class="btn btn-danger" onclick="openAddHolidayModal()">
                        <i class="fas fa-umbrella-beach"></i> Add Holiday
                    </button>
                    <button class="btn btn-primary" onclick="openAddTimetableModal()">
                        <i class="fas fa-plus"></i> Add Class
                    </button>
                    <button class="btn btn-info" onclick="printTimetable()">
                        <i class="fas fa-print"></i> Print Timetable
                    </button>
                </div>
            </div>
        </div>

        <!-- Holiday Alert -->
        <?php if (!empty($holidays)): ?>
            <div class="card holiday-alert">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar-star"></i> Upcoming Holidays</h3>
                </div>
                <div class="card-body">
                    <div class="holiday-list">
                        <?php foreach ($holidays as $day => $holiday_name): ?>
                            <div class="holiday-item">
                                <i class="fas fa-star"></i>
                                <strong><?php echo $day; ?>:</strong> <?php echo htmlspecialchars($holiday_name); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Timetable Display Card -->
        <div class="card timetable-card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-calendar-alt"></i> 
                    Timetable for <?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['section']); ?>
                </h2>
                <div class="timetable-info">
                    <span class="badge badge-primary">7 Periods/Day</span>
                    <span class="badge badge-success"><?php echo count($timetable_data); ?> Classes</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th class="period-header">Period</th>
                            <th class="time-header">Time</th>
                            <?php foreach ($days as $day): ?>
                                <th class="day-header <?php echo isset($holidays[$day]) ? 'holiday' : ''; ?>">
                                    <?php echo $day; ?>
                                    <?php if (isset($holidays[$day])): ?>
                                        <span class="holiday-badge" title="<?php echo htmlspecialchars($holidays[$day]); ?>">
                                            <i class="fas fa-star"></i> Holiday
                                        </span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Define time slots for 7 periods with breaks
                        $time_slots = [
                            1 => ['start' => '08:00', 'end' => '08:45'],
                            2 => ['start' => '08:45', 'end' => '09:30'],
                            3 => ['start' => '09:30', 'end' => '10:15'],
                            4 => ['start' => '11:00', 'end' => '11:45'], // After morning break
                            5 => ['start' => '11:45', 'end' => '1:30'],
                            6 => ['start' => '1:30', 'end' => '2:15'], // After lunch
                            7 => ['start' => '2:15', 'end' => '3:00']
                        ];
                        
                        // Break periods
                        $break_periods = [3 => 'Morning Break'];
                        ?>
                        
                        <?php foreach ($periods as $period): ?>
                            <?php 
                            $is_break_period = isset($break_periods[$period]);
                            $time_slot = $time_slots[$period] ?? ['start' => '', 'end' => ''];
                            ?>
                            <tr class="<?php echo $is_break_period ? 'break-row' : 'class-row'; ?>">
                                <td class="period-cell <?php echo $is_break_period ? 'break-period' : ''; ?>">
                                    <?php if ($is_break_period): ?>
                                        <div class="break-label">
                                            <i class="fas fa-<?php echo $period == 3 ? 'coffee' : ($period == 5 ? 'utensils' : 'clock'); ?>"></i>
                                            <span><?php echo $break_periods[$period]; ?></span>
                                        </div>
                                    <?php else: ?>
                                        Period <?php echo $period; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="time-cell">
                                    <?php if ($is_break_period): ?>
                                        <?php 
                                        $break_times = [
                                            3 => '10:15 - 11:00',
                                            5 => '12:30 - 13:30',
                                            7 => '15:00 - 15:15'
                                        ];
                                        echo $break_times[$period] ?? '';
                                        ?>
                                    <?php else: ?>
                                        <?php echo $time_slot['start'] . ' - ' . $time_slot['end']; ?>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($days as $day): ?>
                                    <?php 
                                    $entry = $timetable_grid[$day][$period] ?? null;
                                    $has_entry = !empty($entry);
                                    $is_holiday = isset($holidays[$day]);
                                    ?>
                                    <td class="timetable-cell 
                                        <?php echo $is_break_period ? 'break-cell' : ''; ?>
                                        <?php echo $has_entry ? ($entry['is_break'] == 1 ? 'has-break' : ($entry['is_holiday'] == 1 ? 'holiday-cell' : 'has-class')) : 'empty'; ?>
                                        <?php echo $is_holiday ? 'holiday-cell' : ''; ?>"
                                        onclick="<?php echo !$is_break_period && !$is_holiday ? "editTimetableSlot('$day', $period)" : ''; ?>"
                                        data-day="<?php echo $day; ?>"
                                        data-period="<?php echo $period; ?>">
                                        
                                        <?php if ($is_holiday): ?>
                                            <div class="holiday-content">
                                                <i class="fas fa-umbrella-beach"></i>
                                                <span><?php echo htmlspecialchars($holidays[$day]); ?></span>
                                            </div>
                                        <?php elseif ($is_break_period): ?>
                                            <?php if ($has_entry && $entry['is_break'] == 1): ?>
                                                <div class="break-content">
                                                    <i class="fas fa-<?php echo $entry['break_type'] == 'lunch' ? 'utensils' : 'coffee'; ?>"></i>
                                                    <span><?php echo htmlspecialchars($entry['break_type'] ?? 'Break'); ?></span>
                                                    <small><?php echo htmlspecialchars($entry['start_time'] ?? '') . ' - ' . htmlspecialchars($entry['end_time'] ?? ''); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <div class="default-break">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?php echo $break_periods[$period]; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($has_entry && $entry['is_holiday'] == 0 && $entry['is_break'] == 0): ?>
                                            <div class="timetable-entry">
                                                <div class="course-code"><?php echo htmlspecialchars($entry['course_code'] ?? ''); ?></div>
                                                <div class="course-name"><?php echo htmlspecialchars($entry['course_name'] ?? 'N/A'); ?></div>
                                                <div class="teacher-name">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($entry['teacher_name'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="room-info">
                                                    <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($entry['room'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="time-info">
                                                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($entry['start_time'] ?? '') . ' - ' . htmlspecialchars($entry['end_time'] ?? ''); ?>
                                                </div>
                                                <div class="actions">
                                                    <a href="?page=timetable&class_id=<?php echo $selected_class; ?>&delete_timetable=<?php echo $entry['id']; ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Are you sure you want to delete this class?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php elseif (!$is_break_period): ?>
                                            <div class="empty-slot">
                                                <i class="fas fa-plus-circle"></i>
                                                <span>Add Class</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Timetable Legend -->
            <div class="timetable-legend">
                <div class="legend-item">
                    <span class="legend-color class-color"></span>
                    <span>Regular Class</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color break-color"></span>
                    <span>Break Time</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color holiday-color"></span>
                    <span>Holiday</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color empty-color"></span>
                    <span>Empty Slot</span>
                </div>
            </div>
        </div>

        <!-- Weekly Summary Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Weekly Summary</h3>
            </div>
            <div class="card-body">
                <?php
                $weekly_stats = [
                    'total_classes' => 0,
                    'total_breaks' => 0,
                    'holidays' => count($holidays)
                ];
                
                foreach ($timetable_data as $entry) {
                    if ($entry['is_holiday'] == 1) {
                        $weekly_stats['holidays']++;
                    } elseif ($entry['is_break'] == 1) {
                        $weekly_stats['total_breaks']++;
                    } elseif (!empty($entry['course_id'])) {
                        $weekly_stats['total_classes']++;
                    }
                }
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon classes">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $weekly_stats['total_classes']; ?></h3>
                            <p>Total Classes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon breaks">
                            <i class="fas fa-coffee"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $weekly_stats['total_breaks']; ?></h3>
                            <p>Scheduled Breaks</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon holidays">
                            <i class="fas fa-umbrella-beach"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $weekly_stats['holidays']; ?></h3>
                            <p>Holidays This Week</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon periods">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3>35</h3>
                            <p>Total Periods (5×7)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Timetable Modal -->
        <div id="addTimetableModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeAddTimetableModal()">&times;</span>
                <h2 id="modalTitle"><i class="fas fa-calendar-plus"></i> Add Timetable Entry</h2>
                <form method="POST" id="timetableForm">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    <input type="hidden" name="day" id="modalDay">
                    <input type="hidden" name="period" id="modalPeriod">
                    <input type="hidden" name="is_break" id="modalIsBreak" value="0">
                    <input type="hidden" name="is_holiday" id="modalIsHoliday" value="0">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Day</label>
                            <input type="text" id="dayDisplay" class="form-control" disabled>
                        </div>
                        <div class="form-group">
                            <label>Period</label>
                            <input type="text" id="periodDisplay" class="form-control" disabled>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course *</label>
                            <select name="course_id" class="form-control" id="courseSelect" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Teacher *</label>
                            <select name="teacher_id" class="form-control" id="teacherSelect" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name'] . ' (' . $teacher['subject'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Room Number</label>
                            <input type="text" name="room" class="form-control" placeholder="e.g., Room 101" id="roomInput">
                        </div>
                        <div class="form-group">
                            <label>Start Time *</label>
                            <input type="time" name="start_time" class="form-control" value="08:00" id="startTime" required>
                        </div>
                        <div class="form-group">
                            <label>End Time *</label>
                            <input type="time" name="end_time" class="form-control" value="08:45" id="endTime" required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add_timetable" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Entry
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddTimetableModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Break Modal -->
        <div id="addBreakModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeAddBreakModal()">&times;</span>
                <h2><i class="fas fa-coffee"></i> Add Break Time</h2>
                <form method="POST">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Day *</label>
                            <select name="day" class="form-control" required>
                                <option value="">Select Day</option>
                                <?php foreach ($days as $day): ?>
                                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Break Period *</label>
                            <select name="break_period" class="form-control" required>
                                <option value="3">Period 3 - Morning Break (10:15-11:00)</option>
                                <option value="5">Period 5 - Lunch Break (12:30-13:30)</option>
                                <option value="7">Period 7 - Short Break (15:00-15:15)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Break Type *</label>
                            <select name="break_type" class="form-control" required>
                                <option value="morning_break">Morning Break</option>
                                <option value="lunch">Lunch Break</option>
                                <option value="short_break">Short Break</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add_break" class="btn btn-warning">
                            <i class="fas fa-coffee"></i> Add Break
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddBreakModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Holiday Modal -->
        <div id="addHolidayModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeAddHolidayModal()">&times;</span>
                <h2><i class="fas fa-umbrella-beach"></i> Add Holiday</h2>
                <form method="POST">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Day *</label>
                            <select name="day" class="form-control" required>
                                <option value="">Select Day</option>
                                <?php foreach ($days as $day): ?>
                                    <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Holiday Name *</label>
                            <input type="text" name="holiday_name" class="form-control" placeholder="e.g., Independence Day, Diwali, Christmas" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Additional information about the holiday..."></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add_holiday" class="btn btn-danger">
                            <i class="fas fa-umbrella-beach"></i> Mark as Holiday
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddHolidayModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Timetable Management</h2>
            </div>
            <div class="card-body" style="text-align: center; padding: 60px;">
                <div class="empty-state">
                    <i class="fas fa-calendar-alt fa-4x" style="color: #6c757d; margin-bottom: 20px;"></i>
                    <h3>No Class Selected</h3>
                    <p>Please select a class from the dropdown above to view or manage its timetable.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Enhanced Timetable Styles */
.timetable-container {
    max-width: 1400px;
    margin: 0 auto;
}

.timetable-card {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border-radius: 15px;
    overflow: hidden;
}

.timetable-table {
    border-collapse: separate;
    border-spacing: 3px;
    width: 100%;
    margin: 0;
    font-size: 0.9rem;
}

.timetable-table th {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 18px 15px;
    text-align: center;
    font-weight: 600;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
    transition: all 0.3s ease;
}

.timetable-table th:hover {
    background: linear-gradient(135deg, var(--secondary), var(--primary));
    transform: translateY(-2px);
}

.timetable-table .day-header.holiday {
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
}

.timetable-table .holiday-badge {
    display: block;
    font-size: 0.7rem;
    margin-top: 5px;
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 10px;
}

.timetable-table td {
    padding: 0;
    border: 1px solid #e9ecef;
    height: 120px;
    vertical-align: top;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.timetable-table .period-cell {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    font-weight: 600;
    text-align: center;
    width: 100px;
    color: var(--dark);
    font-size: 0.9rem;
    padding: 15px 10px;
}

.timetable-table .break-period {
    background: linear-gradient(135deg, #fff3e0, #ffecb3);
    color: #e65100;
}

.timetable-table .break-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.timetable-table .break-label i {
    font-size: 1.2rem;
}

.timetable-table .time-cell {
    background: linear-gradient(135deg, #f1f8e9, #e8f5e9);
    font-size: 0.85rem;
    text-align: center;
    width: 120px;
    color: #388e3c;
    padding: 15px 10px;
    font-weight: 500;
}

/* Cell Types */
.timetable-cell.has-class {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border: 2px solid #2196f3;
    cursor: pointer;
}

.timetable-cell.has-class:hover {
    background: linear-gradient(135deg, #bbdefb, #90caf9);
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 10px 25px rgba(33, 150, 243, 0.2);
    z-index: 2;
}

.timetable-cell.has-break {
    background: linear-gradient(135deg, #fff3e0, #ffcc80);
    border: 2px solid #ff9800;
}

.timetable-cell.break-cell {
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    cursor: default;
}

.timetable-cell.holiday-cell {
    background: linear-gradient(135deg, #ffebee, #ffcdd2);
    border: 2px dashed #f44336;
    cursor: default;
}

.timetable-cell.empty {
    background: linear-gradient(135deg, #f5f5f5, #eeeeee);
    cursor: pointer;
}

.timetable-cell.empty:hover {
    background: linear-gradient(135deg, #e0e0e0, #f5f5f5);
    transform: translateY(-2px);
}

/* Timetable Entry Content */
.timetable-entry {
    padding: 12px;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
}

.timetable-entry .course-code {
    font-size: 0.8rem;
    color: #1565c0;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.3);
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
    width: fit-content;
}

.timetable-entry .course-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--dark);
    line-height: 1.2;
}

.timetable-entry .teacher-name,
.timetable-entry .room-info,
.timetable-entry .time-info {
    font-size: 0.8rem;
    color: #555;
    display: flex;
    align-items: center;
    gap: 5px;
}

.timetable-entry .teacher-name i {
    color: #9c27b0;
}

.timetable-entry .room-info i {
    color: #4caf50;
}

.timetable-entry .time-info i {
    color: #ff9800;
}

.timetable-entry .actions {
    position: absolute;
    top: 8px;
    right: 8px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.timetable-cell.has-class:hover .timetable-entry .actions {
    opacity: 1;
}

/* Break Content */
.break-content,
.default-break {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 8px;
    color: #e65100;
}

.break-content i,
.default-break i {
    font-size: 1.8rem;
    opacity: 0.8;
}

.break-content span,
.default-break span {
    font-weight: 600;
    font-size: 0.9rem;
}

.break-content small {
    font-size: 0.75rem;
    opacity: 0.7;
}

/* Holiday Content */
.holiday-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 8px;
    color: #d32f2f;
    padding: 15px;
    text-align: center;
}

.holiday-content i {
    font-size: 2rem;
    opacity: 0.7;
}

.holiday-content span {
    font-weight: 600;
    font-size: 0.9rem;
}

/* Empty Slot */
.empty-slot {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #757575;
    gap: 8px;
    transition: all 0.3s ease;
}

.empty-slot i {
    font-size: 1.8rem;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.empty-slot span {
    font-size: 0.85rem;
    font-weight: 500;
}

.timetable-cell.empty:hover .empty-slot {
    color: var(--primary);
}

.timetable-cell.empty:hover .empty-slot i {
    opacity: 1;
    transform: scale(1.1);
}

/* Row Styles */
.break-row {
    background: rgba(255, 243, 224, 0.3);
}

.break-row:hover {
    background: rgba(255, 243, 224, 0.5);
}

/* Timetable Legend */
.timetable-legend {
    display: flex;
    justify-content: center;
    gap: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    color: #555;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 2px solid transparent;
}

.class-color {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-color: #2196f3;
}

.break-color {
    background: linear-gradient(135deg, #fff3e0, #ffcc80);
    border-color: #ff9800;
}

.holiday-color {
    background: linear-gradient(135deg, #ffebee, #ffcdd2);
    border-color: #f44336;
    border-style: dashed;
}

.empty-color {
    background: linear-gradient(135deg, #f5f5f5, #eeeeee);
    border-color: #9e9e9e;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.action-buttons .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

/* Holiday Alert */
.holiday-alert {
    background: linear-gradient(135deg, #fff3e0, #ffecb3);
    border: 2px solid #ff9800;
}

.holiday-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.holiday-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 8px;
    border-left: 4px solid #ff9800;
}

.holiday-item i {
    color: #ff9800;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.classes {
    background: linear-gradient(135deg, #2196f3, #1976d2);
}

.stat-icon.breaks {
    background: linear-gradient(135deg, #ff9800, #f57c00);
}

.stat-icon.holidays {
    background: linear-gradient(135deg, #f44336, #d32f2f);
}

.stat-icon.periods {
    background: linear-gradient(135deg, #9c27b0, #7b1fa2);
}

.stat-info h3 {
    font-size: 2rem;
    margin-bottom: 5px;
    color: var(--dark);
}

.stat-info p {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Empty State */
.empty-state {
    max-width: 400px;
    margin: 0 auto;
    text-align: center;
}

.empty-state h3 {
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    line-height: 1.6;
}

/* Modal Styles */
.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    animation: modalSlideIn 0.4s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .timetable-table {
        font-size: 0.85rem;
    }
    
    .timetable-table .period-cell {
        width: 80px;
    }
    
    .timetable-table .time-cell {
        width: 100px;
    }
    
    .timetable-table td {
        height: 100px;
    }
}

@media (max-width: 992px) {
    .timetable-table {
        display: block;
        overflow-x: auto;
    }
    
    .timetable-table th,
    .timetable-table td {
        min-width: 140px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }
    
    .timetable-legend {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .action-buttons,
    .timetable-legend,
    .modal {
        display: none !important;
    }
    
    .timetable-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .timetable-table th {
        background: #f5f5f5 !important;
        color: #000 !important;
    }
}
</style>

<script>
// Modal Functions
function openAddTimetableModal(day = '', period = '') {
    const modal = document.getElementById('addTimetableModal');
    const modalTitle = document.getElementById('modalTitle');
    const dayInput = document.getElementById('modalDay');
    const periodInput = document.getElementById('modalPeriod');
    const dayDisplay = document.getElementById('dayDisplay');
    const periodDisplay = document.getElementById('periodDisplay');
    const isBreakInput = document.getElementById('modalIsBreak');
    const isHolidayInput = document.getElementById('modalIsHoliday');
    
    modal.style.display = 'block';
    
    if (day && period) {
        modalTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Timetable Entry';
        dayInput.value = day;
        periodInput.value = period;
        dayDisplay.value = day;
        periodDisplay.value = 'Period ' + period;
        
        // Check if it's a break period
        const breakPeriods = [3, 5, 7];
        if (breakPeriods.includes(parseInt(period))) {
            alert('This is a break period. Please use the "Add Break" button instead.');
            closeAddTimetableModal();
            openAddBreakModal();
            return;
        }
        
        // Reset form to class mode
        isBreakInput.value = '0';
        isHolidayInput.value = '0';
        document.getElementById('courseSelect').disabled = false;
        document.getElementById('teacherSelect').disabled = false;
        document.getElementById('roomInput').disabled = false;
        document.getElementById('courseSelect').required = true;
        document.getElementById('teacherSelect').required = true;
    } else {
        modalTitle.innerHTML = '<i class="fas fa-plus"></i> Add Timetable Entry';
        dayInput.value = '';
        periodInput.value = '';
        dayDisplay.value = '';
        periodDisplay.value = '';
    }
}

function closeAddTimetableModal() {
    document.getElementById('addTimetableModal').style.display = 'none';
    document.getElementById('timetableForm').reset();
}

function openAddBreakModal() {
    document.getElementById('addBreakModal').style.display = 'block';
}

function closeAddBreakModal() {
    document.getElementById('addBreakModal').style.display = 'none';
}

function openAddHolidayModal() {
    document.getElementById('addHolidayModal').style.display = 'block';
}

function closeAddHolidayModal() {
    document.getElementById('addHolidayModal').style.display = 'none';
}

// Edit timetable slot
function editTimetableSlot(day, period) {
    // Check if it's a holiday
    const cell = document.querySelector(`td[data-day="${day}"][data-period="${period}"]`);
    if (cell && cell.classList.contains('holiday-cell')) {
        alert('This day is marked as a holiday. Remove the holiday first to add classes.');
        return;
    }
    
    openAddTimetableModal(day, period);
}

// Print timetable
function printTimetable() {
    window.print();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['addTimetableModal', 'addBreakModal', 'addHolidayModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            if (modalId === 'addTimetableModal') closeAddTimetableModal();
            if (modalId === 'addBreakModal') closeAddBreakModal();
            if (modalId === 'addHolidayModal') closeAddHolidayModal();
        }
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAddTimetableModal();
        closeAddBreakModal();
        closeAddHolidayModal();
    }
});

// Add smooth scrolling for timetable
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to timetable cells
    const cells = document.querySelectorAll('.timetable-cell');
    cells.forEach((cell, index) => {
        cell.style.animationDelay = `${index * 0.05}s`;
    });
});
</script>>