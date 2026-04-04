<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Item - The Cozy Corner</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Add Item</h1>
            <p>Add a new item to the menu</p>
        </div>
        <a href="admin.php" class="add-item-btn">Back to Admin</a>
    </header>

    <?php
    $message = '';
    $messageType = 'success';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $category = trim($_POST['category'] ?? '');

        if (empty($name) || empty($price) || empty($category)) {
            $messageType = 'error';
            $message = 'Name, Price, and Category are required.';
        } elseif (!is_numeric($price) || floatval($price) <= 0) {
            $messageType = 'error';
            $message = 'Price must be a positive number.';
        } elseif (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $messageType = 'error';
            $message = 'Image upload failed. Please select a valid image file.';
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['image_file']['tmp_name']);
            if (!in_array($fileType, $allowedTypes, true)) {
                $messageType = 'error';
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
                    $menuData = json_decode(file_get_contents('menu.json'), true);
                    if (!is_array($menuData)) {
                        $menuData = [];
                    }

                    $menuData[] = [
                        'name' => $name,
                        'description' => $description,
                        'price' => (float)$price,
                        'image' => 'uploads/' . basename($targetFile),
                        'category' => $category
                    ];
                    file_put_contents('menu.json', json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    header('Location: index.php?message=' . urlencode('Item added successfully.'));
                    exit;
                } else {
                    $messageType = 'error';
                    $message = 'Unable to move uploaded file.';
                }
            }
        }
    }
    ?>

    <main class="category" style="max-width: 640px; margin: 32px auto;">
        <?php if ($message): ?>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 12px 16px; border-radius: 4px; border: 1px solid <?php echo $messageType === 'error' ? '#f5c6cb' : '#c3e6cb'; ?>; background-color: <?php echo $messageType === 'error' ? '#f8d7da' : '#d4edda'; ?>;">
                <span style="font-size: 20px; color: <?php echo $messageType === 'error' ? '#721c24' : '#155724'; ?>;"><?php echo $messageType === 'error' ? '✕' : '✓'; ?></span>
                <span style="flex: 1; color: <?php echo $messageType === 'error' ? '#721c24' : '#155724'; ?>; font-weight: 600; font-size: 14px;">
                    <?php echo htmlspecialchars($message); ?>
                </span>
                <button onclick="this.parentElement.style.display='none';" style="background: none; border: none; font-size: 20px; color: <?php echo $messageType === 'error' ? '#721c24' : '#155724'; ?>; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" style="background:#fff; border:1px solid #e3d9cc; border-radius:12px; padding:18px;">
            <label for="name">Name</label><br>
            <input type="text" id="name" name="name" required style="width:100%; padding:8px; margin-bottom:10px;" />

            <label for="description">Description</label><br>
            <textarea id="description" name="description" style="width:100%; padding:8px; margin-bottom:10px;" rows="3"></textarea>

            <label for="price">Price</label><br>
            <input type="number" id="price" name="price" step="0.01" required style="width:100%; padding:8px; margin-bottom:10px;" />

            <label for="image_file">Image File</label><br>
            <input type="file" id="image_file" name="image_file" accept="image/*" required style="width:100%; padding:8px; margin-bottom:10px;" />

            <label for="category">Category</label><br>
            <select id="category" name="category" required style="width:100%; padding:8px; margin-bottom:14px;">
                <option value="Breakfast">Breakfast</option>
                <option value="Lunch">Lunch</option>
                <option value="Drinks">Drinks</option>
            </select>

            <button type="submit" class="pill" style="width:100%;">Add Item</button>
        </form>
    </main>

    <footer>
        <p>© 2025 The Cozy Corner. All rights reserved.</p>
    </footer>

    <script>
        // Auto-dismiss message after 3 seconds
        const messageDiv = document.querySelector('div[style*="align-items: center"]');
        if (messageDiv) {
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>