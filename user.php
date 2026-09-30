<?php

session_start();

// Security Guard: Ensure the user is logged in
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['user_id'])
) {
    header('Location: login.php');
    exit;
}

// Ensure a CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'db.php';

$message = '';
$current_user_id = intval($_SESSION['user_id']);

// --- CREATE & UPDATE ACTIONS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        header('HTTP/1.1 403 Forbidden');
        die("CSRF token validation failed.");
    }

    // 1. CREATE TASK
    if (
        isset($_POST['action']) &&
        $_POST['action'] === 'create'
    ) {

        $title = isset($_POST['title'])
            ? trim($_POST['title'])
            : '';

        $description = isset($_POST['description'])
            ? trim($_POST['description'])
            : '';

        if (!empty($title)) {

            $stmt = $pdo->prepare(
                "INSERT INTO tasks (user_id, title, description)
                 VALUES (?, ?, ?)"
            );

            if ($stmt->execute([
                $current_user_id,
                $title,
                $description
            ])) {
                $message = "Task added successfully.";
            }

        } else {
            $message = "Task title cannot be empty.";
        }
    }

    // 2. TOGGLE STATUS
    if (
        isset($_POST['action']) &&
        $_POST['action'] === 'toggle_status'
    ) {

        $task_id = intval($_POST['task_id']);

        $new_status =
            ($_POST['current_status'] === 'pending')
            ? 'completed'
            : 'pending';

        $stmt = $pdo->prepare(
            "UPDATE tasks
             SET status = ?
             WHERE id = ? AND user_id = ?"
        );

        if ($stmt->execute([
            $new_status,
            $task_id,
            $current_user_id
        ])) {
            $message = "Task status updated.";
        }
    }
}

// --- DELETE ACTION ---

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'delete' &&
    isset($_GET['task_id'])
) {

    if (
        !isset($_GET['token']) ||
        !hash_equals($_SESSION['csrf_token'], $_GET['token'])
    ) {
        header('HTTP/1.1 403 Forbidden');
        die("CSRF token validation failed.");
    }

    $task_id = intval($_GET['task_id']);

    $stmt = $pdo->prepare(
        "DELETE FROM tasks
         WHERE id = ? AND user_id = ?"
    );

    if ($stmt->execute([
        $task_id,
        $current_user_id
    ])) {
        $message = "Task deleted successfully.";
    }
}

// --- READ ACTION ---

$stmt = $pdo->prepare(
    "SELECT id, title, description, status
     FROM tasks
     WHERE user_id = ?
     ORDER BY id DESC"
);

$stmt->execute([$current_user_id]);

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);


// --- STATISTICS ---

$total_tasks = count($tasks);

$completed_tasks = 0;
$pending_tasks = 0;

foreach ($tasks as $task) {

    if ($task['status'] === 'completed') {
        $completed_tasks++;
    } else {
        $pending_tasks++;
    }
}

