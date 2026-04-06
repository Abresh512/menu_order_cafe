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

$errors = [];
$success = false;
$name = '';
$description = '';
$price = '';
$image = '';
$category = 'Breakfast';
$available = true;
$popular = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $category = trim($_POST['category'] ?? 'Breakfast');
    $available = isset($_POST['available']);
    $popular = isset($_POST['popular']);
    $imagePath = '';

    if ($name === '' || $price === '' || $category === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (!is_numeric($price) || floatval($price) <= 0) {
        $errors[] = 'Price must be a positive number.';
    }
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid image file.';
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['image_file']['tmp_name']);
        if (!in_array($fileType, $allowedTypes, true)) {
            $errors[] = 'Image must be JPEG, PNG, GIF, or WEBP.';
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
            } else {
                $errors[] = 'Unable to save the uploaded image. Please try again.';
            }
        }
    }

    $menuData = loadJson('menu.json');
    foreach ($menuData as $item) {
        if (strtolower($item['name']) === strtolower($name)) {
            $errors[] = 'A menu item with this name already exists.';
            break;
        }
    }

    if (empty($errors)) {
        $nextId = 1;
        foreach ($menuData as $item) {
            $nextId = max($nextId, intval($item['id']) + 1);
        }
        $menuData[] = [
            'id' => $nextId,
            'name' => $name,
            'description' => $description,
            'price' => round(floatval($price), 2),
            'image' => $imagePath,
            'category' => $category,
            'available' => $available,
            'popular' => $popular,
        ];
        saveJson('menu.json', $menuData);
        $success = true;
        $name = $description = $price = '';
        $category = 'Breakfast';
        $available = true;
        $popular = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Menu Item — Cozy Corner Café</title>
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
        
        .add-item-form {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow-soft);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-section {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .form-section h3 {
            margin: 0 0 16px 0;
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 20px;
            align-items: start;
        }
        
        @media (min-width: 768px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .checkbox-group {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 8px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: var(--transition);
        }
        
        .checkbox-label:hover {
            background: var(--bg);
        }
        
        .checkbox-label input {
            margin: 0;
        }
        
        .image-preview {
            margin-top: 12px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            box-shadow: var(--shadow-medium);
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-content">
            <div class="admin-title">
                <h1>Add Menu Item</h1>
                <p>Create new menu items for your café</p>
            </div>
            <div class="admin-actions">
                <a href="admin_orders.php" class="button-secondary">Manage Orders</a>
                <a href="index.php" class="button-secondary">View Menu</a>
                <a href="login.php?logout=1" class="button">Logout</a>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?php if ($success): ?>
            <div class="message-bar" id="messageBar">
                New menu item added successfully!
                <button type="button" class="message-close" onclick="hideMessage()">×</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="message-bar" style="border-color: var(--error); background: rgba(220,38,38,0.1); color: var(--error);">
                <ul style="margin: 0; padding-left: 18px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo esc($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="message-close" onclick="this.parentElement.style.display='none'">×</button>
            </div>
        <?php endif; ?>

        <article class="add-item-form">
            <form method="post" enctype="multipart/form-data">
                <div class="form-section">
                    <h3>Basic Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><strong>Item Name</strong></label>
                            <input id="name" type="text" name="name" value="<?php echo esc($name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="price"><strong>Price (Birr)</strong></label>
                            <input id="price" type="number" name="price" step="0.01" value="<?php echo esc($price); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Category & Image</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category"><strong>Category</strong></label>
                            <select id="category" name="category" required>
                                <?php foreach (["Breakfast", "Lunch", "Dinner", "Drinks"] as $option): ?>
                                    <option value="<?php echo esc($option); ?>" <?php echo $category === $option ? 'selected' : ''; ?>><?php echo esc($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="image_file"><strong>Upload Image</strong></label>
                            <input id="image_file" type="file" name="image_file" accept="image/*" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Description</h3>
                    <div class="form-group full-width">
                        <label for="description"><strong>Item Description</strong></label>
                        <textarea id="description" name="description" rows="4" required><?php echo esc($description); ?></textarea>
                    </div>
                </div>

                <div class="image-preview" id="image-preview">
                    <img id="preview-img" src="https://images.unsplash.com/photo-1511920170033-f8396924c348?auto=format&fit=crop&w=900&q=80" alt="Image Preview">
                </div>

                <div class="form-section">
                    <h3>Options</h3>
                    <div class="checkbox-group">
                        <label class="checkbox-label"><input type="checkbox" name="available" <?php echo $available ? 'checked' : ''; ?>> <strong>In Stock</strong></label>
                        <label class="checkbox-label"><input type="checkbox" name="popular" <?php echo $popular ? 'checked' : ''; ?>> <strong>Popular Item</strong></label>
                    </div>
                </div>

                <button type="submit" class="button">Add Menu Item</button>
            </form>
        </article>
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

        // Image preview functionality
        document.getElementById('image_file').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('image-preview');
            const img = document.getElementById('preview-img');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

        // Form validation feedback
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = 'var(--error)';
                }
            });
        });
    </script>
    <script src="app.js"></script>
</body>
</html>
