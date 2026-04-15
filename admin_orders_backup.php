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

function saveJson($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

if (empty($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit;
}

$orders = loadJson('orders.json');
$menu = loadJson('menu.json');
$message = '';
$filterDate = $_GET['filter_date'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $orderId = trim($_POST['order_id']);
    $action = trim($_POST['action']);

    if ($action === 'delete') {
        $beforeCount = count($orders);
        $orders = array_values(array_filter($orders, function ($order) use ($orderId) {
            return (string)$order['id'] !== (string)$orderId;
        }));

        if (count($orders) < $beforeCount) {
            saveJson('orders.json', $orders);
            $message = 'Order deleted.';
        } else {
            $message = 'Order not found.';
        }
    } elseif ($action === 'update' && isset($_POST['status'])) {
        $newStatus = trim($_POST['status']);
        $allowed = ['Pending', 'Preparing', 'Ready', 'Completed'];

        foreach ($orders as &$order) {
            if ((string)$order['id'] === (string)$orderId) {
                if (in_array($newStatus, $allowed, true)) {
                    $order['status'] = $newStatus;
                    $message = 'Order status updated.';
                }
                break;
            }
        }
        unset($order);
        saveJson('orders.json', $orders);
    }

    header('Location: admin_orders.php?message=' . urlencode($message));
    exit;
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$today = date('Y-m-d');
$totalOrdersToday = 0;
$itemCounts = [];
foreach ($orders as $order) {
    if (date('Y-m-d', intval($order['timestamp'])) === $today) {
        $totalOrdersToday++;
    }
    foreach ($order['items'] as $item) {
        $name = $item['name'];
        $itemCounts[$name] = ($itemCounts[$name] ?? 0) + intval($item['quantity']);
    }
}

$displayOrders = $orders;
if ($filterDate) {
    $displayOrders = array_values(array_filter($orders, function ($order) use ($filterDate) {
        return isset($order['date']) && $order['date'] === $filterDate;
    }));
}

arsort($itemCounts);
$topItems = array_slice($itemCounts, 0, 5, true);

function timeAgo($timestamp) {
    $seconds = max(0, time() - intval($timestamp));
    if ($seconds < 60) {
        return $seconds . ' sec ago';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' min ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' hrs ago';
    }
    return floor($seconds / 86400) . ' days ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Orders — Friends Café</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .analytics-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:18px; margin-top:24px; }
        .analytics-card { background:#fff; border:1px solid #e6d5c9; border-radius:18px; padding:20px; }
        .analytics-card h3 { margin:0 0 12px; font-size:1rem; color:#5c4436; }
        .analytics-card p { margin:0; color:#4c3a2e; font-size:1.8rem; font-weight:700; }
        .orders-list { display:grid; gap:18px; margin-top:28px; }
        .order-card { background:#fff; border:1px solid #e6d5c9; border-radius:20px; padding:22px; }
        .order-header { display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:center; }
        .order-meta { color:#6d5747; }
        .order-items { margin-top:18px; border-top:1px solid #f2ebe1; padding-top:18px; }
        .order-item { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f2ebe1; }
        .order-item:last-child { border-bottom:none; }
        .status-pill { display:inline-flex; padding:8px 12px; border-radius:999px; font-weight:700; font-size:.85rem; }
        .status-Pending { background:#fff4dc; color:#9a6d27; }
        .status-Preparing { background:#e9f3fc; color:#26578a; }
        .status-Ready { background:#e6f7ea; color:#2f7d3a; }
        .status-Completed { background:#ede7ff; color:#4b3ea4; }
        .order-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .order-actions button, .order-actions select { padding:10px 14px; border-radius:12px; border:1px solid #d8c0ae; background:#fff; color:#4b3a2f; cursor:pointer; }
        .order-actions button { min-width:120px; }
    </style>
</head>
<body>
    <main class="page-shell">
        <section class="section-title">
            <div>
                <h2>Order Management</h2>
                <p style="margin:8px 0 0; color:#6c5b4d;">Track tables, update statuses, and review today’s busiest items.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a class="button-secondary" href="add_item.php">Add New Item</a>
                <a class="button-secondary" href="index.php">Visit Menu</a>
                <a class="button" href="login.php?logout=1">Logout</a>
            </div>
        </section>

        <form method="get" style="margin-bottom:18px; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
            <label for="filter_date" style="font-weight:600; color:#3c2f25;">Filter by date:</label>
            <input type="date" id="filter_date" name="filter_date" value="<?php echo esc($filterDate); ?>" style="padding:10px 12px; border:1px solid #d7c9b9; border-radius:12px;">
            <button type="submit" class="button-secondary">Apply</button>
            <a class="button-secondary" href="admin_orders.php">Clear</a>
        </form>

        <?php if ($message): ?>
            <div class="message-bar"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="analytics-grid">
            <div class="analytics-card">
                <h3>Total orders today</h3>
                <p><?php echo esc($totalOrdersToday); ?></p>
            </div>
            <div class="analytics-card">
                <h3>Menu items stored</h3>
                <p><?php echo esc(count($menu)); ?></p>
            </div>
            <div class="analytics-card">
                <h3>Most ordered item</h3>
                <p><?php echo esc(key($topItems) ?: 'None'); ?></p>
            </div>
        </div>

        <?php if (count($topItems)): ?>
            <section style="margin-top:24px;">
                <h3 style="margin:0 0 12px; color:#4f3a30;">Top ordered items</h3>
                <ul style="margin:0; padding-left:18px; color:#5f4b42;">
                    <?php foreach ($topItems as $name => $count): ?>
                        <li><?php echo esc($name); ?> — <?php echo esc($count); ?> orders</li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <div class="orders-list">
            <?php if (empty($orders)): ?>
                <div class="order-card"><p style="margin:0; color:#5f4b42;">No orders yet.</p></div>
            <?php else: ?>
                <?php foreach (array_reverse($displayOrders) as $order): ?>
                    <article class="order-card">
                        <div class="order-header">
                            <div>
                                <strong>Order #<?php echo esc($order['id']); ?></strong>
                                <p class="order-meta"><?php echo esc($order['date'] ?? date('Y-m-d', intval($order['timestamp']))); ?> • Table <?php echo esc($order['table_number']); ?> • <?php echo esc(timeAgo($order['timestamp'])); ?> • <?php echo esc($order['payment_method']); ?></p>
                            </div>
                            <span class="status-pill status-<?php echo esc($order['status']); ?>"><?php echo esc($order['status']); ?></span>
                        </div>
                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item">
                                    <span><?php echo esc($item['name']); ?> x<?php echo esc($item['quantity']); ?></span>
                                    <strong><?php echo number_format($item['subtotal'], 2); ?> Birr</strong>
                                </div>
                            <?php endforeach; ?>
                            <div class="order-item" style="font-weight:700; border:none; margin-top:12px;">
                                <span>Total</span>
                                <strong><?php echo number_format($order['total'], 2); ?> Birr</strong>
                            </div>
                        </div>
                        <div style="margin-top:12px; color:#5f4b42;">Customer: <?php echo esc($order['customer_name']); ?> • Phone: <?php echo esc($order['phone']); ?></div>
                        <?php if (!empty($order['notes'])): ?>
                            <p style="margin:12px 0 0; color:#5f4b42;">Notes: <?php echo esc($order['notes']); ?></p>
                        <?php endif; ?>
                        <form class="order-actions" method="post">
                            <input type="hidden" name="order_id" value="<?php echo esc($order['id']); ?>">
                            <input type="hidden" name="action" value="update">
                            <select name="status" aria-label="Update status">
                                <?php foreach (['Pending', 'Preparing', 'Ready', 'Completed'] as $statusOption): ?>
                                    <option value="<?php echo esc($statusOption); ?>" <?php echo $order['status'] === $statusOption ? 'selected' : ''; ?>><?php echo esc($statusOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button-secondary">Update</button>
                        </form>
                        <form class="order-actions" method="post" onsubmit="return confirm('Delete this order?');">
                            <input type="hidden" name="order_id" value="<?php echo esc($order['id']); ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="button-secondary">Delete</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <script src="app.js"></script>
</body>
</html>
