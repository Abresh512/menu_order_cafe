<?php
session_start();
if (empty($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

if (empty($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit;
}

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function loadJson($path) {
    $content = @file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveJson($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function findMenuIndexById($menu, $id) {
    foreach ($menu as $index => $item) {
        if (isset($item['id']) && intval($item['id']) === intval($id)) {
            return $index;
        }
    }
    return -1;
}

$message = '';
$menu = loadJson('menu.json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $itemId = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

    if ($action === 'delete' && $itemId > 0) {
        $index = findMenuIndexById($menu, $itemId);
        if ($index >= 0) {
            array_splice($menu, $index, 1);
            saveJson('menu.json', $menu);
            $message = 'Menu item deleted successfully.';
        } else {
            $message = 'Menu item not found.';
        }
    }

    if ($message) {
        $_SESSION['message'] = $message;
        header('Location: admin_items.php');
        exit;
    }
}

$menu = loadJson('menu.json');
$itemCount = count($menu);
$todayCount = 0;

foreach ($menu as $item) {
    if (isset($item['popular']) && $item['popular']) {
        $todayCount++;
    }
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu — Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header { background: var(--surface); border-bottom: 2px solid var(--primary); padding: 22px 0; margin-bottom: 28px; }
        .admin-header-content { max-width: 1200px; margin: 0 auto; padding: 0 16px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between; }
        .admin-title h1 { margin: 0; font-size: 1.8rem; color: var(--text-primary); }
        .admin-title p { margin: 6px 0 0; color: var(--text-secondary); }
        .admin-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .manage-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .manage-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px; box-shadow: var(--shadow-soft); }
        .manage-card strong { display: block; font-size: 2rem; margin-bottom: 6px; }
        .items-grid { display: grid; gap: 20px; }
        .item-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-soft); transition: var(--transition); }
        .item-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-medium); }
        .item-card-top { display: grid; grid-template-columns: 84px 1fr auto; gap: 16px; align-items: center; padding: 18px; }
        .item-image { width: 84px; height: 84px; border-radius: 16px; overflow: hidden; border: 1px solid var(--border); background: #f8fafc; }
        .item-image img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
        .item-meta h3 { margin: 0 0 6px; font-size: 1.05rem; color: var(--text-primary); }
        .item-meta p { margin: 0; color: var(--text-secondary); font-size: 0.95rem; line-height: 1.4; }
        .item-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .tag { padding: 6px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .tag-category { background: #eef2ff; color: #4338ca; }
        .tag-available { background: #dcfce7; color: #166534; }
        .tag-unavailable { background: #fee2e2; color: #991b1b; }
        .tag-popular { background: #fef3c7; color: #92400e; }
        .item-actions { display: flex; flex-wrap: wrap; gap: 12px; padding: 0 18px 18px; }
        .item-actions a, .item-actions button { min-width: 104px; }
        .button-danger { background: #dc3545; color: white; }
        .button-danger:hover { background: #b02a37; }
        .delete-confirm-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); align-items: center; justify-content: center; padding: 20px; z-index: 999; }
        .delete-confirm-backdrop.show { display: flex; }
        .delete-confirm-card { width: 100%; max-width: 420px; background: #fff; border-radius: 22px; padding: 28px; box-shadow: 0 20px 60px rgba(15,23,42,0.18); }
        .delete-confirm-card h3 { margin: 0 0 12px; font-size: 1.4rem; color: #111827; }
        .delete-confirm-card p { margin: 0; color: var(--text-secondary); line-height: 1.65; }
        .delete-confirm-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
        @media (max-width: 760px) {
            .item-card-top { grid-template-columns: 1fr; }
            .item-actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-content">
            <div class="admin-title">
                <h1>Manage Menu</h1>
                <p>Edit, delete, or update the items shown on the home page.</p>
            </div>
            <div class="admin-actions">
                <a href="add_item.php" class="button button-primary">Add Item</a>
                <a href="admin_orders.php" class="button button-secondary">Manage Orders</a>
                <a href="index.php" class="button button-secondary">View Menu</a>
                <a href="login.php?logout=1" class="button button-secondary">Logout</a>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?php if ($message): ?>
            <div id="messageBar" class="message-bar" style="margin-bottom:24px;">
                <?php echo esc($message); ?>
            </div>
        <?php endif; ?>

        <div class="manage-summary">
            <div class="manage-card">
                <strong><?php echo esc($itemCount); ?></strong>
                <p>Total menu items</p>
            </div>
            <div class="manage-card">
                <strong><?php echo esc(max(0, $todayCount)); ?></strong>
                <p>Popular items</p>
            </div>
        </div>

        <section class="items-grid">
            <?php if (empty($menu)): ?>
                <div class="section-empty">
                    <p><strong>No menu items available.</strong> Add your first item now.</p>
                </div>
            <?php else: ?>
                <?php foreach ($menu as $item): ?>
                    <article class="item-card">
                        <div class="item-card-top">
                            <div class="item-image">
                                <img src="<?php echo esc($item['image'] ?? ''); ?>" alt="<?php echo esc($item['name']); ?>" onerror="this.src='https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&w=900&q=80';">
                            </div>
                            <div class="item-meta">
                                <h3><?php echo esc($item['name']); ?></h3>
                                <p><?php echo esc($item['description'] ?? 'No description'); ?></p>
                                <div class="item-tags">
                                    <span class="tag tag-category"><?php echo esc($item['category'] ?? 'Uncategorized'); ?></span>
                                    <?php if (!empty($item['available'])): ?>
                                        <span class="tag tag-available">Available</span>
                                    <?php else: ?>
                                        <span class="tag tag-unavailable">Unavailable</span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['popular'])): ?>
                                        <span class="tag tag-popular">Popular</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <p style="margin:0 0 8px; font-weight:700; font-size:1.1rem; color:var(--text-primary);"><?php echo number_format($item['price'] ?? 0, 2); ?> Birr</p>
                                <p style="margin:0; color:var(--text-secondary);">ID: <?php echo esc($item['id']); ?></p>
                            </div>
                        </div>
                        <div class="item-actions">
                            <a href="edit_item.php?id=<?php echo esc($item['id']); ?>" class="button button-secondary">Edit</a>
                            <form method="post" class="delete-item-form" data-confirm="Delete this item?" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?php echo esc($item['id']); ?>">
                                <button type="submit" class="button btn-delete">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <div id="deleteConfirmModal" class="delete-confirm-backdrop" aria-hidden="true">
        <div class="delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
            <h3 id="deleteConfirmTitle">Delete menu item?</h3>
            <p id="deleteConfirmText">This action cannot be undone. Are you sure you want to delete this item?</p>
            <div class="delete-confirm-actions">
                <button type="button" class="button button-secondary" onclick="closeDeleteConfirm()">Cancel</button>
                <button type="button" class="button button-danger" id="confirmDeleteButton">Delete item</button>
            </div>
        </div>
    </div>

    <script>
        const adminMessageBar = document.getElementById('messageBar');
        if (adminMessageBar) {
            setTimeout(() => {
                adminMessageBar.style.transition = 'opacity 0.35s ease';
                adminMessageBar.style.opacity = '0';
                setTimeout(() => {
                    adminMessageBar.style.display = 'none';
                }, 350);
            }, 3200);
        }

        const modal = document.getElementById('deleteConfirmModal');
        const confirmDeleteButton = document.getElementById('confirmDeleteButton');
        const deleteMessageText = document.getElementById('deleteConfirmText');
        let activeDeleteForm = null;

        document.querySelectorAll('.delete-item-form').forEach(form => {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                activeDeleteForm = this;
                const message = this.dataset.confirm || 'Are you sure?';
                deleteMessageText.textContent = message;
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
            });
        });

        function closeDeleteConfirm() {
            if (!modal) return;
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            activeDeleteForm = null;
        }

        confirmDeleteButton.addEventListener('click', function() {
            if (activeDeleteForm) {
                activeDeleteForm.submit();
            }
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeDeleteConfirm();
            }
        });
    </script>
</body>
</html>
