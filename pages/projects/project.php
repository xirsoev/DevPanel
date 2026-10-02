<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';

$projectId = (int)($_GET['id'] ?? 0);

if ($projectId <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Получаем проект
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.name AS client_name,
        c.email AS client_email,
        c.phone AS client_phone,
        c.company AS client_company
    FROM projects p
    LEFT JOIN clients c ON c.id = p.client_id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->execute([$projectId]);

$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Получаем задачи проекта
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM tasks
    WHERE project_id = ?
    ORDER BY
        CASE status
            WHEN 'in_progress' THEN 1
            WHEN 'todo' THEN 2
            WHEN 'completed' THEN 3
            ELSE 4
        END,
        created_at DESC
");

$stmt->execute([$projectId]);

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Статистика задач
|--------------------------------------------------------------------------
*/

$totalTasks = count($tasks);
$completedTasks = 0;
$activeTasks = 0;
$todoTasks = 0;

foreach ($tasks as $task) {

    if ($task['status'] === 'completed') {
        $completedTasks++;
    }

    if ($task['status'] === 'in_progress') {
        $activeTasks++;
    }

    if ($task['status'] === 'todo') {
        $todoTasks++;
    }
}


function projectStatusLabel(string $status): string
{
    return match ($status) {
        'planning' => 'Планирование',
        'in_progress' => 'В работе',
        'completed' => 'Завершён',
        'paused' => 'На паузе',
        default => ucfirst($status)
    };
}


function taskStatusLabel(string $status): string
{
    return match ($status) {
        'todo' => 'К выполнению',
        'in_progress' => 'В работе',
        'completed' => 'Завершена',
        default => ucfirst($status)
    };
}


function priorityLabel(string $priority): string
{
    return match ($priority) {
        'low' => 'Низкий',
        'medium' => 'Средний',
        'high' => 'Высокий',
        default => ucfirst($priority)
    };
}

?>

<!DOCTYPE html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($project['name']) ?> — DevPanel
    </title>

    <link rel="stylesheet" href="../../assets/css/style.css?v=20261002">
    <link rel="stylesheet" href="../../assets/css/theme-light.css?v=20261002">

</head>


<body>

<div class="project-details-page">


    <!-- Header -->

    <div class="page-header">

        <div>

            <a
                href="index.php"
                class="back-link"
            >
                ← К проектам
            </a>

            <span class="page-kicker">
                ПРОЕКТ
            </span>

            <h1>
                <?= htmlspecialchars($project['name']) ?>
            </h1>

            <p>
                <?= htmlspecialchars(
                    $project['description']
                    ?: 'Описание проекта не добавлено.'
                ) ?>
            </p>

        </div>


        <div class="project-header-actions">

            <span
                class="status status-<?= htmlspecialchars($project['status']) ?>"
            >
                <?= projectStatusLabel($project['status']) ?>
            </span>

        </div>

    </div>


    <!-- Main project information -->

    <div class="project-details-grid">


        <!-- Overview -->

        <section class="details-card">

            <div class="details-card-header">

                <div>
                        <span class="page-kicker">
                        ОБЗОР
                    </span>

                    <h2>Обзор проекта</h2>
                </div>

            </div>


            <div class="details-list">

                <div class="detail-row">

                    <span>Категория</span>

                    <strong>
                        <?= htmlspecialchars(
                            $project['category']
                            ?: 'Без категории'
                        ) ?>
                    </strong>

                </div>


                <div class="detail-row">

                    <span>Статус</span>

                    <strong>
                        <?= projectStatusLabel($project['status']) ?>
                    </strong>

                </div>


                <div class="detail-row">

                    <span>Срок</span>

                    <strong>

                        <?php if ($project['deadline']): ?>

                            <?= htmlspecialchars(
                                date(
                                    'd M Y',
                                    strtotime($project['deadline'])
                                )
                            ) ?>

                        <?php else: ?>

                            Не задан

                        <?php endif; ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>Создан</span>

                    <strong>
                        <?= htmlspecialchars(
                            date(
                                'd M Y',
                                strtotime($project['created_at'])
                            )
                        ) ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- Progress -->

        <section class="details-card">

            <div class="details-card-header">

                <div>
                    <span class="page-kicker">
                        ПРОГРЕСС
                    </span>

                    <h2>Прогресс проекта</h2>
                </div>

                <strong class="big-progress">
                    <?= (int)$project['progress'] ?>%
                </strong>

            </div>


            <div class="large-progress-bar">

                <div
                    class="large-progress-value"
                    style="width: <?= max(
                        0,
                        min(
                            100,
                            (int)$project['progress']
                        )
                    ) ?>%;"
                ></div>

            </div>


            <div class="progress-info">

                <span>
                    <?= $completedTasks ?> из
                    <?= $totalTasks ?> задач завершено
                </span>

                <span>
                    <?= $activeTasks ?> в работе
                </span>

            </div>

        </section>


        <!-- Client -->

        <section class="details-card">

            <div class="details-card-header">

                <div>
                    <span class="page-kicker">
                        КЛИЕНТ
                    </span>

                    <h2>Информация о клиенте</h2>
                </div>

            </div>


            <?php if ($project['client_name']): ?>

                <div class="client-profile">

                    <div class="client-avatar">
                        <?= strtoupper(
                            mb_substr(
                                $project['client_name'],
                                0,
                                1
                            )
                        ) ?>
                    </div>


                    <div>

                        <strong>
                            <?= htmlspecialchars(
                                $project['client_name']
                            ) ?>
                        </strong>

                        <?php if ($project['client_company']): ?>

                            <span>
                                <?= htmlspecialchars(
                                    $project['client_company']
                                ) ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="details-list client-details">

                    <?php if ($project['client_email']): ?>

                        <div class="detail-row">

                            <span>Почта</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $project['client_email']
                                ) ?>
                            </strong>

                        </div>

                    <?php endif; ?>


                    <?php if ($project['client_phone']): ?>

                        <div class="detail-row">

                            <span>Телефон</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $project['client_phone']
                                ) ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="empty-state-small">

                    <div class="empty-icon">
                        ◇
                    </div>

                    <h3>Клиент не назначен</h3>

                    <p>
                        Для этого проекта клиент ещё не назначен.
                    </p>

                </div>

            <?php endif; ?>

        </section>


        <!-- Task summary -->

        <section class="details-card">

            <div class="details-card-header">

                <div>
                    <span class="page-kicker">
                        ЗАДАЧИ
                    </span>

                    <h2>Сводка по задачам</h2>
                </div>

            </div>


            <div class="task-summary">

                <div class="task-summary-item">

                    <strong>
                        <?= $totalTasks ?>
                    </strong>

                    <span>
                        Всего
                    </span>

                </div>


                <div class="task-summary-item">

                    <strong>
                        <?= $todoTasks ?>
                    </strong>

                    <span>
                        К выполнению
                    </span>

                </div>


                <div class="task-summary-item">

                    <strong>
                        <?= $activeTasks ?>
                    </strong>

                    <span>
                        В работе
                    </span>

                </div>


                <div class="task-summary-item">

                    <strong>
                        <?= $completedTasks ?>
                    </strong>

                    <span>
                        Завершено
                    </span>

                </div>

            </div>

        </section>

    </div>


    <!-- Tasks -->

    <section class="project-tasks-section">

        <div class="section-heading">

            <div>

                <span class="page-kicker">
                    РАБОТА
                </span>

                <h2>Задачи проекта</h2>

                <p>
                    Задачи, связанные с проектом
                </p>

            </div>


        </div>


        <?php if (empty($tasks)): ?>

            <div class="empty-projects">

                <div class="empty-icon">
                    ✓
                </div>

                <h3>Пока нет задач</h3>

                <p>
                    К этому проекту ещё не привязаны задачи.
                </p>

            </div>

        <?php else: ?>

            <div class="tasks-list">

                <?php foreach ($tasks as $task): ?>

                    <div class="task-item">


                        <div class="task-check">

                            <?php if ($task['status'] === 'completed'): ?>

                                ✓

                            <?php else: ?>

                                ○

                            <?php endif; ?>

                        </div>


                        <div class="task-main">

                            <h3>
                                <?= htmlspecialchars(
                                    $task['title']
                                ) ?>
                            </h3>

                            <?php if (!empty($task['description'])): ?>

                                <p>
                                    <?= htmlspecialchars(
                                        $task['description']
                                    ) ?>
                                </p>

                            <?php endif; ?>

                        </div>


                        <span
                            class="task-priority priority-<?= htmlspecialchars(
                                $task['priority']
                            ) ?>"
                        >
                            <?= priorityLabel($task['priority']) ?>
                        </span>


                        <span
                            class="task-status task-status-<?= htmlspecialchars(
                                $task['status']
                            ) ?>"
                        >
                            <?= taskStatusLabel($task['status']) ?>
                        </span>


                        <?php if (!empty($task['due_date'])): ?>

                            <span class="task-date">

                                <?= htmlspecialchars(
                                    date(
                                        'd.m.Y',
                                        strtotime($task['due_date'])
                                    )
                                ) ?>

                            </span>

                        <?php endif; ?>


                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


</div>

</body>

</html>
