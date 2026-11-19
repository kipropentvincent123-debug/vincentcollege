
<?php
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';

$messages = [
    'created' => 'Examination created successfully.',
    'updated' => 'Examination updated successfully.',
    'deleted' => 'Examination deleted successfully.',
    'hasmarks' => 'Cannot delete this examination because marks have already been entered.',
    'notfound' => 'The examination was not found.',
    'invalid' => 'Invalid examination request.',
    'deletefailed' => 'Unable to delete examination.'
];

if (isset($_GET['error']) && isset($messages[$_GET['error']])) {
    $message = $messages[$_GET['error']];
    $messageType = 'error';
} elseif (isset($_GET['created']) || isset($_GET['updated']) || isset($_GET['deleted'])) {
    $key = isset($_GET['created']) ? 'created' :
           (isset($_GET['updated']) ? 'updated' : 'deleted');

    $message = $messages[$key];
    $messageType = 'success';
}

// Create examination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {

    $examName = trim($_POST['exam_name'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $examDate = trim($_POST['exam_date'] ?? '');

    $validDate = DateTime::createFromFormat('Y-m-d', $examDate);

    if ($examName === '' || $academicYear === '' ||
        $semester === '' || !$validDate ||
        $validDate->format('Y-m-d') !== $examDate) {

        $message = 'Please enter valid information in all fields.';
        $messageType = 'error';

    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO examinations
                (exam_name, academic_year, semester, exam_date)
                VALUES
                (:exam_name, :academic_year, :semester, :exam_date)
            ");

            $stmt->execute([
                'exam_name' => $examName,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'exam_date' => $examDate
            ]);

            header('Location: manage-exams.php?created=1');
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Unable to create examination.';
            $messageType = 'error';
        }
    }
}

// Search and filters
$search = trim($_GET['search'] ?? '');
$yearFilter = trim($_GET['academic_year'] ?? '');
$semesterFilter = trim($_GET['semester'] ?? '');

$sql = "SELECT * FROM examinations WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND exam_name LIKE :search";
    $params['search'] = "%$search%";
}

if ($yearFilter !== '') {
    $sql .= " AND academic_year = :academic_year";
    $params['academic_year'] = $yearFilter;
}

if ($semesterFilter !== '') {
    $sql .= " AND semester = :semester";
    $params['semester'] = $semesterFilter;
}

$sql .= " ORDER BY exam_date DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$examinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter options
$years = $pdo->query("
    SELECT DISTINCT academic_year
    FROM examinations
    ORDER BY academic_year DESC
")->fetchAll(PDO::FETCH_COLUMN);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Examination Management | Kiprop College</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f5f9;
    color: #333;
}

header {
    background: #17365d;
    color: white;
    padding: 20px;
}

header h1 { margin: 0 0 8px; }

header a {
    color: white;
    text-decoration: none;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 15px;
}

.panel {
    background: white;
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px #ddd;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
}

input, select {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background: #17365d;
    color: white;
    border: none;
    padding: 11px 18px;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 15px;
}

button:hover { background: #24558d; }

.message {
    padding: 13px;
    margin-bottom: 18px;
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

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 12px;
    align-items: end;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

th { background: #edf1f5; }

.table-wrapper { overflow-x: auto; }

.edit-link {
    color: #1769aa;
    font-weight: bold;
    text-decoration: none;
}

.delete-button {
    background: #c0392b;
    margin: 0;
    padding: 7px 12px;
}

.delete-button:hover { background: #922b21; }

.result-count {
    color: #666;
    margin-bottom: 15px;
}

@media(max-width: 750px) {
    .form-grid, .filter-grid {
        grid-template-columns: 1fr;
    }

    .panel { padding: 15px; }
}
</style>
</head>

<body>

<header>
    <h1>Kiprop College Management System</h1>
    <p>Examination Management</p>
    <a href="../index.php">Back to Main Dashboard</a>
</header>

<div class="container">

<div class="panel">

<h2>Create New Examination</h2>

<?php if ($message !== ''): ?>
    <div class="message <?= h($messageType) ?>">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<form method="POST">

<div class="form-grid">

<div>
<label for="exam_name">Examination Name</label>
<input type="text" id="exam_name" name="exam_name"
       placeholder="e.g. End of Semester Examination"
       maxlength="150" required>
</div>

<div>
<label for="academic_year">Academic Year</label>
<input type="text" id="academic_year" name="academic_year"
       placeholder="e.g. 2026/2027"
       maxlength="20" required>
</div>

<div>
<label for="semester">Semester</label>
<select id="semester" name="semester" required>
<option value="">Select Semester</option>
<option value="Semester 1">Semester 1</option>
<option value="Semester 2">Semester 2</option>
<option value="Semester 3">Semester 3</option>
</select>
</div>

<div>
<label for="exam_date">Examination Date</label>
<input type="date" id="exam_date" name="exam_date" required>
</div>

</div>

<button type="submit" name="create_exam">
Create Examination
</button>

</form>
</div>

<div class="panel">

<h2>Search and Filter Examinations</h2>

<form method="GET">

<div class="filter-grid">

<div>
<label for="search">Examination Name</label>
<input type="text" id="search" name="search"
       value="<?= h($search) ?>"
       placeholder="Search examination...">
</div>

<div>
<label for="year_filter">Academic Year</label>
<select name="academic_year" id="year_filter">
<option value="">All Years</option>
<?php foreach ($years as $year): ?>
<option value="<?= h($year) ?>"
    <?= $yearFilter === $year ? 'selected' : '' ?>>
    <?= h($year) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div>
<label for="semester_filter">Semester</label>
<select name="semester" id="semester_filter">
<option value="">All Semesters</option>
<option value="Semester 1" <?= $semesterFilter === 'Semester 1' ? 'selected' : '' ?>>Semester 1</option>
<option value="Semester 2" <?= $semesterFilter === 'Semester 2' ? 'selected' : '' ?>>Semester 2</option>
<option value="Semester 3" <?= $semesterFilter === 'Semester 3' ? 'selected' : '' ?>>Semester 3</option>
</select>
</div>

<div>
<button type="submit">Apply Filters</button>
<a href="manage-exams.php">Clear</a>
</div>

</div>
</form>

</div>

<div class="panel">

<h2>Examination Records</h2>

<p class="result-count">
    Total examinations displayed: <?= count($examinations) ?>
</p>

<div class="table-wrapper">

<table>

<thead>
<tr>
<th>ID</th>
<th>Examination</th>
<th>Academic Year</th>
<th>Semester</th>
<th>Exam Date</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if ($examinations): ?>

<?php foreach ($examinations as $exam): ?>

<tr>

<td><?= (int)$exam['id'] ?></td>

<td><?= h($exam['exam_name']) ?></td>

<td><?= h($exam['academic_year']) ?></td>

<td><?= h($exam['semester']) ?></td>

<td><?= h($exam['exam_date']) ?></td>

<td>

<a class="edit-link"
   href="edit-exam.php?id=<?= (int)$exam['id'] ?>">
   Edit
</a>

<form method="POST"
      action="delete-exam.php"
      onsubmit="return confirm('Delete this examination? Examinations with marks cannot be deleted.');"
      style="display:inline;">

<input type="hidden" name="id"
       value="<?= (int)$exam['id'] ?>">

<button type="submit" class="delete-button">
Delete
</button>

</form>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="6">No examinations found.</td>
</tr>

<?php endif; ?>

</tbody>
</table>

</div>
</div>
</div>

</body>
</html>
