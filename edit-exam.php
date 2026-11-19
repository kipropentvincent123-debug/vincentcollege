
<?php
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit('Invalid examination ID.');
}

$stmt = $pdo->prepare("SELECT * FROM examinations WHERE id = :id");
$stmt->execute(['id' => $id]);
$exam = $stmt->fetch();

if (!$exam) {
    exit('Examination not found.');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examName = trim($_POST['exam_name'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $examDate = trim($_POST['exam_date'] ?? '');

    if (
        $examName === '' ||
        $academicYear === '' ||
        $semester === '' ||
        $examDate === ''
    ) {
        $message = 'Please fill in all fields.';
    } else {
        $update = $pdo->prepare("
            UPDATE examinations
            SET exam_name = :exam_name,
                academic_year = :academic_year,
                semester = :semester,
                exam_date = :exam_date
            WHERE id = :id
        ");

        $update->execute([
            'exam_name' => $examName,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'exam_date' => $examDate,
            'id' => $id
        ]);

        header('Location: manage-exams.php?updated=1');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Examination | Kiprop College</title>

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .container {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px #ddd;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #17365d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        a {
            color: #1769aa;
            text-decoration: none;
        }

        .error {
            color: #721c24;
            background: #f8d7da;
            padding: 12px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Examination</h2>

    <?php if ($message !== ''): ?>
        <p class="error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="exam_name">Examination Name</label>
        <input
            type="text"
            id="exam_name"
            name="exam_name"
            value="<?= htmlspecialchars($exam['exam_name']) ?>"
            required
        >

        <label for="academic_year">Academic Year</label>
        <input
            type="text"
            id="academic_year"
            name="academic_year"
            value="<?= htmlspecialchars($exam['academic_year']) ?>"
            required
        >

        <label for="semester">Semester</label>
        <select id="semester" name="semester" required>
            <option value="Semester 1" <?= $exam['semester'] === 'Semester 1' ? 'selected' : '' ?>>Semester 1</option>
            <option value="Semester 2" <?= $exam['semester'] === 'Semester 2' ? 'selected' : '' ?>>Semester 2</option>
            <option value="Semester 3" <?= $exam['semester'] === 'Semester 3' ? 'selected' : '' ?>>Semester 3</option>
        </select>

        <label for="exam_date">Examination Date</label>
        <input
            type="date"
            id="exam_date"
            name="exam_date"
            value="<?= htmlspecialchars($exam['exam_date']) ?>"
            required
        >

        <button type="submit">Update Examination</button>
    </form>

    <p><a href="manage-exams.php">Back to Examination Records</a></p>
</div>

</body>
</html>
