
<?php
require_once __DIR__ . '/../config/database.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid receipt ID.");
}

try {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.academic_year,
            p.semester,
            p.amount,
            p.payment_method,
            p.transaction_reference,
            p.payment_date,
            p.description,
            s.admission_number,
            s.first_name,
            s.last_name,
            c.course_name
        FROM payments p
        INNER JOIN students s ON s.id = p.student_id
        LEFT JOIN courses c ON c.id = s.course_id
        WHERE p.id = :id
        LIMIT 1
    ");

    $stmt->execute(['id' => $id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        die("Payment record not found.");
    }

} catch (PDOException $e) {
    die("Unable to load receipt: " . e($e->getMessage()));
}

$receiptYear = date('Y', strtotime($payment['payment_date']));

$receiptNumber = 'VC-' . $receiptYear . '-'
    . str_pad((string)$payment['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= e($receiptNumber) ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 25px;
        }

        .receipt {
            max-width: 750px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            border: 1px solid #cbd5e1;
        }

        .college {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .college h1 {
            color: #1e3a8a;
            margin: 0 0 8px;
            font-size: 27px;
        }

        .college p {
            margin: 4px;
            color: #64748b;
        }

        .receipt-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .receipt-title h2 {
            margin: 0;
            letter-spacing: 2px;
        }

        .receipt-number {
            color: #475569;
            margin-top: 8px;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .details td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .details td:first-child {
            width: 40%;
            color: #475569;
            font-weight: bold;
        }

        .amount-box {
            background: #eff6ff;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin: 25px 0;
        }

        .amount-box span {
            color: #475569;
        }

        .amount-box strong {
            display: block;
            color: #1e3a8a;
            font-size: 27px;
            margin-top: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 45px;
            color: #64748b;
            font-size: 13px;
        }

        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 55px;
            gap: 30px;
        }

        .signature div {
            width: 45%;
            border-top: 1px solid #64748b;
            padding-top: 8px;
            text-align: center;
            font-size: 13px;
        }

        .actions {
            max-width: 750px;
            margin: 0 auto 15px;
            display: flex;
            justify-content: space-between;
        }

        button, .back {
            padding: 11px 18px;
            border: none;
            border-radius: 5px;
            background: #1d4ed8;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .back {
            background: #475569;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .receipt {
                margin: 0 auto;
                border: none;
                padding: 25px;
            }

            .actions {
                display: none;
            }

            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>
</head>

<body>

<div class="actions">
    <a href="receipts.php" class="back">Back to Receipts</a>
    <button onclick="window.print()">Print Receipt</button>
</div>

<div class="receipt">

    <div class="college">
        <h1>VINCENT COLLEGE</h1>
        <p>Official Student Payment Receipt</p>
    </div>

    <div class="receipt-title">
        <h2>PAYMENT RECEIPT</h2>
        <div class="receipt-number">
            Receipt Number: <strong><?= e($receiptNumber) ?></strong>
        </div>
    </div>

    <table class="details">
        <tr>
            <td>Admission Number</td>
            <td><?= e($payment['admission_number']) ?></td>
        </tr>

        <tr>
            <td>Student Name</td>
            <td>
                <?= e($payment['first_name'] . ' ' . $payment['last_name']) ?>
            </td>
        </tr>

        <tr>
            <td>Course</td>
            <td><?= e($payment['course_name'] ?? 'Not specified') ?></td>
        </tr>

        <tr>
            <td>Payment Date</td>
            <td><?= e($payment['payment_date']) ?></td>
        </tr>

        <tr>
            <td>Payment Method</td>
            <td><?= e($payment['payment_method']) ?></td>
        </tr>

        <tr>
            <td>Transaction Reference</td>
            <td><?= e($payment['transaction_reference'] ?: 'N/A') ?></td>
        </tr>

        <tr>
            <td>Academic Year</td>
            <td><?= e($payment['academic_year'] ?: 'Not specified') ?></td>
        </tr>

        <tr>
            <td>Semester</td>
            <td><?= e($payment['semester'] ?: 'Not specified') ?></td>
        </tr>

        <tr>
            <td>Description</td>
            <td><?= e($payment['description'] ?: 'School fees payment') ?></td>
        </tr>
    </table>

    <div class="amount-box">
        <span>Amount Received</span>
        <strong>
            KSh <?= number_format((float)$payment['amount'], 2) ?>
        </strong>
    </div>

    <p>
        This receipt acknowledges that the college has recorded the
        payment shown above.
    </p>

    <div class="signature">
        <div>Received By</div>
        <div>Official Stamp</div>
    </div>

    <div class="footer">
        <p>Thank you for your payment.</p>
        <p>Generated from Vincent College Management System</p>
    </div>

</div>

</body>
</html>
