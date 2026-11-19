
<?php
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // ADD LECTURER
    if ($action === 'add') {

        $staffNumber = trim($_POST['staff_number'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $designation = trim($_POST['designation'] ?? '');

        if ($staffNumber === '' || $fullName === '') {
            $message = 'Staff number and full name are required.';
            $messageType = 'danger';

        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';

        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO staff
                    (staff_number, full_name, email, phone, department_id, designation)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $staffNumber,
                    $fullName,
                    $email ?: null,
                    $phone ?: null,
                    $departmentId ?: null,
                    $designation ?: null
                ]);

                header('Location: index.php?status=added');
                exit;

            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = 'This staff number already exists.';
                } else {
                    $message = 'Unable to register lecturer. Please check your database.';
                }
                $messageType = 'danger';
            }
        }
    }

    // UPDATE LECTURER
    if ($action === 'update') {

        $id = (int)($_POST['id'] ?? 0);
        $staffNumber = trim($_POST['staff_number'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $designation = trim($_POST['designation'] ?? '');

        if ($id <= 0 || $staffNumber === '' || $fullName === '') {
            $message = 'Please provide valid lecturer details.';
            $messageType = 'danger';

        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'danger';

        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE staff
                    SET staff_number = ?,
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        department_id = ?,
                        designation = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $staffNumber,
                    $fullName,
                    $email ?: null,
                    $phone ?: null,
                    $departmentId ?: null,
                    $designation ?: null,
                    $id
                ]);

                header('Location: index.php?status=updated');
                exit;

            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $message = 'That staff number is already in use.';
                } else {
                    $message = 'Unable to update lecturer.';
                }
                $messageType = 'danger';
            }
        }
    }

    // DELETE LECTURER
    if ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                $stmt->execute([$id]);

                header('Location: index.php?status=deleted');
                exit;

            } catch (PDOException $e) {
                $message = 'Unable to delete this lecturer. Check whether another record depends on it.';
                $messageType = 'danger';
            }
        }
    }
}

// Success messages
if (isset($_GET['status'])) {
    $messages = [
        'added' => 'Lecturer registered successfully!',
        'updated' => 'Lecturer updated successfully!',
        'deleted' => 'Lecturer deleted successfully!'
    ];

    if (isset($messages[$_GET['status']])) {
        $message = $messages[$_GET['status']];
        $messageType = 'success';
    }
}

// Fetch departments
$departments = $pdo->query("
    SELECT id, department_name
    FROM departments
    ORDER BY department_name
")->fetchAll();

// Search lecturers
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT s.*, d.department_name
    FROM staff s
    LEFT JOIN departments d ON s.department_id = d.id
";

if ($search !== '') {
    $sql .= "
        WHERE s.staff_number LIKE ?
        OR s.full_name LIKE ?
        OR s.email LIKE ?
    ";
}

$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {
    $term = "%$search%";
    $stmt->execute([$term, $term, $term]);
} else {
    $stmt->execute();
}

$lecturers = $stmt->fetchAll();

// Get lecturer for editing
$editLecturer = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$editId]);
    $editLecturer = $stmt->fetch();

    if (!$editLecturer) {
        $message = 'Lecturer record not found.';
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Lecturer Management | Kiprop College</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    margin: 0;
    padding: 20px;
    color: #1e293b;
}

.container {
    max-width: 1200px;
    margin: auto;
}

h2 {
    color: #1e3a8a;
}

.card {
    background: white;
    padding: 22px;
    margin-bottom: 22px;
    border-radius: 10px;
    box-shadow: 0 2px 8px #00000010;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

label {
    font-weight: bold;
    display: block;
}

input, select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    margin-top: 7px;
}

button, .btn {
    display: inline-block;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
}

.primary {
    background: #1d4ed8;
    color: white;
}

.edit {
    background: #0f766e;
    color: white;
}

.delete {
    background: #dc2626;
    color: white;
}

.cancel {
    background: #64748b;
    color: white;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #1e3a8a;
    color: white;
}

.table-wrap {
    overflow-x: auto;
}

.alert {
    padding: 13px;
    margin-bottom: 15px;
    border-radius: 6px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.danger {
    background: #fee2e2;
    color: #991b1b;
}

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}

.search-form input {
    margin: 0;
}

.actions {
    display: flex;
    gap: 7px;
}

