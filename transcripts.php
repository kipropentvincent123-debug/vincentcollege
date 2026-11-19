
<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Grading scale
function getGrade($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 70) return 'B';
    if ($marks >= 60) return 'C';
    if ($marks >= 50) return 'D';
    return 'E';
}

function getRemark($marks) {
    if ($marks >= 80) return 'Excellent';
    if ($marks >= 70) return 'Very Good';
    if ($marks >= 60) return 'Good';
    if ($marks >= 50) return 'Pass';
    return 'Fail';
}

$admissionNumber = trim($_GET['admission_number'] ?? '');

$student = null;
$results = [];
$periods = [];
$overallTotal = 0;
$overallCount = 0;
$overallAverage = null;

if ($admissionNumber !== '') {

    // Find student
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.admission_number,
            s.first_name,
            s.last_name,
            s.gender,
            s.admission_date,
            s.status,
            c.course_name,
            c.course_code
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        WHERE s.admission_number = ?
        LIMIT 1
    ");

    $stmt->execute([$admissionNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {

        // Retrieve all recorded examination results
        $stmt = $pdo->prepare("
            SELECT
                cu.unit_code,
                cu.unit_name,
                e.exam_name,
                e.academic_year,
                e.semester,
                e.exam_date,
                em.marks
            FROM exam_marks em
            INNER JOIN examinations e
                ON e.id = em.examination_id
            INNER JOIN course_units cu
                ON cu.id = em.course_unit_id
            WHERE em.student_id = ?
            ORDER BY
                e.academic_year,
                e.semester,
                cu.unit_code,
                e.exam_date
        ");

        $stmt->execute([$student['id']]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group results by academic year and semester
        foreach ($results as $result) {

            $key = $result['academic_year'] . '|' .
                   $result['semester'];

            if (!isset($periods[$key])) {
                $periods[$key] = [
                    'academic_year' => $result['academic_year'],
                    'semester' => $result['semester'],
                    'results' => [],
                    'total' => 0,
                    'count' => 0
                ];
            }

            $periods[$key]['results'][] = $result;

            $periods[$key]['total'] += (float)$result['marks'];
            $periods[$key]['count']++;

            $overallTotal += (float)$result['marks'];
            $overallCount++;
        }

        if ($overallCount > 0) {
            $overallAverage = $overallTotal / $overallCount;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Student Transcript | Vincent College</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f3f6fa;
    margin: 0;
    padding: 25px;
    color: #243247;
}

.container {
    max-width: 1100px;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 3px 12px #ddd;
}

header {
    text-align: center;
    border-bottom: 3px solid #17365d;
    padding-bottom: 18px;
    margin-bottom: 25px;
}

header h1 {
    color: #17365d;
    margin-bottom: 5px;
}

header p {
    margin: 5px;
}

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
}

input {
    padding: 12px;
    border: 1px solid #ccd4df;
    border-radius: 5px;
    flex: 1;
}

button, .back {
    padding: 12px 20px;
    background: #1769aa;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
}

.student-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    background: #f4f7fb;
    padding: 18px;
    margin-bottom: 25px;
    border-radius: 7px;
}

.section-title {
    background: #17365d;
    color: white;
    padding: 12px;
    margin-top: 25px;
    border-radius: 5px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
}

th, td {
    padding: 11px;
    border: 1px solid #ddd;
    text-align: left;
}

th {
    background: #edf2f7;
}

.summary {
    background: #f4f7fb;
    padding: 15px;
    margin-top: 15px;
    border-radius: 6px;
}

.overall {
    background: #e8f0fa;
    padding: 20px;
    margin-top: 25px;
    border-left: 5px solid #17365d;
}

.message {
    padding: 15px;
    background: #fff0f0;
    color: #a11;
    border-radius: 5px;
}

.actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

@media(max-width: 650px) {
    body {
        padding: 10px;
    }

    .container {
        padding: 15px;
    }

    .student-info {
        grid-template-columns: 1fr;
    }

    .search-form {
        flex-direction: column;
    }

    .table-wrapper {
        overflow-x: auto;
    }
}

@media print {
    body {
        background: white;
        padding: 0;
    }

    .container {
        box-shadow: none;
        max-width: 100%;
        padding: 10px;
    }

    .no-print {
        display: none !important;
    }

    .section-title {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
    }

    .overall {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
</head>

<body>

<div class="container">

<header>
    <h1>VINCENT COLLEGE</h1>
    <p>STUDENT ACADEMIC TRANSCRIPT</p>
    <p>Examinations and Results Department</p>
</header>

<div class="no-print">

    <form method="GET" class="search-form">
        <input
            type="text"
            name="admission_number"
            placeholder="Enter student admission number"
            value="<?= h($admissionNumber) ?>"
            required
        >

        <button type="submit">Search Student</button>
    </form>

    <a href="index.php" class="back">Back to Examinations</a>

</div>

<?php if ($admissionNumber !== '' && !$student): ?>

    <div class="message">
        No student found with admission number
        <?= h($admissionNumber) ?>.
    </div>

<?php elseif ($student): ?>

    <h2>Student Information</h2>

    <div class="student-info">

        <div>
            <strong>Admission Number:</strong>
            <?= h($student['admission_number']) ?>
        </div>

        <div>
            <strong>Student Name:</strong>
            <?= h($student['first_name'] . ' ' . $student['last_name']) ?>
        </div>

        <div>
            <strong>Gender:</strong>
            <?= h($student['gender']) ?>
        </div>

        <div>
            <strong>Course:</strong>
            <?= h($student['course_name']) ?>
        </div>

        <div>
            <strong>Course Code:</strong>
            <?= h($student['course_code']) ?>
        </div>

        <div>
            <strong>Admission Date:</strong>
            <?= h($student['admission_date']) ?>
        </div>

    </div>

    <?php if (count($results) > 0): ?>

        <div class="actions no-print">
            <button onclick="window.print()">
                Print Transcript
            </button>
        </div>

        <?php foreach ($periods as $period): ?>

            <h3 class="section-title">
                Academic Year:
                <?= h($period['academic_year']) ?>
                |
                <?= h($period['semester']) ?>
            </h3>

            <div class="table-wrapper">

            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Unit Code</th>
                        <th>Unit Name</th>
                        <th>Examination</th>
                        <th>Exam Date</th>
                        <th>Marks</th>
                        <th>Grade</th>
                        <th>Remark</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($period['results'] as $index => $result): ?>

                    <?php $mark = (float)$result['marks']; ?>

                    <tr>
                        <td><?= $index + 1 ?></td>

                        <td><?= h($result['unit_code']) ?></td>

                        <td><?= h($result['unit_name']) ?></td>

                        <td><?= h($result['exam_name']) ?></td>

                        <td><?= h($result['exam_date']) ?></td>

                        <td><?= number_format($mark, 2) ?></td>

                        <td><?= getGrade($mark) ?></td>

                        <td><?= getRemark($mark) ?></td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

            </div>

            <?php
            $semesterAverage =
                $period['total'] / $period['count'];
            ?>

            <div class="summary">

                <strong>Recorded Examination Entries:</strong>
                <?= $period['count'] ?>

                <br>

                <strong>Semester Average:</strong>
                <?= number_format($semesterAverage, 2) ?>%

                <br>

                <strong>Semester Grade:</strong>
                <?= getGrade($semesterAverage) ?>

                <br>

                <strong>Remark:</strong>
                <?= getRemark($semesterAverage) ?>

            </div>

        <?php endforeach; ?>

        <div class="overall">

            <h3>Overall Academic Performance</h3>

            <p>
                <strong>Total Recorded Examination Entries:</strong>
                <?= $overallCount ?>
            </p>

            <p>
                <strong>Overall Average:</strong>
                <?= number_format($overallAverage, 2) ?>%
            </p>

            <p>
                <strong>Overall Grade:</strong>
                <?= getGrade($overallAverage) ?>
            </p>

            <p>
                <strong>Overall Remark:</strong>
                <?= getRemark($overallAverage) ?>
            </p>

        </div>

    <?php else: ?>

        <div class="message">
            No examination marks have been recorded
            for this student yet.
        </div>

    <?php endif; ?>

<?php endif; ?>

</div>

</body>
</html>
