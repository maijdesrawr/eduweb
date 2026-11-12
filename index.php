<?php
session_start();
require_once "db.php";

// ==========================================
// DISPLAY FLASH ERROR MESSAGE ONCE
// ==========================================

$loginError = $_SESSION['loginError'] ?? '';
$oldAccountID = $_SESSION['oldAccountID'] ?? '';

// Remove the messages from the session after retrieving them
unset($_SESSION['loginError']);
unset($_SESSION['oldAccountID']);

// ==========================================
// FUNCTION TO REDIRECT WITH ERROR MESSAGE
// ==========================================

function redirectWithError($message, $accountID = '') {

    $_SESSION['loginError'] = $message;
    $_SESSION['oldAccountID'] = $accountID;

    header("Location: index.php");
    exit();
}

// ==========================================
// LOGIN PROCESS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accountID = trim($_POST['admin_id'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check empty fields
    if ($accountID === '' || $password === '') {

        redirectWithError(
            "Please enter your User ID and Password.",
            $accountID
        );

    }

    // ==========================================
    // 1. CHECK SYSTEM ADMINISTRATOR ACCOUNT
    // ==========================================

    $stmt = $conn->prepare("
        SELECT admin_id, Fname, role, password
        FROM admins
        WHERE admin_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        redirectWithError(
            "Unable to process login. Please try again.",
            $accountID
        );
    }

    $stmt->bind_param("s", $accountID);
    $stmt->execute();

    $result = $stmt->get_result();
    $account = $result->fetch_assoc();

    $stmt->close();

    if ($account) {

        $storedPassword = $account['password'];

        $passwordValid = password_verify($password, $storedPassword)
            || hash_equals($storedPassword, $password);

        // Only allow System Administrators
        if (
            $passwordValid &&
            strtolower($account['role']) === 'system admin'
        ) {

            // Regenerate session ID
            session_regenerate_id(true);

            // Store administrator session
            $_SESSION['admin_id'] = $account['admin_id'];
            $_SESSION['admin_name'] = $account['Fname'];
            $_SESSION['admin_role'] = $account['role'];

            // Upgrade old plaintext password to a hash
            if (!password_verify($password, $storedPassword)) {

                $newHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $update = $conn->prepare("
                    UPDATE admins
                    SET password = ?
                    WHERE admin_id = ?
                ");

                if ($update) {
                    $update->bind_param(
                        "ss",
                        $newHash,
                        $accountID
                    );

                    $update->execute();
                    $update->close();
                }
            }

            header("Location: dashboard.php");
            exit();

        } else {

            redirectWithError(
                "Invalid account ID or password.",
                $accountID
            );
        }

    } else {

        // ==========================================
        // 2. CHECK PARENT ACCOUNT
        // ==========================================

        $stmt = $conn->prepare("
            SELECT parent_id, Fname, Password
            FROM parents
            WHERE parent_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            redirectWithError(
                "Unable to process login. Please try again.",
                $accountID
            );
        }

        $stmt->bind_param("s", $accountID);
        $stmt->execute();

        $result = $stmt->get_result();
        $parent = $result->fetch_assoc();

        $stmt->close();

        if ($parent) {

            $storedPassword = $parent['Password'];

            $passwordValid = password_verify(
                $password,
                $storedPassword
            ) || hash_equals($storedPassword, $password);

            if ($passwordValid) {

                // Regenerate session ID
                session_regenerate_id(true);

                // Store parent session
                $_SESSION['parent_id'] = $parent['parent_id'];
                $_SESSION['parent_name'] = $parent['Fname'];
                $_SESSION['parent_role'] = 'parent';

                // Upgrade old plaintext password to a hash
                if (!password_verify($password, $storedPassword)) {

                    $newHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare("
                        UPDATE parents
                        SET Password = ?
                        WHERE parent_id = ?
                    ");

                    if ($update) {

                        $update->bind_param(
                            "ss",
                            $newHash,
                            $accountID
                        );

                        $update->execute();
                        $update->close();
                    }
                }

                header("Location: parent_dashboard.php");
                exit();

            } else {

                redirectWithError(
                    "Invalid account ID or password.",
                    $accountID
                );
            }

        } else {

            redirectWithError(
                "Invalid account ID or password.",
                $accountID
            );
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>EduPocket | Login</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #eef4ff;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            color: #a92323;
            margin-bottom: 25px;
        }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 20px;
            outline: none;
            font-size: 14px;
        }

        input:focus {
            border-color: #a92323;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #a92323;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        button:hover {
            background: #8f1d1d;
        }

        /* ERROR MESSAGE */

        .error {
            display: block;
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px 15px;
            border: 1px solid #fecaca;
            border-radius: 7px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
            line-height: 1.5;
        }

        .footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 25px;
        }

    </style>

</head>

<body>

    <div class="login-container">

        <div class="logo">EduPocket</div>

        <h2>Welcome!</h2>

        <p class="subtitle">
            Log in to your EduPocket account.
        </p>

        <!-- DISPLAY ERROR MESSAGE -->

        <?php if (!empty($loginError)): ?>

            <div class="error">
                <?php
                    echo htmlspecialchars(
                        $loginError,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>
            </div>

        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form action="index.php" method="POST">

            <label for="admin_id">User ID</label>

            <input
                type="text"
                id="admin_id"
                name="admin_id"
                placeholder="Enter your User ID"
                value="<?php echo htmlspecialchars($_POST['admin_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="username"
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter Password"
                autocomplete="current-password"
            >

            <button type="submit">Log In</button>

        </form>

    </div>

</body>
</html>
