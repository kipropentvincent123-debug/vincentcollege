
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

    // ADD UNIT
    if ($action === 'add') {

        $courseId = (int)($_POST['course_id'] ?? 0);
        $unitCode = trim($_POST['unit_code'] ?? '');
        $unitName = trim($_POST['unit_name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $academicYear = trim($_POST['academic_year'] ?? '');

        if ($courseId <= 0 || $unitCode === '' ||
            $unitName === '' || $semester === '' || $academicYear === '') {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO course_units
                    (course_id, unit_code, unit_name, semester, academic_year)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $courseId,
                    $unitCode,
                    $unitName,
                    $semester,
                    $academicYear
                ]);

                header('Location: units.php?status=added');
                exit;

            } catch (PDOException $e) {
                $message = $e->getCode() === '23000'
                    ? 'This unit code already exists or the selected programme is invalid.'
                    : 'Unable to register unit.';
                $messageType = 'danger';
            }
        }
    }

    // UPDATE UNIT
    if ($action === 'update') {

        $id = (int)($_POST['id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        $unitCode = trim($_POST['unit_code'] ?? '');
        $unitName = trim($_POST['unit_name'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $academicYear = trim($_POST['academic_year'] ?? '');

        if ($id <= 0 || $courseId <= 0 || $unitCode === '' ||
            $unitName === '' || $semester === '' || $academicYear === '') {

            $message = 'Please fill in all required fields.';
            $messageType = 'danger';

        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE course_units
                    SET course_id = ?,
                        unit_code = ?,
                        unit_name = ?,
                        semester = ?,
                        academic_year = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $courseId,
                    $unitCode,
                    $unitName,
                    $semester,
                    $academicYear,
                    $id
                ]);

                header('Location: units.php?status=updated');
                exit;

            } catch (PDOException $e) {
                $message = $e->getCode() === '23000'
                    ? 'This unit code already exists or the selected programme is invalid.'
                    : 'Unable to update unit.';
                $messageType = 'danger';
            }
        }
    }

    // DELETE UNIT
    if ($action === 'delete') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM course_units WHERE id = ?
                ");
                $stmt->execute([$id]);

                header('Location: units.php?status=deleted');
                exit;

            } catch (PDOException $e) {
                $message = 'Unable to delete this unit. It may already be in use.';
                $messageType = 'danger';
            }
        }
    }
}

// Success messages
$successMessages = [
    'added' => 'Unit registered successfully!',
    'updated' => 'Unit updated successfully!',
    'deleted' => 'Unit deleted successfully!'
];

if (isset($_GET['status'], $successMessages[$_GET['status']])) {
    $message = $successMessages[$_GET['status']];
    $messageType = 'success';
}

// Fetch programmes
$courses = $pdo->query("
    SELECT id, course_name, course_code
    FROM courses
    ORDER BY course_name
")->fetchAll();

// Search
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT u.*, c.course_name, c.course_code
    FROM course_units u
    INNER JOIN courses c ON u.course_id = c.id
";

if ($search !== '') {
    $sql .= "
        WHERE u.unit_code LIKE ?
        OR u.unit_name LIKE ?
        OR c.course_name LIKE ?
    ";
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {
    $term = "%$search%";
    $stmt->execute([$term, $term, $term]);
} else {
    $stmt->execute();
}

$units = $stmt->fetchAll();

// Fetch unit for editing
$editUnit = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * FROM course_units WHERE id = ?
    ");
    $stmt->execute([$editId]);

    $editUnit = $stmt->fetch();

    if (!$editUnit) {
        $message = 'Unit record not found.';
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Course Units | Kiprop College</title>

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
    min-width: 800px;
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

<h2>Course Units Management</h2>
<p>Register and manage units offered under college programmes.</p>

<?php if ($message): ?>
<div class="alert <?= h($messageType) ?>">
    <?= h($message) ?>
</div>
<?php endif; ?>

<div class="card">

<h3>
<?= $editUnit ? 'Edit Course Unit' : 'Register New Course Unit' ?>
</h3>

<form method="POST">

<input type="hidden"
       name="action"
       value="<?= $editUnit ? 'update' : 'add' ?>">

<?php if ($editUnit): ?>
<input type="hidden" name="id"
       value="<?= h($editUnit['id']) ?>">
<?php endif; ?>

<div class="form-grid">

<div>
<label>Programme *</label>

<select name="course_id" required>
<option value="">Select Programme</option>

<?php foreach ($courses as $course): ?>

<option value="<?= h($course['id']) ?>"
    <?= isset($editUnit['course_id']) &&
        $editUnit['course_id'] == $course['id']
        ? 'selected' : '' ?>>

    <?= h($course['course_name']) ?>
    (<?= h($course['course_code']) ?>)

</option>

<?php endforeach; ?>

</select>
</div>

<div>
<label>Unit Code *</label>

<input type="text" name="unit_code" required
value="<?= h($editUnit['unit_code'] ?? '') ?>">
</div>

<div>
<label>Unit Name *</label>

<input type="text" name="unit_name" required
value="<?= h($editUnit['unit_name'] ?? '') ?>">
</div>

<div>
<label>Semester *</label>

<select name="semester" required>

<option value="">Select Semester</option>

<?php
$semesters = ['Semester 1', 'Semester 2', 'Semester 3'];

foreach ($semesters as $semester):
?>

<option value="<?= h($semester) ?>"
<?= ($editUnit['semester'] ?? '') === $semester ? 'selected' : '' ?>>

<?= h($semester) ?>

</option>

<?php endforeach; ?>

</select>
</div>

<div>
<label>Academic Year *</label>

<input type="text"
name="academic_year"
placeholder="e.g. 2026/2027"
required
value="<?= h($editUnit['academic_year'] ?? '') ?>">

</div>

</div>

<button type="submit" class="primary">
<?= $editUnit ? 'Update Unit' : 'Register Unit' ?>
</button>

<?php if ($editUnit): ?>
<a href="units.php" class="btn cancel">Cancel Edit</a>
<?php endif; ?>

</form>
</div>

<div class="card">

<h3>Registered Course Units</h3>

<form method="GET" class="search-form">

<input type="text"
name="search"
placeholder="Search unit code, unit name or programme..."
value="<?= h($search) ?>">

<button type="submit" class="primary">Search</button>

<a href="units.php" class="btn cancel">Clear</a>

</form>

<div class="table-wrap">

<table>

<thead>
<tr>
<th>#</th>
<th>Programme</th>
<th>Unit Code</th>
<th>Unit Name</th>
<th>Semester</th>
<th>Academic Year</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if ($units): ?>

<?php foreach ($units as $index => $unit): ?>

<tr>

<td><?= $index + 1 ?></td>

<td><?= h($unit['course_name']) ?></td>

<td><?= h($unit['unit_code']) ?></td>

<td><?= h($unit['unit_name']) ?></td>

<td><?= h($unit['semester']) ?></td>

<td><?= h($unit['academic_year']) ?></td>

<td>

<div class="actions">

<a href="units.php?edit=<?= h($unit['id']) ?>"
class="btn edit">Edit</a>

<form method="POST"
onsubmit="return confirm('Are you sure you want to delete this unit?');">

<input type="hidden" name="action" value="delete">

<input type="hidden" name="id"
value="<?= h($unit['id']) ?>">

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
<td colspan="7">No course units registered yet.</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

<p>Total units: <strong><?= count($units) ?></strong></p>

</div>

<p>
<a href="index.php">Lecturer Management</a> |
<a href="../admin/dashboard.php">Back to Dashboard</a>
</p>

</div>

</body>
</html>
