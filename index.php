<?php
session_start();
if (empty($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function loadJson($file) {
    $content = @file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}


$menu = loadJson('menu.json');
$categories = ['Breakfast', 'Lunch', 'Dinner', 'Drinks'];
$popularItems = array_values(array_filter($menu, function ($item) {
    return !empty($item['popular']);
}));
$cartCount = array_sum($_SESSION['cart'] ?? []);

$message = '';
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$adminLink = !empty($_SESSION['admin_user']) ? 'admin_orders.php' : 'login.php';
$adminLabel = !empty($_SESSION['admin_user']) ? 'Dashboard' : 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends Café</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header fade-in">
        <div class="site-header-top">
            <div class="site-header-left">
                <div class="brand-logo">
                    <img src="images/friends-logo.jpg" alt="Friends Cafe Logo">
                </div>
                <div class="header-text">
                    <h1>Friends</h1>
                    <p>Fast, friendly, and fresh café ordering for every table.</p>
                </div>
            </div>

            <div class="site-actions">
                <a href="orders.php" class="button button-secondary">My Orders</a>
                <a href="<?php echo esc($adminLink); ?>" class="button button-secondary"><?php echo esc($adminLabel); ?></a>
            </div>
            <button type="button" class="hamburger-btn" aria-label="Open menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
        <div class="mobile-menu" aria-hidden="true">
            <a href="orders.php" class="mobile-menu-link">My Orders</a>
            <a href="<?php echo esc($adminLink); ?>" class="mobile-menu-link"><?php echo esc($adminLabel); ?></a>
        </div>
    </header>

    <main class="page-shell">
        <?php if ($message): ?>
            <div class="message-bar" id="messageBar">
                <?php echo esc($message); ?>
                <button type="button" class="message-close" onclick="hideMessage()">×</button>
            </div>
        <?php endif; ?>

        <div class="nav-row">
            <form method="post" class="search-field" action="index.php">
                <input type="search" name="search" id="searchInput" placeholder="Search menu items..." aria-label="Search menu" autocomplete="off">
            </form>
            <div class="filters" role="group" aria-label="Filter categories">
                <button type="button" class="filter-button active" data-filter="all">All</button>
                <?php foreach ($categories as $category): ?>
                    <button type="button" class="filter-button" data-filter="<?php echo esc($category); ?>"><?php echo esc($category); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($popularItems)): ?>
            <section>
                <div class="section-title">
                    <h2>Popular Items</h2>
                    <small>Customer favorites</small>
                </div>
                <div class="menu-grid" id="popular-section">
                    <?php foreach ($popularItems as $item): ?>
                        <article class="menu-card" data-name="<?php echo esc(strtolower($item['name'])); ?>" data-category="<?php echo esc($item['category']); ?>" data-available="<?php echo !empty($item['available']) ? 'true' : 'false'; ?>">
                            <div class="menu-card-body">
                                <div>
                                    <h3 class="menu-card-title"><?php echo esc($item['name']); ?></h3>
                                    <p class="menu-card-description"><?php echo esc($item['description']); ?></p>
                                </div>
                                <div class="menu-card-image">
                                    <img src="<?php echo esc($item['image']); ?>" alt="<?php echo esc($item['name']); ?>">
                                </div>
                                <div class="menu-card-meta">
                                    <span class="menu-card-price"><?php echo number_format($item['price'], 2); ?> Birr</span>
                                    <span class="status-pill <?php echo !empty($item['available']) ? 'status-available' : 'status-unavailable'; ?>"><?php echo !empty($item['available']) ? 'In stock' : 'Out of stock'; ?></span>
                                </div>
                                <div class="menu-card-actions">
                                    <button type="button" class="button cart-add" data-item-id="<?php echo esc($item['id']); ?>" <?php echo empty($item['available']) ? 'aria-disabled="true" disabled' : ''; ?>>Add</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php foreach ($categories as $category): ?>
            <section data-section="<?php echo esc($category); ?>">
                <div class="section-title">
                    <h2><?php echo esc($category); ?></h2>
                    <small><?php echo esc($category); ?> selections</small>
                </div>
                <div class="menu-grid">
                    <?php
                    $items = array_values(array_filter($menu, function ($item) use ($category) {
                        return $item['category'] === $category;
                    }));
                    usort($items, function ($a, $b) {
                        return strcmp($a['name'], $b['name']);
                    });
                    ?>
                    <?php if (empty($items)): ?>
                        <div class="section-empty">No items in this category yet.</div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <article class="menu-card" data-name="<?php echo esc(strtolower($item['name'])); ?>" data-category="<?php echo esc($item['category']); ?>" data-available="<?php echo !empty($item['available']) ? 'true' : 'false'; ?>">
                                <div class="menu-card-body">
                                    <div>
                                        <h3 class="menu-card-title"><?php echo esc($item['name']); ?></h3>
                                        <p class="menu-card-description"><?php echo esc($item['description']); ?></p>
                                    </div>
                                    <div class="menu-card-image">
                                        <img src="<?php echo esc($item['image']); ?>" alt="<?php echo esc($item['name']); ?>">
                                    </div>
                                    <div class="menu-card-meta">
                                        <span class="menu-card-price"><?php echo number_format($item['price'], 2); ?> Birr</span>
                                        <span class="status-pill <?php echo !empty($item['available']) ? 'status-available' : 'status-unavailable'; ?>"><?php echo !empty($item['available']) ? 'In stock' : 'Out of stock'; ?></span>
                                    </div>
                                    <div class="menu-card-actions">
                                        <button type="button" class="button cart-add" data-item-id="<?php echo esc($item['id']); ?>" <?php echo empty($item['available']) ? 'aria-disabled="true" disabled' : ''; ?>>Add</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <div class="drawer-overlay"></div>
    <aside class="cart-drawer" aria-hidden="true">
        <div class="drawer-header">
            <div>
                <h2>Your Cart</h2>
                <p class="drawer-subtitle">Review items and checkout quickly.</p>
            </div>
            <button type="button" class="drawer-close" aria-label="Close cart">×</button>
        </div>
        <div class="cart-content">
            <div class="cart-empty">No items yet. Browse the menu and tap Add.</div>
            <div class="cart-items"></div>
        </div>
        <div class="drawer-footer">
            <div class="cart-total">
                <span>Total</span>
                <strong>0.00 Birr</strong>
            </div>
            <button type="button" class="button checkout-button" disabled>Checkout</button>
        </div>
    </aside>
    <div id="toast" class="toast" aria-live="polite"></div>

    <footer class="footer">© <?php echo date('Y'); ?> Friends Café. Designed for fast local ordering.</footer>

    <button type="button" class="cart-bubble" aria-label="Open cart">🛒 <span class="cart-count"><?php echo (int)$cartCount; ?></span></button>

    <script>
        const filters = document.querySelectorAll('.filter-button');
        const sections = document.querySelectorAll('section[data-section]');
        const searchInput = document.getElementById('searchInput');
        const popularSection = document.getElementById('popular-section');

        function applyFilters() {
            const searchText = searchInput.value.trim().toLowerCase();
            const activeFilter = document.querySelector('.filter-button.active').dataset.filter;

            if (popularSection) {
                const popularParent = popularSection.closest('section');
                const popularCards = popularSection.querySelectorAll('.menu-card');
                let popularVisible = false;

                popularCards.forEach(card => {
                    const name = card.dataset.name;
                    const matchesSearch = !searchText || name.includes(searchText);
                    const visible = matchesSearch && activeFilter === 'all';
                    card.style.display = visible ? 'grid' : 'none';
                    if (visible) {
                        popularVisible = true;
                    }
                });

                if (popularParent) {
                    popularParent.style.display = popularVisible ? 'block' : 'none';
                }
            }

            sections.forEach(section => {
                const cards = section.querySelectorAll('.menu-card');
                let anyVisible = false;

                cards.forEach(card => {
                    const name = card.dataset.name;
                    const category = card.dataset.category;
                    const matchesCategory = activeFilter === 'all' || category === activeFilter;
                    const matchesSearch = !searchText || name.includes(searchText);
                    const visible = matchesCategory && matchesSearch;
                    card.style.display = visible ? 'grid' : 'none';
                    if (visible) {
                        anyVisible = true;
                    }
                });

                section.style.display = anyVisible ? 'block' : 'none';
            });
        }

        filters.forEach(button => {
            button.addEventListener('click', () => {
                filters.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                applyFilters();
            });
        });

        searchInput.addEventListener('input', applyFilters);

        const drawer = document.querySelector('.cart-drawer');
        const overlay = document.querySelector('.drawer-overlay');
        const drawerClose = document.querySelector('.drawer-close');
        const cartItemsContainer = document.querySelector('.cart-items');
        const cartEmpty = document.querySelector('.cart-empty');
        const cartTotalValue = document.querySelector('.cart-total strong');
        const checkoutButton = document.querySelector('.checkout-button');
        const toast = document.getElementById('toast');
        const cartBubble = document.querySelector('.cart-bubble');

        function fetchCart(action = 'summary', id = null) {
            const url = new URL('cart.php', location.href);
            url.searchParams.set('ajax', '1');
            url.searchParams.set('action', action);
            if (id) {
                url.searchParams.set('id', id);
            }
            return fetch(url.toString(), { credentials: 'same-origin' }).then(response => response.json());
        }

        function renderCart(data) {
            if (!data) {
                return;
            }
            document.querySelector('.cart-count').textContent = data.count;
            if (cartTotalValue) {
                cartTotalValue.textContent = data.total.toFixed(2) + ' Birr';
            }

            if (!data.items.length) {
                if (cartEmpty) cartEmpty.style.display = 'block';
                if (cartItemsContainer) cartItemsContainer.innerHTML = '';
                if (checkoutButton) {
                    checkoutButton.disabled = true;
                    checkoutButton.setAttribute('aria-disabled', 'true');
                }
                return;
            }

            if (cartEmpty) cartEmpty.style.display = 'none';
            if (checkoutButton) {
                checkoutButton.disabled = false;
                checkoutButton.removeAttribute('aria-disabled');
            }
            if (!cartItemsContainer) return;

            cartItemsContainer.innerHTML = data.items.map(item => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <strong>${item.name}</strong>
                        <span>${item.quantity} × ${item.price.toFixed(2)} Birr</span>
                    </div>
                    <div class="cart-item-actions">
                        <button type="button" data-action="minus" data-id="${item.id}">−</button>
                        <span>${item.quantity}</span>
                        <button type="button" data-action="plus" data-id="${item.id}">+</button>
                        <button type="button" class="cart-remove" data-action="remove" data-id="${item.id}">×</button>
                    </div>
                </div>
            `).join('');
        }

        function openDrawer() {
            if (overlay) overlay.classList.add('visible');
            if (drawer) drawer.classList.add('open');
            if (drawer) drawer.setAttribute('aria-hidden', 'false');
        }

        function closeDrawer() {
            if (overlay) overlay.classList.remove('visible');
            if (drawer) drawer.classList.remove('open');
            if (drawer) drawer.setAttribute('aria-hidden', 'true');
        }

        function showToast(message) {
            if (!toast || !message) return;
            toast.textContent = message;
            toast.classList.add('visible');
            window.clearTimeout(toast.timeoutId);
            toast.timeoutId = window.setTimeout(() => toast.classList.remove('visible'), 3000);
        }

        document.querySelectorAll('.cart-add').forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                const id = button.dataset.itemId;
                fetchCart('add', id).then(data => {
                    if (data.error) {
                        showToast(data.error);
                        return;
                    }
                    renderCart(data);
                    openDrawer();
                    showToast(data.message || 'Added to cart');
                });
            });
        });

        if (cartBubble) {
            cartBubble.addEventListener('click', event => {
                event.preventDefault();
                fetchCart().then(data => {
                    renderCart(data);
                    openDrawer();
                });

                cartBubble.classList.add('pulse');
                setTimeout(() => cartBubble.classList.remove('pulse'), 1800);
            });
        }

        if (checkoutButton) {
            checkoutButton.addEventListener('click', () => {
                if (!checkoutButton.disabled) {
                    window.location.href = 'checkout.php';
                }
            });
        }

        fetchCart().then(data => {
            renderCart(data);
        });

        if (overlay) overlay.addEventListener('click', closeDrawer);
        if (drawerClose) drawerClose.addEventListener('click', closeDrawer);

        if (cartItemsContainer) {
            cartItemsContainer.addEventListener('click', event => {
                const button = event.target.closest('[data-action]');
                if (!button) return;
                const action = button.dataset.action;
                const id = button.dataset.id;
                fetchCart(action, id).then(data => {
                    if (data.error) {
                        showToast(data.error);
                        return;
                    }
                    renderCart(data);
                    showToast(data.message || 'Cart updated');
                });
            });
        }

        // Auto-hide message functionality
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

        const hamburgerBtn = document.querySelector('.hamburger-btn');
        const mobileMenu = document.querySelector('.mobile-menu');

        if (hamburgerBtn && mobileMenu) {
            hamburgerBtn.addEventListener('click', () => {
                const isOpen = mobileMenu.classList.toggle('open');
                hamburgerBtn.setAttribute('aria-expanded', String(isOpen));
                mobileMenu.setAttribute('aria-hidden', String(!isOpen));
            });
        }
    </script>
    <script src="app.js"></script>
</body>
</html>
