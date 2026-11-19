
<?php
require_once __DIR__ . '/../config/database.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');

try {
    $sql = "
        SELECT
            p.id,
            p.student_id,
            p.academic_year,
            p.semester,
            p.amount,
            p.payment_method,
            p.transaction_reference,
            p.payment_date,
            p.description,
            s.admission_number,
            s.first_name,
            s.last_name
        FROM payments p
        INNER JOIN students s ON s.id = p.student_id
    ";

    if ($search !== '') {
        $sql .= "
            WHERE
                s.admission_number LIKE :search
                OR s.first_name LIKE :search
                OR s.last_name LIKE :search
                OR p.transaction_reference LIKE :search
        ";
    }

    $sql .= " ORDER BY p.payment_date DESC, p.id DESC";

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {
        $stmt->execute(['search' => '%' . $search . '%']);
    } else {
        $stmt->execute();
    }

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Unable to load receipts: " . e($e->getMessage()));
}

$total = 0;

foreach ($payments as $payment) {
    $total += (float)$payment['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipts | Vincent College</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 5px;
            font-size: 25px;
        }

        .subtitle {
            color: #64748b;
            margin: 0;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .summary {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .summary strong {
            display: block;
            font-size: 23px;
            margin-top: 6px;
        }

        .search {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        input {
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            min-width: 250px;
            font-size: 14px;
        }

        button, .btn {
            display: inline-block;
            padding: 10px 15px;
            border: 0;
            border-radius: 6px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary {
            background: #475569;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #475569;
        }

        .amount {
            font-weight: bold;
            color: #047857;
        }

        .print-btn {
            background: #059669;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Payment Receipts</h1>
            <p class="subtitle">View and print student payment receipts</p>
        </div>

        <a href="index.php" class="btn btn-secondary">Back to Fees</a>
    </div>

    <div class="card">
        <div class="summary">
            <div>
                <span>Total Receipts</span>
                <strong><?= count($payments) ?></strong>
            </div>

            <div>
                <span>Total Payments Listed</span>
                <strong>KSh <?= number_format($total, 2) ?></strong>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="search">
            <input
                type="text"
                name="search"
                placeholder="Search admission number, student or reference"
                value="<?= e($search) ?>"
            >

            <button type="submit">Search</button>

            <a href="receipts.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Admission No.</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Payment Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="empty">
                            No payment receipts found.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $receiptYear = date(
                            'Y',
                            strtotime($payment['payment_date'])
                        );

                        $receiptNumber = 'VC-' . $receiptYear . '-'
                            . str_pad((string)$payment['id'], 5, '0', STR_PAD_LEFT);
                        ?>

                        <tr>
                            <td><?= e($receiptNumber) ?></td>

                            <td><?= e($payment['admission_number']) ?></td>

                            <td>
                                <?= e($payment['first_name'] . ' ' . $payment['last_name']) ?>
                            </td>

                            <td class="amount">
                                KSh <?= number_format((float)$payment['amount'], 2) ?>
                            </td>

                            <td><?= e($payment['payment_method']) ?></td>

                            <td><?= e($payment['payment_date']) ?></td>

                            <td>
                                <a
                                    class="btn print-btn"
                                    href="print-receipt.php?id=<?= (int)$payment['id'] ?>"
                                    target="_blank"
                                >
                                    Print Receipt
                                </a>
                            </td>
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
