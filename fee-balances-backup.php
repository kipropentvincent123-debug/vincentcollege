
<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function paymentStatus($feeStructureCount, $totalFee, $totalPaid) {
    if ($feeStructureCount <= 0) {
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

// Get each course's total fees across all created fee structures.
// Grouping by course prevents duplicate student records.
$sql = "
    SELECT
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.course_id,
        c.course_name,
        COALESCE(fs.total_fee, 0) AS total_fee,
        COALESCE(fs.fee_structure_count, 0) AS fee_structure_count,
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
        GROUP BY course_id
    ) fs
        ON fs.course_id = s.course_id
    LEFT JOIN (
        SELECT
            student_id,
            SUM(amount) AS total_paid
        FROM payments
        GROUP BY student_id
    ) p
        ON p.student_id = s.id
    ORDER BY s.admission_number
";

$students = $pdo->query($sql)->fetchAll();

// Get courses for the filter.
$courses = $pdo->query(
    "SELECT id, course_name FROM courses ORDER BY course_name"
)->fetchAll();

// Search and filter values.
$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Accept only a numeric course ID.
if ($courseFilter !== '' && !ctype_digit($courseFilter)) {
    $courseFilter = '';
}

// Calculate dashboard summaries and prepare filtered records.
$totalStudents = count($students);
$totalFees = 0;
$totalPaid = 0;
$totalBalance = 0;

$filteredStudents = [];

foreach ($students as $student) {
    $fee = (float)$student['total_fee'];
    $paid = (float)$student['total_paid'];
    $feeCount = (int)$student['fee_structure_count'];

    $balance = $feeCount > 0 ? max(0, $fee - $paid) : 0;

    $status = paymentStatus($feeCount, $fee, $paid);

    // Dashboard totals include all students, regardless of filters.
    $totalFees += $fee;
    $totalPaid += $paid;
    $totalBalance += $balance;

    $student['balance'] = $balance;
    $student['status'] = $status;

    // Search by admission number or student name.
    $name = $student['first_name'] . ' ' . $student['last_name'];

    if (
        $search !== '' &&
        stripos($student['admission_number'], $search) === false &&
        stripos($name, $search) === false
    ) {
        continue;
    }

    // Filter by course ID.
    if (
        $courseFilter !== '' &&
        (string)$student['course_id'] !== $courseFilter
    ) {
        continue;
    }

    // Filter by payment status.
    if (
        $statusFilter !== '' &&
        $status !== $statusFilter
    ) {
        continue;
    }

    $filteredStudents[] = $student;
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

input, select, button {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
}

button {
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
    min-width: 900px;
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

    input, select, button {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="container">

<a href="index.php" class="back">← Back to Fees</a>

<h2>Student Fee Balances</h2>

<div class="cards">

    <div class="card">
        <h3>Total Students</h3>
        <p><?= number_format($totalStudents) ?></p>
    </div>

    <div class="card">
        <h3>Total Fees Expected</h3>
        <p>KSh <?= number_format($totalFees, 2) ?></p>
    </div>

    <div class="card">
        <h3>Total Payments Received</h3>
        <p>KSh <?= number_format($totalPaid, 2) ?></p>
    </div>

    <div class="card">
        <h3>Outstanding Balances</h3>
        <p>KSh <?= number_format($totalBalance, 2) ?></p>
    </div>

</div>

<form method="GET" class="filters">

    <input
        type="text"
        name="search"
        placeholder="Search admission number or name"
        value="<?= h($search) ?>"
    >

    <select name="course">
        <option value="">All Courses</option>

        <?php foreach ($courses as $course): ?>
            <option
                value="<?= (int)$course['id'] ?>"
                <?= $courseFilter === (string)$course['id'] ? 'selected' : '' ?>
            >
                <?= h($course['course_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value="">All Payment Statuses</option>
        <option value="Paid" <?= $statusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
        <option value="Partial" <?= $statusFilter === 'Partial' ? 'selected' : '' ?>>Partial</option>
        <option value="Unpaid" <?= $statusFilter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
        <option value="No Fee Structure" <?= $statusFilter === 'No Fee Structure' ? 'selected' : '' ?>>No Fee Structure</option>
    </select>

    <button type="submit">Search / Filter</button>

    <a href="fee-balances.php" class="reset">Reset</a>

</form>

<div class="table-container">

<h3>Student Payment Records</h3>

<table>
<thead>
<tr>
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
    <td colspan="7">No matching student records found.</td>
</tr>

<?php else: ?>

<?php foreach ($filteredStudents as $student): ?>

<?php
$class = strtolower(str_replace(' ', '-', $student['status']));
?>

<tr>
    <td><?= h($student['admission_number']) ?></td>

    <td>
        <?= h($student['first_name'] . ' ' . $student['last_name']) ?>
    </td>

    <td><?= h($student['course_name'] ?? 'Not assigned') ?></td>

    <td>KSh <?= number_format((float)$student['total_fee'], 2) ?></td>

    <td>KSh <?= number_format((float)$student['total_paid'], 2) ?></td>

    <td>KSh <?= number_format((float)$student['balance'], 2) ?></td>

    <td class="<?= h($class) ?>">
        <?= h($student['status']) ?>
    </td>
</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

<p>Showing <?= count($filteredStudents) ?> student record(s).</p>

</div>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function paymentStatus($feeStructureCount, $totalFee, $totalPaid) {
    if ($feeStructureCount <= 0) {
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

// Get each course's total fees across all created fee structures.
// Grouping by course prevents duplicate student records.
$sql = "
    SELECT
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.course_id,
        c.course_name,
        COALESCE(fs.total_fee, 0) AS total_fee,
        COALESCE(fs.fee_structure_count, 0) AS fee_structure_count,
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
        GROUP BY course_id
    ) fs
        ON fs.course_id = s.course_id
    LEFT JOIN (
        SELECT
            student_id,
            SUM(amount) AS total_paid
        FROM payments
        GROUP BY student_id
    ) p
        ON p.student_id = s.id
    ORDER BY s.admission_number
";

$students = $pdo->query($sql)->fetchAll();

// Get courses for the filter.
$courses = $pdo->query(
    "SELECT id, course_name FROM courses ORDER BY course_name"
)->fetchAll();

// Search and filter values.
$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Accept only a numeric course ID.
if ($courseFilter !== '' && !ctype_digit($courseFilter)) {
    $courseFilter = '';
}

// Calculate dashboard summaries and prepare filtered records.
$totalStudents = count($students);
$totalFees = 0;
$totalPaid = 0;
$totalBalance = 0;

$filteredStudents = [];

foreach ($students as $student) {
    $fee = (float)$student['total_fee'];
    $paid = (float)$student['total_paid'];
    $feeCount = (int)$student['fee_structure_count'];

    $balance = $feeCount > 0 ? max(0, $fee - $paid) : 0;

    $status = paymentStatus($feeCount, $fee, $paid);

    // Dashboard totals include all students, regardless of filters.
    $totalFees += $fee;
    $totalPaid += $paid;
    $totalBalance += $balance;

    $student['balance'] = $balance;
    $student['status'] = $status;

    // Search by admission number or student name.
    $name = $student['first_name'] . ' ' . $student['last_name'];

    if (
        $search !== '' &&
        stripos($student['admission_number'], $search) === false &&
        stripos($name, $search) === false
    ) {
        continue;
    }

    // Filter by course ID.
    if (
        $courseFilter !== '' &&
        (string)$student['course_id'] !== $courseFilter
    ) {
        continue;
    }

    // Filter by payment status.
    if (
        $statusFilter !== '' &&
        $status !== $statusFilter
    ) {
        continue;
    }

    $filteredStudents[] = $student;
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

input, select, button {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
}

button {
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
    min-width: 900px;
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

    input, select, button {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="container">

<a href="index.php" class="back">← Back to Fees</a>

<h2>Student Fee Balances</h2>

<div class="cards">

    <div class="card">
        <h3>Total Students</h3>
        <p><?= number_format($totalStudents) ?></p>
    </div>

    <div class="card">
        <h3>Total Fees Expected</h3>
        <p>KSh <?= number_format($totalFees, 2) ?></p>
    </div>

    <div class="card">
        <h3>Total Payments Received</h3>
        <p>KSh <?= number_format($totalPaid, 2) ?></p>
    </div>

    <div class="card">
        <h3>Outstanding Balances</h3>
        <p>KSh <?= number_format($totalBalance, 2) ?></p>
    </div>

</div>

<form method="GET" class="filters">

    <input
        type="text"
        name="search"
        placeholder="Search admission number or name"
        value="<?= h($search) ?>"
    >

    <select name="course">
        <option value="">All Courses</option>

        <?php foreach ($courses as $course): ?>
            <option
                value="<?= (int)$course['id'] ?>"
                <?= $courseFilter === (string)$course['id'] ? 'selected' : '' ?>
            >
                <?= h($course['course_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value="">All Payment Statuses</option>
        <option value="Paid" <?= $statusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
        <option value="Partial" <?= $statusFilter === 'Partial' ? 'selected' : '' ?>>Partial</option>
        <option value="Unpaid" <?= $statusFilter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
        <option value="No Fee Structure" <?= $statusFilter === 'No Fee Structure' ? 'selected' : '' ?>>No Fee Structure</option>
    </select>

    <button type="submit">Search / Filter</button>

    <a href="fee-balances.php" class="reset">Reset</a>

</form>

<div class="table-container">

<h3>Student Payment Records</h3>

<table>
<thead>
<tr>
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
    <td colspan="7">No matching student records found.</td>
</tr>

<?php else: ?>

<?php foreach ($filteredStudents as $student): ?>

<?php
$class = strtolower(str_replace(' ', '-', $student['status']));
?>

<tr>
    <td><?= h($student['admission_number']) ?></td>

    <td>
        <?= h($student['first_name'] . ' ' . $student['last_name']) ?>
    </td>

    <td><?= h($student['course_name'] ?? 'Not assigned') ?></td>

    <td>KSh <?= number_format((float)$student['total_fee'], 2) ?></td>

    <td>KSh <?= number_format((float)$student['total_paid'], 2) ?></td>

    <td>KSh <?= number_format((float)$student['balance'], 2) ?></td>

    <td class="<?= h($class) ?>">
        <?= h($student['status']) ?>
    </td>
</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

<p>Showing <?= count($filteredStudents) ?> student record(s).</p>

</div>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function paymentStatus($feeStructureCount, $totalFee, $totalPaid) {
    if ($feeStructureCount <= 0) {
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

// Get each course's total fees across all created fee structures.
// Grouping by course prevents duplicate student records.
$sql = "
    SELECT
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.course_id,
        c.course_name,
        COALESCE(fs.total_fee, 0) AS total_fee,
        COALESCE(fs.fee_structure_count, 0) AS fee_structure_count,
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
        GROUP BY course_id
    ) fs
        ON fs.course_id = s.course_id
    LEFT JOIN (
        SELECT
            student_id,
            SUM(amount) AS total_paid
        FROM payments
        GROUP BY student_id
    ) p
        ON p.student_id = s.id
    ORDER BY s.admission_number
";

$students = $pdo->query($sql)->fetchAll();

// Get courses for the filter.
$courses = $pdo->query(
    "SELECT id, course_name FROM courses ORDER BY course_name"
)->fetchAll();

// Search and filter values.
$search = trim($_GET['search'] ?? '');
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Accept only a numeric course ID.
if ($courseFilter !== '' && !ctype_digit($courseFilter)) {
    $courseFilter = '';
}

// Calculate dashboard summaries and prepare filtered records.
$totalStudents = count($students);
$totalFees = 0;
$totalPaid = 0;
$totalBalance = 0;

$filteredStudents = [];

foreach ($students as $student) {
    $fee = (float)$student['total_fee'];
    $paid = (float)$student['total_paid'];
    $feeCount = (int)$student['fee_structure_count'];

    $balance = $feeCount > 0 ? max(0, $fee - $paid) : 0;

    $status = paymentStatus($feeCount, $fee, $paid);

    // Dashboard totals include all students, regardless of filters.
    $totalFees += $fee;
    $totalPaid += $paid;
    $totalBalance += $balance;

    $student['balance'] = $balance;
    $student['status'] = $status;

    // Search by admission number or student name.
    $name = $student['first_name'] . ' ' . $student['last_name'];

    if (
        $search !== '' &&
        stripos($student['admission_number'], $search) === false &&
        stripos($name, $search) === false
    ) {
        continue;
    }

    // Filter by course ID.
    if (
        $courseFilter !== '' &&
        (string)$student['course_id'] !== $courseFilter
    ) {
        continue;
    }

    // Filter by payment status.
    if (
        $statusFilter !== '' &&
        $status !== $statusFilter
    ) {
        continue;
    }

    $filteredStudents[] = $student;
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

input, select, button {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
}

button {
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
    min-width: 900px;
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

    input, select, button {
        width: 100%;
    }
}
</style>
</head>

<body>

<div class="container">

<a href="index.php" class="back">← Back to Fees</a>

<h2>Student Fee Balances</h2>

<div class="cards">

    <div class="card">
        <h3>Total Students</h3>
        <p><?= number_format($totalStudents) ?></p>
    </div>

    <div class="card">
        <h3>Total Fees Expected</h3>
        <p>KSh <?= number_format($totalFees, 2) ?></p>
    </div>

    <div class="card">
        <h3>Total Payments Received</h3>
        <p>KSh <?= number_format($totalPaid, 2) ?></p>
    </div>

    <div class="card">
        <h3>Outstanding Balances</h3>
        <p>KSh <?= number_format($totalBalance, 2) ?></p>
    </div>

</div>

<form method="GET" class="filters">

    <input
        type="text"
        name="search"
        placeholder="Search admission number or name"
        value="<?= h($search) ?>"
    >

    <select name="course">
        <option value="">All Courses</option>

        <?php foreach ($courses as $course): ?>
            <option
                value="<?= (int)$course['id'] ?>"
                <?= $courseFilter === (string)$course['id'] ? 'selected' : '' ?>
            >
                <?= h($course['course_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status">
        <option value="">All Payment Statuses</option>
        <option value="Paid" <?= $statusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
        <option value="Partial" <?= $statusFilter === 'Partial' ? 'selected' : '' ?>>Partial</option>
        <option value="Unpaid" <?= $statusFilter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
        <option value="No Fee Structure" <?= $statusFilter === 'No Fee Structure' ? 'selected' : '' ?>>No Fee Structure</option>
    </select>

    <button type="submit">Search / Filter</button>

    <a href="fee-balances.php" class="reset">Reset</a>

</form>

<div class="table-container">

<h3>Student Payment Records</h3>

<table>
<thead>
<tr>
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
    <td colspan="7">No matching student records found.</td>
</tr>

<?php else: ?>

<?php foreach ($filteredStudents as $student): ?>

<?php
$class = strtolower(str_replace(' ', '-', $student['status']));
?>

<tr>
    <td><?= h($student['admission_number']) ?></td>

    <td>
        <?= h($student['first_name'] . ' ' . $student['last_name']) ?>
    </td>

    <td><?= h($student['course_name'] ?? 'Not assigned') ?></td>

    <td>KSh <?= number_format((float)$student['total_fee'], 2) ?></td>

    <td>KSh <?= number_format((float)$student['total_paid'], 2) ?></td>

    <td>KSh <?= number_format((float)$student['balance'], 2) ?></td>

    <td class="<?= h($class) ?>">
        <?= h($student['status']) ?>
    </td>
</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

<p>Showing <?= count($filteredStudents) ?> student record(s).</p>

</div>
</div>

</body>
</html>
