<?php
// Check if user is logged in as teacher
if (!isset($current_user) || $current_user['role'] != 'teacher') {
    echo "<div class='error-message'>Access denied. Please login as teacher.</div>";
    return;
}

// Get teacher's ID
$teacher_id = null;
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$current_user['id']]);
$teacher_data = $teacher_stmt->fetch();

if (!$teacher_data) {
    echo "<div class='error-message'>Teacher profile not found.</div>";
    return;
}

$teacher_id = $teacher_data['id'];

// Get teacher's timetable
$timetable_stmt = $pdo->prepare("
    SELECT t.*, c.course_name, c.course_code, cls.class_name, cls.section 
    FROM timetable t 
    JOIN courses c ON t.course_id = c.id 
    JOIN classes cls ON t.class_id = cls.id 
    WHERE t.teacher_id = ? 
    ORDER BY 
        FIELD(t.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
        t.period
");
$timetable_stmt->execute([$teacher_id]);
$timetable_data = $timetable_stmt->fetchAll();

// Organize data by day and period
$timetable_grid = [];
foreach ($timetable_data as $entry) {
    $timetable_grid[$entry['day']][$entry['period']] = $entry;
}

// Days of week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Periods
$periods = range(1, 8);

// Get teacher info
$teacher_info_stmt = $pdo->prepare("SELECT t.*, u.name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$teacher_info_stmt->execute([$teacher_id]);
$teacher_info = $teacher_info_stmt->fetch();
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            My Timetable - <?php echo htmlspecialchars($teacher_info['name']); ?>
        </h2>
        <div class="teacher-info">
            <span class="badge badge-primary"><?php echo htmlspecialchars($teacher_info['subject']); ?></span>
        </div>
    </div>
    
    <?php if (count($timetable_data) > 0): ?>
        <div class="table-responsive">
            <table class="timetable-table teacher-view">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Time</th>
                        <?php foreach ($days as $day): ?>
                            <th><?php echo $day; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $period): ?>
                        <tr>
                            <td class="period-number">Period <?php echo $period; ?></td>
                            <td class="time-slot">
                                <?php 
                                // Calculate time slots
                                $start_hour = 9;
                                $period_length = 45;
                                $break_after = 3;
                                
                                $total_minutes = ($period - 1) * $period_length;
                                if ($period > $break_after) {
                                    $total_minutes += 15;
                                }
                                
                                $start_minutes = $start_hour * 60 + $total_minutes;
                                $end_minutes = $start_minutes + $period_length;
                                
                                $start_time = floor($start_minutes / 60) . ':' . str_pad($start_minutes % 60, 2, '0', STR_PAD_LEFT);
                                $end_time = floor($end_minutes / 60) . ':' . str_pad($end_minutes % 60, 2, '0', STR_PAD_LEFT);
                                echo $start_time . ' - ' . $end_time;
                                ?>
                            </td>
                            <?php foreach ($days as $day): ?>
                                <?php 
                                $entry = $timetable_grid[$day][$period] ?? null;
                                $has_entry = !empty($entry);
                                ?>
                                <td class="timetable-cell teacher-cell <?php echo $has_entry ? 'has-entry' : 'free'; ?>"
                                    data-day="<?php echo $day; ?>"
                                    data-period="<?php echo $period; ?>">
                                    <?php if ($has_entry): ?>
                                        <div class="timetable-entry">
                                            <div class="course-name"><?php echo htmlspecialchars($entry['course_name']); ?></div>
                                            <div class="class-info">
                                                <?php echo htmlspecialchars($entry['class_name'] . ' - ' . $entry['section']); ?>
                                            </div>
                                            <div class="room">Room: <?php echo htmlspecialchars($entry['room'] ?? 'N/A'); ?></div>
                                            <div class="time"><?php echo htmlspecialchars($entry['start_time'] ?? '') . ' - ' . htmlspecialchars($entry['end_time'] ?? ''); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="free-period">
                                            <i class="fas fa-coffee"></i>
                                            <span>Free Period</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Today's Classes Card -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3 class="card-title">Today's Classes (<?php echo date('l'); ?>)</h3>
            </div>
            <div class="today-classes">
                <?php
                $today = date('l');
                $today_classes = array_filter($timetable_data, function($entry) use ($today) {
                    return $entry['day'] === $today;
                });
                
                if (count($today_classes) > 0): 
                    usort($today_classes, function($a, $b) {
                        return $a['period'] <=> $b['period'];
                    });
                ?>
                    <div class="class-list">
                        <?php foreach ($today_classes as $class): ?>
                            <div class="class-card">
                                <div class="class-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($class['start_time'] ?? '') . ' - ' . htmlspecialchars($class['end_time'] ?? ''); ?>
                                </div>
                                <div class="class-details">
                                    <h4><?php echo htmlspecialchars($class['course_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?></p>
                                    <p><i class="fas fa-door-open"></i> Room: <?php echo htmlspecialchars($class['room'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="class-period">
                                    <span class="badge badge-primary">Period <?php echo $class['period']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-calendar-times fa-3x" style="margin-bottom: 20px;"></i>
                        <p>No classes scheduled for today.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-calendar-alt fa-3x" style="margin-bottom: 20px;"></i>
            <p>No timetable assigned yet.</p>
            <p>Please contact administrator to assign classes.</p>
        </div>
    <?php endif; ?>
</div>

<style>
/* Teacher Timetable Specific Styles */
.teacher-view .timetable-cell {
    height: 120px;
}

.teacher-cell.free {
    background: linear-gradient(135deg, #e8f5e9, #f1f8e9);
}

.teacher-cell.free:hover {
    background: linear-gradient(135deg, #c8e6c9, #dcedc8);
}

.teacher-cell.has-entry {
    background: linear-gradient(135deg, #e3f2fd, #e8eaf6);
}

.teacher-cell.has-entry:hover {
    background: linear-gradient(135deg, #bbdefb, #c5cae9);
}

.free-period {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--success);
}

.free-period i {
    font-size: 1.5rem;
    margin-bottom: 8px;
    opacity: 0.7;
}

.free-period span {
    font-size: 0.8rem;
    font-weight: 600;
}

/* Today's Classes Styles */
.today-classes {
    padding: 20px;
}

.class-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.class-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
    border-left: 4px solid var(--primary);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.class-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
}

.class-time {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
}

.class-details h4 {
    color: var(--dark);
    margin-bottom: 5px;
    font-size: 1.1rem;
}

.class-details p {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.class-period {
    text-align: right;
}
</style>