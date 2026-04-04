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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_number'])) {
    $selected = trim($_POST['table_number']);
    if ($selected !== '') {
        $_SESSION['table_number'] = $selected;
        header('Location: index.php?message=' . urlencode('Table selected for checkout.'));
        exit;
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$adminLink = !empty($_SESSION['admin_user']) ? 'admin_orders.php' : 'login.php';
$adminLabel = !empty($_SESSION['admin_user']) ? 'Dashboard' : 'Login';
$tableNumber = $_SESSION['table_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cozy Corner Café</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="site-brand">
            <div>
                <h1>Cozy Corner Café</h1>
                <p>Fast, friendly, and fresh café ordering for every table.</p>
            </div>
            <div class="site-actions">
                <a class="header-link" href="<?php echo esc($adminLink); ?>"><?php echo esc($adminLabel); ?></a>
                <?php if (!empty($_SESSION['admin_user'])): ?>
                    <a class="header-link" href="add_item.php">Add Item</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero">
            <div class="hero-copy">
                <h2>Choose your perfect plate and order from your table.</h2>
                <p>Browse breakfast, lunch, dinner, and drinks. Save your table, build a fast order, and checkout with a single click.</p>
            </div>
            <div class="hero-overview">
                <span>20 Premium tables</span>
                <span>Session cart + table memory</span>
                <span>Admin dashboard with live order updates</span>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?php if ($message): ?>
            <div class="message-bar"><?php echo esc($message); ?></div>
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

        <form method="post" style="margin: 0 auto 24px; max-width: 560px; display: grid; gap: 12px;">
            <label style="font-weight:700; color:#5a4636;">Save table for checkout</label>
            <select name="table_number" style="padding: 12px 14px; border-radius: 14px; border:1px solid #d7c3b3;">
                <option value="">Select table (optional)</option>
                <?php for ($i = 1; $i <= 20; $i++): ?>
                    <option value="Table <?php echo $i; ?>" <?php echo ($tableNumber === 'Table ' . $i) ? 'selected' : ''; ?>>Table <?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="button">Save Table</button>
        </form>

        <?php if ($tableNumber): ?>
            <div class="message-bar">Selected table: <strong><?php echo esc($tableNumber); ?></strong>. You can change it before checkout.</div>
        <?php endif; ?>

        <?php if (!empty($popularItems)): ?>
            <section>
                <div class="section-title">
                    <h2>Popular Items</h2>
                    <small>Customer favorites</small>
                </div>
                <div class="menu-grid" id="popular-section">
                    <?php foreach ($popularItems as $item): ?>
                        <article class="menu-card" data-name="<?php echo esc(strtolower($item['name'])); ?>" data-category="<?php echo esc($item['category']); ?>" data-available="<?php echo !empty($item['available']) ? 'true' : 'false'; ?>">
                            <img src="<?php echo esc($item['image']); ?>" alt="<?php echo esc($item['name']); ?>" loading="lazy" decoding="async">
                            <div class="menu-card-body">
                                <div>
                                    <h3 class="menu-card-title"><?php echo esc($item['name']); ?></h3>
                                    <p class="menu-card-description"><?php echo esc($item['description']); ?></p>
                                </div>
                                <div class="menu-card-meta">
                                    <span class="menu-card-price"><?php echo number_format($item['price'], 2); ?> Birr</span>
                                    <span class="status-pill <?php echo !empty($item['available']) ? 'status-available' : 'status-unavailable'; ?>"><?php echo !empty($item['available']) ? 'In stock' : 'Out of stock'; ?></span>
                                </div>
                                <div class="menu-card-actions">
                                    <a class="button" href="cart.php?action=add&id=<?php echo esc($item['id']); ?>" <?php echo empty($item['available']) ? 'aria-disabled="true" disabled' : ''; ?>>Add to Cart</a>
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
                                <img src="<?php echo esc($item['image']); ?>" alt="<?php echo esc($item['name']); ?>" loading="lazy" decoding="async">
                                <div class="menu-card-body">
                                    <div>
                                        <h3 class="menu-card-title"><?php echo esc($item['name']); ?></h3>
                                        <p class="menu-card-description"><?php echo esc($item['description']); ?></p>
                                    </div>
                                    <div class="menu-card-meta">
                                        <span class="menu-card-price"><?php echo number_format($item['price'], 2); ?> Birr</span>
                                        <span class="status-pill <?php echo !empty($item['available']) ? 'status-available' : 'status-unavailable'; ?>"><?php echo !empty($item['available']) ? 'In stock' : 'Out of stock'; ?></span>
                                    </div>
                                    <div class="menu-card-actions">
                                        <a class="button" href="cart.php?action=add&id=<?php echo esc($item['id']); ?>" <?php echo empty($item['available']) ? 'aria-disabled="true" disabled' : ''; ?>>Add to Cart</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <div class="cart-bubble">
        <a href="cart.php">
            <span>🛒 Cart</span>
            <span class="cart-count"><?php echo (int)$cartCount; ?></span>
        </a>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Cozy Corner Café. Designed for fast local ordering.</footer>

    <script>
        const filters = document.querySelectorAll('.filter-button');
        const sections = document.querySelectorAll('section[data-section]');
        const searchInput = document.getElementById('searchInput');

        function applyFilters() {
            const searchText = searchInput.value.trim().toLowerCase();
            const activeFilter = document.querySelector('.filter-button.active').dataset.filter;

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
    </script>
</body>
</html>