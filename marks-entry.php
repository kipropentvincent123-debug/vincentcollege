
<?php
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get selected values
$examinationId = (int)($_POST['examination_id'] ?? $_GET['examination_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);
$unitId = (int)($_POST['course_unit_id'] ?? $_GET['course_unit_id'] ?? 0);

// Load examinations
$examinations = $pdo->query("
    SELECT id, exam_name, academic_year, semester
    FROM examinations
    ORDER BY exam_date DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Load courses
$courses = $pdo->query("
    SELECT id, course_name
    FROM courses
    ORDER BY course_name
")->fetchAll(PDO::FETCH_ASSOC);

// Load units for selected course
$units = [];

if ($courseId > 0) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            unit_code,
            unit_name,
            semester,
            academic_year
        FROM course_units
        WHERE course_id = ?
        ORDER BY semester, unit_code
    ");

    $stmt->execute([$courseId]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Save marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {

    $studentIds = $_POST['student_id'] ?? [];
    $marksList = $_POST['marks'] ?? [];

    if (!$examinationId || !$courseId || !$unitId) {

        $message = 'Please select examination, course and unit.';
        $messageType = 'error';

    } elseif (count($studentIds) === 0) {

        $message = 'No students available for marks entry.';
        $messageType = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            // Verify selected unit belongs to course
            $unitCheck = $pdo->prepare("
                SELECT id
                FROM course_units
                WHERE id = ? AND course_id = ?
            ");

            $unitCheck->execute([$unitId, $courseId]);

            if (!$unitCheck->fetch()) {
                throw new Exception(
                    'The selected unit does not belong to the selected course.'
                );
            }

            // Verify examination
            $examCheck = $pdo->prepare("
                SELECT id
                FROM examinations
                WHERE id = ?
            ");

            $examCheck->execute([$examinationId]);

            if (!$examCheck->fetch()) {
                throw new Exception('Invalid examination selected.');
            }

            // Verify students
            $studentCheck = $pdo->prepare("
                SELECT id
                FROM students
                WHERE id = ?
                  AND course_id = ?
                  AND status = 'active'
            ");

            // Check existing marks
            $existingCheck = $pdo->prepare("
                SELECT id
                FROM exam_marks
                WHERE examination_id = ?
                  AND student_id = ?
                  AND course_unit_id = ?
            ");

            // Insert new marks
            $insert = $pdo->prepare("
                INSERT INTO exam_marks
                (
                    examination_id,
                    student_id,
                    course_unit_id,
                    marks
                )
                VALUES (?, ?, ?, ?)
            ");

            // Update existing marks
            $update = $pdo->prepare("
                UPDATE exam_marks
                SET marks = ?
                WHERE examination_id = ?
                  AND student_id = ?
                  AND course_unit_id = ?
            ");

            $saved = 0;

            foreach ($studentIds as $index => $studentId) {

                $studentId = (int)$studentId;
                $mark = trim($marksList[$index] ?? '');

                // Skip empty marks
                if ($mark === '') {
                    continue;
                }

                // Validate marks
                if (
                    !is_numeric($mark) ||
                    $mark < 0 ||
                    $mark > 100
                ) {
                    throw new Exception(
                        'Marks must be between 0 and 100.'
                    );
                }

                // Verify student belongs to selected course
                $studentCheck->execute([$studentId, $courseId]);

                if (!$studentCheck->fetch()) {
                    throw new Exception(
                        'An invalid or inactive student was selected.'
                    );
                }

                // Check whether marks already exist
                $existingCheck->execute([
                    $examinationId,
                    $studentId,
                    $unitId
                ]);

                if ($existingCheck->fetch()) {

                    // Update existing marks
                    $update->execute([
                        $mark,
                        $examinationId,
                        $studentId,
                        $unitId
                    ]);

                } else {

                    // Insert new marks
                    $insert->execute([
                        $examinationId,
                        $studentId,
                        $unitId,
                        $mark
                    ]);
                }

                $saved++;
            }

            $pdo->commit();

            $message = "$saved student mark(s) saved successfully.";
            $messageType = 'success';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Load active students and their existing marks
$students = [];

if ($courseId > 0 && $unitId > 0 && $examinationId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.admission_number,
            s.first_name,
            s.last_name,
            em.marks

        FROM students s

        LEFT JOIN exam_marks em
            ON em.student_id = s.id
            AND em.examination_id = ?
            AND em.course_unit_id = ?

        WHERE s.course_id = ?
          AND s.status = 'active'

        ORDER BY s.first_name, s.last_name
    ");

    $stmt->execute([
        $examinationId,
        $unitId,
        $courseId
    ]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Students Examination Marks Entry</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f3f6fa;
    margin: 0;
    padding: 20px;
    color: #243247;
}

.container {
    max-width: 1100px;
    margin: auto;
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}

h2 {
    margin-top: 0;
    color: #17365d;
}

.filters {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

select,
input {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccd4df;
    border-radius: 5px;
}

button {
    background: #1769aa;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

button:hover {
    background: #104d80;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e0e5eb;
    text-align: left;
}

th {
    background: #17365d;
    color: white;
}

.message {
    padding: 12px;
    margin: 15px 0;
    border-radius: 5px;
}

.success {
    background: #d9f2df;
    color: #17632b;
}

.error {
    background: #fce0e0;
    color: #9b2020;
}

.save-button {
    margin-top: 20px;
}

.table-wrapper {
    overflow-x: auto;
}

@media(max-width:700px) {

    .filters {
        grid-template-columns: 1fr;
    }

    .container {
        padding: 15px;
    }

}

</style>

</head>

<body>

<div class="container">

<h2>Students' Examination Marks Entry</h2>

<?php if ($message): ?>

<div class="message <?= h($messageType) ?>">
    <?= h($message) ?>
</div>

<?php endif; ?>


<!-- EXAMINATION, COURSE AND UNIT SELECTION -->

<form method="GET">

<div class="filters">

    <!-- Examination -->

    <div>

        <label>Examination</label>

        <select
            name="examination_id"
            required
            onchange="this.form.submit()"
        >

            <option value="">Select Examination</option>

            <?php foreach ($examinations as $exam): ?>

                <option
                    value="<?= (int)$exam['id'] ?>"
                    <?= $examinationId === (int)$exam['id']
                        ? 'selected' : '' ?>
                >

                    <?= h($exam['exam_name']) ?> -
                    <?= h($exam['academic_year']) ?> -
                    <?= h($exam['semester']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <!-- Course -->

    <div>

        <label>Course</label>

        <select
            name="course_id"
            required
            onchange="this.form.submit()"
        >

            <option value="">Select Course</option>

            <?php foreach ($courses as $course): ?>

                <option
                    value="<?= (int)$course['id'] ?>"
                    <?= $courseId === (int)$course['id']
                        ? 'selected' : '' ?>
                >

                    <?= h($course['course_name']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <!-- Unit -->

    <div>

        <label>Unit / Semester</label>

        <select
    name="course_unit_id"
    required
    onchange="this.form.submit()"
>

            <option value="">Select Unit</option>

            <?php foreach ($units as $unit): ?>

                <option
                    value="<?= (int)$unit['id'] ?>"
                    <?= $unitId === (int)$unit['id']
                        ? 'selected' : '' ?>
                >

                    <?= h($unit['unit_code']) ?> -
                    <?= h($unit['unit_name']) ?> -
                    <?= h($unit['semester']) ?> -
                    <?= h($unit['academic_year']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>

</div>

<button type="submit">
    Load Students
</button>

</form>


<!-- STUDENTS MARKS TABLE -->

<?php if ($examinationId && $courseId && $unitId): ?>

    <?php if (count($students) > 0): ?>

        <form method="POST">

            <input
                type="hidden"
                name="examination_id"
                value="<?= $examinationId ?>"
            >

            <input
                type="hidden"
                name="course_id"
                value="<?= $courseId ?>"
            >

            <input
                type="hidden"
                name="course_unit_id"
                value="<?= $unitId ?>"
            >

            <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>No.</th>
                        <th>Admission Number</th>
                        <th>Student Name</th>
                        <th>Marks (0–100)</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($students as $index => $student): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= h($student['admission_number']) ?>
                            </td>

                            <td>
                                <?= h(
                                    $student['first_name'] . ' ' .
                                    $student['last_name']
                                ) ?>
                            </td>

                            <td>

                                <input
                                    type="hidden"
                                    name="student_id[]"
                                    value="<?= (int)$student['id'] ?>"
                                >

                                <input
                                    type="number"
                                    name="marks[]"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?= $student['marks'] !== null
                                        ? h($student['marks']) : '' ?>"
                                    placeholder="Enter marks"
                                >

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

            </div>

            <button
                type="submit"
                name="save_marks"
                value="1"
                class="save-button"
            >

                Save Students' Marks

            </button>

        </form>

    <?php else: ?>

        <p>No active students found for this course.</p>

    <?php endif; ?>

<?php endif; ?>

</div>

</body>
</html>
