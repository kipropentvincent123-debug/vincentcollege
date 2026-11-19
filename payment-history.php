
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

// Escape output safely
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$payments = [];
$totalPayments = 0;
$error = '';

$search = trim($_GET['search'] ?? '');

try {

    // Get all payment records
    $sql = "
        SELECT
            payments.id,
            students.admission_number,
            students.first_name,
            students.last_name,
            payments.amount,
            payments.payment_method,
            payments.transaction_reference,
            payments.payment_date,
            payments.description
        FROM payments
        INNER JOIN students
            ON payments.student_id = students.id
    ";

    if ($search !== '') {
        $sql .= "
            WHERE
                students.admission_number LIKE :search
                OR students.first_name LIKE :search
                OR students.last_name LIKE :search
                OR payments.transaction_reference LIKE :search
                OR payments.payment_method LIKE :search
        ";
    }

    $sql .= "
        ORDER BY payments.payment_date DESC, payments.id DESC
    ";

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {
        $stmt->execute([
            ':search' => '%' . $search . '%'
        ]);
    } else {
        $stmt->execute();
    }

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total payments received
    $totalStmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
    ");

    $totalPayments = $totalStmt->fetchColumn();

} catch (PDOException $e) {

    error_log($e->getMessage());

    $error = "Unable to load payment history. Please check your database.";

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Payment History | Kiprop College</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #1e293b;
}

.container {
    max-width: 1250px;
    margin: auto;
}

h1 {
    color: #1e293b;
}

.subtitle {
    color: #64748b;
}

.buttons {
    margin: 20px 0;
}

.btn {
    display: inline-block;
    padding: 11px 17px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    margin-right: 8px;
    margin-bottom: 8px;
}

.btn:hover {
    background: #1d4ed8;
}

.summary {
    background: white;
    padding: 22px;
    border-radius: 8px;
    margin: 20px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

.summary h3 {
    color: #64748b;
    margin-top: 0;
}

.summary p {
    font-size: 28px;
    font-weight: bold;
    color: #2563eb;
    margin-bottom: 0;
}

.table-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    overflow-x: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #f1f5f9;
    color: #334155;
}

tr:hover {
    background: #f8fafc;
}

.amount {
    font-weight: bold;
    color: #15803d;
}

.search-form {
    display: flex;
    gap: 10px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.search-form input {
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    flex: 1;
    min-width: 220px;
}

.search-form button {
    padding: 12px 20px;
    background: #2563eb;
    border: none;
    border-radius: 5px;
    color: white;
    cursor: pointer;
}

.clear-btn {
    padding: 12px 15px;
    background: #64748b;
    color: white;
    text-decoration: none;
    border-radius: 5px;
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
    border-radius: 5px;
    margin: 15px 0;
}

@media(max-width: 600px) {

    body {
        padding: 12px;
    }

    .summary p {
        font-size: 23px;
    }

    .table-container {
        padding: 12px;
    }

}

</style>

</head>

<body>

<div class="container">

    <h1>Student Payment History</h1>

    <p class="subtitle">
        Kiprop College Management System
    </p>

    <div class="buttons">

        <a href="add-payment.php" class="btn">
            + Record Payment
        </a>

        <a href="index.php" class="btn">
            Fees Dashboard
        </a>

    </div>

    <div class="summary">

        <h3>Total Payments Received</h3>

        <p>
            KSh <?= number_format((float)$totalPayments, 2) ?>
        </p>

    </div>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= e($error) ?>
        </div>

    <?php else: ?>

        <div class="table-container">

            <h2>Payment Records</h2>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search admission number, student or reference..."
                    value="<?= e($search) ?>"
                >

                <button type="submit">
                    Search
                </button>

                <a href="payment-history.php" class="clear-btn">
                    Clear
                </a>

            </form>

            <table>

                <thead>

                    <tr>
                        <th>Admission No.</th>
                        <th>Student Name</th>
                        <th>Amount (KSh)</th>
                        <th>Payment Method</th>
                        <th>Reference</th>
                        <th>Payment Date</th>
                        <th>Description</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($payments) > 0): ?>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>
                                    <?= e($payment['admission_number']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $payment['first_name'] . ' ' .
                                        $payment['last_name']
                                    ) ?>
                                </td>

                                <td class="amount">
                                    <?= number_format(
                                        (float)$payment['amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($payment['payment_method']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $payment['transaction_reference'] ?: '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($payment['payment_date']) ?>
                                </td>

                                <td>
                                    <?= e($payment['description'] ?: '-') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" class="empty">
                                No payment records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
