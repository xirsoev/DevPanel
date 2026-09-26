<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
$name = '';
$email = '';
$stmt = $pdo->query('SELECT COUNT(*) FROM users');
$registrationOpen = (int)$stmt->fetchColumn() === 0;

if (!$registrationOpen) {
    // Registration is intentionally limited to the first administrator account.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registrationOpen) {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Имя должно содержать от 2 до 100 символов.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Введите корректный email.';
    } elseif (strlen($password) < 12 || strlen($password) > 72) {
        $error = 'Пароль должен содержать от 12 до 72 символов.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'Пароли не совпадают.';
    } else {
        try {
            $lock = (int)$pdo->query("SELECT GET_LOCK('devpanel_first_account', 5)")->fetchColumn();
            if ($lock !== 1) {
                throw new RuntimeException('Не удалось открыть регистрацию. Попробуйте ещё раз.');
            }
            $stillOpen = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
            if (!$stillOpen) {
                $registrationOpen = false;
            } else {
                $insert = $pdo->prepare('INSERT INTO users (name,email,password) VALUES (?,?,?)');
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                header('Location: index.php'); exit;
            }
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000' ? 'Этот email уже зарегистрирован.' : 'Не удалось создать аккаунт. Проверьте работу MySQL и попробуйте снова.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('devpanel_first_account')");
        }
    }
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Регистрация — DevPanel</title><link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/auth.css"><script src="assets/js/auth.js" defer></script></head><body class="auth-page"><main class="auth-card"><a class="auth-brand" href="register.php"><span class="auth-logo">DP</span> DevPanel</a><p class="auth-kicker">ВАШЕ РАБОЧЕЕ ПРОСТРАНСТВО</p><?php if ($registrationOpen): ?><h1>Создайте аккаунт</h1><p class="auth-subtitle">Начните управлять проектами и задачами.</p><?php if ($error): ?><div class="auth-error" role="alert"><?= h($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>Ваше имя<input type="text" name="name" autocomplete="name" minlength="2" maxlength="100" required autofocus value="<?= h($name) ?>"></label><label>Email<input type="email" name="email" autocomplete="email" maxlength="150" required value="<?= h($email) ?>"></label><label>Пароль<span class="password-wrap"><input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required><button type="button" class="password-toggle" aria-label="Показать пароль" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></span><small>Не менее 12 символов</small></label><label>Повторите пароль<span class="password-wrap"><input type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="72" required><button type="button" class="password-toggle" aria-label="Показать пароль" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label><button class="auth-submit" type="submit">Зарегистрироваться <span>→</span></button></form><?php else: ?><h1>Аккаунт уже создан</h1><p class="auth-subtitle">Регистрация первого администратора завершена. Войдите в DevPanel.</p><?php endif; ?><p class="auth-foot">Уже есть аккаунт? <a href="login.php">Войти</a></p></main></body></html>
