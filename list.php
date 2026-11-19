
<?php
require_once __DIR__ . '/../config/database.php';

$sql = "SELECT
            s.id,
            s.admission_number,
            s.first_name,
            s.last_name,
            s.gender,
            s.email,
            s.phone,
            s.status,
            c.course_name
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        ORDER BY s.id DESC";

$stmt = $pdo->query($sql);
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records | Kiprop College</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 24px;
            border-radius: 10px;
        }

        h2 { color: #12345a; }

        .button {
            display: inline-block;
            background: #12345a;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 18px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #12345a;
            color: white;
        }

        tr:hover { background: #f8fafc; }

        @media (max-width: 600px) {
            body { padding: 10px; }
            .container { padding: 15px; }
        }
    </style>
</head>

<body>
<div class="container">

    <h2>Kiprop College Management System</h2>
    <h3>Registered Students</h3>

    <a class="button" href="add.php">+ Register Student</a>

    <p>Total records: <?= count($students) ?></p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Admission No.</th>
                    <th>Student Name</th>
                    <th>Gender</th>
                    <th>Course</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>

                </tr>
            </thead>

            <tbody>
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['admission_number']) ?></td>
                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td><?= htmlspecialchars($student['gender']) ?></td>
                            <td><?= htmlspecialchars($student['course_name'] ?? 'Not assigned') ?></td>
                            <td><?= htmlspecialchars($student['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($student['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($student['status'] ?? 'Active') ?></td><td>
    <td>
    <a href="edit.php?id=<?= (int)$student['id'] ?>">Edit</a>

    |

    <a href="delete.php?id=<?= (int)$student['id'] ?>"
       style="color:red;">
       Delete
    </a>
</td>
</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No students registered yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
