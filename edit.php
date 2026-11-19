
<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid student ID. Open the student list and select Edit.');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student record not found.');
}

$courses = $pdo->query(
    "SELECT id, course_name FROM courses ORDER BY course_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_number = trim($_POST['admission_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);

    if (
        $admission_number === '' ||
        $first_name === '' ||
        $last_name === '' ||
        !in_array($gender, ['Male', 'Female'], true) ||
        !$course_id
    ) {
        $error = 'Please complete all required fields.';
    } elseif (
        $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Please enter a valid email address.';
    } else {
        $courseCheck = $pdo->prepare(
            "SELECT id FROM courses WHERE id = ?"
        );
        $courseCheck->execute([$course_id]);

        if (!$courseCheck->fetch()) {
            $error = 'Please select a valid course.';
        } else {
            try {
                $update = $pdo->prepare(
                    "UPDATE students SET
                        admission_number = ?,
                        first_name = ?,
                        last_name = ?,
                        gender = ?,
                        email = ?,
                        phone = ?,
                        course_id = ?
                     WHERE id = ?"
                );

                $update->execute([
                    $admission_number,
                    $first_name,
                    $last_name,
                    $gender,
                    $email !== '' ? $email : null,
                    $phone !== '' ? $phone : null,
                    $course_id,
                    $id
                ]);

                $stmt = $pdo->prepare(
                    "SELECT * FROM students WHERE id = ?"
                );
                $stmt->execute([$id]);
                $student = $stmt->fetch();

                $success = 'Student record updated successfully.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'That admission number may already exist.';
                } else {
                    $error = 'Unable to update the student record.';
                }
            }
        }
    }

    if ($error !== '') {
        $student['admission_number'] = $admission_number;
        $student['first_name'] = $first_name;
        $student['last_name'] = $last_name;
        $student['gender'] = $gender;
        $student['email'] = $email;
        $student['phone'] = $phone;
        $student['course_id'] = $course_id ?: '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | Kiprop College</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 25px 15px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h2 { color: #12345a; }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button, .back {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 16px;
            border: none;
            border-radius: 5px;
            background: #12345a;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin: 12px 0;
            border-radius: 5px;
            background: #e0f2fe;
        }

        .error { background: #fee2e2; }
    </style>
</head>

<body>
<div class="container">
    <h2>Kiprop College</h2>
    <h3>Edit Student Record</h3>

    <?php if ($success !== ''): ?>
        <div class="message">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label>Admission Number *</label>
        <input type="text" name="admission_number"
            value="<?= htmlspecialchars($student['admission_number'] ?? '') ?>"
            required>

        <label>First Name *</label>
        <input type="text" name="first_name"
            value="<?= htmlspecialchars($student['first_name'] ?? '') ?>"
            required>

        <label>Last Name *</label>
        <input type="text" name="last_name"
            value="<?= htmlspecialchars($student['last_name'] ?? '') ?>"
            required>

        <label>Gender *</label>
        <select name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male"
                <?= ($student['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>
                Male
            </option>
            <option value="Female"
                <?= ($student['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>
                Female
            </option>
        </select>

        <label>Email Address</label>
        <input type="email" name="email"
            value="<?= htmlspecialchars($student['email'] ?? '') ?>">

        <label>Phone Number</label>
        <input type="text" name="phone"
            value="<?= htmlspecialchars($student['phone'] ?? '') ?>">

        <label>Course *</label>
        <select name="course_id" required>
            <option value="">Select Course</option>

            <?php foreach ($courses as $course): ?>
                <option value="<?= (int)$course['id'] ?>"
                    <?= (string)($student['course_id'] ?? '') ===
                        (string)$course['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['course_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Save Changes</button>
        <a class="back" href="list.php">Back to Student List</a>
    </form>
</div>
</body>
</html>
