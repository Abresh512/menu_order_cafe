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

function findMenuItem($menu, $id) {
    foreach ($menu as $item) {
        if (isset($item['id']) && intval($item['id']) === intval($id)) {
            return $item;
        }
    }
    return null;
}

$menu = loadJson('menu.json');
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$action = $_GET['action'] ?? '';
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId > 0 && in_array($action, ['add', 'plus', 'minus', 'remove'], true)) {
    $item = findMenuItem($menu, $itemId);
    if (!$item) {
        $_SESSION['flash'] = 'Menu item not found.';
        header('Location: cart.php');
        exit;
    }

    $itemKey = (string)$itemId;
    if ($action === 'remove') {
        unset($_SESSION['cart'][$itemKey]);
        $_SESSION['flash'] = 'Item removed from cart.';
        header('Location: cart.php');
        exit;
    }

    if ($action === 'add' || $action === 'plus') {
        if (empty($item['available'])) {
            $_SESSION['flash'] = 'This item is currently out of stock.';
        } else {
            $quantity = $_SESSION['cart'][$itemKey] ?? 0;
            if ($quantity < 20) {
                $_SESSION['cart'][$itemKey] = $quantity + 1;
            }
            $_SESSION['flash'] = 'Item added to cart.';
        }
        header('Location: cart.php');
        exit;
    }

    if ($action === 'minus') {
        $quantity = $_SESSION['cart'][$itemKey] ?? 0;
        if ($quantity > 1) {
            $_SESSION['cart'][$itemKey] = $quantity - 1;
            $_SESSION['flash'] = 'Quantity updated.';
        } else {
            unset($_SESSION['cart'][$itemKey]);
            $_SESSION['flash'] = 'Item removed from cart.';
        }
        header('Location: cart.php');
        exit;
    }
}

$cartItems = [];
$total = 0.0;
foreach ($_SESSION['cart'] as $id => $quantity) {
    $item = findMenuItem($menu, $id);
    if (!$item) {
        continue;
    }
    $quantity = intval($quantity);
    if ($quantity < 1) {
        continue;
    }
    $subtotal = $item['price'] * $quantity;
    $cartItems[] = [
        'item' => $item,
        'quantity' => $quantity,
        'subtotal' => $subtotal,
    ];
    $total += $subtotal;
}

$cartCount = array_sum($_SESSION['cart']);
$tableNumber = $_SESSION['table_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart — Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell">
        <section class="section-title">
            <div>
                <h2>Your Cart</h2>
                <p style="margin:8px 0 0; color:#6c5b4d;">Review your order, adjust quantities, and proceed to checkout.</p>
            </div>
            <div>
                <a class="button-secondary" href="index.php">Continue Shopping</a>
            </div>
        </section>

        <?php if ($flash): ?>
            <div class="message-bar"><?php echo esc($flash); ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="section-empty">
                <p><strong>Your cart is empty.</strong> Add items from the menu to start your order.</p>
            </div>
        <?php else: ?>
            <div class="section-title" style="margin-top: 24px;">
                <div>
                    <h2>Order Summary</h2>
                    <p style="margin:8px 0 0; color:#6c5b4d;"><?php echo count($cartItems); ?> item<?php echo count($cartItems) === 1 ? '' : 's'; ?> in your cart.</p>
                </div>
            </div>

            <div class="menu-grid">
                <?php foreach ($cartItems as $cartItem): ?>
                    <?php $item = $cartItem['item']; ?>
                    <article class="menu-card">
                        <img src="<?php echo esc($item['image']); ?>" alt="<?php echo esc($item['name']); ?>" loading="lazy" decoding="async">
                        <div class="menu-card-body">
                            <div>
                                <h3 class="menu-card-title"><?php echo esc($item['name']); ?></h3>
                                <p class="menu-card-description"><?php echo esc($item['description']); ?></p>
                            </div>
                            <div class="menu-card-meta">
                                <span>Qty: <?php echo esc($cartItem['quantity']); ?></span>
                                <span class="menu-card-price"><?php echo number_format($cartItem['subtotal'], 2); ?> Birr</span>
                            </div>
                            <div class="menu-card-actions">
                                <a class="button-secondary" href="cart.php?action=minus&id=<?php echo esc($item['id']); ?>">−</a>
                                <a class="button-secondary" href="cart.php?action=plus&id=<?php echo esc($item['id']); ?>">+</a>
                                <a class="button-secondary" href="cart.php?action=remove&id=<?php echo esc($item['id']); ?>">Remove</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 28px; display: flex; flex-wrap: wrap; gap: 18px; justify-content: space-between; align-items: center;">
                <div style="min-width: 220px;">
                    <p style="margin:0; font-weight:700; color:#3c2f25;">Table</p>
                    <p style="margin:6px 0 0; color:#5a4636;">
                        <?php echo $tableNumber ? esc($tableNumber) : 'Select your table at checkout.'; ?>
                    </p>
                </div>
                <div style="text-align:right; min-width:220px;">
                    <p style="margin:0; font-weight:700; color:#3c2f25;">Total</p>
                    <p style="margin:6px 0 0; font-size:1.4rem; font-weight:700; color:#7f5f3c;"><?php echo number_format($total, 2); ?> Birr</p>
                </div>
            </div>

            <div style="margin-top: 26px; display:flex; gap:12px; flex-wrap:wrap;">
                <a class="button" href="checkout.php">Proceed to Checkout</a>
                <a class="button-secondary" href="index.php">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
