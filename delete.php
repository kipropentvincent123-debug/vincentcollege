
<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$student = null;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid student ID.');
}

$stmt = $pdo->prepare(
    "SELECT id, admission_number, first_name, last_name
     FROM students WHERE id = ?"
);
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirm'] ?? '') !== 'yes') {
        header('Location: list.php');
        exit;
    }

    try {
        $delete = $pdo->prepare(
            "DELETE FROM students WHERE id = ?"
        );
        $delete->execute([$id]);

        header('Location: list.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Unable to delete this student. The record may be linked to other college records.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Student | Kiprop College</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 25px 15px;
        }

        .container {
            max-width: 550px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        .warning {
            background: #fee2e2;
            padding: 15px;
            border-radius: 6px;
        }

        button, .cancel {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        button {
            background: #b91c1c;
            color: white;
        }

        .cancel {
            background: #e2e8f0;
            color: #111827;
        }

        .error {
            color: #b91c1c;
        }
    </style>
</head>

<body>
<div class="container">
    <h2>Delete Student</h2>

    <p class="warning">
        Are you sure you want to delete this student record?
        This action may affect linked academic records.
    </p>

    <p>
        <strong>Admission Number:</strong>
        <?= htmlspecialchars($student['admission_number']) ?>
    </p>

    <p>
        <strong>Student Name:</strong>
        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
    </p>

    <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="confirm" value="yes">
        <button type="submit">Confirm Delete</button>
        <a class="cancel" href="list.php">Cancel</a>
    </form>
</div>
</body>
</html>
