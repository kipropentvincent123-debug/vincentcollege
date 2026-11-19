
<?php
session_start();

require_once __DIR__ . "/config/database.php";

$message = "";
$success = false;

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminExists = (int) $stmt->fetchColumn() > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {

    $fullName = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $setupKey = $_POST["setup_key"] ?? "";

    if ($fullName === "" || $username === "" || $email === "") {
        $message = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Enter a valid email address.";

    } elseif (strlen($password) < 12) {
        $message = "Password must contain at least 12 characters.";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";

    } elseif (!defined("ADMIN_SETUP_KEY") ||
              !hash_equals(ADMIN_SETUP_KEY, $setupKey)) {
        $message = "Invalid administrator setup key.";

    } else {

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare(
                "SELECT id FROM users WHERE username = ? OR email = ?"
            );
            $check->execute([$username, $email]);

            if ($check->fetch()) {
                throw new RuntimeException(
                    "Username or email is already registered."
                );
            }

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $insert = $pdo->prepare(
                "INSERT INTO users
                (full_name, username, email, password, role, status)
                VALUES (?, ?, ?, ?, 'admin', 'active')"
            );

            $insert->execute([
                $fullName,
                $username,
                $email,
                $hashedPassword
            ]);

            $pdo->commit();

            $success = true;
            $adminExists = true;
            $message = "Administrator account created successfully.";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : "Unable to create administrator. Check the server error log.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Setup | Vincent College</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #eef2f7;
        }

        .container {
            width: 100%;
            max-width: 460px;
            padding: 32px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        h1 { text-align: center; color: #17365d; }
        p { text-align: center; color: #64748b; }

        label {
            display: block;
            margin: 15px 0 6px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 6px;
            background: #17365d;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover { background: #24558c; }

        .message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            background: #fef3c7;
            color: #92400e;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .login-link {
            display: block;
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Vincent College</h1>
    <p>Administrator Account Setup</p>

    <?php if ($message !== ""): ?>
        <div class="message <?= $success ? 'success' : '' ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($adminExists): ?>

        <p>An administrator account already exists or has been created.</p>
        <a class="login-link" href="login.php">Go to Login</a>

    <?php else: ?>

        <form method="POST" action="">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" required>

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password (minimum 12 characters)</label>
            <input type="password" id="password" name="password"
                   minlength="12" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password"
                   name="confirm_password" minlength="12" required>

            <label for="setup_key">Administrator Setup Key</label>
            <input type="password" id="setup_key" name="setup_key" required>

            <button type="submit">Create Administrator</button>
        </form>

    <?php endif; ?>

</div>
</body>
</html>
