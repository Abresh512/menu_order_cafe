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
</head>
<body>
    <main class="page-shell" style="max-width: 720px; padding-top: 40px;">
        <section class="section-title">
            <div>
                <h2>Add New Menu Item</h2>
                <p style="margin:8px 0 0; color:#6c5b4d;">Create a new menu entry for breakfast, lunch, dinner, or drinks.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a class="button-secondary" href="admin_orders.php">Dashboard</a>
                <a class="button-secondary" href="index.php">View Menu</a>
            </div>
        </section>

        <?php if ($success): ?>
            <div class="message-bar" style="border-color:#c7e8d6; background:#eff6f0; color:#165c3f;">
                New menu item added successfully.
            </div>
        <?php endif; ?>

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
            <form method="post" enctype="multipart/form-data">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="<?php echo esc($name); ?>" required>

                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo esc($description); ?></textarea>

                <label for="price">Price (Birr)</label>
                <input id="price" type="number" name="price" step="0.01" value="<?php echo esc($price); ?>" required>

                <label for="image_file">Upload Image</label>
                <input id="image_file" type="file" name="image_file" accept="image/*" required>
                <div id="image-preview" style="margin-top:10px; display:none;">
                    <img id="preview-img" src="" alt="Image Preview" style="max-width:100%; height:auto; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                </div>

                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach (["Breakfast", "Lunch", "Dinner", "Drinks"] as $option): ?>
                        <option value="<?php echo esc($option); ?>" <?php echo $category === $option ? 'selected' : ''; ?>><?php echo esc($option); ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:16px;">
                    <label><input type="checkbox" name="available" <?php echo $available ? 'checked' : ''; ?>> In stock</label>
                    <label><input type="checkbox" name="popular" <?php echo $popular ? 'checked' : ''; ?>> Popular item</label>
                </div>

                <button type="submit" class="button" style="margin-top:20px;">Add Item</button>
            </form>
        </article>
    </main>

    <script>
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

        // Simple form validation feedback
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            });
        });
    </script>
    <script src="app.js"></script>
</body>
</html>
