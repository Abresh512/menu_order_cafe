<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Item - Cafe Menu Admin</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <main style="max-width:800px; margin:32px auto; background:#fff; border-radius:12px; padding:20px; box-shadow:0 6px 18px rgba(0,0,0,.08);">
        <h1 style="margin-top:0;">Edit Menu Item</h1>
        <a href="admin.php" style="display:inline-block; margin-bottom:14px; color:#007bff; text-decoration:none;">← Back to Admin</a>

        <?php
        $id = isset($_GET['id']) ? intval($_GET['id']) : -1;
        $menuData = json_decode(@file_get_contents('menu.json'), true);
        if (!is_array($menuData)) {
            $menuData = [];
        }

        if (!isset($menuData[$id])) {
            echo '<p style="color:#d9534f;">Item not found.</p>';
            echo '</main></body></html>';
            exit;
        }

        $item = $menuData[$id];
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $category = trim($_POST['category'] ?? '');
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
                    'name' => $name,
                    'description' => $description,
                    'price' => (float)$price,
                    'image' => $imagePath,
                    'category' => $category
                ];
                file_put_contents('menu.json', json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                header('Location: admin.php');
                exit;
            }
        }
        ?>

        <?php if ($message): ?>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 12px 16px; border-radius: 4px; border: 1px solid #f5c6cb; background-color: #f8d7da;">
                <span style="font-size: 20px; color: #721c24;">✕</span>
                <span style="flex: 1; color: #721c24; font-weight: 600; font-size: 14px;">
                    <?php echo htmlspecialchars($message); ?>
                </span>
                <button onclick="this.parentElement.style.display='none';" style="background: none; border: none; font-size: 20px; color: #721c24; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label>Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required style="width:100%; margin:8px 0; padding:10px; border:1px solid #ccc; border-radius:6px;" />
            <label>Description</label><textarea name="description" style="width:100%; margin:8px 0; padding:10px; border:1px solid #ccc; border-radius:6px;" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
            <label>Price</label><input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($item['price']); ?>" required style="width:100%; margin:8px 0; padding:10px; border:1px solid #ccc; border-radius:6px;" />
            <label>Image File (optional)</label><input type="file" name="image_file" accept="image/*" style="width:100%; margin:8px 0; padding:10px; border:1px solid #ccc; border-radius:6px;" />
            <p style="font-size:0.85rem; color:#666;">Current: <img src="<?php echo htmlspecialchars($item['image']); ?>" style="max-width:80px; max-height:60px; border-radius:4px; margin-top:4px;" /></p>
            <label>Category</label>
            <select name="category" required style="width:100%; margin:8px 0; padding:10px; border:1px solid #ccc; border-radius:6px;">
                <option value="Breakfast" <?php echo $item['category']==='Breakfast'?'selected':''; ?>>Breakfast</option>
                <option value="Lunch" <?php echo $item['category']==='Lunch'?'selected':''; ?>>Lunch</option>
                <option value="Drinks" <?php echo $item['category']==='Drinks'?'selected':''; ?>>Drinks</option>
            </select>
            <button type="button" onclick="showUpdateModal()" class="pill" style="width:100%; margin-top:14px;">Update Item</button>
        </form>
    </main>

    <!-- Update Confirmation Modal -->
    <div id="updateModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; color: #007bff;">Confirm Update</h3>
            <p>Are you sure you want to update this menu item?</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button onclick="closeUpdateModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: #f8f9fa; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button onclick="confirmUpdate()" style="padding: 8px 16px; border: none; background: #007bff; color: white; border-radius: 4px; cursor: pointer;">Update</button>
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target === modal) {
                closeUpdateModal();
            }
        }

        // Auto-dismiss message after 3 seconds
        const messageDiv = document.querySelector('div[style*="align-items: center"]');
        if (messageDiv && messageDiv.querySelector('span:nth-child(2)')) {
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }
    </script>\r\n    <script src="app.js"></script>\r\n</body>
</html>
