
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';

$success = '';
$error = '';

// Load users for specific notifications
$users = $pdo->query("
    SELECT id, full_name, role
    FROM users
    WHERE role IN ('student', 'lecturer', 'registrar', 'accountant')
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Send notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $recipient_type = $_POST['recipient_type'] ?? '';
    $recipient_id = !empty($_POST['recipient_id'])
        ? (int) $_POST['recipient_id']
        : null;
    $notification_type = $_POST['notification_type'] ?? 'general';

    $allowed_recipients = ['all', 'student', 'staff', 'specific'];

    $allowed_types = [
        'general',
        'fees',
        'timetable',
        'examination',
        'attendance'
    ];

    if (
        $title === '' ||
        $message === '' ||
        !in_array($recipient_type, $allowed_recipients, true) ||
        !in_array($notification_type, $allowed_types, true)
    ) {
        $error = "Please complete all required fields correctly.";

    } elseif ($recipient_type === 'specific' && !$recipient_id) {
        $error = "Please select a recipient.";

    } else {
        try {
            if ($recipient_type === 'specific') {
                $check = $pdo->prepare("
                    SELECT id FROM users
                    WHERE id = ?
                    AND role IN ('student', 'lecturer', 'registrar', 'accountant')
                ");
                $check->execute([$recipient_id]);

                if (!$check->fetch()) {
                    throw new Exception("Invalid recipient selected.");
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO notifications
                (
                    title,
                    message,
                    recipient_type,
                    recipient_id,
                    notification_type,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $title,
                $message,
                $recipient_type,
                $recipient_id,
                $notification_type,
                $_SESSION['user_id']
            ]);

            $success = "Notification sent successfully!";

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch sent notifications
$stmt = $pdo->query("
    SELECT n.*, u.full_name AS sender
    FROM notifications n
    LEFT JOIN users u ON n.created_by = u.id
    ORDER BY n.created_at DESC
");

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Notifications | Vincent College</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #333;
}

.sidebar {
    width: 240px;
    height: 100vh;
    background: #172554;
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    padding: 25px 15px;
}

.sidebar h2 {
    font-size: 20px;
    margin-bottom: 30px;
}

.sidebar a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 13px;
    border-radius: 5px;
    margin-bottom: 7px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #2563eb;
}

.main {
    margin-left: 240px;
    padding: 30px;
}

.header {
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 9px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

h2 {
    margin-top: 0;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

button {
    background: #2563eb;
    color: white;
    border: none;
    padding: 13px 22px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 15px;
}

button:hover {
    background: #1d4ed8;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 13px;
    margin-bottom: 18px;
    border-radius: 5px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 13px;
    margin-bottom: 18px;
    border-radius: 5px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #f1f5f9;
}

.table-wrap {
    overflow-x: auto;
}

@media(max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main {
        margin-left: 0;
        padding: 15px;
    }
}
</style>
</head>

<body>

<div class="sidebar">
    <h2>Vincent College</h2>

    <a href="dashboard.php">Dashboard</a>
    <a href="timetable.php">Timetable</a>
    <a href="attendance.php">Attendance</a>
    <a href="notifications.php" class="active">Notifications</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main">

    <div class="header">
        <h1>Notifications Management</h1>
        <p>Send announcements and important information to users.</p>
    </div>

    <div class="card">

        <h2>Compose Notification</h2>

        <?php if ($success): ?>
            <div class="success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label>Notification Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>Notification Message</label>
                <textarea name="message" required></textarea>
            </div>

            <div class="form-group">
                <label>Recipient</label>

                <select name="recipient_type"
                        id="recipient_type"
                        onchange="toggleRecipient()"
                        required>

                    <option value="all">All Users</option>
                    <option value="student">Students Only</option>
                    <option value="staff">Staff Only</option>
                    <option value="specific">Specific User</option>

                </select>
            </div>

            <div class="form-group" id="specific_user"
                 style="display:none;">

                <label>Select User</label>

                <select name="recipient_id">
                    <option value="">Select User</option>

                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int)$user['id'] ?>">
                            <?= htmlspecialchars($user['full_name']) ?>
                            (<?= htmlspecialchars($user['role']) ?>)
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label>Notification Category</label>

                <select name="notification_type" required>
                    <option value="general">General Announcement</option>
                    <option value="fees">Fees Reminder</option>
                    <option value="timetable">Timetable Changes</option>
                    <option value="examination">Examinations</option>
                    <option value="attendance">Attendance Alert</option>
                </select>
            </div>

            <button type="submit">
                Send Notification
            </button>

        </form>

    </div>

    <div class="card">

        <h2>Sent Notifications</h2>

        <div class="table-wrap">

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Recipient</th>
                    <th>Category</th>
                    <th>Sent By</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($notifications) > 0): ?>

                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td><?= (int)$n['id'] ?></td>

                        <td>
                            <?= htmlspecialchars($n['title']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(ucfirst($n['recipient_type'])) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(ucfirst($n['notification_type'])) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($n['sender'] ?? 'Admin') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($n['created_at']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">No notifications sent yet.</td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

        </div>
    </div>

</div>

<script>
function toggleRecipient() {
    const type = document.getElementById('recipient_type').value;

    document.getElementById('specific_user').style.display =
        type === 'specific' ? 'block' : 'none';
}
</script>

</body>
</html>
