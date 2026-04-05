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

function findMenuItem($menu, $id) {
    foreach ($menu as $item) {
        if (isset($item['id']) && intval($item['id']) === intval($id)) {
            return $item;
        }
    }
    return null;
}

$menu = loadJson('menu.json');
$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.0;
foreach ($cart as $id => $quantity) {
    $item = findMenuItem($menu, $id);
    if (!$item) {
        continue;
    }
    $quantity = intval($quantity);
    if ($quantity < 1) {
        continue;
    }
    $subtotal = $item['price'] * $quantity;
    $items[] = [
        'name' => $item['name'],
        'price' => $item['price'],
        'quantity' => $quantity,
        'subtotal' => $subtotal,
    ];
    $total += $subtotal;
}

if (empty($items) && !isset($_GET['success'])) {
    header('Location: cart.php');
    exit;
}

$errors = [];
$message = '';
$success = false;
$orderSummary = null;
$tableNumber = $_SESSION['table_number'] ?? '';
$customerName = '';
$phone = '';
$notes = '';
$paymentMethod = 'cash';

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $tableNumber = trim($_POST['table_number'] ?? $tableNumber);
    $notes = trim($_POST['notes'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'cash');

    if (!hash_equals($_SESSION['checkout_token'], $token)) {
        $errors[] = 'Unable to process this checkout request. Please refresh and try again.';
    }
    if ($customerName === '') {
        $errors[] = 'Customer name is required.';
    }
    if ($phone === '' || !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors[] = 'Please provide a valid phone number.';
    }
    if ($tableNumber === '') {
        $errors[] = 'Please select a table number before checking out.';
    }
    if (!in_array($paymentMethod, ['cash', 'online'], true)) {
        $errors[] = 'Please select a valid payment method.';
    }
    if (empty($items)) {
        $errors[] = 'Your cart is empty. Add items before checkout.';
    }

    if (empty($errors)) {
        $orders = loadJson('orders.json');

        $order = [
            'id' => uniqid('order_', true),
            'date' => date('Y-m-d'),
            'items' => $items,
            'total' => round($total, 2),
            'table_number' => $tableNumber,
            'customer_name' => $customerName,
            'phone' => $phone,
            'notes' => $notes,
            'payment_method' => $paymentMethod,
            'status' => 'Pending',
            'timestamp' => time(),
        ];

        $orders[] = $order;
        saveJson('orders.json', $orders);
        $_SESSION['cart'] = [];
        $_SESSION['table_number'] = $tableNumber;
        $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
        $_SESSION['order_success'] = $order;

        header('Location: checkout.php?success=1');
        exit;
    }
}

if (isset($_GET['success']) && isset($_SESSION['order_success'])) {
    $success = true;
    $orderSummary = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
}

$estimatedMinutes = rand(14, 24);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell">
        <?php if ($success && $orderSummary): ?>
            <section class="section-title">
                <div>
                    <h2>Order Confirmed</h2>
                    <p style="margin:8px 0 0; color:#6c5b4d;">Thanks <?php echo esc($orderSummary['customer_name']); ?>, your order is on the way.</p>
                </div>
            </section>
            <div class="message-bar" style="border-color:#c9dff0; background:#eef6fb; color:#264a6d;">
                <p style="margin:0;"><strong>Order ID:</strong> <?php echo esc($orderSummary['id']); ?> • <strong>Table:</strong> <?php echo esc($orderSummary['table_number']); ?> • <strong>Estimated ready:</strong> <?php echo esc($estimatedMinutes); ?> min</p>
            </div>
            <div class="menu-grid" style="margin-top:20px;">
                <?php foreach ($orderSummary['items'] as $item): ?>
                    <article class="menu-card">
                        <div class="menu-card-body">
                            <h3 class="menu-card-title"><?php echo esc($item['name']); ?></h3>
                            <p class="menu-card-description">Qty <?php echo esc($item['quantity']); ?> • <?php echo number_format($item['subtotal'], 2); ?> Birr</p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 24px; display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between;">
                <p style="margin:0; font-size:1.1rem; color:#4a3a2c;">Total paid: <strong><?php echo number_format($orderSummary['total'], 2); ?> Birr</strong></p>
                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                    <a class="button" href="index.php">Back to Menu</a>
                    <a class="button-secondary" href="orders.php">View My Orders</a>
                </div>
            </div>
        <?php else: ?>
            <section class="section-title">
                <div>
                    <h2>Checkout</h2>
                    <p style="margin:8px 0 0; color:#6c5b4d;">Complete your order with a table number and payment method.</p>
                </div>
                <div>
                    <a class="button-secondary" href="cart.php">Back to Cart</a>
                </div>
            </section>

            <?php if (!empty($errors)): ?>
                <div class="message-bar" style="border-color:#f1c0c0; background:#fdefef; color:#822b2b;">
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo esc($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <article class="form-card">
                <form method="post">
                    <label for="customer_name">Customer Name</label>
                    <input id="customer_name" type="text" name="customer_name" value="<?php echo esc($customerName); ?>" required>

                    <label for="phone">Phone Number</label>
                    <input id="phone" type="tel" name="phone" value="<?php echo esc($phone); ?>" required placeholder="e.g. +251 900 000000">

                    <label for="table_number">Table Number</label>
                    <select id="table_number" name="table_number" required>
                        <option value="">Choose a table</option>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="Table <?php echo $i; ?>" <?php echo $tableNumber === 'Table ' . $i ? 'selected' : ''; ?>>Table <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>

                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="cash" <?php echo $paymentMethod === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="online" <?php echo $paymentMethod === 'online' ? 'selected' : ''; ?>>Mock Online Payment</option>
                    </select>

                    <label for="notes">Order Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo esc($notes); ?></textarea>

                    <section style="margin-top: 20px;">
                        <h3 style="margin:0 0 10px; color:#3d2c23;">Order Summary</h3>
                        <?php foreach ($items as $item): ?>
                            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e9ded3;">
                                <span><?php echo esc($item['name']); ?> x<?php echo esc($item['quantity']); ?></span>
                                <strong><?php echo number_format($item['subtotal'], 2); ?> Birr</strong>
                            </div>
                        <?php endforeach; ?>
                        <div style="display:flex; justify-content:space-between; margin-top:14px; font-size:1.05rem; font-weight:700;">
                            <span>Total</span>
                            <span><?php echo number_format($total, 2); ?> Birr</span>
                        </div>
                    </section>

                    <div style="margin-top:24px; display:flex; gap:12px; flex-wrap:wrap;">
                        <button type="submit" class="button">Place Order</button>
                        <a class="button-secondary" href="cart.php">Cancel</a>
                    </div>

                    <input type="hidden" name="token" value="<?php echo esc($_SESSION['checkout_token']); ?>">
                </form>
            </article>
        <?php endif; ?>
    </main>
    <script src="app.js"></script>
</body>
</html>