$username = htmlspecialchars(
    $_SESSION['username'] ?? 'User',
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>RENE OS - My Tasks</title>

    <link rel="stylesheet" href="styles.css">

</head>

<body>

<div class="app">

    <!-- =========================
         SIDEBAR
    ========================== -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-title">
                RENE<span>OS</span>
            </div>

            <div class="brand-subtitle">
                TASK MANAGEMENT SYSTEM
            </div>

        </div>


        <div class="system-status">

            <span class="status-dot"></span>

            SYSTEM ONLINE

        </div>


        <nav class="sidebar-nav">

            <a href="user.php"
               class="nav-item active">

                <span class="nav-icon">◈</span>

                Dashboard

            </a>


            <a href="user.php"
               class="nav-item">

                <span class="nav-icon">▣</span>

                My Tasks

            </a>


            <?php if (
                isset($_SESSION['role']) &&
                $_SESSION['role'] === 'admin'
            ): ?>

                <a href="admin.php"
                   class="nav-item admin-link">

                    <span class="nav-icon">◆</span>

                    Admin Dashboard

                </a>

            <?php endif; ?>


            <a href="logout.php"
               class="nav-item logout-link">

                <span class="nav-icon">↪</span>

                Log Out

            </a>

        </nav>


        <!-- SIDE SYSTEM PANEL -->

        <div class="sidebar-system">

            <div class="reactor-small">
                <div class="reactor-core"></div>
            </div>

            <div class="system-label">
                RENE CORE
            </div>

            <div class="system-version">
                SYSTEM v1.0
            </div>

        </div>

    </aside>


    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="main-content">


        <!-- TOP BAR -->

        <header class="topbar">

            <div>

                <div class="eyebrow">
                    RENE OPERATING SYSTEM
                </div>

                <h1>
                    Mission Control
                </h1>

                <p class="welcome-text">
                    Welcome back, <?php echo $username; ?>.
                    Your workspace is ready.
                </p>

            </div>


            <div class="user-panel">

                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>

                <div>

                    <div class="user-name">
                        <?php echo $username; ?>
                    </div>

                    <div class="user-role">
                        <?php echo strtoupper($_SESSION['role'] ?? 'USER'); ?>
                    </div>

                </div>

            </div>

        </header>


        <!-- MESSAGE -->

        <?php if (!empty($message)): ?>

            <div class="system-message">

                <span>●</span>

                <?php
                echo htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        <?php endif; ?>


        <!-- =========================
             STATISTICS
        ========================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-icon">
                    ◈
                </div>

                <div>

                    <div class="stat-label">
                        TOTAL MISSIONS
                    </div>

                    <div class="stat-number">
                        <?php echo $total_tasks; ?>
                    </div>

                </div>

            </div>


            <div class="stat-card active-stat">

                <div class="stat-icon">
                    ◉
                </div>

                <div>

                    <div class="stat-label">
                        ACTIVE
                    </div>

                    <div class="stat-number">
                        <?php echo $pending_tasks; ?>
                    </div>

                </div>

            </div>


            <div class="stat-card completed-stat">

                <div class="stat-icon">
                    ✓
                </div>

                <div>

                    <div class="stat-label">
                        COMPLETED
                    </div>

                    <div class="stat-number">
                        <?php echo $completed_tasks; ?>
                    </div>

                </div>

            </div>


        </section>


        <!-- =========================
             CREATE TASK
        ========================== -->

        <section class="create-task-panel">

            <div class="section-heading">

                <div>

                    <div class="eyebrow">
                        TASK PROTOCOL
                    </div>

                    <h2>
                        Initialize New Mission
                    </h2>

                </div>

                <div class="section-code">
                    CREATE_001
                </div>

            </div>


            <form
                action="user.php"
                method="POST"
                class="task-form"
            >

                <input
                    type="hidden"
                    name="action"
                    value="create"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >


                <div class="form-group">

                    <label>
                        MISSION TITLE
                    </label>

                    <input
                        type="text"
                        name="title"
                        placeholder="Enter task title..."
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        DESCRIPTION
                    </label>

                    <textarea
                        name="description"
                        rows="3"
                        placeholder="Enter mission details..."
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    + INITIALIZE TASK

                </button>

            </form>

        </section>


        <!-- =========================
             TASK LIST
        ========================== -->

        <section class="task-panel">

            <div class="section-heading">

                <div>

                    <div class="eyebrow">
                        WORKSPACE
                    </div>

                    <h2>
                        Active Missions
                    </h2>

                </div>

                <div class="mission-count">
                    <?php echo $total_tasks; ?> TOTAL
                </div>

            </div>


            <div class="task-list">


                <?php if (empty($tasks)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            ◇
                        </div>

                        <h3>
                            No Missions Detected
                        </h3>

                        <p>
                            Your workspace is currently clear.
                            Initialize a new task above.
                        </p>

                    </div>


                <?php else: ?>


                    <?php foreach ($tasks as $task): ?>

                        <div
                            class="task-card
                            <?php echo $task['status'] === 'completed'
                                ? 'task-completed'
                                : ''; ?>"
                        >


                            <!-- STATUS -->

                            <div class="task-status">

                                <form
                                    action="user.php"
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo htmlspecialchars(
                                            $_SESSION['csrf_token'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="task_id"
                                        value="<?php echo $task['id']; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="current_status"
                                        value="<?php echo htmlspecialchars(
                                            $task['status'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >


                                    <?php if (
                                        $task['status'] === 'completed'
                                    ): ?>

                                        <button
                                            type="submit"
                                            class="status-button completed"
                                        >
                                            ✓ COMPLETED
                                        </button>

                                    <?php else: ?>

                                        <button
                                            type="submit"
                                            class="status-button pending"
                                        >
                                            ◷ ACTIVE
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </div>


                            <!-- TASK INFORMATION -->

                            <div class="task-information">

                                <h3>

                                    <?php
                                    echo htmlspecialchars(
                                        $task['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </h3>


                                <?php if (
                                    !empty($task['description'])
                                ): ?>

                                    <p>

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $task['description'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )
                                        );
                                        ?>

                                    </p>

                                <?php endif; ?>

                            </div>


                            <!-- DELETE -->

                            <div class="task-actions">

                                <?php

                                $deleteUrl =
                                    "user.php?action=delete" .
                                    "&task_id=" .
                                    urlencode(
                                        (string)$task['id']
                                    ) .
                                    "&token=" .
                                    urlencode(
                                        $_SESSION['csrf_token']
                                    );

                                ?>


                                <a
                                    href="<?php echo htmlspecialchars(
                                        $deleteUrl,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    class="delete-button"
                                    onclick="return confirm(
                                        'Are you sure you want to delete this task?'
                                    );"
                                >

                                    DELETE

                                </a>

                            </div>


                        </div>

                    <?php endforeach; ?>


                <?php endif; ?>


            </div>

        </section>


        <!-- FOOTER -->

        <footer class="system-footer">

            <span>
                RENE OS
            </span>

            <span>
                SECURE USER WORKSPACE
            </span>

            <span>
                SYSTEM ONLINE ●
            </span>

        </footer>


    </main>

</div>

</body>
</html>