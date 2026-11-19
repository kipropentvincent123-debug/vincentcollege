
<?php
require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$old = [
    'student_id' => '',
    'academic_year' => '',
    'semester' => '',
    'amount' => '',
    'payment_method' => '',
    'transaction_reference' => '',
    'payment_date' => date('Y-m-d'),
    'description' => ''
];

// Get students.
$students = $pdo->query("
    SELECT id, admission_number, first_name, last_name, course_id
    FROM students
    ORDER BY first_name ASC, last_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get fee structures for each course.
$feeStructures = $pdo->query("
    SELECT DISTINCT course_id, academic_year, semester
    FROM fee_structures
    ORDER BY academic_year DESC, semester ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Organize available academic years and semesters by course.
$feeOptions = [];

foreach ($feeStructures as $structure) {
    $courseId = (string)$structure['course_id'];

    if (!isset($feeOptions[$courseId])) {
        $feeOptions[$courseId] = [];
    }

    $feeOptions[$courseId][] = [
        'academic_year' => $structure['academic_year'],
        'semester' => $structure['semester']
    ];
}

// Process payment submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($old as $key => $value) {
        $old[$key] = trim((string)($_POST[$key] ?? $value));
    }

    $studentId = (int)$old['student_id'];
    $academicYear = $old['academic_year'];
    $semester = $old['semester'];
    $amount = $old['amount'];
    $method = $old['payment_method'];
    $reference = $old['transaction_reference'];
    $paymentDate = $old['payment_date'];
    $description = $old['description'];

    $allowedMethods = ['M-Pesa', 'Bank', 'Cash'];

    if (
        $studentId <= 0 ||
        $academicYear === '' ||
        $semester === '' ||
        !is_numeric($amount) ||
        (float)$amount <= 0 ||
        !in_array($method, $allowedMethods, true) ||
        $paymentDate === ''
    ) {
        $error = 'Please fill in all required fields correctly.';
    } elseif (
        strlen($academicYear) > 20 ||
        strlen($semester) > 30 ||
        strlen($reference) > 100 ||
        strlen($description) > 255
    ) {
        $error = 'One or more fields exceed the allowed length.';
    } else {

        $dateObject = DateTime::createFromFormat('!Y-m-d', $paymentDate);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $paymentDate
        ) {
            $error = 'Please provide a valid payment date.';
        } else {

            try {

                // Confirm that the student exists.
                $studentStmt = $pdo->prepare("
                    SELECT id, course_id
                    FROM students
                    WHERE id = ?
                    LIMIT 1
                ");

                $studentStmt->execute([$studentId]);
                $selectedStudent = $studentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$selectedStudent) {
                    $error = 'Selected student does not exist.';
                } else {

                    // Confirm that the selected semester has a fee structure
                    // for the student's course.
                    $feeStmt = $pdo->prepare("
                        SELECT id
                        FROM fee_structures
                        WHERE course_id = ?
                          AND academic_year = ?
                          AND semester = ?
                        LIMIT 1
                    ");

                    $feeStmt->execute([
                        $selectedStudent['course_id'],
                        $academicYear,
                        $semester
                    ]);

                    if (!$feeStmt->fetch()) {

                        $error = 'No fee structure exists for this student\'s course, academic year and semester.';

                    } else {

                        // Record payment with academic year and semester.
                        $stmt = $pdo->prepare("
                            INSERT INTO payments (
                                student_id,
                                academic_year,
                                semester,
                                amount,
                                payment_method,
                                transaction_reference,
                                payment_date,
                                description
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $studentId,
                            $academicYear,
                            $semester,
                            round((float)$amount, 2),
                            $method,
                            $reference !== '' ? $reference : null,
                            $paymentDate,
                            $description !== '' ? $description : null
                        ]);

                        $message = 'Payment recorded successfully!';

                        // Clear form after successful payment.
                        $old = [
                            'student_id' => '',
                            'academic_year' => '',
                            'semester' => '',
                            'amount' => '',
                            'payment_method' => '',
                            'transaction_reference' => '',
                            'payment_date' => date('Y-m-d'),
                            'description' => ''
                        ];
                    }
                }

            } catch (PDOException $e) {
                $error = 'Unable to record payment. Please check your database.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Record Payment | Kiprop College</title>

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
    max-width: 650px;
    margin: 20px auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

h2 {
    color: #17365d;
}

label {
    display: block;
    margin-top: 18px;
    font-weight: bold;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    margin-top: 7px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    font-size: 15px;
}

button, .back {
    display: inline-block;
    margin-top: 22px;
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    background: #17365d;
    color: white;
    text-decoration: none;
    cursor: pointer;
}

.back {
    background: #64748b;
}

button:hover {
    background: #2563eb;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 5px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 5px;
}

small {
    color: #64748b;
}

@media(max-width: 600px) {
    body {
        padding: 10px;
    }

    .container {
        padding: 20px;
    }

    button, .back {
        width: 100%;
        text-align: center;
    }
}
</style>

</head>

<body>

<div class="container">

<h2>Record Student Payment</h2>

<p>Kiprop College Management System</p>

<?php if ($message): ?>
    <p class="success"><?= h($message) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endif; ?>

<form method="POST">

    <label>Student *</label>

    <select name="student_id" id="student_id" required>
        <option value="">Select Student</option>

        <?php foreach ($students as $student): ?>

            <option
                value="<?= (int)$student['id'] ?>"
                data-course="<?= (int)$student['course_id'] ?>"
                <?= $old['student_id'] == $student['id'] ? 'selected' : '' ?>
            >
                <?= h(
                    $student['admission_number'] . ' - ' .
                    $student['first_name'] . ' ' .
                    $student['last_name']
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <label>Academic Year *</label>

    <select name="academic_year" id="academic_year" required>
        <option value="">Select Academic Year</option>
    </select>

    <label>Semester *</label>

    <select name="semester" id="semester" required>
        <option value="">Select Semester</option>
    </select>

    <small>Available academic years and semesters depend on the selected student's course.</small>

    <label>Payment Amount (KSh) *</label>

    <input
        type="number"
        name="amount"
        min="0.01"
        step="0.01"
        value="<?= h($old['amount']) ?>"
        required
    >

    <label>Payment Method *</label>

    <select name="payment_method" required>
        <option value="">Select Method</option>

        <option value="M-Pesa" <?= $old['payment_method'] === 'M-Pesa' ? 'selected' : '' ?>>
            M-Pesa
        </option>

        <option value="Bank" <?= $old['payment_method'] === 'Bank' ? 'selected' : '' ?>>
            Bank
        </option>

        <option value="Cash" <?= $old['payment_method'] === 'Cash' ? 'selected' : '' ?>>
            Cash
        </option>
    </select>

    <label>Transaction Reference</label>

    <input
        type="text"
        name="transaction_reference"
        maxlength="100"
        value="<?= h($old['transaction_reference']) ?>"
    >

    <label>Payment Date *</label>

    <input
        type="date"
        name="payment_date"
        value="<?= h($old['payment_date']) ?>"
        required
    >

    <label>Description</label>

    <textarea
        name="description"
        rows="3"
        maxlength="255"
    ><?= h($old['description']) ?></textarea>

    <button type="submit">Save Payment</button>

    <a href="index.php" class="back">Back</a>

</form>

</div>

<script>
const feeOptions = <?= json_encode(
    $feeOptions,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const studentSelect = document.getElementById('student_id');
const yearSelect = document.getElementById('academic_year');
const semesterSelect = document.getElementById('semester');

const oldYear = <?= json_encode($old['academic_year']) ?>;
const oldSemester = <?= json_encode($old['semester']) ?>;

function updateFeeOptions(selectedYear = '', selectedSemester = '') {

    const studentOption =
        studentSelect.options[studentSelect.selectedIndex];

    const courseId = studentOption
        ? studentOption.dataset.course
        : '';

    const options = feeOptions[courseId] || [];

    yearSelect.innerHTML =
        '<option value="">Select Academic Year</option>';

    semesterSelect.innerHTML =
        '<option value="">Select Semester</option>';

    semesterSelect.disabled = true;

    const years = [...new Set(
        options.map(item => item.academic_year)
    )];

    years.forEach(year => {

        const option = document.createElement('option');

        option.value = year;
        option.textContent = year;

        if (year === selectedYear) {
            option.selected = true;
        }

        yearSelect.appendChild(option);
    });

    updateSemesterOptions(selectedSemester);
}

function updateSemesterOptions(selectedSemester = '') {

    const studentOption =
        studentSelect.options[studentSelect.selectedIndex];

    const courseId = studentOption
        ? studentOption.dataset.course
        : '';

    const selectedYear = yearSelect.value;

    const options = (feeOptions[courseId] || []).filter(
        item => item.academic_year === selectedYear
    );

    semesterSelect.innerHTML =
        '<option value="">Select Semester</option>';

    options.forEach(item => {

        const option = document.createElement('option');

        option.value = item.semester;
        option.textContent = item.semester;

        if (item.semester === selectedSemester) {
            option.selected = true;
        }

        semesterSelect.appendChild(option);
    });

    semesterSelect.disabled = !selectedYear || options.length === 0;
}

studentSelect.addEventListener('change', function() {
    updateFeeOptions();
});

yearSelect.addEventListener('change', function() {
    updateSemesterOptions();
});

// Restore selected values after an unsuccessful submission.
updateFeeOptions(oldYear, oldSemester);
</script>

</body>
</html>
