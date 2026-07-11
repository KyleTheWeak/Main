#!/usr/bin/env php
<?php

class TaskManager
{
    private string $storageFile;
    private array $tasks;

    public function __construct(string $storageFile)
    {
        $this->storageFile = $storageFile;
        $this->tasks = [];
        $this->loadTasks();
    }

    public function loadTasks(): void
    {
        if (!file_exists($this->storageFile)) {
            $this->tasks = [];
            return;
        }

        $raw = file_get_contents($this->storageFile);
        if ($raw === false || trim($raw) === '') {
            $this->tasks = [];
            return;
        }

        $decoded = json_decode($raw, true);
        $this->tasks = is_array($decoded) ? $decoded : [];
    }

    public function saveTasks(): void
    {
        $directory = dirname($this->storageFile);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create storage directory.');
        }

        file_put_contents($this->storageFile, json_encode($this->tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function addTask(string $title, ?string $description = null, string $priority = 'medium', string $category = 'General', ?string $dueDate = null, array $tags = []): array
    {
        $nextId = 1;
        foreach ($this->tasks as $task) {
            if (isset($task['id']) && (int) $task['id'] >= $nextId) {
                $nextId = (int) $task['id'] + 1;
            }
        }

        $task = [
            'id' => $nextId,
            'title' => trim($title),
            'description' => trim((string) $description),
            'priority' => $this->normalizePriority($priority),
            'category' => trim($category) !== '' ? trim($category) : 'General',
            'due_date' => $dueDate !== null && trim($dueDate) !== '' ? trim($dueDate) : null,
            'tags' => array_values(array_unique(array_map('trim', $tags))),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->tasks[] = $task;
        $this->saveTasks();
        return $task;
    }

    public function completeTask(int $id): bool
    {
        foreach ($this->tasks as &$task) {
            if ((int) $task['id'] === $id) {
                $task['status'] = 'completed';
                $task['updated_at'] = date('Y-m-d H:i:s');
                $this->saveTasks();
                return true;
            }
        }

        return false;
    }

    public function deleteTask(int $id): bool
    {
        $initialCount = count($this->tasks);
        $this->tasks = array_values(array_filter($this->tasks, static function ($task) use ($id): bool {
            return (int) $task['id'] !== $id;
        }));

        if (count($this->tasks) === $initialCount) {
            return false;
        }

        $this->saveTasks();
        return true;
    }

    public function listTasks(string $status = 'all', ?string $search = null): array
    {
        $filtered = array_values(array_filter($this->tasks, function ($task) use ($status, $search): bool {
            $matchesStatus = $status === 'all' || ($task['status'] ?? 'pending') === $status;
            if (!$matchesStatus) {
                return false;
            }

            if ($search === null || trim($search) === '') {
                return true;
            }

            $needle = strtolower(trim($search));
            $haystack = strtolower(implode(' ', [
                $task['title'] ?? '',
                $task['description'] ?? '',
                $task['category'] ?? '',
                implode($task['tags'] ?? [], ' '),
            ]));

            return str_contains($haystack, $needle);
        }));

        usort($filtered, function ($first, $second): int {
            $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            $firstPriority = $priorityOrder[$first['priority']] ?? 2;
            $secondPriority = $priorityOrder[$second['priority']] ?? 2;

            if ($firstPriority !== $secondPriority) {
                return $firstPriority <=> $secondPriority;
            }

            return strcmp($first['due_date'] ?? '', $second['due_date'] ?? '');
        });

        return $filtered;
    }

    public function getStats(): array
    {
        $total = count($this->tasks);
        $completed = count(array_filter($this->tasks, static function ($task): bool {
            return ($task['status'] ?? 'pending') === 'completed';
        }));
        $pending = $total - $completed;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
        ];
    }

    public function renderTasks(array $tasks): void
    {
        echo "\n📋 Task Dashboard\n";
        echo "=========================\n";

        if ($tasks === []) {
            echo "No tasks found. Add one with: php MyTaskManager.php add \"Task title\"\n";
            return;
        }

        foreach ($tasks as $task) {
            $statusIcon = ($task['status'] ?? 'pending') === 'completed' ? '✅' : '⏳';
            $priorityIcon = $this->priorityIcon($task['priority'] ?? 'medium');
            $dueText = isset($task['due_date']) && $task['due_date'] !== '' ? $task['due_date'] : 'No due date';
            $tagsText = isset($task['tags']) && $task['tags'] !== [] ? ' #' . implode(' #', $task['tags']) : '';

            echo sprintf(
                "%s #%d [%s] %s\n",
                $statusIcon,
                $task['id'],
                strtoupper((string) ($task['priority'] ?? 'medium')),
                $task['title']
            );
            echo sprintf("   %s Category: %s | Due: %s%s\n", $priorityIcon, $task['category'] ?? 'General', $dueText, $tagsText);
            if (!empty($task['description'])) {
                echo '   ' . $task['description'] . "\n";
            }
        }
    }

    public function printStats(): void
    {
        $stats = $this->getStats();
        echo "\n📊 Statistics\n";
        echo "=========================\n";
        echo "Total tasks: {$stats['total']}\n";
        echo "Completed: {$stats['completed']}\n";
        echo "Pending: {$stats['pending']}\n";
    }

    public function renderTasksHtml(array $tasks, string $message = '', string $error = ''): void
    {
        $stats = $this->getStats();
        $statusFilter = $_GET['status'] ?? 'all';
        $searchValue = $_GET['search'] ?? '';

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>IT Student Task Manager</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:24px;} .container{max-width:1100px;margin:auto;background:#111827;padding:24px;border-radius:18px;box-shadow:0 12px 40px rgba(0,0,0,0.25);} h1{margin-top:0;color:#38bdf8;} .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0 24px;} .card{background:#1f2937;padding:16px;border-radius:12px;} .card strong{display:block;font-size:24px;} form{display:grid;gap:12px;margin-bottom:20px;background:#1f2937;padding:16px;border-radius:12px;} .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;} label{font-size:13px;color:#cbd5e1;} input,select,textarea,button{width:100%;padding:10px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#fff;} button{background:#38bdf8;border:none;cursor:pointer;font-weight:bold;} .task{background:#1f2937;padding:16px;border-radius:12px;margin-bottom:12px;border-left:4px solid #38bdf8;} .task.completed{border-left-color:#34d399;} .meta{color:#94a3b8;font-size:13px;margin-top:8px;} .actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;} .actions form{margin:0;padding:0;background:transparent;} .actions button{padding:8px 12px;font-size:13px;} .message{padding:10px 12px;border-radius:8px;margin-bottom:12px;} .message.success{background:#14532d;color:#dcfce7;} .message.error{background:#7f1d1d;color:#fee2e2;} .empty{padding:16px;background:#1f2937;border-radius:12px;}</style></head><body>';
        echo '<div class="container">';
        echo '<h1>🧠 IT Student Task Manager</h1>';
        echo '<p>Organize your assignments, labs, and study plans in one place.</p>';

        if ($message !== '') {
            echo '<div class="message success">' . htmlspecialchars($message) . '</div>';
        }
        if ($error !== '') {
            echo '<div class="message error">' . htmlspecialchars($error) . '</div>';
        }

        echo '<div class="grid">';
        echo '<div class="card"><strong>' . $stats['total'] . '</strong>Total tasks</div>';
        echo '<div class="card"><strong>' . $stats['completed'] . '</strong>Completed</div>';
        echo '<div class="card"><strong>' . $stats['pending'] . '</strong>Pending</div>';
        echo '</div>';

        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="add">';
        echo '<div class="row">';
        echo '<div><label>Title</label><input type="text" name="title" required></div>';
        echo '<div><label>Priority</label><select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>';
        echo '<div><label>Category</label><input type="text" name="category" value="General"></div>';
        echo '<div><label>Due date</label><input type="date" name="due_date"></div>';
        echo '</div>';
        echo '<div><label>Description</label><textarea name="description" rows="3"></textarea></div>';
        echo '<div><label>Tags (comma separated)</label><input type="text" name="tags" placeholder="exam,lab,homework"></div>';
        echo '<button type="submit">Add Task</button>';
        echo '</form>';

        echo '<form method="get">';
        echo '<div class="row">';
        echo '<div><label>Status</label><select name="status"><option value="all"' . ($statusFilter === 'all' ? ' selected' : '') . '>All</option><option value="pending"' . ($statusFilter === 'pending' ? ' selected' : '') . '>Pending</option><option value="completed"' . ($statusFilter === 'completed' ? ' selected' : '') . '>Completed</option></select></div>';
        echo '<div><label>Search</label><input type="text" name="search" value="' . htmlspecialchars($searchValue) . '"></div>';
        echo '<div><button type="submit">Filter Tasks</button></div>';
        echo '</div>';
        echo '</form>';

        echo '<section>';
        if ($tasks === []) {
            echo '<div class="empty">No tasks found yet. Add your first one above.</div>';
        } else {
            foreach ($tasks as $task) {
                $isCompleted = ($task['status'] ?? 'pending') === 'completed';
                $dueText = isset($task['due_date']) && $task['due_date'] !== '' ? htmlspecialchars((string) $task['due_date']) : 'No due date';
                $tagsText = isset($task['tags']) && $task['tags'] !== [] ? ' #' . implode(' #', array_map('htmlspecialchars', $task['tags'])) : '';
                echo '<div class="task' . ($isCompleted ? ' completed' : '') . '">';
                echo '<strong>#' . (int) $task['id'] . ' ' . htmlspecialchars((string) ($task['title'] ?? 'Untitled')) . '</strong>';
                echo '<div class="meta">Priority: ' . htmlspecialchars((string) ($task['priority'] ?? 'medium')) . ' · Category: ' . htmlspecialchars((string) ($task['category'] ?? 'General')) . ' · Due: ' . $dueText . ' · Status: ' . ($isCompleted ? 'Completed' : 'Pending') . '</div>';
                if (!empty($task['description'])) {
                    echo '<p>' . htmlspecialchars((string) $task['description']) . '</p>';
                }
                if ($tagsText !== '') {
                    echo '<div class="meta">Tags: ' . substr($tagsText, 1) . '</div>';
                }
                echo '<div class="actions">';
                if (!$isCompleted) {
                    echo '<form method="post"><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="' . (int) $task['id'] . '"><button type="submit">Complete</button></form>';
                }
                echo '<form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $task['id'] . '"><button type="submit">Delete</button></form>';
                echo '</div></div>';
            }
        }
        echo '</section></div></body></html>';
    }

    private function normalizePriority(string $priority): string
    {
        $normalized = strtolower(trim($priority));
        return in_array($normalized, ['low', 'medium', 'high', 'urgent'], true) ? $normalized : 'medium';
    }

    private function priorityIcon(string $priority): string
    {
        return match ($priority) {
            'urgent' => '🚨',
            'high' => '🔥',
            'medium' => '⚡',
            default => '📝',
        };
    }
}

function showHelp(): void
{
    echo "Modern IT Student Task Manager\n";
    echo "Usage:\n";
    echo "  php MyTaskManager.php add \"Task title\" [--description \"text\"] [--priority low|medium|high|urgent] [--category \"Study\"] [--due YYYY-MM-DD] [--tag tag]\n";
    echo "  php MyTaskManager.php list [--status pending|completed|all] [--search term]\n";
    echo "  php MyTaskManager.php complete <id>\n";
    echo "  php MyTaskManager.php delete <id>\n";
    echo "  php MyTaskManager.php search <term>\n";
    echo "  php MyTaskManager.php stats\n";
    echo "  php MyTaskManager.php interactive\n";
}

function parseOptions(array $argv): array
{
    $options = [];
    for ($index = 2; $index < count($argv); $index++) {
        $arg = $argv[$index];
        $next = $argv[$index + 1] ?? null;

        if ($arg === '--description' && $next !== null) {
            $options['description'] = $next;
            $index++;
        } elseif ($arg === '--priority' && $next !== null) {
            $options['priority'] = $next;
            $index++;
        } elseif ($arg === '--category' && $next !== null) {
            $options['category'] = $next;
            $index++;
        } elseif ($arg === '--due' && $next !== null) {
            $options['due'] = $next;
            $index++;
        } elseif ($arg === '--tag' && $next !== null) {
            $options['tag'][] = $next;
            $index++;
        } elseif ($arg === '--status' && $next !== null) {
            $options['status'] = $next;
            $index++;
        } elseif ($arg === '--search' && $next !== null) {
            $options['search'] = $next;
            $index++;
        }
    }

    return $options;
}

function runInteractiveMenu(TaskManager $manager): void
{
    while (true) {
        echo "\n🧠 IT Student Task Manager\n";
        echo "1. View tasks\n";
        echo "2. Add task\n";
        echo "3. Complete task\n";
        echo "4. Delete task\n";
        echo "5. Search tasks\n";
        echo "6. Show stats\n";
        echo "0. Exit\n";
        echo "Choose an option: ";

        $choice = trim((string) fgets(STDIN));
        if ($choice === '0') {
            echo "Goodbye!\n";
            break;
        }

        switch ($choice) {
            case '1':
                $manager->renderTasks($manager->listTasks('all'));
                break;
            case '2':
                echo "Title: ";
                $title = trim((string) fgets(STDIN));
                echo "Description: ";
                $description = trim((string) fgets(STDIN));
                echo "Priority (low/medium/high/urgent): ";
                $priority = trim((string) fgets(STDIN));
                echo "Category: ";
                $category = trim((string) fgets(STDIN));
                echo "Due date (YYYY-MM-DD): ";
                $dueDate = trim((string) fgets(STDIN));
                $manager->addTask($title, $description !== '' ? $description : null, $priority !== '' ? $priority : 'medium', $category !== '' ? $category : 'General', $dueDate !== '' ? $dueDate : null);
                echo "Task added.\n";
                break;
            case '3':
                echo "Task ID: ";
                $id = (int) trim((string) fgets(STDIN));
                $result = $manager->completeTask($id);
                echo $result ? "Task completed.\n" : "Task not found.\n";
                break;
            case '4':
                echo "Task ID: ";
                $id = (int) trim((string) fgets(STDIN));
                $result = $manager->deleteTask($id);
                echo $result ? "Task deleted.\n" : "Task not found.\n";
                break;
            case '5':
                echo "Search term: ";
                $term = trim((string) fgets(STDIN));
                $manager->renderTasks($manager->listTasks('all', $term));
                break;
            case '6':
                $manager->printStats();
                break;
            default:
                echo "Invalid option.\n";
        }
    }
}

$storageFile = __DIR__ . '/tasks.json';
$manager = new TaskManager($storageFile);

if (PHP_SAPI !== 'cli') {
    $message = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($action === 'add') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $priority = trim((string) ($_POST['priority'] ?? 'medium'));
            $category = trim((string) ($_POST['category'] ?? 'General'));
            $dueDate = trim((string) ($_POST['due_date'] ?? ''));
            $rawTags = trim((string) ($_POST['tags'] ?? ''));
            $tags = $rawTags !== '' ? array_map('trim', preg_split('/,/', $rawTags) ?: []) : [];

            if ($title === '') {
                $error = 'Title is required.';
            } else {
                $manager->addTask($title, $description !== '' ? $description : null, $priority !== '' ? $priority : 'medium', $category !== '' ? $category : 'General', $dueDate !== '' ? $dueDate : null, $tags);
                $message = 'Task added successfully.';
            }
        } elseif ($action === 'complete' && $id > 0) {
            $message = $manager->completeTask($id) ? 'Task completed.' : 'Task not found.';
        } elseif ($action === 'delete' && $id > 0) {
            $message = $manager->deleteTask($id) ? 'Task deleted.' : 'Task not found.';
        }
    }

