<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Item — Manage Menu</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        body { background: #f4f7fb; }
        .edit-shell { max-width: 980px; margin: 32px auto; padding: 0 16px; }
        .edit-card { background: #fff; border-radius: 22px; padding: 28px; box-shadow: 0 18px 55px rgba(15,23,42,0.08); border: 1px solid rgba(15,23,42,0.06); }
        .edit-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 28px; }
        .edit-top h1 { margin: 0; font-size: 2rem; color: #111827; }
        .edit-top p { margin: 8px 0 0; color: #4b5563; line-height: 1.6; max-width: 560px; }
        .edit-back { display: inline-flex; align-items: center; gap: 10px; color: #2563eb; font-weight: 600; text-decoration: none; }
        .edit-back:hover { text-decoration: underline; }
        .edit-grid { display: grid; grid-template-columns: 1fr 360px; gap: 28px; }
        .form-panel { display: grid; gap: 18px; }
        label { display: block; font-weight: 700; color: #334155; margin-bottom: 8px; }
        input[type="text"], input[type="number"], select, textarea, input[type="file"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 14px 16px; font-size: 0.97rem; color: #0f172a; background: #fff; transition: border-color 0.2s ease; }
        input:focus, select:focus, textarea:focus, input[type="file"]:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }
        textarea { min-height: 140px; resize: vertical; }
        .field-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .toggle-group { display: flex; align-items: center; gap: 12px; }
        .toggle-group input { width: auto; margin: 0; transform: scale(1.1); }
        .preview-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px; display: grid; gap: 18px; }
        .preview-image { width: 100%; height: 240px; border-radius: 18px; overflow: hidden; background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%); display: grid; place-items: center; }
        .preview-image img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
        .preview-meta { display: grid; gap: 10px; }
        .preview-meta h2 { margin: 0; font-size: 1.45rem; color: #0f172a; }
        .preview-meta p { margin: 0; color: #475569; line-height: 1.7; }
        .preview-badges { display: flex; flex-wrap: wrap; gap: 10px; }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
        .badge.category { background: #e0e7ff; color: #3730a3; }
        .badge.available { background: #dcfce7; color: #166534; }
        .badge.unavailable { background: #fee2e2; color: #b91c1c; }
        .badge.popular { background: #fef3c7; color: #92400e; }
        .preview-price { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 18px; }
        .preview-price span { color: #64748b; font-size: 0.95rem; }
        .preview-price strong { font-size: 1.35rem; color: #111827; }
        .button-primary { display: inline-flex; align-items: center; justify-content: center; gap: 10px; border: none; border-radius: 14px; background: #2563eb; color: #fff; padding: 14px 20px; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, background 0.2s ease; }
        .button-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .button-secondary { display: inline-flex; align-items: center; justify-content: center; border: 1px solid #cbd5e1; border-radius: 14px; background: #fff; color: #334155; padding: 14px 20px; text-decoration: none; cursor: pointer; }
        .button-secondary:hover { background: #f8fafc; }
        .message-alert { display: flex; align-items: center; gap: 12px; padding: 16px 18px; border-radius: 16px; border: 1px solid #f1f5f9; background: #f8fafc; color: #0f172a; }
        .message-alert strong { font-weight: 700; }
        @media (max-width: 860px) {
            .edit-grid { grid-template-columns: 1fr; }
            .field-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="edit-shell">
        <div class="edit-card">
            <div class="edit-top">
                <div>
                    <a class="edit-back" href="admin_items.php">← Back to Menu Management</a>
                    <h1>Edit Item</h1>
                    <p>Update item details quickly and save.</p>
                </div>
                <a class="button-secondary" href="admin_items.php">Back</a>
            </div>

            <?php
            $id = isset($_GET['id']) ? intval($_GET['id']) : -1;
            $menuData = json_decode(@file_get_contents('menu.json'), true);
            if (!is_array($menuData)) {
                $menuData = [];
            }

            if (!isset($menuData[$id])) {
                echo '<div class="message-alert"><strong>Item not found.</strong> Please return to Manage Menu and select an item.</div>';
                echo '</div></main></body></html>';
                exit;
            }

            $item = $menuData[$id];
            $message = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = trim($_POST['price'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $available = isset($_POST['available']) ? true : false;
                $popular = isset($_POST['popular']) ? true : false;
                $imagePath = trim($item['image']);

                if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $fileType = mime_content_type($_FILES['image_file']['tmp_name']);
                    if (!in_array($fileType, $allowedTypes, true)) {
                        $message = 'Image must be JPEG, PNG, GIF, or WEBP.';
                    } else {
                        $uploadDir = __DIR__ . '/uploads';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($_FILES['image_file']['name'], PATHINFO_FILENAME));
                        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
                        $targetFile = $uploadDir . '/' . $safeName . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetFile)) {
                            $imagePath = 'uploads/' . basename($targetFile);
                        }
                    }
                }

                if (empty($name) || empty($price) || empty($category)) {
                    $message = 'Name, Price, and Category are required.';
                } elseif (!is_numeric($price) || floatval($price) <= 0) {
                    $message = 'Price must be a positive number.';
                } elseif (!$message) {
                    $menuData[$id] = [
                        'id' => $item['id'] ?? $id,
                        'name' => $name,
                        'description' => $description,
                        'price' => (float)$price,
                        'image' => $imagePath,
                        'category' => $category,
                        'available' => $available,
                        'popular' => $popular,
                    ];
                    file_put_contents('menu.json', json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    header('Location: admin_items.php');
                    exit;
                }
            }
            ?>

            <?php if ($message): ?>
                <div class="message-alert" style="border-color:#f5c6cb; background:#fef2f2; color:#991b1b;">
                    <strong>Oops:</strong> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="edit-grid">
                <section class="form-panel">
                    <form method="post" enctype="multipart/form-data">
                        <div>
                            <label for="name">Item Name</label>
                            <input id="name" type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required />
                        </div>

                        <div>
                            <label for="description">Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($item['description']); ?></textarea>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="price">Price</label>
                                <input id="price" type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($item['price']); ?>" required />
                            </div>
                            <div>
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="Breakfast" <?php echo ($item['category'] === 'Breakfast') ? 'selected' : ''; ?>>Breakfast</option>
                                    <option value="Lunch" <?php echo ($item['category'] === 'Lunch') ? 'selected' : ''; ?>>Lunch</option>
                                    <option value="Dinner" <?php echo ($item['category'] === 'Dinner') ? 'selected' : ''; ?>>Dinner</option>
                                    <option value="Drinks" <?php echo ($item['category'] === 'Drinks') ? 'selected' : ''; ?>>Drinks</option>
                                </select>
                            </div>
                        </div>

                        <div class="toggle-group">
                            <label><input type="checkbox" name="available" <?php echo !empty($item['available']) ? 'checked' : ''; ?> /> Available</label>
                            <label><input type="checkbox" name="popular" <?php echo !empty($item['popular']) ? 'checked' : ''; ?> /> Popular</label>
                        </div>

                        <div>
                            <label for="image_file">Upload new image</label>
                            <input id="image_file" type="file" name="image_file" accept="image/*" />
                        </div>

                        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:10px;">
                            <button type="button" class="button-primary" onclick="showUpdateModal()">Save</button>
                            <a class="button-secondary" href="admin_items.php">Cancel</a>
                        </div>
                    </form>
                </section>

                <aside class="preview-panel">
                    <div class="preview-image">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" onerror="this.src='https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&w=900&q=80';" />
                    </div>
                    <div class="preview-meta">
                        <h2><?php echo htmlspecialchars($item['name']); ?></h2>
                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                        <div class="preview-badges">
                            <span class="badge category"><?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?></span>
                            <?php if (!empty($item['available'])): ?>
                                <span class="badge available">Available</span>
                            <?php else: ?>
                                <span class="badge unavailable">Out of stock</span>
                            <?php endif; ?>
                            <?php if (!empty($item['popular'])): ?>
                                <span class="badge popular">Popular</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="preview-price">
                        <span>Current price</span>
                        <strong><?php echo number_format($item['price'], 2); ?> Birr</strong>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <!-- Update Confirmation Modal -->
    <div id="updateModal" class="modal" style="display: none; position: fixed; inset: 0; background-color: rgba(15,23,42,0.55); backdrop-filter: blur(1px); z-index: 1000;">
        <div style="background: #ffffff; max-width: 420px; margin: 12% auto; border-radius: 20px; padding: 26px 24px; box-shadow: 0 22px 60px rgba(15,23,42,0.18);">
            <h3 style="margin-top:0; font-size:1.35rem; color:#0f172a;">Save changes?</h3>
            <p style="color:#475569; line-height:1.8;">Confirm to update this item.</p>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px; flex-wrap:wrap;">
                <button type="button" onclick="closeUpdateModal()" style="padding: 12px 18px; border-radius: 14px; border: 1px solid #cbd5e1; background: #fff; color:#334155; cursor:pointer;">Cancel</button>
                <button type="button" onclick="confirmUpdate()" style="padding: 12px 18px; border-radius: 14px; border:none; background:#2563eb; color:#fff; cursor:pointer;">Yes, save</button>
            </div>
        </div>
    </div>

    <script>
        function showUpdateModal() {
            document.getElementById('updateModal').style.display = 'block';
        }
        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }
        function confirmUpdate() {
            document.querySelector('form').submit();
        }
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target === modal) {
                closeUpdateModal();
            }
        });
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('updateModal').style.display === 'block') {
                closeUpdateModal();
            }
        });
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>