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
$filterStatus = $_GET['filter_status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $orderId = $_POST['order_id'] ?? '';

    if ($action === 'delete' && $orderId) {
        $orderId = trim($_POST['order_id']);
        $beforeCount = count($orders);
        $orders = array_values(array_filter($orders, function ($order) use ($orderId) {
            return (string)$order['id'] !== (string)$orderId;
        }));

        if (count($orders) < $beforeCount) {
            saveJson('orders.json', $orders);
            $message = 'Order deleted successfully.';
        } else {
            $message = 'Order not found.';
        }
    } elseif ($action === 'update' && isset($_POST['status']) && $orderId) {
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
    } elseif ($action === 'clear_orders' && isset($_POST['clear_period'])) {
        $clearPeriod = trim($_POST['clear_period']);
        $beforeCount = count($orders);
        
        if ($clearPeriod === 'all') {
            $orders = []; // Clear all orders
            $message = 'All orders deleted successfully.';
        }
        
        if (count($orders) < $beforeCount) {
            saveJson('orders.json', $orders);
        }
    }

    header('Location: admin_orders.php?message=' . urlencode($message));
    exit;
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Apply filters
$displayOrders = $orders;
if ($filterDate) {
    $displayOrders = array_values(array_filter($displayOrders, function ($order) use ($filterDate) {
        return isset($order['date']) && $order['date'] === $filterDate;
    }));
}
if ($filterStatus) {
    $displayOrders = array_values(array_filter($displayOrders, function ($order) use ($filterStatus) {
        return $order['status'] === $filterStatus;
    }));
}

// Sort orders by timestamp (newest first)
usort($displayOrders, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Calculate simple stats
$today = date('Y-m-d');
$totalOrdersToday = 0;
$pendingCount = 0;
$preparingCount = 0;
$readyCount = 0;
$completedCount = 0;

foreach ($orders as $order) {
    if (date('Y-m-d', intval($order['timestamp'])) === $today) {
        $totalOrdersToday++;
    }
    if ($order['status'] === 'Pending') $pendingCount++;
    elseif ($order['status'] === 'Preparing') $preparingCount++;
    elseif ($order['status'] === 'Ready') $readyCount++;
    elseif ($order['status'] === 'Completed') $completedCount++;
}

function timeAgo($timestamp) {
    $seconds = max(0, time() - intval($timestamp));
    if ($seconds < 60) return 'Just now';
    if ($seconds < 3600) return floor($seconds / 60) . ' min ago';
    if ($seconds < 86400) return floor($seconds / 3600) . ' hrs ago';
    return date('M j, g:i A', $timestamp);
}

function findItemImage($orderItems, $menu) {
    foreach ($orderItems as $item) {
        foreach ($menu as $menuItem) {
            if (isset($menuItem['name'], $menuItem['image']) &&
                strcasecmp($menuItem['name'], $item['name']) === 0 &&
                trim($menuItem['image']) !== '') {
                return $menuItem['image'];
            }
        }
    }
    return '';
}

function isNewOrder($timestamp) {
    return (time() - intval($timestamp)) <= 3600;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management — Friends Café</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header {
            background: var(--surface);
            border-bottom: 2px solid var(--primary);
            padding: 16px 0;
            margin-bottom: 22px;
        }
        
        .admin-header-content {
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        
        .admin-title h1 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5rem;
        }
        
        .admin-title p {
            margin: 4px 0 0;
            color: var(--text-secondary);
            font-size: 0.92rem;
        }
        
        .admin-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .status-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        
        .status-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            box-shadow: var(--shadow-soft);
        }
        
        .status-card.new { border-left: 4px solid #3b82f6; }
        .status-card.cooking { border-left: 4px solid #f59e0b; }
        .status-card.ready { border-left: 4px solid #10b981; }
        .status-card.completed { border-left: 4px solid #6b7280; }
        
        .status-card strong {
            display: block;
            font-size: 1.4rem;
            margin-bottom: 4px;
            color: var(--text-primary);
        }
        
        .status-card span {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .order-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.02em;
            font-size: 0.82rem;
            min-width: 110px;
            text-transform: uppercase;
        }
        .order-status.status-pending { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
        .order-status.status-preparing { background: rgba(245, 158, 11, 0.15); color: #92400e; }
        .order-status.status-ready { background: rgba(16, 185, 129, 0.12); color: #047857; }
        .order-status.status-completed { background: rgba(107, 114, 128, 0.12); color: #334155; }
        
        .filters-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 14px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: var(--text-primary);
            min-width: 80px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg);
            color: var(--text-primary);
            min-width: 190px;
        }
        
        .orders-container {
            display: grid;
            gap: 18px;
        }
        
        .order-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 16px;
            transition: var(--transition);
        }
        
        .order-card:hover {
            box-shadow: var(--shadow-medium);
        }
        
        .order-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }
        
        .order-card-top h3 {
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: -0.02em;
        }
        
        .order-card-top .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.94rem;
            margin-top: 8px;
        }
        
        .order-card-top .order-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .order-badge {
            background: rgba(59, 130, 246, 0.1);
            color: #1D4ED8;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        
        .order-summary {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 18px;
            align-items: center;
            margin-bottom: 18px;
        }
        
        .item-thumb {
            width: 84px;
            height: 84px;
            border-radius: 18px;
            object-fit: cover;
            border: 1px solid var(--border);
            background: var(--bg);
        }
        
        .item-thumb.placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .item-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .item-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .item-meta,
        .item-note {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .order-total-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 18px;
        }
        
        .order-total-line span {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .order-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .order-controls .button {
            min-width: 110px;
        }
        
        .status-update {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-update select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg);
            color: var(--text-primary);
            min-width: 150px;
        }
        
        .status-update button {
            padding: 10px 16px;
            border-radius: 12px;
        }
        
        .order-status {
            white-space: nowrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state h3 {
            margin: 0 0 8px;
            color: var(--text-primary);
        }
        
        .clear-orders-section {
            background: #F3F4F6;
            border: 1px solid #D1D5DB;
            border-radius: 20px;
            padding: 24px 26px;
            margin-top: 32px;
            margin-bottom: 32px;
            text-align: center;
        }
        
        .clear-orders-section.blurry {
            filter: none;
            opacity: 1;
        }
        
        .clear-orders-section h3 {
            margin: 0 0 12px;
            color: #374151;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .clear-orders-section p {
            margin: 0 auto 18px;
            color: #4B5563;
            max-width: 600px;
            line-height: 1.65;
        }
        
        .clear-orders-section .button {
            background: #6B7280;
            color: #FFFFFF;
            padding: 10px 20px;
            border-radius: 14px;
            min-width: 170px;
            border: none;
        }
        
        .clear-orders-section .button:hover {
            background: #4B5563;
        }
        
        .clear-orders-section .button:active {
            transform: translateY(1px);
        }
        
        .clear-orders-section .button:focus {
            outline: 3px solid rgba(107,114,128,0.24);
        }
        
        .clear-orders-section .button + .button { margin-left: 0; }
        
        .clear-orders-section .button {
            width: auto;
        }
        
        .clear-orders-section .button {
            background: #b91c1c;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 14px;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        
        .clear-orders-section .button:hover {
            background: #991b1b;
            transform: translateY(-1px);
        }
        
        .message-bar {
            position: relative;
            padding: 12px 16px 12px 40px;
            border-radius: 12px;
            margin-bottom: 18px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text-primary);
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .message-close {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            color: inherit;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .message-close:hover {
            background: rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .admin-header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
                width: 100%;
            }
            
            .order-card-top {
                flex-direction: column;
                align-items: stretch;
            }
            
            .order-summary {
                grid-template-columns: 1fr;
            }
            
            .order-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-content">
            <div class="admin-title">
                <h1>Orders</h1>
                <p>Manage current orders and update status</p>
            </div>
            <div class="admin-actions">
                <a href="admin_items.php" class="button-secondary">Manage Menu</a>
                <a href="add_item.php" class="button-secondary">Add Item</a>
                <a href="index.php" class="button-secondary">View Menu</a>
                <a href="login.php?logout=1" class="button">Logout</a>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?php if ($message): ?>
            <div class="message-bar" id="messageBar">
                <?php echo esc($message); ?>
                <button type="button" class="message-close" onclick="hideMessage()">×</button>
            </div>
        <?php endif; ?>

        <!-- Status Dashboard -->
        <div class="status-dashboard">
            <div class="status-card new">
                <strong><?php echo esc($pendingCount); ?></strong>
                <span>New Orders</span>
            </div>
            <div class="status-card cooking">
                <strong><?php echo esc($preparingCount); ?></strong>
                <span>Cooking</span>
            </div>
            <div class="status-card ready">
                <strong><?php echo esc($readyCount); ?></strong>
                <span>Ready</span>
            </div>
            <div class="status-card completed">
                <strong><?php echo esc($completedCount); ?></strong>
                <span>Completed</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <label for="filter_date">Date:</label>
                <input type="date" id="filter_date" name="filter_date" value="<?php echo esc($filterDate); ?>">
            </div>
            <div class="filter-group">
                <label for="filter_status">Status:</label>
                <select id="filter_status" name="filter_status">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $filterStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Preparing" <?php echo $filterStatus === 'Preparing' ? 'selected' : ''; ?>>Preparing</option>
                    <option value="Ready" <?php echo $filterStatus === 'Ready' ? 'selected' : ''; ?>>Ready</option>
                    <option value="Completed" <?php echo $filterStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <button type="button" class="button-secondary" onclick="applyFilters()">Apply Filters</button>
            <a href="admin_orders.php" class="button-secondary">Clear</a>
        </div>

        <!-- Orders List -->
        <div class="orders-container">
            <?php if (empty($displayOrders)): ?>
                <div class="empty-state">
                    <h3>No orders found</h3>
                    <p><?php echo ($filterDate || $filterStatus) ? 'Try adjusting your filters' : 'No orders have been placed yet'; ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($displayOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-card-top">
                            <div>
                                <h3><?php echo esc($order['table_number'] ?: 'Order #' . $order['id']); ?></h3>
                                <div class="order-meta">
                                    <span>📅 <?php echo esc($order['date'] ?? date('Y-m-d', intval($order['timestamp']))); ?></span>
                                    <span>⏰ <?php echo esc(timeAgo($order['timestamp'])); ?></span>
                                    <span>💳 <?php echo esc(ucfirst($order['payment_method'])); ?></span>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <?php if (isNewOrder($order['timestamp'])): ?>
                                    <span class="order-badge">NEW</span>
                                <?php endif; ?>
                                <span class="order-status status-<?php echo strtolower(esc($order['status'])); ?>">
                                    <strong><?php echo esc($order['status']); ?></strong>
                                </span>
                            </div>
                        </div>

                        <?php $thumbnail = findItemImage($order['items'], $menu); ?>
                        <div class="order-summary">
                            <?php if ($thumbnail): ?>
                                <img class="item-thumb" src="<?php echo esc($thumbnail); ?>" alt="<?php echo esc($order['items'][0]['name'] ?? 'Item'); ?>">
                            <?php else: ?>
                                <div class="item-thumb placeholder">No Image</div>
                            <?php endif; ?>
                            <div class="item-details">
                                <div class="item-title"><?php echo esc($order['items'][0]['name'] ?? 'Item'); ?></div>
                                <div class="item-meta"><?php echo esc($order['items'][0]['quantity'] ?? 1); ?> × <?php echo number_format($order['items'][0]['price'] ?? 0, 2); ?> Birr</div>
                                <?php if (count($order['items']) > 1): ?>
                                    <div class="item-note">+ <?php echo count($order['items']) - 1; ?> more item(s)</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="order-total-line">
                            <span>Total</span>
                            <strong><?php echo number_format($order['total'], 2); ?> Birr</strong>
                        </div>

                        <div class="order-controls">
                            <form class="status-update" method="post">
                                <input type="hidden" name="order_id" value="<?php echo esc($order['id']); ?>">
                                <input type="hidden" name="action" value="update">
                                <select name="status">
                                    <?php foreach (['Pending', 'Preparing', 'Ready', 'Completed'] as $status): ?>
                                        <option value="<?php echo esc($status); ?>" <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                                            <?php echo esc($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button">Update</button>
                            </form>
                            
                            <button type="button" class="button-secondary" onclick="showDeleteOrderModal('<?php echo esc($order['id']); ?>')">Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="clear-orders-section blurry">
            <h3>Delete All Orders</h3>
            <p style="margin-bottom: 16px; color: #4B5563;">This will permanently delete all orders from the system.</p>
            <form method="post" style="display: flex; justify-content: center; gap: 8px;">
                <input type="hidden" name="action" value="clear_orders">
                <input type="hidden" name="clear_period" value="all">
                <button type="button" class="button" onclick="showDeleteAllModal()">Delete All Orders</button>
            </form>
        </div>
    </main>

    <script>
        function hideMessage() {
            const messageBar = document.getElementById('messageBar');
            if (messageBar) {
                messageBar.style.opacity = '0';
                messageBar.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    messageBar.style.display = 'none';
                }, 300);
            }
        }
        
        // Clear message from URL to prevent re-appearance on refresh
        function clearMessageFromURL() {
            const url = new URL(window.location);
            url.searchParams.delete('message');
            window.history.replaceState({}, '', url);
        }
        
        // Auto-hide message after 5 seconds and clear URL
        setTimeout(() => {
            hideMessage();
            clearMessageFromURL();
        }, 5000);
        
        function applyFilters() {
            const date = document.getElementById('filter_date').value;
            const status = document.getElementById('filter_status').value;
            const params = new URLSearchParams();
            
            if (date) params.append('filter_date', date);
            if (status) params.append('filter_status', status);
            
            window.location.href = 'admin_orders.php' + (params.toString() ? '?' + params.toString() : '');
        }

        // Add enter key support for filters
        document.getElementById('filter_date').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
        
        document.getElementById('filter_status').addEventListener('change', applyFilters);
    </script>

    <!-- Delete Order Modal -->
    <div id="deleteOrderModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; color: #dc3545;">Confirm Delete</h3>
            <p id="deleteOrderMessage">Are you sure you want to delete this order?</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button onclick="closeDeleteOrderModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: #f8f9fa; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="confirmDeleteOrderBtn" onclick="confirmDeleteOrder()" style="padding: 8px 16px; border: none; background: #dc3545; color: white; border-radius: 4px; cursor: pointer;">Delete</button>
            </div>
        </div>
    </div>

    <!-- Delete All Orders Modal -->
    <div id="deleteAllModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; color: #dc3545;">⚠️ Confirm Delete All Orders</h3>
            <p>Are you sure you want to DELETE ALL orders? This action cannot be undone!</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button onclick="closeDeleteAllModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: #f8f9fa; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button onclick="confirmDeleteAll()" style="padding: 8px 16px; border: none; background: #dc3545; color: white; border-radius: 4px; cursor: pointer;">Delete All</button>
            </div>
        </div>
    </div>

    <script>
        let deleteOrderId = null;

        function showDeleteOrderModal(orderId) {
            deleteOrderId = orderId;
            document.getElementById('deleteOrderMessage').textContent = `Are you sure you want to delete order #${orderId}?`;
            document.getElementById('deleteOrderModal').style.display = 'block';
        }

        function closeDeleteOrderModal() {
            document.getElementById('deleteOrderModal').style.display = 'none';
            deleteOrderId = null;
        }

        function confirmDeleteOrder() {
            if (deleteOrderId !== null) {
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const orderInput = document.createElement('input');
                orderInput.type = 'hidden';
                orderInput.name = 'order_id';
                orderInput.value = deleteOrderId;
                
                form.appendChild(actionInput);
                form.appendChild(orderInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showDeleteAllModal() {
            document.getElementById('deleteAllModal').style.display = 'block';
        }

        function closeDeleteAllModal() {
            document.getElementById('deleteAllModal').style.display = 'none';
        }

        function confirmDeleteAll() {
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'clear_orders';
            
            const periodInput = document.createElement('input');
            periodInput.type = 'hidden';
            periodInput.name = 'clear_period';
            periodInput.value = 'all';
            
            form.appendChild(actionInput);
            form.appendChild(periodInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteOrderModal = document.getElementById('deleteOrderModal');
            const deleteAllModal = document.getElementById('deleteAllModal');
            
            if (event.target === deleteOrderModal) {
                closeDeleteOrderModal();
            }
            if (event.target === deleteAllModal) {
                closeDeleteAllModal();
            }
        }

        // Close modals with Escape key
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteOrderModal();
                closeDeleteAllModal();
            }
        });
    </script>
</body>
</html>
