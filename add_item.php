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
    <title>Add Menu Item — Friends Café</title>
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
            background: #ffffff;
            border: 1px solid rgba(209, 213, 219, 0.8);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 22px 45px rgba(15, 23, 42, 0.08);
            max-width: 840px;
            margin: 0 auto;
        }
        
        .form-section {
            margin-bottom: 20px;
            padding: 22px;
            border: 1px solid rgba(229, 231, 235, 0.9);
            border-radius: 22px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            background: #f8fafc;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-section h3 {
            margin: 0 0 18px 0;
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 700;
            text-align: left;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
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
        
        .form-group label {
            font-size: 0.95rem;
            color: #475569;
            font-weight: 600;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .checkbox-group {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 8px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 10px 14px;
            border-radius: 14px;
            transition: var(--transition);
            border: 1px solid rgba(226, 232, 240, 0.9);
            background: #fff;
        }
        
        .checkbox-label:hover {
            background: #f8fafc;
        }
        
        .checkbox-label input {
            margin: 0;
            accent-color: var(--primary);
        }
        
        .image-preview {
            margin-top: 12px;
            text-align: center;
            min-height: 160px;
            display: grid;
            place-items: center;
            border: 1px dashed rgba(209, 213, 219, 0.9);
            border-radius: 18px;
            background: #f8fafc;
            color: var(--text-secondary);
        }
        
        .image-preview img {
            max-width: 220px;
            max-height: 180px;
            width: auto;
            height: auto;
            border-radius: 16px;
            box-shadow: var(--shadow-medium);
            display: none;
        }
        
        .image-preview.has-image {
            border-color: var(--primary);
            background: #fff;
        }
        
        .image-preview.has-image .preview-placeholder {
            display: none;
        }
        
        .preview-placeholder {
            font-size: 0.98rem;
            color: #475569;
            padding: 14px;
        }
        
        .add-item-form input,
        .add-item-form select,
        .add-item-form textarea,
        .add-item-form .file-input {
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .add-item-form input,
        .add-item-form select,
        .add-item-form textarea {
            border-radius: 16px;
            padding: 14px 16px;
            border: 1px solid rgba(209, 213, 219, 0.95);
            background: #ffffff;
            color: var(--text-primary);
        }
        .add-item-form input:focus,
        .add-item-form select:focus,
        .add-item-form textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12);
        }
        .add-item-form .file-input {
            padding: 14px 18px;
            border-radius: 16px;
            border: 1px dashed rgba(209, 213, 219, 0.95);
            background: #f9fafb;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            font-weight: 600;
        }
        .add-item-form .file-input span:last-child {
            color: var(--primary);
        }
        .add-item-form .file-input:hover {
            background: #eef2ff;
            border-color: rgba(59, 130, 246, 0.45);
        }
        
        .add-item-form button {
            width: 100%;
            padding: 14px 18px;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 700;
            background: var(--primary);
            color: #fff;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        
        .add-item-form button:hover {
            transform: translateY(-1px);
            background: var(--primary-hover);
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
                            <label class="file-input" for="image_file">
                                <span id="file-input-label">Choose an image</span>
                                <span>Browse</span>
                            </label>
                            <input id="image_file" type="file" name="image_file" accept="image/*" required hidden>
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
                    <div class="preview-placeholder">Pick an image to preview it here.</div>
                    <img id="preview-img" alt="Image Preview">
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
        const imageInput = document.getElementById('image_file');
        const preview = document.getElementById('image-preview');
        const img = document.getElementById('preview-img');

        imageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            const fileLabel = document.getElementById('file-input-label');
        if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    preview.classList.add('has-image');
                    fileLabel.textContent = file.name;
                };
                reader.readAsDataURL(file);
            } else {
                img.src = '';
                img.style.display = 'none';
                preview.classList.remove('has-image');
                fileLabel.textContent = 'Choose an image';
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
