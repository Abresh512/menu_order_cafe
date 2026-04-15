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

function normalizePhone($phone) {
    return preg_replace('/[^0-9]/', '', (string)$phone);
}

$orders = loadJson('orders.json');
$searchName = trim($_POST['customer_name'] ?? '');
$searchPhone = trim($_POST['phone'] ?? '');
$message = '';
$matchedOrders = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($searchName === '' || $searchPhone === '') {
        $message = 'Please enter both your name and phone number to find your orders.';
    } else {
        $normalizedSearchPhone = normalizePhone($searchPhone);

        foreach ($orders as $order) {
            if (empty($order['customer_name']) || empty($order['phone'])) {
                continue;
            }

            if (
                strcasecmp(trim($order['customer_name']), $searchName) === 0
                && normalizePhone($order['phone']) === $normalizedSearchPhone
            ) {
                $matchedOrders[] = $order;
            }
        }

        if (empty($matchedOrders)) {
            $message = 'No orders were found for that name and phone number.';
        }
    }
}

function formatDate($date) {
    $timestamp = strtotime($date);
    return $timestamp ? date('F j, Y', $timestamp) : $date;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Friends Café</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell" style="max-width:800px; margin:40px auto;">
        <section class="section-title">
            <div>
                <h2>My Orders</h2>
                <p style="margin:8px 0 0; color:#6c5b4d;">Enter the same name and phone used at checkout to see your order history.</p>
            </div>
            <div>
                <a class="button-secondary" href="index.php">Back to Menu</a>
            </div>
        </section>

        <?php if ($message): ?>
            <div class="message-bar" style="margin-bottom:18px;">
                <?php echo esc($message); ?>
            </div>
        <?php endif; ?>

        <article class="form-card" style="margin-bottom:30px;">
            <form method="post">
                <label for="customer_name"><strong>Customer Name</strong></label>
                <input id="customer_name" type="text" name="customer_name" value="<?php echo esc($searchName); ?>" required>

                <label for="phone"><strong>Phone Number</strong></label>
                <input id="phone" type="tel" name="phone" value="<?php echo esc($searchPhone); ?>" required placeholder="e.g. +251 900 000000">

                <button type="submit" class="button">Search Orders</button>
            </form>
        </article>

        <?php if (!empty($matchedOrders)): ?>
            <section style="display:grid; gap:18px;">
                <?php foreach ($matchedOrders as $order): ?>
                    <article class="menu-card">
                        <div class="menu-card-body">
                            <div style="display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                                <div>
                                    <h3 class="menu-card-title">Order #<?php echo esc($order['id']); ?></h3>
                                    <p class="menu-card-description">Placed on <?php echo esc(formatDate($order['date'])); ?> • Table <?php echo esc($order['table_number'] ?? 'N/A'); ?></p>
                                </div>
                                <div style="text-align:right; min-width:130px;">
                                    <span class="status-pill <?php echo strtolower(trim($order['status'] ?? '')) === 'pending' ? 'status-pending' : 'status-available'; ?>">
                                        <?php echo esc($order['status'] ?? 'Pending'); ?>
                                    </span>
                                    <div style="margin-top:6px; font-weight:700; color:#7f5f3c;"><?php echo number_format($order['total'], 2); ?> Birr</div>
                                </div>
                            </div>

                            <div style="margin-top:14px; color:#5a4636;">
                                <p style="margin:0 0 8px;"><strong>Customer:</strong> <?php echo esc($order['customer_name']); ?> • <strong>Phone:</strong> <?php echo esc($order['phone']); ?></p>
                                <?php if (!empty($order['notes'])): ?>
                                    <p style="margin:0 0 8px;"><strong>Notes:</strong> <?php echo esc($order['notes']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="menu-card-actions" style="flex-direction:column; align-items:flex-start; gap:12px; margin-top:12px;">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div style="width:100%; display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #e9ded3;">
                                        <div>
                                            <strong><?php echo esc($item['name']); ?></strong>
                                            <div style="color:#6c5b4d; font-size:.95rem;"><strong>Quantity:</strong> <?php echo esc($item['quantity']); ?> • <?php echo number_format($item['price'], 2); ?> Birr</div>
                                        </div>
                                        <div style="font-weight:700; color:#3c2f25;"><?php echo number_format($item['subtotal'], 2); ?> Birr</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    <script src="app.js"></script>
</body>
</html>
