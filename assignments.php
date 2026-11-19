
<?php
require_once __DIR__ . '/../config/database.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        if ($action === 'assign') {

            $staffId = (int)($_POST['staff_id'] ?? 0);
            $unitId = (int)($_POST['course_unit_id'] ?? 0);

            if ($staffId <= 0 || $unitId <= 0) {
                throw new Exception('Please select both a lecturer and a course unit.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO lecturer_unit_assignments
                (staff_id, course_unit_id)
                VALUES (?, ?)
            ");

            $stmt->execute([$staffId, $unitId]);

            $message = 'Lecturer assigned to course unit successfully!';
            $messageType = 'success';
        }

        if ($action === 'delete') {

            $assignmentId = (int)($_POST['assignment_id'] ?? 0);

            $stmt = $pdo->prepare("
                DELETE FROM lecturer_unit_assignments
                WHERE id = ?
            ");

            $stmt->execute([$assignmentId]);

            $message = 'Assignment removed successfully.';
            $messageType = 'success';
        }

    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {
            $message = 'This course unit is already assigned to a lecturer.';
        } else {
            $message = 'Database error: ' . $e->getMessage();
        }

        $messageType = 'error';

    } catch (Exception $e) {

        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get lecturers
$lecturers = $pdo->query("
    SELECT id, staff_number, full_name
    FROM staff
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get course units
$units = $pdo->query("
    SELECT u.id, u.unit_code, u.unit_name,
           c.course_name, u.semester, u.academic_year
    FROM course_units u
    INNER JOIN courses c ON u.course_id = c.id
    ORDER BY c.course_name, u.unit_code
")->fetchAll(PDO::FETCH_ASSOC);

// Search assignments
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT a.id, a.assigned_at,
           s.staff_number, s.full_name,
           u.unit_code, u.unit_name,
           c.course_name,
           u.semester, u.academic_year
    FROM lecturer_unit_assignments a
    INNER JOIN staff s ON a.staff_id = s.id
    INNER JOIN course_units u ON a.course_unit_id = u.id
    INNER JOIN courses c ON u.course_id = c.id
";

if ($search !== '') {
    $sql .= "
        WHERE s.staff_number LIKE ?
        OR s.full_name LIKE ?
        OR u.unit_code LIKE ?
        OR u.unit_name LIKE ?
        OR c.course_name LIKE ?
    ";
}

$sql .= " ORDER BY a.id DESC";

$stmt = $pdo->prepare($sql);

if ($search !== '') {
    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $term, $term]);
} else {
    $stmt->execute();
}

$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Unit Assignments</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        h2 {
            color: #17365d;
        }

        .card {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        select, input {
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button, .link-button {
            display: inline-block;
            padding: 11px 17px;
            margin-top: 15px;
            border: none;
            border-radius: 5px;
            color: white;
            background: #1769aa;
            cursor: pointer;
            text-decoration: none;
        }

        button:hover, .link-button:hover {
            opacity: 0.85;
        }

        .delete-button {
            background: #c0392b;
            margin: 0;
            padding: 8px 12px;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 750px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #17365d;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .navigation {
            margin-bottom: 20px;
        }

        .navigation a {
            margin-right: 10px;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Lecturer–Course Unit Assignment</h2>

    <div class="navigation">
        <a class="link-button" href="index.php">Lecturer Management</a>
        <a class="link-button" href="units.php">Course Units</a>
        <a class="link-button" href="../admin/dashboard.php">Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h3>Assign Lecturer to Course Unit</h3>

        <?php if (empty($lecturers)): ?>

            <p>Please register a lecturer before making an assignment.</p>

        <?php elseif (empty($units)): ?>

            <p>Please register course units before making an assignment.</p>

        <?php else: ?>

            <form method="POST">

                <input type="hidden" name="action" value="assign">

                <label>Select Lecturer</label>

                <select name="staff_id" required>
                    <option value="">-- Select Lecturer --</option>

                    <?php foreach ($lecturers as $lecturer): ?>
                        <option value="<?= (int)$lecturer['id'] ?>">
                            <?= h($lecturer['staff_number']) ?>
                            - <?= h($lecturer['full_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label>Select Course Unit</label>

                <select name="course_unit_id" required>
                    <option value="">-- Select Course Unit --</option>

                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int)$unit['id'] ?>">
                            <?= h($unit['unit_code']) ?>
                            - <?= h($unit['unit_name']) ?>
                            (<?= h($unit['course_name']) ?>,
                            <?= h($unit['semester']) ?>,
                            <?= h($unit['academic_year']) ?>)
                        </option>
                    <?php endforeach; ?>

                </select>

                <button type="submit">Assign Lecturer</button>

            </form>

        <?php endif; ?>

    </div>

    <div class="card">

        <h3>Registered Lecturer Assignments</h3>

        <form method="GET">
            <label>Search Assignments</label>

            <input
                type="text"
                name="search"
                placeholder="Search lecturer, unit code or programme"
                value="<?= h($search) ?>"
            >

            <button type="submit">Search</button>
            <a class="link-button" href="assignments.php">Clear Search</a>
        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Lecturer Number</th>
                        <th>Lecturer Name</th>
                        <th>Unit Code</th>
                        <th>Unit Name</th>
                        <th>Programme</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($assignments)): ?>

                        <tr>
                            <td colspan="9">No lecturer assignments found.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($assignments as $index => $assignment): ?>

                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= h($assignment['staff_number']) ?></td>
                                <td><?= h($assignment['full_name']) ?></td>
                                <td><?= h($assignment['unit_code']) ?></td>
                                <td><?= h($assignment['unit_name']) ?></td>
                                <td><?= h($assignment['course_name']) ?></td>
                                <td><?= h($assignment['semester']) ?></td>
                                <td><?= h($assignment['academic_year']) ?></td>

                                <td>
                                    <form method="POST"
                                          onsubmit="return confirm('Remove this lecturer assignment?');">

                                        <input type="hidden" name="action" value="delete">

                                        <input
                                            type="hidden"
                                            name="assignment_id"
                                            value="<?= (int)$assignment['id'] ?>"
                                        >

                                        <button type="submit" class="delete-button">
                                            Remove
                                        </button>

                                    </form>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
