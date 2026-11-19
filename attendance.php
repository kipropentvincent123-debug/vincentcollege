<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["attendance_csrf"])) {
    $_SESSION["attendance_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["attendance_csrf"];
$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Load timetable entries
|--------------------------------------------------------------------------
*/
try {
    $entriesStmt = $pdo->query("
        SELECT
            te.id,
            te.day_of_week,
            te.start_time,
            te.end_time,
            t.course_id,
            t.semester,
            t.academic_year,
            cu.unit_code,
            cu.unit_name,
            c.course_name,
            c.course_code,
            cl.room_name,
            st.full_name AS lecturer_name
        FROM timetable_entries te
        INNER JOIN timetables t
            ON t.id = te.timetable_id
        INNER JOIN course_units cu
            ON cu.id = te.course_unit_id
        INNER JOIN courses c
            ON c.id = t.course_id
        LEFT JOIN classrooms cl
            ON cl.id = te.classroom_id
        LEFT JOIN lecturer_unit_assignments lua
            ON lua.id = te.lecturer_assignment_id
        LEFT JOIN staff st
            ON st.id = lua.staff_id
        ORDER BY
            c.course_name,
            cu.unit_name,
            te.day_of_week,
            te.start_time
    ");

    $entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log($ex->getMessage());
    die("Unable to load timetable entries. Please check your database structure.");
}

/*
|--------------------------------------------------------------------------
| Selected timetable entry and date
|--------------------------------------------------------------------------
*/
$entryId = isset($_POST["entry_id"])
    ? (int)$_POST["entry_id"]
    : (isset($_GET["entry_id"]) ? (int)$_GET["entry_id"] : 0);

$attendanceDate = $_POST["attendance_date"]
    ?? $_GET["attendance_date"]
    ?? date("Y-m-d");

$selectedEntry = null;

foreach ($entries as $entry) {
    if ((int)$entry["id"] === $entryId) {
        $selectedEntry = $entry;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Validate attendance date
|--------------------------------------------------------------------------
*/
function validDate($date)
{
    $d = DateTime::createFromFormat("Y-m-d", $date);

    return $d && $d->format("Y-m-d") === $date;
}

/*
|--------------------------------------------------------------------------
| Save attendance
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_attendance"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($csrfToken, $_POST["csrf_token"])
    ) {
        $error = "Security check failed. Please refresh the page and try again.";

    } elseif (!$selectedEntry) {
        $error = "Please select a valid timetable entry.";

    } elseif (!validDate($attendanceDate)) {
        $error = "Please select a valid attendance date.";

    } else {
        try {
            /*
             * Get active students registered for this course.
             */
            $studentsStmt = $pdo->prepare("
                SELECT id
                FROM students
                WHERE course_id = ?
                  AND status = 'active'
            ");

            $studentsStmt->execute([$selectedEntry["course_id"]]);
            $validStudentIds = $studentsStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($validStudentIds)) {
                $error = "There are no active students registered for this course.";

            } else {
                $allowedStatuses = [
                    "Present",
                    "Absent",
                    "Late",
                    "Excused"
                ];

                $statuses = $_POST["status"] ?? [];
                $remarks = $_POST["remarks"] ?? [];

                $pdo->beginTransaction();

                $saveStmt = $pdo->prepare("
                    INSERT INTO attendance (
                        student_id,
                        timetable_entry_id,
                        attendance_date,
                        status,
                        recorded_by,
                        remarks
                    ) VALUES (?, ?, ?, ?, NULL, ?)
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        remarks = VALUES(remarks),
                        recorded_by = NULL
                ");

                $savedCount = 0;

                foreach ($validStudentIds as $studentId) {
                    $studentId = (int)$studentId;

                    $status = $statuses[$studentId] ?? "Present";

                    if (!in_array($status, $allowedStatuses, true)) {
                        $status = "Present";
                    }

                    $remark = trim($remarks[$studentId] ?? "");

                    $saveStmt->execute([
                        $studentId,
                        $entryId,
                        $attendanceDate,
                        $status,
                        $remark !== "" ? $remark : null
                    ]);

                    $savedCount++;
                }

                $pdo->commit();

                $_SESSION["attendance_flash"] =
                    "Attendance saved successfully for {$savedCount} students.";

                header(
                    "Location: attendance.php?entry_id=" .
                    $entryId .
                    "&attendance_date=" .
                    urlencode($attendanceDate)
                );
                exit;
            }

        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($ex->getMessage());
            $error = "Unable to save attendance. Please check the database and try again.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Display success message
|--------------------------------------------------------------------------
*/
if (isset($_SESSION["attendance_flash"])) {
    $message = $_SESSION["attendance_flash"];
    unset($_SESSION["attendance_flash"]);
}

/*
|--------------------------------------------------------------------------
| Load students and their attendance for selected entry/date
|--------------------------------------------------------------------------
*/
$students = [];

if ($selectedEntry && validDate($attendanceDate)) {
    $studentsStmt = $pdo->prepare("
        SELECT
            s.id,
            s.admission_number,
            s.first_name,
            s.last_name,
            a.status AS attendance_status,
            a.remarks
        FROM students s
        LEFT JOIN attendance a
            ON a.student_id = s.id
            AND a.timetable_entry_id = ?
            AND a.attendance_date = ?
        WHERE s.course_id = ?
          AND s.status = 'active'
        ORDER BY s.first_name, s.last_name
    ");

    $studentsStmt->execute([
        $entryId,
        $attendanceDate,
        $selectedEntry["course_id"]
    ]);

    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance Management | Vincent College</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #263238;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: #17365d;
            color: white;
            padding: 22px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0 0 8px;
        }

        .panel {
            background: white;
            padding: 22px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        select,
        input[type="date"],
        input[type="text"] {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccd2d8;
            border-radius: 5px;
            font-size: 14px;
            background: white;
        }

        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        button {
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-primary {
            background: #1769aa;
            color: white;
        }

        .btn-success {
            background: #198754;
            color: white;
        }

        .btn-primary:hover,
        .btn-success:hover {
            opacity: 0.9;
        }

        .message {
            background: #d1e7dd;
            color: #0f5132;
            padding: 13px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 13px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .entry-details {
            background: #eef5fb;
            border-left: 4px solid #1769aa;
            padding: 15px;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e3e7eb;
        }

        th {
            background: #17365d;
            color: white;
        }

        tr:hover {
            background: #f8fafc;
        }

        .status-select {
            min-width: 120px;
        }

        .remarks-input {
            min-width: 150px;
        }

        .summary {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .summary-item {
            background: #eef5fb;
            padding: 12px 18px;
            border-radius: 5px;
        }

        .back-link {
            color: #1769aa;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            body {
                padding: 10px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h2>Student Attendance Management</h2>
        <p>Vincent College - Record and manage class attendance.</p>
    </div>

    <p>
        <a class="back-link" href="dashboard.php">
            &larr; Back to Admin Dashboard
        </a>
    </p>

    <?php if ($message): ?>
        <div class="message"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="panel">
        <h3>Select Class and Attendance Date</h3>

        <form method="GET" action="attendance.php">
            <div class="filters">

                <div>
                    <label for="entry_id">Timetable Entry / Class</label>

                    <select name="entry_id" id="entry_id" required>
                        <option value="">-- Select timetable entry --</option>

                        <?php foreach ($entries as $entry): ?>
                            <option
                                value="<?= (int)$entry["id"] ?>"
                                <?= $entryId === (int)$entry["id"] ? "selected" : "" ?>
                            >
                                <?= e($entry["course_code"]) ?>
                                -
                                <?= e($entry["unit_code"]) ?>
                                -
                                <?= e($entry["unit_name"]) ?>
                                |
                                <?= e($entry["day_of_week"]) ?>
                                <?= e(substr($entry["start_time"], 0, 5)) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="attendance_date">Attendance Date</label>
                    <input
                        type="date"
                        name="attendance_date"
                        id="attendance_date"
                        value="<?= e($attendanceDate) ?>"
                        required
                    >
                </div>

                <div>
                    <button type="submit" class="btn-primary">
                        Load Students
                    </button>
                </div>

            </div>
        </form>
    </div>

    <?php if ($selectedEntry): ?>

        <div class="panel">

            <h3>Selected Class Details</h3>

            <div class="entry-details">
                <strong>Course:</strong>
                <?= e($selectedEntry["course_name"]) ?>
                (<?= e($selectedEntry["course_code"]) ?>)
                <br>

                <strong>Unit:</strong>
                <?= e($selectedEntry["unit_name"]) ?>
                (<?= e($selectedEntry["unit_code"]) ?>)
                <br>

                <strong>Semester:</strong>
                <?= e($selectedEntry["semester"]) ?>
                <br>

                <strong>Academic Year:</strong>
                <?= e($selectedEntry["academic_year"]) ?>
                <br>

                <strong>Lecturer:</strong>
                <?= e($selectedEntry["lecturer_name"] ?: "Not assigned") ?>
                <br>

                <strong>Classroom:</strong>
                <?= e($selectedEntry["room_name"] ?: "Not assigned") ?>
                <br>

                <strong>Day:</strong>
                <?= e($selectedEntry["day_of_week"]) ?>
                <br>

                <strong>Time:</strong>
                <?= e(substr($selectedEntry["start_time"], 0, 5)) ?>
                -
                <?= e(substr($selectedEntry["end_time"], 0, 5)) ?>
                <br>

                <strong>Attendance Date:</strong>
                <?= e($attendanceDate) ?>
            </div>

            <h3>Class Register</h3>

            <?php if (empty($students)): ?>

                <div class="error">
                    No active students are registered under this course.
                </div>

            <?php else: ?>

                <div class="summary">
                    <div class="summary-item">
                        <strong>Total Students:</strong>
                        <?= count($students) ?>
                    </div>

                    <div class="summary-item">
                        <strong>Already Recorded:</strong>
                        <?= count(array_filter($students, function ($s) {
                            return !empty($s["attendance_status"]);
                        })) ?>
                    </div>
                </div>

                <form method="POST" action="attendance.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="entry_id"
                        value="<?= (int)$entryId ?>"
                    >

                    <input
                        type="hidden"
                        name="attendance_date"
                        value="<?= e($attendanceDate) ?>"
                    >

                    <div class="table-wrapper">

                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Admission Number</th>
                                    <th>Student Name</th>
                                    <th>Attendance Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($students as $index => $student): ?>

                                    <tr>
                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= e($student["admission_number"]) ?>
                                        </td>

                                        <td>
                                            <?= e($student["first_name"] . " " . $student["last_name"]) ?>
                                        </td>

                                        <td>
                                            <select
                                                class="status-select"
                                                name="status[<?= (int)$student["id"] ?>]"
                                            >
                                                <?php
                                                $currentStatus =
                                                    $student["attendance_status"] ?: "Present";

                                                $statuses = [
                                                    "Present",
                                                    "Absent",
                                                    "Late",
                                                    "Excused"
                                                ];
                                                ?>

                                                <?php foreach ($statuses as $status): ?>
                                                    <option
                                                        value="<?= e($status) ?>"
                                                        <?= $currentStatus === $status ? "selected" : "" ?>
                                                    >
                                                        <?= e($status) ?>
                                                    </option>
                                                <?php endforeach; ?>

                                            </select>
                                        </td>

                                        <td>
                                            <input
                                                class="remarks-input"
                                                type="text"
                                                name="remarks[<?= (int)$student["id"] ?>]"
                                                value="<?= e($student["remarks"] ?? "") ?>"
                                                placeholder="Optional remarks"
                                            >
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>

                    </div>

                    <br>

                    <button
                        type="submit"
                        name="save_attendance"
                        class="btn-success"
                    >
                        Save Attendance
                    </button>

                </form>

            <?php endif; ?>

        </div>

    <?php else: ?>

        <div class="panel">
            <p>
                Select a timetable entry and attendance date to load the
                students registered for that course.
            </p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

