
<?php
session_start();

require_once __DIR__ . "/../config/database.php";

// Protect the dashboard
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$role = $_SESSION["role"] ?? '';

// Get college statistics
$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$staffCount = (int) $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$departmentCount = (int) $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$courseCount = (int) $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Count unread notifications for this administrator
$unreadCount = 0;

$sql = "
    SELECT COUNT(DISTINCT n.id)
    FROM notifications n
    LEFT JOIN notification_reads nr
        ON nr.notification_id = n.id
        AND nr.user_id = :user_id
    WHERE nr.read_at IS NULL
    AND (
        n.recipient_type = 'all'

        OR (
            n.recipient_type = 'staff'
            AND :staff_role IN (
                'admin',
                'lecturer',
                'registrar',
                'accountant'
            )
        )

        OR (
            n.recipient_type = 'student'
            AND :student_role = 'student'
        )

        OR (
            n.recipient_type = 'specific'
            AND n.recipient_id = :specific_user_id
        )
    )
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':user_id' => $userId,
    ':staff_role' => $role,
    ':student_role' => $role,
    ':specific_user_id' => $userId
]);

$unreadCount = (int) $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Administrator Dashboard | Vincent College</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.sidebar {
    position: fixed;
    width: 245px;
    height: 100vh;
    background: #17365d;
    color: white;
    padding: 25px 15px;
    overflow-y: auto;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 35px;
}

.sidebar a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 14px;
    margin-bottom: 8px;
    border-radius: 6px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #2b5485;
}

.main {
    margin-left: 245px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.notification-bell {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 45px;
    background: white;
    border-radius: 8px;
    text-decoration: none;
    font-size: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.notification-bell:hover {
    background: #e2e8f0;
}

.notification-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc2626;
    color: white;
    font-size: 11px;
    font-weight: bold;
    min-width: 20px;
    height: 20px;
    padding: 3px 5px;
    border-radius: 20px;
    text-align: center;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.card h3 {
    color: #64748b;
    font-size: 15px;
}

.number {
    font-size: 32px;
    font-weight: bold;
    color: #17365d;
}

.logout {
    color: white;
    background: #dc2626;
    padding: 11px 16px;
    border-radius: 6px;
    text-decoration: none;
}

.welcome {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-top: 25px;
}

@media (max-width: 1000px) {
    .cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 650px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .sidebar h2 {
        margin-bottom: 15px;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .topbar {
        align-items: flex-start;
    }

    .topbar-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <h2>Vincent College</h2>

    <a href="dashboard.php" class="active">Dashboard</a>

    <a href="notifications.php">Send Notifications</a>

    <a href="../my_notifications.php">My Notifications</a>

    <a href="../students/">Students</a>

    <a href="../staff/">Staff</a>

    <a href="timetable.php">Timetable</a>

    <a href="../index.php">Home</a>

    <a href="../logout.php">Logout</a>

</aside>

<main class="main">

    <div class="topbar">

        <div>
            <h1>Administrator Dashboard</h1>

            <p>
                Welcome,
                <?= htmlspecialchars($_SESSION["full_name"] ?? "Administrator", ENT_QUOTES, "UTF-8") ?>
            </p>
        </div>

        <div class="topbar-actions">

            <a
                href="../my_notifications.php"
                class="notification-bell"
                title="View Notifications"
                aria-label="Notifications: <?= $unreadCount ?> unread"
            >
                🔔

                <?php if ($unreadCount > 0): ?>
                    <span class="notification-count">
                        <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                    </span>
                <?php endif; ?>

            </a>

            <a class="logout" href="../logout.php">Logout</a>

        </div>

    </div>

    <section class="cards">

        <div class="card">
            <h3>Total Students</h3>
            <div class="number"><?= $studentCount ?></div>
        </div>

        <div class="card">
            <h3>Total Staff</h3>
            <div class="number"><?= $staffCount ?></div>
        </div>

        <div class="card">
            <h3>Departments</h3>
            <div class="number"><?= $departmentCount ?></div>
        </div>

        <div class="card">
            <h3>Courses</h3>
            <div class="number"><?= $courseCount ?></div>
        </div>

    </section>

    <section class="welcome">

        <h2>College Management System</h2>

        <p>Your administrator dashboard is now active.</p>

        <p>Use the navigation menu to access college management sections.</p>

    </section>

</main>

</body>
</html>
