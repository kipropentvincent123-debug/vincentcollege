
<?php
require_once __DIR__ . '/../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $admission_number = trim($_POST['admission_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = $_POST['gender'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $course_id = (int) $_POST['course_id'];

    if (
        $admission_number && $first_name && $last_name &&
        $gender && $course_id > 0
    ) {
        try {
            $sql = "INSERT INTO students
                (admission_number, first_name, last_name,
                 gender, email, phone, course_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $admission_number,
                $first_name,
                $last_name,
                $gender,
                $email,
                $phone,
                $course_id
            ]);

            $message = "Student registered successfully!";
        } catch (PDOException $e) {
            $message = "Registration failed. Check the admission number and database details.";
        }
    } else {
        $message = "Please complete all required fields.";
    }
}

$courses = $pdo->query(
    "SELECT id, course_name FROM courses ORDER BY course_name"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration | Kiprop College</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 30px 15px;
        }

        .container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        h2 {
            color: #12345a;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            background: #12345a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4e89;
        }

        .message {
            background: #e0f2fe;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Kiprop College</h2>
    <h3>Student Registration</h3>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Admission Number</label>
        <input type="text" name="admission_number" required>

        <label>First Name</label>
        <input type="text" name="first_name" required>

        <label>Last Name</label>
        <input type="text" name="last_name" required>

        <label>Gender</label>
        <select name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
        </select>

        <label>Email Address</label>
        <input type="email" name="email">

        <label>Phone Number</label>
        <input type="text" name="phone">

        <label>Course</label>
        <select name="course_id" required>
            <option value="">Select Course</option>

            <?php foreach ($courses as $course): ?>
                <option value="<?= $course['id'] ?>">
                    <?= htmlspecialchars($course['course_name']) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <button type="submit">Register Student</button>

    </form>

</div>

</body>
</html>
