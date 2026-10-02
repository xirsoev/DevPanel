<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Введите корректный email и пароль.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, password FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: index.php'); exit;
        }
        $error = 'Email или пароль неверны.';
    }
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Вход — DevPanel</title><link rel="stylesheet" href="assets/css/style.css?v=20261002"><link rel="stylesheet" href="assets/css/auth.css?v=20261002"><script src="assets/js/auth.js" defer></script></head><body class="auth-page"><main class="auth-card"><a class="auth-brand" href="login.php"><span class="auth-logo">DP</span> DevPanel</a><p class="auth-kicker">РАБОЧЕЕ ПРОСТРАНСТВО</p><h1>С возвращением</h1><p class="auth-subtitle">Войдите, чтобы продолжить работу.</p><?php if ($error): ?><div class="auth-error" role="alert"><?= h($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>Email<input type="email" name="email" autocomplete="username" required autofocus value="<?= h($email) ?>"></label><label>Пароль<span class="password-wrap"><input type="password" name="password" autocomplete="current-password" required><button type="button" class="password-toggle" aria-label="Показать пароль" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label><button class="auth-submit" type="submit">Войти <span>→</span></button></form><p class="auth-foot">Первый раз здесь? <a href="register.php">Создать аккаунт</a></p></main></body></html>
