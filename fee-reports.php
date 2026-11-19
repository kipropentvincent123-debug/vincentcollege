
<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$courseId = (int)($_GET['course_id'] ?? 0);

// Get courses
$courses = $pdo->query(
    "SELECT id, course_name FROM courses ORDER BY course_name"
)->fetchAll();

// Build payment date filters
$conditions = [];
$params = [];

if ($startDate !== '') {
    $conditions[] = "p.payment_date >= :start_date";
    $params['start_date'] = $startDate;
}

if ($endDate !== '') {
    $conditions[] = "p.payment_date <= :end_date";
    $params['end_date'] = $endDate;
}

if ($courseId > 0) {
    $conditions[] = "s.course_id = :course_id";
    $params['course_id'] = $courseId;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Total payments collected
$sql = "
    SELECT COALESCE(SUM(p.amount), 0)
    FROM payments p
    JOIN students s ON p.student_id = s.id
    $where
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$totalCollected = (float)$stmt->fetchColumn();

// Payment records
$sql = "
    SELECT
        p.payment_date,
        p.transaction_reference,
        p.payment_method,
        p.amount,
        s.admission_number,
        s.first_name,
        s.last_name,
        c.course_name
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    $where
    ORDER BY p.payment_date DESC, p.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
// Outstanding balances
$balanceSql = "
    SELECT
        COALESCE(SUM(COALESCE(fs.total_fee, 0)), 0) AS total_fees,
        COALESCE(SUM(COALESCE(pay.total_paid, 0)), 0) AS total_paid
    FROM students s
    LEFT JOIN (
        SELECT course_id, SUM(total_fee) AS total_fee
        FROM fee_structures
        GROUP BY course_id
    ) fs ON fs.course_id = s.course_id
    LEFT JOIN (
        SELECT student_id, SUM(amount) AS total_paid
        FROM payments
        GROUP BY student_id
    ) pay ON pay.student_id = s.id
";

if ($courseId > 0) {
    $balanceSql .= " WHERE s.course_id = :course_id";
}

$balanceStmt = $pdo->prepare($balanceSql);

if ($courseId > 0) {
    $balanceStmt->execute(['course_id' => $courseId]);
} else {
    $balanceStmt->execute();
}

$balanceData = $balanceStmt->fetch();

$totalFees = (float)$balanceData['total_fees'];
$totalPaidAll = (float)$balanceData['total_paid'];
$outstanding = max(0, $totalFees - $totalPaidAll);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Fee Reports | Kiprop College</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.container {
    max-width: 1300px;
    margin: auto;
}

.back {
    display: inline-block;
    margin-bottom: 20px;
    color: #17365d;
    text-decoration: none;
}

h2 {
    color: #17365d;
}

.filters, .report {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

input, select, button {
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
}

button {
    background: #17365d;
    color: white;
    border: none;
    cursor: pointer;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
}

.card h3 {
    font-size: 14px;
    color: #64748b;
}

.card p {
    font-size: 22px;
    font-weight: bold;
    color: #17365d;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

th {
    background: #17365d;
    color: white;
}

.print-btn {
    margin-bottom: 15px;
}

@media(max-width: 700px) {
    body { padding: 10px; }
    .cards { grid-template-columns: 1fr; }
    .filters { flex-direction: column; }
    input, select, button { width: 100%; }
}

@media print {
    body { background: white; padding: 0; }
    .back, .filters, .print-btn { display: none; }
    .card, .report { box-shadow: none; }
}
</style>
</head>

<body>

<div class="container">

<a href="index.php" class="back">← Back to Fees</a>

<h2>College Fee Reports</h2>

<form method="GET" class="filters">

    <input type="date" name="start_date"
           value="<?= h($startDate) ?>">

    <input type="date" name="end_date"
           value="<?= h($endDate) ?>">

    <select name="course_id">
        <option value="0">All Courses</option>

        <?php foreach ($courses as $course): ?>
            <option value="<?= (int)$course['id'] ?>"
                <?= $courseId === (int)$course['id'] ? 'selected' : '' ?>>
                <?= h($course['course_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Generate Report</button>

    <a href="fee-reports.php">Reset</a>

</form>

<div class="cards">

    <div class="card">
        <h3>Payments Collected (Selected Period)</h3>
        <p>KSh <?= number_format($totalCollected, 2) ?></p>
    </div>

    <div class="card">
        <h3>Total Fees Expected</h3>
        <p>KSh <?= number_format($totalFees, 2) ?></p>
    </div>

    <div class="card">
        <h3>Outstanding Fees</h3>
        <p>KSh <?= number_format($outstanding, 2) ?></p>
    </div>

</div>

<div class="report">

    <h3>Payment Details</h3>

    <button class="print-btn" onclick="window.print()">
        Print Report
    </button>

    <div class="table-wrapper">

    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th>Admission Number</th>
            <th>Student</th>
            <th>Course</th>
            <th>Payment Method</th>
            <th>Reference</th>
            <th>Amount</th>
        </tr>
        </thead>

        <tbody>

        <?php if (empty($payments)): ?>

            <tr>
                <td colspan="7">No payment records found.</td>
            </tr>

        <?php else: ?>

            <?php foreach ($payments as $payment): ?>

            <tr>
                <td><?= h($payment['payment_date']) ?></td>

                <td><?= h($payment['admission_number']) ?></td>

                <td>
                    <?= h($payment['first_name'] . ' ' . $payment['last_name']) ?>
                </td>

                <td><?= h($payment['course_name'] ?? 'N/A') ?></td>

                <td><?= h($payment['payment_method']) ?></td>

                <td><?= h($payment['transaction_reference'] ?? '') ?></td>

                <td>KSh <?= number_format($payment['amount'], 2) ?></td>
            </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>
    </table>

    </div>

</div>

</div>

</body>
</html>
