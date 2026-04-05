<?php
session_start();
if (empty($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function loadJson($path) {
    $content = @file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
    header('Location: index.php?message=' . urlencode('You have been logged out.'));
    exit;
}

if (!empty($_SESSION['admin_user'])) {
    header('Location: admin_orders.php');
    exit;
}

$users = loadJson('users.json');
$error = '';
$message = $logoutMessage ?? ($_GET['message'] ?? '');
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $found = false;
        foreach ($users as $user) {
            if (isset($user['username']) && strtolower($user['username']) === strtolower($username) && password_verify($password, $user['password'])) {
                $found = true;
                $_SESSION['admin_user'] = $user['username'];
                $_SESSION['admin_display_name'] = $user['display_name'] ?? $user['username'];
                session_regenerate_id(true);
                header('Location: admin_orders.php');
                exit;
            }
        }
        if (!$found) {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell" style="max-width: 540px; padding-top: 40px;">
        <section class="section-title">
            <div>
                <h2>Admin Login</h2>
                <p style="margin:8px 0 0; color:#6c5b4d;">Secure access to the café dashboard and item management.</p>
            </div>
        </section>

        <?php if ($message): ?>
            <div class="message-bar"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message-bar" style="border-color:#f1c0c0; background:#fdefef; color:#822b2b;">
                <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <article class="form-card">
            <form method="post">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" value="<?php echo esc($username); ?>" required>

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>

                <button type="submit" class="button">Sign In</button>
            </form>
        </article>
    </main>
    <script src="app.js"></script>
</body>
</html>
