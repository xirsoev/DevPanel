<?php

require_once __DIR__ . '/config/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

function getCount(PDO $pdo, string $table): int
{
    $allowedTables = ['users', 'clients', 'projects', 'tasks', 'files', 'activity_logs'];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");

    return (int) $stmt->fetchColumn();
}

/* Projects */
$totalProjects = getCount($pdo, 'projects');

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM projects
    WHERE status = 'in_progress'
");
$activeProjects = (int) $stmt->fetchColumn();

/* Tasks */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM tasks
    WHERE status IN ('todo', 'in_progress')
");
$activeTasks = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM tasks
");
$totalTasks = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM tasks
    WHERE status = 'completed'
");
$completedTasks = (int) $stmt->fetchColumn();

/* Clients */
$totalClients = getCount($pdo, 'clients');

/* Completion rate */
$completionRate = $totalTasks > 0
    ? round(($completedTasks / $totalTasks) * 100)
    : 0;


/*
|--------------------------------------------------------------------------
| Weekly task activity
|--------------------------------------------------------------------------
*/

$weekActivity = [
    'Mon' => 0,
    'Tue' => 0,
    'Wed' => 0,
    'Thu' => 0,
    'Fri' => 0,
    'Sat' => 0,
    'Sun' => 0,
];

$stmt = $pdo->query("
    SELECT
        DAYOFWEEK(created_at) AS day_number,
        COUNT(*) AS total
    FROM tasks
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DAYOFWEEK(created_at)
");

while ($row = $stmt->fetch()) {
    $days = [
        1 => 'Sun',
        2 => 'Mon',
        3 => 'Tue',
        4 => 'Wed',
        5 => 'Thu',
        6 => 'Fri',
        7 => 'Sat',
    ];

    if (isset($days[(int) $row['day_number']])) {
    $weekActivity[$days[(int) $row['day_number']]] = (int) $row['total'];
    }
}

$maxActivity = max($weekActivity);

$dayLabels = [
    'Mon' => 'Пн',
    'Tue' => 'Вт',
    'Wed' => 'Ср',
    'Thu' => 'Чт',
    'Fri' => 'Пт',
    'Sat' => 'Сб',
    'Sun' => 'Вс',
];

if ($maxActivity === 0) {
    $maxActivity = 1;
}


/*
|--------------------------------------------------------------------------
| Active projects
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.category,
        p.status,
        p.progress,
        COUNT(t.id) AS task_count
    FROM projects p
    LEFT JOIN tasks t ON t.project_id = p.id
    WHERE p.status IN ('planning', 'in_progress')
    GROUP BY p.id, p.name, p.category, p.status, p.progress
    ORDER BY p.created_at DESC
    LIMIT 5
");

$projects = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Recent activity
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        action,
        description,
        created_at
    FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 5
");

$activities = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function projectStatus(string $status): array
{
    return match ($status) {
        'completed' => ['Завершён', 'completed'],
        'in_progress' => ['В работе', 'progress'],
        'paused' => ['На паузе', 'paused'],
        default => ['Планирование', 'planning'],
    };
}

function timeAgo(string $date): string
{
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'только что';
    }

    if ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' мин. назад';
    }

    if ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' ч. назад';
    }

    $days = floor($difference / 86400);

    return $days . ' дн. назад';
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DevPanel — Обзор</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme-light.css">
</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <div class="brand-logo">DP</div>

            <div class="brand-name">
                Dev<span>Panel</span>
            </div>
        </div>

        <div class="sidebar-section-title">
            РАБОЧЕЕ ПРОСТРАНСТВО
        </div>

        <nav class="sidebar-nav">

            <a href="index.php" class="nav-item active">
                <span class="nav-icon">⌂</span>
                <span>Обзор</span>
            </a>

            <a href="pages/projects/index.php" class="nav-item">
                <span class="nav-icon">▣</span>
                <span>Проекты</span>
            </a>

            <a href="index.php#projects" class="nav-item">
                <span class="nav-icon">✓</span>
                <span>Прогресс</span>
            </a>

        </nav>

        <div class="sidebar-section-title">
            РАЗДЕЛЫ
        </div>

        <nav class="sidebar-nav">

            <a href="index.php#activity" class="nav-item">
                <span class="nav-icon">◒</span>
                <span>Активность</span>
            </a>

        </nav>

        <div class="system-status">
            <span class="status-dot"></span>

            <div>
                <strong>Система работает</strong>
            </div>
        </div>

    </aside>


    <!-- MAIN -->
    <main class="main">

        <!-- TOP BAR -->
        <header class="topbar">

            <div>
                <h1><?= (int)date('G') < 12 ? 'Доброе утро' : ((int)date('G') < 18 ? 'Добрый день' : 'Добрый вечер') ?>, <?= h($_SESSION['user_name'] ?? 'пользователь') ?></h1>

                <p>
                    Что происходит в вашем рабочем пространстве сегодня.
                </p>
            </div>

            <div class="topbar-actions">

                <a href="pages/projects/index.php?action=create"
                   class="icon-button"
                   title="Создать проект"
                   aria-label="Создать проект">
                    +
                </a>

                <div class="profile">

                    <div class="profile-avatar">
                        <?= h(mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'D', 0, 1))) ?>
                    </div>

                    <div class="profile-info">
                        <strong><?= h($_SESSION['user_name'] ?? 'Developer') ?></strong>
                        <span>Администратор</span>
                    </div>

                </div>

                <form method="post" action="logout.php" class="logout-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button class="topbar-button" type="submit" title="Выйти" aria-label="Выйти">↪</button>
                </form>
            </div>

        </header>


        <!-- STAT CARDS -->
        <section class="stats-grid">

            <div class="stat-card">

                <div class="stat-card-top">
                    <span>Всего проектов</span>
                    <div class="stat-icon">▣</div>
                </div>

                <strong class="stat-value">
                    <?= $totalProjects ?>
                </strong>

                <span class="stat-label">
                    Проектов в рабочем пространстве
                </span>

            </div>


            <div class="stat-card">

                <div class="stat-card-top">
                    <span>Активные задачи</span>
                    <div class="stat-icon">✓</div>
                </div>

                <strong class="stat-value">
                    <?= $activeTasks ?>
                </strong>

                <span class="stat-label">
                    Задач в работе
                </span>

            </div>


            <div class="stat-card">

                <div class="stat-card-top">
                    <span>Клиенты</span>
                    <div class="stat-icon">♙</div>
                </div>

                <strong class="stat-value">
                    <?= $totalClients ?>
                </strong>

                <span class="stat-label">
                    Всего клиентов
                </span>

            </div>


            <div class="stat-card">

                <div class="stat-card-top">
                    <span>Выполнено задач</span>
                    <div class="stat-icon">◒</div>
                </div>

                <strong class="stat-value">
                    <?= $completionRate ?>%
                </strong>

                <span class="stat-label">
                    <?= $completedTasks ?> из <?= $totalTasks ?> задач завершено
                </span>

            </div>

        </section>


        <!-- DASHBOARD GRID -->
        <section class="dashboard-grid" id="activity">


            <!-- ACTIVITY CHART -->
            <div class="panel activity-panel">

                <div class="panel-header">

                    <div>
                        <h2>Активность проектов</h2>

                        <p>
                            Задачи, созданные за последние 7 дней
                        </p>
                    </div>


                </div>


                <div class="chart">

                    <?php foreach ($weekActivity as $day => $value): ?>

                        <?php
                        $height = ($value / $maxActivity) * 100;
                        ?>

                        <div class="chart-column">

                            <div class="chart-value">
                                <?= $value ?>
                            </div>

                            <div class="chart-bar-wrapper">

                                <div
                                    class="chart-bar"
                                    style="height: <?= max(8, $height) ?>%;"
                                ></div>

                            </div>

                            <span class="chart-day">
                                <?= $dayLabels[$day] ?? $day ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>


            <!-- RECENT ACTIVITY -->
            <div class="panel recent-panel">

                <div class="panel-header">

                    <div>
                        <h2>Последняя активность</h2>

                        <p>
                            Последние события в рабочем пространстве
                        </p>
                    </div>


                </div>


                <div class="activity-list">

                    <?php if (empty($activities)): ?>

                        <div class="empty-state">
                            Пока нет активности.
                        </div>

                    <?php else: ?>

                        <?php foreach ($activities as $activity): ?>

                            <div class="activity-item">

                                <div class="activity-icon">
                                    ✓
                                </div>

                                <div class="activity-content">

                                    <strong>
                                        <?= htmlspecialchars($activity['action']) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($activity['description'] ?? '') ?>
                                    </span>

                                    <small>
                                        <?= timeAgo($activity['created_at']) ?>
                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- PROJECTS -->
        <section class="panel projects-panel" id="projects">

            <div class="panel-header">

                <div>
                        <h2>Активные проекты</h2>

                    <p>
                        Обзор текущих проектов
                    </p>
                </div>

                <a href="pages/projects/index.php">
                    Все проекты →
                </a>

            </div>


            <div class="projects-list">

                <?php if (empty($projects)): ?>

                    <div class="empty-projects">
                        <h3>Пока нет проектов</h3>

                        <p>
                            Создайте первый проект, чтобы начать работу.
                        </p>

                        <a href="pages/projects/index.php?action=create"
                           class="primary-button">
                            Создать проект
                        </a>
                    </div>

                <?php else: ?>

                    <?php foreach ($projects as $project): ?>

                        <?php
                        [$statusName, $statusClass] = projectStatus($project['status']);
                        $progress = min(100, max(0, (int) $project['progress']));
                        ?>

                        <a
                            href="pages/projects/project.php?id=<?= (int) $project['id'] ?>"
                            class="project-row"
                        >

                            <div class="project-info">

                                <strong>
                                    <?= htmlspecialchars($project['name']) ?>
                                </strong>

                                <span>
                                    <?= htmlspecialchars($project['category'] ?? 'Project') ?>
                                </span>

                            </div>


                            <div class="project-progress">

                                <div class="progress-top">

                                    <span>Прогресс</span>

                                    <strong>
                                        <?= $progress ?>%
                                    </strong>

                                </div>

                                <div class="progress-track">

                                    <div
                                        class="progress-fill"
                                        style="width: <?= $progress ?>%;"
                                    ></div>

                                </div>

                            </div>


                            <div class="project-tasks">

                                <?= (int) $project['task_count'] ?> задач

                            </div>


                            <div class="project-status <?= $statusClass ?>">

                                <?= $statusName ?>

                            </div>

                        </a>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>


<script src="assets/js/app.js"></script>

</body>
</html>