    $status = trim((string) ($_GET['status'] ?? 'all'));
    $search = trim((string) ($_GET['search'] ?? ''));
    $tasks = $manager->listTasks(in_array($status, ['pending', 'completed'], true) ? $status : 'all', $search !== '' ? $search : null);
    $manager->renderTasksHtml($tasks, $message, $error);
    exit(0);
}

$argv = $argv ?? ($_SERVER['argv'] ?? []);

$command = $argv[1] ?? 'interactive';
$options = parseOptions($argv);

switch ($command) {
    case 'help':
    case '--help':
    case '-h':
        showHelp();
        exit(0);

    case 'add':
        $title = $argv[2] ?? null;
        if ($title === null || trim($title) === '') {
            fwrite(STDERR, "Please provide a task title.\n");
            exit(1);
        }

        $task = $manager->addTask(
            $title,
            $options['description'] ?? null,
            $options['priority'] ?? 'medium',
            $options['category'] ?? 'General',
            $options['due'] ?? null,
            $options['tag'] ?? []
        );
        echo "✅ Added task #{$task['id']}: {$task['title']}\n";
        break;

    case 'list':
        $status = $options['status'] ?? 'all';
        $search = $options['search'] ?? null;
        $manager->renderTasks($manager->listTasks($status, $search));
        break;

    case 'complete':
        $id = (int) ($argv[2] ?? 0);
        if ($id <= 0) {
            fwrite(STDERR, "Please provide a valid task ID.\n");
            exit(1);
        }

        echo $manager->completeTask($id) ? "✅ Task completed.\n" : "Task not found.\n";
        break;

    case 'delete':
        $id = (int) ($argv[2] ?? 0);
        if ($id <= 0) {
            fwrite(STDERR, "Please provide a valid task ID.\n");
            exit(1);
        }

        echo $manager->deleteTask($id) ? "🗑️ Task deleted.\n" : "Task not found.\n";
        break;

    case 'search':
        $term = $argv[2] ?? '';
        $manager->renderTasks($manager->listTasks('all', $term));
        break;

    case 'stats':
        $manager->printStats();
        break;

    case 'interactive':
        runInteractiveMenu($manager);
        break;

    default:
        showHelp();
        exit(1);
}
