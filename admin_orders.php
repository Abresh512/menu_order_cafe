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

foreach ($orders as $order) {
    if (date('Y-m-d', intval($order['timestamp'])) === $today) {
        $totalOrdersToday++;
    }
    if ($order['status'] === 'Pending') $pendingCount++;
    elseif ($order['status'] === 'Preparing') $preparingCount++;
    elseif ($order['status'] === 'Ready') $readyCount++;
}

function timeAgo($timestamp) {
    $seconds = max(0, time() - intval($timestamp));
    if ($seconds < 60) return 'Just now';
    if ($seconds < 3600) return floor($seconds / 60) . ' min ago';
    if ($seconds < 86400) return floor($seconds / 3600) . ' hrs ago';
    return date('M j, g:i A', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management — Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header {
            background: var(--surface);
            border-bottom: 2px solid var(--primary);
            padding: 20px 0;
            margin-bottom: 30px;
        }
        
        .admin-header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .admin-title h1 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.8rem;
        }
        
        .admin-title p {
            margin: 4px 0 0;
            color: var(--text-secondary);
        }
        
        .admin-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .filters-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text-primary);
        }
        
        .orders-container {
            display: grid;
            gap: 16px;
        }
        
        .order-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            transition: var(--transition);
        }
        
        .order-card:hover {
            box-shadow: var(--shadow-medium);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .order-info h3 {
            margin: 0 0 8px;
            color: var(--text-primary);
            font-size: 1.2rem;
        }
        
        .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .status-pending { background: rgba(245, 158, 11, 0.1); color: var(--secondary-accent); }
        .status-preparing { background: rgba(220, 38, 38, 0.1); color: var(--primary); }
        .status-ready { background: rgba(22, 163, 74, 0.1); color: var(--success); }
        .status-completed { background: rgba(107, 114, 128, 0.1); color: var(--text-secondary); }
        
        .order-items {
            margin-bottom: 16px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .order-item:last-child {
            border-bottom: none;
            font-weight: 700;
            padding-top: 12px;
        }
        
        .order-customer {
            margin-bottom: 16px;
            padding: 12px;
            background: var(--bg);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .order-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-update {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .status-update select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text-primary);
            min-width: 120px;
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
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .clear-orders-section h3 {
            margin: 0 0 8px;
            color: var(--text-primary);
            font-size: 1.2rem;
        }
        
        .message-bar {
            position: relative;
            padding: 12px 16px 12px 40px;
            border-radius: 8px;
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
            }
            
            .order-header {
                flex-direction: column;
                gap: 12px;
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
                <h1>Order Management</h1>
                <p>Manage orders and update status</p>
            </div>
            <div class="admin-actions">
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

        <!-- Quick Stats -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-number"><?php echo esc($totalOrdersToday); ?></div>
                <div class="stat-label">Orders Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc($pendingCount); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc($preparingCount); ?></div>
                <div class="stat-label">Preparing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc($readyCount); ?></div>
                <div class="stat-label">Ready</div>
            </div>
        </div>

        <!-- Delete All Orders Section -->
        <div class="clear-orders-section" style="border-left: 4px solid var(--error);">
            <h3 style="color: var(--error);">⚠️ Delete All Orders</h3>
            <p style="color: var(--text-secondary); margin-bottom: 16px;"><strong>Warning:</strong> This will permanently delete ALL orders from the system. This action cannot be undone.</p>
            <form method="post" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="action" value="clear_orders">
                <input type="hidden" name="clear_period" value="all">
                <button type="submit" class="button" style="background: var(--error);" onclick="return confirm('⚠️ Are you sure you want to DELETE ALL orders?\\n\\nThis action cannot be undone!')">Delete All Orders</button>
            </form>
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
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Order #<?php echo esc($order['id']); ?></h3>
                                <div class="order-meta">
                                    <span>📅 <?php echo esc($order['date'] ?? date('Y-m-d', intval($order['timestamp']))); ?></span>
                                    <span>🪑 Table <?php echo esc($order['table_number']); ?></span>
                                    <span>⏰ <?php echo esc(timeAgo($order['timestamp'])); ?></span>
                                    <span>💳 <?php echo esc($order['payment_method']); ?></span>
                                </div>
                            </div>
                            <div class="order-status status-<?php echo strtolower(esc($order['status'])); ?>">
                                <strong><?php echo esc($order['status']); ?></strong>
                            </div>
                        </div>

                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item">
                                    <span><?php echo esc($item['name']); ?> × <strong><?php echo esc($item['quantity']); ?></strong></span>
                                    <span><?php echo number_format($item['subtotal'], 2); ?> Birr</span>
                                </div>
                            <?php endforeach; ?>
                            <div class="order-item">
                                <span><strong>Total</strong></span>
                                <span><?php echo number_format($order['total'], 2); ?> Birr</span>
                            </div>
                        </div>

                        <div class="order-customer">
                            <strong>Customer:</strong> <?php echo esc($order['customer_name']); ?> • 
                            <strong>Phone:</strong> <?php echo esc($order['phone']); ?>
                            <?php if (!empty($order['notes'])): ?>
                                <br><strong>Notes:</strong> <?php echo esc($order['notes']); ?>
                            <?php endif; ?>
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
                            
                            <form method="post" onsubmit="return confirm('Delete this order?')">
                                <input type="hidden" name="order_id" value="<?php echo esc($order['id']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="button-secondary">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
</body>
</html>
