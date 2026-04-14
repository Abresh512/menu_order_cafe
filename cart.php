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

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    $itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($action === 'summary') {
        $cartItems = [];
        $total = 0.0;
        $count = 0;
        
        foreach ($_SESSION['cart'] as $id => $quantity) {
            $item = findMenuItem($menu, $id);
            if (!$item) continue;
            
            $quantity = intval($quantity);
            if ($quantity < 1) continue;
            
            $subtotal = $item['price'] * $quantity;
            $cartItems[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
            $total += $subtotal;
            $count += $quantity;
        }
        
        $response = [
            'items' => $cartItems,
            'total' => $total,
            'count' => $count
        ];
    } elseif ($itemId > 0 && in_array($action, ['add', 'plus', 'minus', 'remove'], true)) {
        $item = findMenuItem($menu, $itemId);
        if (!$item) {
            $response = ['error' => 'Menu item not found.'];
        } else {
            $itemKey = (string)$itemId;
            $message = '';
            
            if ($action === 'remove') {
                unset($_SESSION['cart'][$itemKey]);
                $message = 'Item removed from cart.';
            } elseif ($action === 'add' || $action === 'plus') {
                if (empty($item['available'])) {
                    $response = ['error' => 'This item is currently unavailable.'];
                } else {
                    $quantity = $_SESSION['cart'][$itemKey] ?? 0;
                    if ($quantity < 20) {
                        $_SESSION['cart'][$itemKey] = $quantity + 1;
                        $message = 'Item added to cart.';
                    } else {
                        $response = ['error' => 'Maximum quantity reached.'];
                    }
                }
            } elseif ($action === 'minus') {
                $quantity = $_SESSION['cart'][$itemKey] ?? 0;
                if ($quantity > 1) {
                    $_SESSION['cart'][$itemKey] = $quantity - 1;
                    $message = 'Quantity updated.';
                } else {
                    unset($_SESSION['cart'][$itemKey]);
                    $message = 'Item removed from cart.';
                }
            }
            
            if (!isset($response['error'])) {
                // Return updated cart summary after action
                $cartItems = [];
                $total = 0.0;
                $count = 0;
                
                foreach ($_SESSION['cart'] as $id => $quantity) {
                    $item = findMenuItem($menu, $id);
                    if (!$item) continue;
                    
                    $quantity = intval($quantity);
                    if ($quantity < 1) continue;
                    
                    $subtotal = $item['price'] * $quantity;
                    $cartItems[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'quantity' => $quantity,
                        'subtotal' => $subtotal
                    ];
                    $total += $subtotal;
                    $count += $quantity;
                }
                
                $response = [
                    'items' => $cartItems,
                    'total' => $total,
                    'count' => $count,
                    'message' => $message
                ];
            }
        }
    } else {
        $response = ['error' => 'Invalid request'];
    }
    
    echo json_encode($response);
    exit;
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$action = $_GET['action'] ?? '';
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId > 0 && in_array($action, ['add', 'plus', 'minus', 'remove', 'clear'], true)) {
    $message = '';

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        $message = 'Cart cleared successfully.';
    } else {
        $item = findMenuItem($menu, $itemId);
        if (!$item) {
            $_SESSION['flash'] = 'Menu item not found.';
            header('Location: cart.php');
            exit;
        }

        $itemKey = (string)$itemId;

        if ($action === 'remove') {
            unset($_SESSION['cart'][$itemKey]);
            $message = 'Item removed from cart.';
        }

        if ($action === 'add' || $action === 'plus') {
            if (empty($item['available'])) {
                $message = 'This item is currently unavailable.';
            } else {
                $quantity = $_SESSION['cart'][$itemKey] ?? 0;
                if ($quantity < 20) {
                    $_SESSION['cart'][$itemKey] = $quantity + 1;
                }
                $message = 'Quantity updated.';
            }
        }

        if ($action === 'minus') {
            $quantity = $_SESSION['cart'][$itemKey] ?? 0;
            if ($quantity > 1) {
                $_SESSION['cart'][$itemKey] = $quantity - 1;
                $message = 'Quantity updated.';
            } else {
                unset($_SESSION['cart'][$itemKey]);
                $message = 'Item removed from cart.';
            }
        }
    }

    $_SESSION['flash'] = $message;
    header('Location: cart.php');
    exit;
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
    <title>Cart - Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 32px;
            align-items: start;
        }
        
        .cart-items {
            display: grid;
            gap: 16px;
        }
        
        .cart-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 16px;
            align-items: center;
        }
        
        .cart-item-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
        }
        
        .cart-item-details h3 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
        }
        
        .cart-item-details .meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .cart-item-controls {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quantity-controls button {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .quantity-controls span {
            min-width: 40px;
            text-align: center;
            font-weight: 700;
        }
        
        .cart-summary {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            position: sticky;
            top: 20px;
        }
        
        .cart-summary h3 {
            margin: 0 0 20px 0;
            font-size: 1.2rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-weight: 700;
            padding-top: 16px;
        }
        
        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--border);
        }
        
        .cart-actions {
            margin-top: 24px;
            display: grid;
            gap: 12px;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-cart h3 {
            margin: 0 0 16px 0;
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
            
            .cart-summary {
                position: static;
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <section class="section-title">
            <div>
                <h2>Your Cart</h2>
                <p>Review your order and proceed to checkout.</p>
            </div>
            <div>
                <a class="button-secondary" href="index.php">Continue Shopping</a>
            </div>
        </section>

        <?php if ($flash): ?>
            <div class="message-bar"><?php echo esc($flash); ?></div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Browse the menu to add delicious items to your order.</p>
                <a class="button" href="index.php" style="margin-top: 16px;">Browse Menu</a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <div class="cart-items">
                    <?php foreach ($cartItems as $cartItem): ?>
                        <?php $item = $cartItem['item']; ?>
                        <div class="cart-item">
                            <img class="cart-item-image" src="<?php echo esc($item['image']); ?>" alt="<?php echo esc($item['name']); ?>">
                            <div class="cart-item-details">
                                <h3><?php echo esc($item['name']); ?></h3>
                                <div class="meta"><?php echo esc($item['description']); ?></div>
                                <div class="meta" style="margin-top: 8px;">Unit price: <?php echo number_format($item['price'], 2); ?> Birr</div>
                            </div>
                            <div class="cart-item-controls">
                                <div class="quantity-controls">
                                    <a href="cart.php?action=minus&id=<?php echo esc($item['id']); ?>" class="button-secondary">-</a>
                                    <span><?php echo esc($cartItem['quantity']); ?></span>
                                    <a href="cart.php?action=plus&id=<?php echo esc($item['id']); ?>" class="button-secondary">+</a>
                                </div>
                                <a href="cart.php?action=remove&id=<?php echo esc($item['id']); ?>" class="button-secondary">Remove</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="cart-summary">
                    <h3>Order Summary</h3>
                    <?php foreach ($cartItems as $cartItem): ?>
                        <div class="summary-item">
                            <div>
                                <strong><?php echo esc($cartItem['item']['name']); ?></strong>
                                <div style="color: var(--text-secondary); font-size: 0.9rem;">Qty <?php echo esc($cartItem['quantity']); ?></div>
                            </div>
                            <div><?php echo number_format($cartItem['subtotal'], 2); ?> Birr</div>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Total</span>
                        <strong><?php echo number_format($total, 2); ?> Birr</strong>
                    </div>
                    <div class="cart-actions">
                        <a class="button" href="checkout.php">Proceed to Checkout</a>
                        <a class="button-secondary" href="cart.php?action=clear&id=0">Clear Cart</a>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>