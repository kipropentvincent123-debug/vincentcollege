
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Administrator access protection
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}

// Unique function names to avoid redeclaration errors
if (!function_exists('fb_h')) {
    function fb_h($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('fb_money')) {
    function fb_money($amount) {
        return 'KSh ' . number_format((float)$amount, 2);
    }
}

if (!function_exists('fb_payment_status')) {
    function fb_payment_status($feeCount, $totalFee, $totalPaid) {

        if ($feeCount <= 0) {
            return 'No Fee Structure';
        }

        if ($totalPaid >= $totalFee) {
            return 'Paid';
        }

        if ($totalPaid > 0) {
            return 'Partial';
        }

        return 'Unpaid';
    }
}

$periods = [];
$courses = [];
$students = [];
$filteredStudents = [];

$totalStudents = 0;
$totalFees = 0;
$totalPaid = 0;
$totalBalance = 0;

$error = '';

$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$academicYear = '';
$semester = '';

$allowedStatuses = [
    'Paid',
    'Partial',
    'Unpaid',
    'No Fee Structure'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if ($courseFilter !== '' && !ctype_digit($courseFilter)) {
    $courseFilter = '';
}

try {

    // Get all valid academic year and semester combinations
    $periods = $pdo->query("
        SELECT DISTINCT academic_year, semester
        FROM fee_structures
        ORDER BY academic_year DESC, semester DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Organize semesters under their respective academic years
    $periodsByYear = [];

    foreach ($periods as $period) {

        $year = (string)$period['academic_year'];
        $sem = (string)$period['semester'];

        $periodsByYear[$year][] = $sem;
    }

    // Select academic year
    $defaultYear = array_key_first($periodsByYear) ?? '';

    $requestedYear = trim(
        $_GET['academic_year'] ?? $defaultYear
    );

    if (isset($periodsByYear[$requestedYear])) {
        $academicYear = $requestedYear;
    } else {
        $academicYear = $defaultYear;
    }

    // Get semesters belonging to selected year only
    $availableSemesters = $periodsByYear[$academicYear] ?? [];

    $defaultSemester = $availableSemesters[0] ?? '';

    $requestedSemester = trim(
        $_GET['semester'] ?? $defaultSemester
    );

    if (in_array($requestedSemester, $availableSemesters, true)) {
        $semester = $requestedSemester;
    } else {
        $semester = $defaultSemester;
    }

    // Get courses
    $courses = $pdo->query("
        SELECT id, course_name
        FROM courses
        ORDER BY course_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Count all students
    $totalStudents = (int)$pdo->query("
        SELECT COUNT(*)
        FROM students
    ")->fetchColumn();

    // Retrieve fee records for the selected period
    if ($academicYear !== '' && $semester !== '') {

        $sql = "
            SELECT
                s.id,
                s.admission_number,
                s.first_name,
                s.last_name,
                s.course_id,
                c.course_name,

                COALESCE(fs.total_fee, 0) AS total_fee,

                COALESCE(
                    fs.fee_structure_count, 0
                ) AS fee_structure_count,

                COALESCE(p.total_paid, 0) AS total_paid

            FROM students s

            LEFT JOIN courses c
                ON c.id = s.course_id

            LEFT JOIN (
                SELECT
                    course_id,
                    SUM(total_fee) AS total_fee,
                    COUNT(*) AS fee_structure_count
                FROM fee_structures
                WHERE academic_year = ?
                  AND semester = ?
                GROUP BY course_id
            ) fs
                ON fs.course_id = s.course_id

            LEFT JOIN (
                SELECT
                    student_id,
                    SUM(amount) AS total_paid
                FROM payments
                WHERE academic_year = ?
                  AND semester = ?
                GROUP BY student_id
            ) p
                ON p.student_id = s.id

            ORDER BY s.admission_number ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $academicYear,
            $semester,
            $academicYear,
            $semester
        ]);

        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate balances and apply filters
    foreach ($students as $student) {

        $fee = (float)$student['total_fee'];
        $paid = (float)$student['total_paid'];

        $feeCount = (int)$student['fee_structure_count'];

        $balance = $feeCount > 0
            ? max(0, $fee - $paid)
            : 0;

        $status = fb_payment_status(
            $feeCount,
            $fee,
            $paid
        );

        // Summary totals include all students
        $totalFees += $fee;
        $totalPaid += $paid;
        $totalBalance += $balance;

        $student['balance'] = $balance;
        $student['status'] = $status;

        $name = $student['first_name'] . ' ' .
                $student['last_name'];

        // Student search
        if (
            $search !== '' &&
            stripos(
                (string)$student['admission_number'],
                $search
            ) === false &&
            stripos($name, $search) === false
        ) {
            continue;
        }

        // Course filter
        if (
            $courseFilter !== '' &&
            (string)$student['course_id'] !== $courseFilter
        ) {
            continue;
        }

        // Payment status filter
        if (
            $statusFilter !== '' &&
            $status !== $statusFilter
        ) {
            continue;
        }

        $filteredStudents[] = $student;
    }

    // Find old payments without academic year or semester
    $legacyStmt = $pdo->query("
        SELECT
            COUNT(*) AS payment_count,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM payments
        WHERE academic_year IS NULL
           OR semester IS NULL
    ");

    $legacyPayments = $legacyStmt->fetch(PDO::FETCH_ASSOC);

    $legacyCount = (int)$legacyPayments['payment_count'];
    $legacyAmount = (float)$legacyPayments['total_amount'];

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = "Unable to load fee balances. Please check your database.";

    $legacyCount = 0;
    $legacyAmount = 0;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Fee Balances | Kiprop College</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    margin: 0;
    padding: 25px;
    color: #1e293b;
}

.container {
    max-width: 1400px;
    margin: auto;
}

h2 {
    color: #17365d;
}

.back {
    display: inline-block;
    margin-bottom: 15px;
    color: #17365d;
    text-decoration: none;
}

.period {
    background: white;
    padding: 15px 20px;
    border-left: 5px solid #2563eb;
    border-radius: 8px;
    margin: 15px 0;
}

.warning {
    background: #fff7ed;
    border-left: 5px solid #f59e0b;
    color: #92400e;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.card h3 {
    font-size: 14px;
    color: #64748b;
}

.card p {
    font-size: 22px;
    font-weight: bold;
    color: #17365d;
    margin-bottom: 0;
}

.filters {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filters input,
.filters select,
.filters button {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
}

.filters input,
.filters select {
    flex: 1;
    min-width: 150px;
}

.filters button {
    background: #17365d;
    color: white;
    cursor: pointer;
    border: none;
}

.reset {
    padding: 11px;
    color: #17365d;
    text-decoration: none;
}

.table-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #17365d;
    color: white;
}

.paid {
    color: green;
    font-weight: bold;
}

.partial {
    color: #d97706;
    font-weight: bold;
}

.unpaid {
    color: red;
    font-weight: bold;
}

.no-fee-structure {
    color: #64748b;
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #64748b;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

@media(max-width: 900px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media(max-width: 500px) {

    body {
        padding: 10px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .filters {
        flex-direction: column;
    }

    .filters input,
    .filters select,
    .filters button {
        width: 100%;
    }

}

</style>

</head>

<body>

<div class="container">

<a href="index.php" class="back">
    ← Back to Finance
</a>

<h2>Student Fee Balances</h2>

<p>Kiprop College Management System</p>

<?php if ($error !== ''): ?>

    <div class="error">
        <?= fb_h($error) ?>
    </div>

<?php else: ?>

    <div class="period">

        <strong>Selected Academic Period:</strong>

        <?= fb_h($academicYear ?: 'Not Available') ?>

        -

        <?= fb_h($semester ?: 'Not Available') ?>

    </div>

    <?php if ($legacyCount > 0): ?>

        <div class="warning">

            <strong>Unallocated old payments:</strong>

            <?= number_format($legacyCount) ?> payment(s),

            totalling <?= fb_money($legacyAmount) ?>,

            have no academic year or semester.

            They are excluded from semester balances until
            their correct academic periods are identified.

        </div>

    <?php endif; ?>

    <?php if (empty($periods)): ?>

        <div class="warning">
            No fee structures have been created yet.
            Please create a fee structure before calculating balances.
        </div>

    <?php endif; ?>

    <div class="cards">

        <div class="card">

            <h3>Total Students</h3>

            <p><?= number_format($totalStudents) ?></p>

        </div>

        <div class="card">

            <h3>Total Fees</h3>

            <p><?= fb_money($totalFees) ?></p>

        </div>

        <div class="card">

            <h3>Total Paid</h3>

            <p><?= fb_money($totalPaid) ?></p>

        </div>

        <div class="card">

            <h3>Outstanding Balances</h3>

            <p><?= fb_money($totalBalance) ?></p>

        </div>

    </div>

    <form method="GET" class="filters">

        <select name="academic_year" onchange="this.form.submit()">

            <?php foreach (array_keys($periodsByYear ?? []) as $year): ?>

                <option
                    value="<?= fb_h($year) ?>"
                    <?= (string)$year === $academicYear ? 'selected' : '' ?>
                >
                    <?= fb_h($year) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <select name="semester">

            <?php foreach ($availableSemesters ?? [] as $sem): ?>

                <option
                    value="<?= fb_h($sem) ?>"
                    <?= (string)$sem === $semester ? 'selected' : '' ?>
                >
                    <?= fb_h($sem) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <input
            type="text"
            name="search"
            placeholder="Search student or admission number"
            value="<?= fb_h($search) ?>"
        >

        <select name="course">

            <option value="">All Courses</option>

            <?php foreach ($courses as $course): ?>

                <option
                    value="<?= (int)$course['id'] ?>"
                    <?= (string)$course['id'] === $courseFilter ? 'selected' : '' ?>
                >
                    <?= fb_h($course['course_name']) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <select name="status">

            <option value="">All Statuses</option>

            <?php foreach ($allowedStatuses as $status): ?>

                <option
                    value="<?= fb_h($status) ?>"
                    <?= $statusFilter === $status ? 'selected' : '' ?>
                >
                    <?= fb_h($status) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">
            Apply Filters
        </button>

        <a href="fee-balances.php" class="reset">
            Reset
        </a>

    </form>

    <div class="table-container">

        <h3>
            Fee Balances:
            <?= fb_h($academicYear) ?> -
            <?= fb_h($semester) ?>
        </h3>

        <table>

            <thead>

                <tr>
                    <th>#</th>
                    <th>Admission Number</th>
                    <th>Student Name</th>
                    <th>Course</th>
                    <th>Total Fees</th>
                    <th>Total Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

                <?php if (empty($filteredStudents)): ?>

                    <tr>

                        <td colspan="8" class="empty">
                            No student fee records found for the selected filters.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php $number = 1; ?>

                    <?php foreach ($filteredStudents as $student): ?>

                        <?php
                        $statusClass = strtolower(
                            str_replace(' ', '-', $student['status'])
                        );
                        ?>

                        <tr>

                            <td>
                                <?= $number++ ?>
                            </td>

                            <td>
                                <?= fb_h($student['admission_number']) ?>
                            </td>

                            <td>
                                <?= fb_h(
                                    $student['first_name'] . ' ' .
                                    $student['last_name']
                                ) ?>
                            </td>

                            <td>
                                <?= fb_h(
                                    $student['course_name'] ?? 'Not Assigned'
                                ) ?>
                            </td>

                            <td>
                                <?= fb_money($student['total_fee']) ?>
                            </td>

                            <td>
                                <?= fb_money($student['total_paid']) ?>
                            </td>

                            <td>
                                <?= fb_money($student['balance']) ?>
                            </td>

                            <td class="<?= fb_h($statusClass) ?>">
                                <?= fb_h($student['status']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

<?php endif; ?>

</div>

</body>
</html>
