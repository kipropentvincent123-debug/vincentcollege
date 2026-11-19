
<?php
require_once __DIR__ . '/../config/database.php';

$departments = [];
$message = '';
$messageType = '';

$admissionNumber = '';
$firstName = '';
$lastName = '';
$gender = '';
$email = '';
$phone = '';
$departmentId = '';
$courseId = '';
$admissionDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionNumber = trim($_POST['admission_number'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $departmentId = (int)($_POST['department_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $admissionDate = $_POST['admission_date'] ?? '';

    if (
        $admissionNumber === '' ||
        $firstName === '' ||
        $lastName === '' ||
        $gender === '' ||
        $departmentId <= 0 ||
        $courseId <= 0 ||
        $admissionDate === ''
    ) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';

    } elseif (
        !in_array($gender, ['Male', 'Female'], true)
    ) {
        $message = 'Please select a valid gender.';
        $messageType = 'error';

    } elseif (
        strlen($admissionNumber) > 50 ||
        strlen($firstName) > 100 ||
        strlen($lastName) > 100 ||
        strlen($email) > 150 ||
        strlen($phone) > 30
    ) {
        $message = 'One or more fields are too long.';
        $messageType = 'error';

    } elseif (
        $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $admissionDate)
    ) {
        $message = 'Please enter a valid admission date.';
        $messageType = 'error';

    } else {
        $dateParts = explode('-', $admissionDate);

        if (!checkdate(
            (int)$dateParts[1],
            (int)$dateParts[2],
            (int)$dateParts[0]
        )) {
            $message = 'Please enter a valid calendar date.';
            $messageType = 'error';
        }
    }

    if ($message === '') {
        try {
            // Confirm that the selected course belongs to the department.
            $courseCheck = $pdo->prepare(
                "SELECT id FROM courses
                 WHERE id = ? AND department_id = ?"
            );
            $courseCheck->execute([$courseId, $departmentId]);

            if (!$courseCheck->fetch()) {
                $message = 'The selected course does not belong to that department.';
                $messageType = 'error';
            } else {
                // Check for duplicate admission number.
                $duplicateCheck = $pdo->prepare(
                    "SELECT id FROM students WHERE admission_number = ?"
                );
                $duplicateCheck->execute([$admissionNumber]);

                if ($duplicateCheck->fetch()) {
                    $message = 'This admission number is already registered.';
                    $messageType = 'error';
                } else {
                    // Register student.
                    $insert = $pdo->prepare(
                        "INSERT INTO students
                        (
                            admission_number,
                            first_name,
                            last_name,
                            gender,
                            email,
                            phone,
                            course_id,
                            admission_date,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
                    );

                    $insert->execute([
                        $admissionNumber,
                        $firstName,
                        $lastName,
                        $gender,
                        $email !== '' ? $email : null,
                        $phone !== '' ? $phone : null,
                        $courseId,
                        $admissionDate
                    ]);

                    $message = 'Student registered successfully.';
                    $messageType = 'success';

                    $admissionNumber = '';
                    $firstName = '';
                    $lastName = '';
                    $gender = '';
                    $email = '';
                    $phone = '';
                    $departmentId = '';
                    $courseId = '';
                    $admissionDate = date('Y-m-d');
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Registration failed. Please check the database setup.';
            $messageType = 'error';
        }
    }
}

// Load departments once.
try {
    $stmt = $pdo->query(
        "SELECT id, department_name
         FROM departments
         ORDER BY department_name"
    );

    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $message = 'Unable to load departments.';
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Student | Kiprop College</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px 15px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .container {
            max-width: 850px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        h2 {
            margin-top: 0;
            color: #17365d;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #17365d;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 5px;
            background: #17365d;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #24558c;
        }

        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #17365d;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Student Registration</h2>
    <p class="subtitle">Kiprop College Management System</p>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <div class="form-grid">

            <div class="form-group">
                <label for="admission_number">Admission Number *</label>
                <input
                    type="text"
                    id="admission_number"
                    name="admission_number"
                    maxlength="50"
                    value="<?= htmlspecialchars($admissionNumber) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="admission_date">Admission Date *</label>
                <input
                    type="date"
                    id="admission_date"
                    name="admission_date"
                    value="<?= htmlspecialchars($admissionDate) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="first_name">First Name *</label>
                <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    maxlength="100"
                    value="<?= htmlspecialchars($firstName) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    maxlength="100"
                    value="<?= htmlspecialchars($lastName) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    maxlength="30"
                    value="<?= htmlspecialchars($phone) ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= htmlspecialchars($email) ?>"
                >
            </div>

            <div class="form-group">
                <label for="department_id">Department *</label>
                <select id="department_id" name="department_id" required>
                    <option value="">Select Department</option>

                    <?php foreach ($departments as $department): ?>
                        <option
                            value="<?= (int)$department['id'] ?>"
                            <?= (string)$departmentId === (string)$department['id'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($department['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="course_id">Course *</label>
                <select
                    id="course_id"
                    name="course_id"
                    required
                    disabled
                >
                    <option value="">Select Department First</option>
                </select>
            </div>

            <div class="form-group full-width">
                <button type="submit">Register Student</button>
            </div>

        </div>
    </form>

    <a class="back-link" href="../admin/dashboard.php">
        Back to Dashboard
    </a>

</div>

<script>
const departmentSelect = document.getElementById('department_id');
const courseSelect = document.getElementById('course_id');

async function loadCourses(departmentId, selectedCourse = '') {
    courseSelect.innerHTML = '<option value="">Loading courses...</option>';
    courseSelect.disabled = true;

    if (!departmentId) {
        courseSelect.innerHTML =
            '<option value="">Select Department First</option>';
        return;
    }

    try {
        const response = await fetch(
            'get-courses.php?department_id=' +
            encodeURIComponent(departmentId)
        );

        if (!response.ok) {
            throw new Error('Unable to retrieve courses.');
        }

        const courses = await response.json();

        courseSelect.innerHTML =
            '<option value="">Select Course</option>';

        if (!Array.isArray(courses) || courses.length === 0) {
            courseSelect.innerHTML =
                '<option value="">No courses available</option>';
            return;
        }

        courses.forEach(course => {
            const option = document.createElement('option');

            option.value = course.id;
            option.textContent =
                course.course_name + ' (' + course.course_code + ')';

            if (String(course.id) === String(selectedCourse)) {
                option.selected = true;
            }

            courseSelect.appendChild(option);
        });

        courseSelect.disabled = false;

    } catch (error) {
        console.error(error);
        courseSelect.innerHTML =
            '<option value="">Error loading courses</option>';
    }
}

departmentSelect.addEventListener('change', function () {
    loadCourses(this.value);
});

// Restore the course list if the form was submitted with an error.
<?php if ($departmentId !== ''): ?>
loadCourses(
    <?= json_encode((string)$departmentId) ?>,
    <?= json_encode((string)$courseId) ?>
);
<?php endif; ?>
</script>

</body>
</html>