@media(max-width: 650px) {
    body {
        padding: 10px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .search-form {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<div class="container">

<h2>Lecturer Management</h2>
<p>Register, search and manage college lecturers.</p>

<?php if ($message): ?>
<div class="alert <?= h($messageType) ?>">
    <?= h($message) ?>
</div>
<?php endif; ?>


<!-- REGISTRATION / EDIT FORM -->

<div class="card">

<h3>
<?= $editLecturer ? 'Edit Lecturer Details' : 'Register New Lecturer' ?>
</h3>

<form method="POST">

<input type="hidden"
       name="action"
       value="<?= $editLecturer ? 'update' : 'add' ?>">

<?php if ($editLecturer): ?>
<input type="hidden"
       name="id"
       value="<?= h($editLecturer['id']) ?>">
<?php endif; ?>

<div class="form-grid">

<div>
<label>Staff Number *</label>
<input type="text"
       name="staff_number"
       required
       value="<?= h($editLecturer['staff_number'] ?? '') ?>">
</div>

<div>
<label>Full Name *</label>
<input type="text"
       name="full_name"
       required
       value="<?= h($editLecturer['full_name'] ?? '') ?>">
</div>

<div>
<label>Email</label>
<input type="email"
       name="email"
       value="<?= h($editLecturer['email'] ?? '') ?>">
</div>

<div>
<label>Phone</label>
<input type="text"
       name="phone"
       value="<?= h($editLecturer['phone'] ?? '') ?>">
</div>

<div>
<label>Department</label>

<select name="department_id">

<option value="">Select Department</option>

<?php foreach ($departments as $department): ?>

<option value="<?= h($department['id']) ?>"
    <?= isset($editLecturer['department_id']) &&
        $editLecturer['department_id'] == $department['id']
        ? 'selected' : '' ?>>

    <?= h($department['department_name']) ?>

</option>

<?php endforeach; ?>

</select>
</div>

<div>
<label>Designation</label>

<select name="designation">

<option value="">Select Designation</option>

<?php
$designations = [
    'Lecturer',
    'Senior Lecturer',
    'Head of Department',
    'Assistant Lecturer',
    'Tutor',
    'Other'
];

$currentDesignation = $editLecturer['designation'] ?? '';

foreach ($designations as $designation):
?>

<option value="<?= h($designation) ?>"
    <?= $currentDesignation === $designation ? 'selected' : '' ?>>

    <?= h($designation) ?>

</option>

<?php endforeach; ?>

</select>
</div>

</div>

<button type="submit" class="primary">
<?= $editLecturer ? 'Update Lecturer' : 'Register Lecturer' ?>
</button>

<?php if ($editLecturer): ?>
<a href="index.php" class="btn cancel">Cancel Edit</a>
<?php endif; ?>

</form>

</div>


<!-- LECTURER LIST -->

<div class="card">

<h3>Registered Lecturers</h3>

<form method="GET" class="search-form">

<input type="text"
       name="search"
       placeholder="Search staff number, name or email..."
       value="<?= h($search) ?>">

<button type="submit" class="primary">Search</button>

<a href="index.php" class="btn cancel">Clear</a>

</form>

<div class="table-wrap">

<table>

<thead>
<tr>
<th>#</th>
<th>Staff Number</th>
<th>Full Name</th>
<th>Email</th>
<th>Phone</th>
<th>Department</th>
<th>Designation</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if ($lecturers): ?>

<?php foreach ($lecturers as $index => $lecturer): ?>

<tr>

<td><?= $index + 1 ?></td>

<td><?= h($lecturer['staff_number']) ?></td>

<td><?= h($lecturer['full_name']) ?></td>

<td><?= h($lecturer['email']) ?></td>

<td><?= h($lecturer['phone']) ?></td>

<td><?= h($lecturer['department_name'] ?? 'Not assigned') ?></td>

<td><?= h($lecturer['designation']) ?></td>

<td>

<div class="actions">

<a class="btn edit"
   href="index.php?edit=<?= h($lecturer['id']) ?>">
   Edit
</a>

<form method="POST"
      onsubmit="return confirm('Are you sure you want to delete this lecturer?');">

<input type="hidden" name="action" value="delete">

<input type="hidden"
       name="id"
       value="<?= h($lecturer['id']) ?>">

<button type="submit" class="delete">
Delete
</button>

</form>

</div>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="8">No lecturers found.</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

<p>Total lecturers found: <strong><?= count($lecturers) ?></strong></p>

</div>

<p>
<a href="../admin/dashboard.php">Back to Dashboard</a>
</p>

</div>

</body>
</html>
