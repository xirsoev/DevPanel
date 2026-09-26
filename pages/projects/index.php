<?php
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$formError = '';

/*
|--------------------------------------------------------------------------
| Создание проекта
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {

    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'planning';
    $progress = (int)($_POST['progress'] ?? 0);
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    $allowedStatuses = ['planning', 'in_progress', 'completed', 'paused'];

    if (
        $name !== '' &&
        in_array($status, $allowedStatuses, true) &&
        $progress >= 0 &&
        $progress <= 100
    ) {
        $stmt = $pdo->prepare("
            INSERT INTO projects
            (client_id, name, description, category, status, progress, deadline)
            VALUES (NULL, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $description !== '' ? $description : null,
            $category !== '' ? $category : null,
            $status,
            $progress,
            $deadline
        ]);

        header('Location: index.php?created=1');
        exit;
    } else {
        $formError = 'Укажите название проекта и корректный прогресс от 0 до 100.';
    }
}

/*
|--------------------------------------------------------------------------
| Удаление проекта
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {

    verify_csrf();

    $projectId = (int)($_POST['project_id'] ?? 0);

    if ($projectId > 0) {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
    }

    header('Location: index.php?deleted=1');
    exit;
}

/*
|--------------------------------------------------------------------------
| Получаем проекты
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.description,
        p.category,
        p.status,
        p.progress,
        p.deadline,
        p.created_at,
        c.name AS client_name
    FROM projects p
    LEFT JOIN clients c ON c.id = p.client_id
    ORDER BY p.created_at DESC
");

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProjects = count($projects);

$inProgress = 0;
$completed = 0;
$planning = 0;
$paused = 0;

foreach ($projects as $project) {
    switch ($project['status']) {
        case 'in_progress':
            $inProgress++;
            break;

        case 'completed':
            $completed++;
            break;

        case 'planning':
            $planning++;
            break;

        case 'paused':
            $paused++;
            break;
    }
}

function statusLabel(string $status): string
{
    return match ($status) {
        'planning' => 'Планирование',
        'in_progress' => 'В работе',
        'completed' => 'Завершён',
        'paused' => 'На паузе',
        default => ucfirst($status)
    };
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проекты — DevPanel</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/theme-light.css">
</head>
<body>
<main class="main projects-main">
<div class="projects-page">

    <?php if ($formError): ?>
        <div class="auth-error" role="alert"><?= h($formError) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <a href="../../index.php" class="back-link">← На главную</a>
            <span class="page-kicker">РАБОЧЕЕ ПРОСТРАНСТВО</span>
            <h1>Проекты</h1>
            <p>Управляйте проектами и следите за их прогрессом.</p>
        </div>

        <button class="primary-btn" id="openCreateProject">
            <span>+</span>
            Новый проект
        </button>
    </div>


    <!-- Statistics -->

    <div class="project-stats">

        <div class="project-stat">
            <div class="stat-icon">▣</div>
            <div>
                <span>Всего проектов</span>
                <strong><?= $totalProjects ?></strong>
            </div>
        </div>

        <div class="project-stat">
            <div class="stat-icon">✓</div>
            <div>
                <span>В работе</span>
                <strong><?= $inProgress ?></strong>
            </div>
        </div>

        <div class="project-stat">
            <div class="stat-icon">●</div>
            <div>
                <span>Завершено</span>
                <strong><?= $completed ?></strong>
            </div>
        </div>

        <div class="project-stat">
            <div class="stat-icon">◷</div>
            <div>
                <span>Планирование</span>
                <strong><?= $planning ?></strong>
            </div>
        </div>

    </div>


    <!-- Projects -->

    <div class="projects-section">

        <div class="section-heading">
            <div>
                <h2>Все проекты</h2>
                <p>Проекты вашего рабочего пространства</p>
            </div>

            <span class="project-count">
                <?= $totalProjects ?> <?= $totalProjects === 1 ? 'проект' : ($totalProjects >= 2 && $totalProjects <= 4 ? 'проекта' : 'проектов') ?>
            </span>
        </div>


        <?php if (empty($projects)): ?>

            <div class="empty-projects">

                <div class="empty-icon">▣</div>

                <h3>Пока нет проектов</h3>

                <p>
                    Создайте первый проект, чтобы начать работу.
                </p>

                <button class="primary-btn" id="openCreateProjectEmpty">
                    Создать проект
                </button>

            </div>

        <?php else: ?>

            <div class="projects-grid">

                <?php foreach ($projects as $project): ?>

                    <article class="project-card">

                        <div class="project-card-top">

                            <div>
                                <span class="project-category">
                                    <?= htmlspecialchars($project['category'] ?: 'Без категории') ?>
                                </span>

                                <h3>
                                    <?= htmlspecialchars($project['name']) ?>
                                </h3>
                            </div>

                            <span class="status status-<?= htmlspecialchars($project['status']) ?>">
                                <?= statusLabel($project['status']) ?>
                            </span>

                        </div>


                        <p class="project-description">
                            <?= htmlspecialchars(
                                $project['description']
                                ?: 'Описание не добавлено.'
                            ) ?>
                        </p>


                        <div class="project-meta">

                            <div>
                                <span>Прогресс</span>

                                <strong>
                                    <?= (int)$project['progress'] ?>%
                                </strong>
                            </div>

                            <div>
                                <span>Срок</span>

                                <strong>
                                    <?= $project['deadline']
                                        ? htmlspecialchars(
                                            date('d.m.Y', strtotime($project['deadline']))
                                        )
                                        : 'Не задан'
                                    ?>
                                </strong>
                            </div>

                        </div>


                        <div class="progress-bar">
                            <div
                                class="progress-value"
                                style="width: <?= max(0, min(100, (int)$project['progress'])) ?>%;"
                            ></div>
                        </div>


                        <div class="project-footer">

                            <span>
                                <?= $project['client_name']
                                    ? htmlspecialchars($project['client_name'])
                                    : 'Клиент не указан'
                                ?>
                            </span>

                            <div class="project-actions">

                                <a
                                    href="project.php?id=<?= (int)$project['id'] ?>"
                                    class="secondary-btn"
                                >
                                    Открыть
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Удалить этот проект?');"
                                >
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input
                                        type="hidden"
                                        name="project_id"
                                        value="<?= (int)$project['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_project"
                                        class="danger-btn"
                                    >
                                        Удалить
                                    </button>
                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- Create project modal -->

<div class="modal-overlay" id="createProjectModal">

    <div class="modal">

        <div class="modal-header">

            <div>
                <span class="page-kicker">ПРОЕКТ</span>
                <h2>Новый проект</h2>
            </div>

            <button
                class="modal-close"
                id="closeCreateProject"
                type="button"
            >
                ×
            </button>

        </div>


        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="form-group">

                <label>Название проекта</label>

                <input
                    type="text"
                    name="name"
                    placeholder="Например, личный кабинет"
                    required
                    maxlength="150"
                >

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>Категория</label>

                    <input
                        type="text"
                        name="category"
                        placeholder="Веб-приложение"
                        maxlength="100"
                    >

                </div>


                <div class="form-group">

                <label>Статус</label>

                    <select name="status">

                        <option value="planning">
                            Планирование
                        </option>

                        <option value="in_progress">
                            В работе
                        </option>

                        <option value="paused">
                            На паузе
                        </option>

                        <option value="completed">
                            Завершён
                        </option>

                    </select>

                </div>

            </div>


            <div class="form-group">

                <label>Описание</label>

                <textarea
                    name="description"
                    rows="4"
                    placeholder="Коротко опишите проект..."
                ></textarea>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>Прогресс</label>

                    <input
                        type="number"
                        name="progress"
                        value="0"
                        min="0"
                        max="100"
                    >

                </div>


                <div class="form-group">

                    <label>Срок</label>

                    <input
                        type="date"
                        name="deadline"
                    >

                </div>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="secondary-btn"
                    id="cancelCreateProject"
                >
                    Отмена
                </button>

                <button
                    type="submit"
                    name="create_project"
                    class="primary-btn"
                >
                    Создать проект
                </button>

            </div>

        </form>

    </div>

</div>


<script>

(function () {

    const modal = document.getElementById('createProjectModal');

    const openButtons = [
        document.getElementById('openCreateProject'),
        document.getElementById('openCreateProjectEmpty')
    ].filter(Boolean);

    const closeButtons = [
        document.getElementById('closeCreateProject'),
        document.getElementById('cancelCreateProject')
    ].filter(Boolean);


    function openModal() {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }


    function closeModal() {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }


    openButtons.forEach(button => {
        button.addEventListener('click', openModal);
    });


    closeButtons.forEach(button => {
        button.addEventListener('click', closeModal);
    });


    modal.addEventListener('click', function (event) {

        if (event.target === modal) {
            closeModal();
        }

    });


    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {
            closeModal();
        }

    });

})();

</script>

</main>
</body>
</html>
