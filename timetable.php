
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Admin authentication
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

// CSRF protection
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectPage($timetableId = 0, $unitId = 0) {
    $url = "timetable.php";

    $params = [];

    if ($timetableId > 0) {
        $params["timetable_id"] = $timetableId;
    }

    if ($unitId > 0) {
        $params["course_unit_id"] = $unitId;
    }

    if ($params) {
        $url .= "?" . http_build_query($params);
    }

    header("Location: " . $url);
    exit;
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $_SESSION["error"] = "Invalid request. Please try again.";
        redirectPage(
            (int)($_POST["timetable_id"] ?? 0),
            (int)($_POST["course_unit_id"] ?? 0)
        );
    }

    $action = $_POST["action"] ?? "";

    try {

        // SAVE TIMETABLE ENTRY
        if ($action === "save") {

            $timetableId = (int)($_POST["timetable_id"] ?? 0);
            $unitId = (int)($_POST["course_unit_id"] ?? 0);
            $assignmentId = (int)($_POST["lecturer_assignment_id"] ?? 0);
            $classroomId = (int)($_POST["classroom_id"] ?? 0);

            $day = $_POST["day_of_week"] ?? "";
            $startTime = $_POST["start_time"] ?? "";
            $endTime = $_POST["end_time"] ?? "";

            $days = [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday"
            ];

            if (
                !$timetableId ||
                !$unitId ||
                !$assignmentId ||
                !$classroomId ||
                !in_array($day, $days, true) ||
                !preg_match('/^\d{2}:\d{2}$/', $startTime) ||
                !preg_match('/^\d{2}:\d{2}$/', $endTime) ||
                $startTime >= $endTime
            ) {
                throw new Exception("Please complete all fields correctly.");
            }

            // Verify timetable
            $stmt = $pdo->prepare("
                SELECT id, course_id
                FROM timetables
                WHERE id = ?
            ");
            $stmt->execute([$timetableId]);
            $timetable = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$timetable) {
                throw new Exception("Selected timetable does not exist.");
            }

            // Verify course unit belongs to timetable course
            $stmt = $pdo->prepare("
                SELECT id
                FROM course_units
                WHERE id = ?
                AND course_id = ?
            ");
            $stmt->execute([
                $unitId,
                $timetable["course_id"]
            ]);

            if (!$stmt->fetch()) {
                throw new Exception("Invalid course unit selected.");
            }

            // Verify lecturer assignment belongs to unit
            $stmt = $pdo->prepare("
                SELECT id
                FROM lecturer_unit_assignments
                WHERE id = ?
                AND course_unit_id = ?
            ");
            $stmt->execute([
                $assignmentId,
                $unitId
            ]);

            if (!$stmt->fetch()) {
                throw new Exception("Invalid lecturer assignment.");
            }

            // Verify classroom is available
            $stmt = $pdo->prepare("
                SELECT id
                FROM classrooms
                WHERE id = ?
                AND status = 'Available'
            ");
            $stmt->execute([$classroomId]);

            if (!$stmt->fetch()) {
                throw new Exception("Selected classroom is unavailable.");
            }

            // Save timetable entry
            $stmt = $pdo->prepare("
                INSERT INTO timetable_entries (
                    timetable_id,
                    course_unit_id,
                    lecturer_assignment_id,
                    classroom_id,
                    day_of_week,
                    start_time,
                    end_time
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $timetableId,
                $unitId,
                $assignmentId,
                $classroomId,
                $day,
                $startTime . ":00",
                $endTime . ":00"
            ]);

            $_SESSION["success"] = "Timetable entry saved successfully.";

            redirectPage($timetableId, $unitId);
        }

        // DELETE TIMETABLE ENTRY
        if ($action === "delete") {

            $entryId = (int)($_POST["entry_id"] ?? 0);
            $timetableId = (int)($_POST["timetable_id"] ?? 0);

            $stmt = $pdo->prepare("
                DELETE FROM timetable_entries
                WHERE id = ?
                AND timetable_id = ?
            ");

            $stmt->execute([$entryId, $timetableId]);

            $_SESSION["success"] = "Timetable entry deleted.";

            redirectPage($timetableId);
        }

    } catch (Throwable $ex) {

        $_SESSION["error"] = $ex->getMessage();

        redirectPage(
            (int)($_POST["timetable_id"] ?? 0),
            (int)($_POST["course_unit_id"] ?? 0)
        );
    }
}

// Flash messages
$success = $_SESSION["success"] ?? "";
$error = $_SESSION["error"] ?? "";

unset($_SESSION["success"], $_SESSION["error"]);

// Load timetables
$stmt = $pdo->query("
    SELECT id, course_id, semester, academic_year
    FROM timetables
    ORDER BY id DESC
");

$timetables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Selected timetable
$selectedTimetableId = (int)($_GET["timetable_id"] ?? 0);

if (!$selectedTimetableId && !empty($timetables)) {
    $selectedTimetableId = (int)$timetables[0]["id"];
}

$selectedTimetable = null;

foreach ($timetables as $item) {
    if ((int)$item["id"] === $selectedTimetableId) {
        $selectedTimetable = $item;
        break;
    }
}

// Load course units belonging to selected course
$courseUnits = [];

if ($selectedTimetable) {

    $stmt = $pdo->prepare("
        SELECT id, unit_code, unit_name, semester
        FROM course_units
        WHERE course_id = ?
        ORDER BY unit_code
    ");

    $stmt->execute([$selectedTimetable["course_id"]]);

    $courseUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Selected course unit
$selectedUnitId = (int)($_GET["course_unit_id"] ?? 0);

$validUnitIds = array_column($courseUnits, "id");

if (
    $selectedUnitId &&
    !in_array($selectedUnitId, $validUnitIds)
) {
    $selectedUnitId = 0;
}

if (!$selectedUnitId && !empty($courseUnits)) {
    $selectedUnitId = (int)$courseUnits[0]["id"];
}

// Load lecturers assigned to selected unit
$lecturers = [];

if ($selectedUnitId) {

    $stmt = $pdo->prepare("
        SELECT
            lua.id,
            lua.staff_id,
            s.full_name
        FROM lecturer_unit_assignments lua
        JOIN staff s ON s.id = lua.staff_id
        WHERE lua.course_unit_id = ?
        ORDER BY s.full_name
    ");

    $stmt->execute([$selectedUnitId]);

    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Load available classrooms
$stmt = $pdo->query("
    SELECT id, room_name, capacity, room_type
    FROM classrooms
    WHERE status = 'Available'
    ORDER BY room_name
");

$classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load saved timetable entries
$stmt = $pdo->query("
    SELECT
        te.id,
        te.timetable_id,
        te.day_of_week,
        te.start_time,
        te.end_time,

        t.semester,
        t.academic_year,

        cu.unit_code,
        cu.unit_name,

        s.full_name AS lecturer,

        c.room_name

    FROM timetable_entries te

    JOIN timetables t
        ON t.id = te.timetable_id

    JOIN course_units cu
        ON cu.id = te.course_unit_id

    JOIN lecturer_unit_assignments lua
        ON lua.id = te.lecturer_assignment_id

    JOIN staff s
        ON s.id = lua.staff_id

    JOIN classrooms c
        ON c.id = te.classroom_id

    ORDER BY
        FIELD(
            te.day_of_week,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        ),
        te.start_time
");

$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Timetable Management | Vincent College</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.sidebar {
    position: fixed;
    width: 230px;
    height: 100vh;
    background: #17365d;
    color: white;
    padding: 25px 15px;
}

.sidebar h2 {
    font-size: 20px;
    margin-bottom: 30px;
}

.sidebar a {
    display: block;
    padding: 13px;
    margin-bottom: 8px;
    text-decoration: none;
    color: white;
    border-radius: 6px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #294d78;
}

.main {
    margin-left: 230px;
    padding: 30px;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    margin: 0 0 8px;
    color: #17365d;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.card h2 {
    color: #17365d;
    margin-top: 0;
    font-size: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: 600;
    margin-bottom: 7px;
}

input,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: white;
    font-size: 14px;
}

input:focus,
select:focus {
    outline: none;
    border-color: #17365d;
}

button {
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.save-btn {
    background: #17365d;
    color: white;
    margin-top: 20px;
}

.save-btn:hover {
    background: #294d78;
}

.delete-btn {
    background: #dc2626;
    color: white;
    padding: 7px 12px;
}

.alert {
    padding: 13px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 14px;
}

th {
    background: #17365d;
    color: white;
}

tr:hover td {
    background: #f8fafc;
}

.badge {
    display: inline-block;
    background: #dcfce7;
    color: #166534;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
}

.empty {
    padding: 20px;
    text-align: center;
    color: #64748b;
}

@media(max-width: 850px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .sidebar a {
        display: inline-block;
        margin-right: 5px;
    }

    .main {
        margin-left: 0;
        padding: 15px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

}

</style>
</head>

<body>

<div class="sidebar">

    <h2>Vincent College</h2>

    <a href="dashboard.php">Dashboard</a>

    <a href="timetable.php" class="active">
        Timetable
    </a>

    <a href="../logout.php">Logout</a>

</div>

<div class="main">

    <div class="header">

        <h1>Timetable Management</h1>

        <p>Create, manage and view class schedules.</p>

    </div>

    <?php if ($success): ?>
        <div class="alert success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>


    <!-- TIMETABLE AND COURSE UNIT SELECTION -->

    <div class="card">

        <h2>Select Timetable and Course Unit</h2>

        <form method="GET" action="timetable.php">

            <div class="form-grid">

                <div class="form-group">

                    <label>Timetable</label>

                    <select
                        name="timetable_id"
                        onchange="this.form.submit()"
                        required
                    >

                        <option value="">Select timetable</option>

                        <?php foreach ($timetables as $t): ?>

                            <option
                                value="<?= (int)$t["id"] ?>"
                                <?= $selectedTimetableId == $t["id"] ? "selected" : "" ?>
                            >

                                <?= e($t["semester"]) ?>
                                -
                                <?= e($t["academic_year"]) ?>
                                (ID <?= (int)$t["id"] ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Course Unit</label>

                    <select
                        name="course_unit_id"
                        onchange="this.form.submit()"
                        required
                    >

                        <option value="">Select course unit</option>

                        <?php foreach ($courseUnits as $unit): ?>

                            <option
                                value="<?= (int)$unit["id"] ?>"
                                <?= $selectedUnitId == $unit["id"] ? "selected" : "" ?>
                            >

                                <?= e($unit["unit_code"]) ?>
                                -
                                <?= e($unit["unit_name"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

        </form>

    </div>


    <!-- ADD TIMETABLE ENTRY -->

    <div class="card">

        <h2>Add Timetable Entry</h2>

        <?php if (!$selectedTimetable): ?>

            <p>No timetable available. Create a timetable first.</p>

        <?php elseif (!$courseUnits): ?>

            <p>No course units found for this course.</p>

        <?php elseif (!$lecturers): ?>

            <p>No lecturer has been assigned to this course unit.</p>

        <?php elseif (!$classrooms): ?>

            <p>No available classrooms found.</p>

        <?php else: ?>

            <form method="POST" action="timetable.php">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION["csrf_token"]) ?>">

                <input type="hidden" name="action" value="save">

                <input type="hidden" name="timetable_id"
                    value="<?= (int)$selectedTimetableId ?>">

                <input type="hidden" name="course_unit_id"
                    value="<?= (int)$selectedUnitId ?>">

                <div class="form-grid">

                    <div class="form-group">

                        <label>Lecturer</label>

                        <select name="lecturer_assignment_id" required>

                            <option value="">Select lecturer</option>

                            <?php foreach ($lecturers as $lecturer): ?>

                                <option value="<?= (int)$lecturer["id"] ?>">

                                    <?= e($lecturer["full_name"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Classroom</label>

                        <select name="classroom_id" required>

                            <option value="">Select classroom</option>

                            <?php foreach ($classrooms as $room): ?>

                                <option value="<?= (int)$room["id"] ?>">

                                    <?= e($room["room_name"]) ?>
                                    (Capacity: <?= (int)$room["capacity"] ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Day of Week</label>

                        <select name="day_of_week" required>

                            <option value="">Select day</option>

                            <option>Monday</option>
                            <option>Tuesday</option>
                            <option>Wednesday</option>
                            <option>Thursday</option>
                            <option>Friday</option>
                            <option>Saturday</option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Start Time</label>

                        <input
                            type="time"
                            name="start_time"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>End Time</label>

                        <input
                            type="time"
                            name="end_time"
                            required
                        >

                    </div>

                </div>

                <button type="submit" class="save-btn">
                    Save Timetable Entry
                </button>

            </form>

        <?php endif; ?>

    </div>


    <!-- SAVED TIMETABLE ENTRIES -->

    <div class="card">

        <h2>Saved Timetable Entries</h2>

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Unit Code</th>
                        <th>Course Unit</th>
                        <th>Lecturer</th>
                        <th>Classroom</th>
                        <th>Day</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($entries): ?>

                    <?php foreach ($entries as $entry): ?>

                        <tr>

                            <td><?= (int)$entry["id"] ?></td>

                            <td><?= e($entry["semester"]) ?></td>

                            <td><?= e($entry["academic_year"]) ?></td>

                            <td><?= e($entry["unit_code"]) ?></td>

                            <td><?= e($entry["unit_name"]) ?></td>

                            <td><?= e($entry["lecturer"]) ?></td>

                            <td><?= e($entry["room_name"]) ?></td>

                            <td><?= e($entry["day_of_week"]) ?></td>

                            <td><?= date("H:i", strtotime($entry["start_time"])) ?></td>

                            <td><?= date("H:i", strtotime($entry["end_time"])) ?></td>

                            <td>

                                <form method="POST"
                                    action="timetable.php"
                                    onsubmit="return confirm('Delete this timetable entry?');">

                                    <input type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION["csrf_token"]) ?>">

                                    <input type="hidden"
                                        name="action"
                                        value="delete">

                                    <input type="hidden"
                                        name="entry_id"
                                        value="<?= (int)$entry["id"] ?>">

                                    <input type="hidden"
                                        name="timetable_id"
                                        value="<?= (int)$entry["timetable_id"] ?>">

                                    <button type="submit" class="delete-btn">
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="11" class="empty">
                            No timetable entries have been saved.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
