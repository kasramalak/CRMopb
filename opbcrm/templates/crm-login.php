<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = OPBCRM_Users::verify_login($username, $password);
    if ($user) {
        $_SESSION['opbcrm_user_id'] = $user->id;
        $_SESSION['opbcrm_username'] = $user->username;
        $_SESSION['opbcrm_role'] = $user->role;
        // Optionally update last_login
        global $wpdb;
        $table = $wpdb->prefix . 'opbcrm_users';
        $wpdb->update($table, ['last_login' => current_time('mysql')], ['id' => $user->id]);
        wp_redirect(site_url('/crm-dashboard'));
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Login</title>
    <link href="https://fonts.googleapis.com/css?family=Inter:400,500,700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .crm-glass-login {
            background: rgba(255,255,255,0.25);
            box-shadow: 0 8px 32px 0 rgba(31,38,135,0.18);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.18);
            padding: 40px 32px 32px 32px;
            width: 350px;
            max-width: 90vw;
        }
        .crm-glass-login h2 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 18px;
            color: #22223b;
            text-align: center;
        }
        .crm-glass-login form {
            display: flex;
            flex-direction: column;
        }
        .crm-glass-login input[type="text"],
        .crm-glass-login input[type="password"] {
            font-size: 1rem;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #dbeafe;
            margin-bottom: 18px;
            background: rgba(255,255,255,0.7);
            outline: none;
            transition: border 0.2s;
        }
        .crm-glass-login input[type="text"]:focus,
        .crm-glass-login input[type="password"]:focus {
            border: 1.5px solid #6366f1;
        }
        .crm-glass-login button {
            background: linear-gradient(90deg, #6366f1 0%, #60a5fa 100%);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 12px 0;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .crm-glass-login button:hover {
            background: linear-gradient(90deg, #60a5fa 0%, #6366f1 100%);
        }
        .crm-glass-login .crm-error {
            color: #ef4444;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="crm-glass-login">
        <h2>CRM Login</h2>
        <?php if ($error): ?>
            <div class="crm-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html> 