
<?php
session_start();

require_once __DIR__ . "/config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, full_name, username, email, password, role, status
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1"
        );

        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (
            $user &&
            password_verify($password, $user["password"]) &&
            $user["status"] === "active"
        ) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {
                header("Location: admin/dashboard.php");
                exit;
            }

            $error = "Your account does not have administrator access.";
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Vincent College</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #eef2f7;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 35px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        h1 {
            text-align: center;
            color: #17365d;
        }

        p {
            text-align: center;
            color: #64748b;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
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

        .error {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h1>Vincent College</h1>
    <p>Administrator Login</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="username">Username or Email</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
