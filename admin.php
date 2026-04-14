<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cafe Menu Admin Panel</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        .admin-container { max-width: 1000px; margin: 24px auto; background: #fff; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); padding: 20px; }
        .admin-actions { display: flex; gap: 12px; margin-bottom: 20px; }
        .admin-btn { border: 0; border-radius: 8px; padding: 10px 16px; cursor: pointer; color: #fff; font-weight:600; text-decoration:none; font-size:0.95rem; }
        .admin-btn.add { background:#28a745; }
        .admin-btn.menu { background:#556270; }
        .admin-table { width:100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { text-align:left; padding:10px; border-bottom:1px solid #e9ecef; word-wrap: break-word; overflow-wrap: break-word; }
        .admin-table th { background:#f8f9fa; color:#333; }
        .admin-table img { width:64px; height:40px; object-fit:cover; border-radius:5px; }
        .action-btn { border:0; font-weight:600; border-radius:6px; padding:6px 12px; cursor:pointer; color:#fff; }
        .action-edit { background:#007bff; }
        .action-delete { background:#dc3545; margin-left:8px; }
        .no-data { text-align:center; padding:20px; font-size:1.05rem; color:#6c757d; }
    </style>
</head>
<body>
    <main class="admin-container">
        <h1 style="margin:0 0 14px;">☕ Cafe Menu Admin Panel</h1>
        <div class="admin-actions">
            <a class="admin-btn add" href="add_item.php">Add New Item</a>
            <a class="admin-btn menu" href="index.php">View Menu</a>
        </div>

        <?php
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
            $deleteId = intval($_POST['delete_id']);
            $menuData = json_decode(@file_get_contents('menu.json'), true);
            if (!is_array($menuData)) {
                $menuData = [];
            }
            if (isset($menuData[$deleteId])) {
                array_splice($menuData, $deleteId, 1);
                file_put_contents('menu.json', json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $message = 'Item deleted successfully.';
            } else {
                $message = 'Item not found.';
            }
        }

        $menuData = json_decode(@file_get_contents('menu.json'), true);
        if (!is_array($menuData)) {
            $menuData = [];
        }

        if ($message) {
            echo '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 12px 16px; border-radius: 4px; border: 1px solid #c3e6cb; background-color: #d4edda;">
                <span style="font-size: 20px; color: #155724;">✓</span>
                <span style="flex: 1; color: #155724; font-weight: 600; font-size: 14px;">' . htmlspecialchars($message) . '</span>
                <button onclick="this.parentElement.style.display=\'none\';" style="background: none; border: none; font-size: 20px; color: #155724; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">×</button>
            </div>';
        }
        ?>

        <?php if (count($menuData) === 0): ?>
            <div class="no-data">No menu items found. Add one now.</div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($menuData as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" /></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo number_format($item['price'], 2); ?> Birr</td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td>
                            <a href="edit_item.php?id=<?php echo $index; ?>" class="action-btn action-edit">Edit</a>
                            <button type="button" onclick="showDeleteModal(<?php echo $index; ?>, '<?php echo htmlspecialchars(addslashes($item['name'])); ?>')" class="action-btn action-delete">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; color: #dc3545;">Confirm Delete</h3>
            <p id="deleteMessage">Are you sure you want to delete this item?</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button onclick="closeDeleteModal()" style="padding: 8px 16px; border: 1px solid #ccc; background: #f8f9fa; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="confirmDeleteBtn" onclick="confirmDelete()" style="padding: 8px 16px; border: none; background: #dc3545; color: white; border-radius: 4px; cursor: pointer;">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let deleteId = null;

        function showDeleteModal(id, itemName) {
            deleteId = id;
            document.getElementById('deleteMessage').textContent = `Are you sure you want to delete "${itemName}"?`;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteId = null;
        }

        function confirmDelete() {
            if (deleteId !== null) {
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_id';
                input.value = deleteId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }

        // Close modal with Escape key
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('deleteModal').style.display === 'block') {
                closeDeleteModal();
            }
        });

        // Auto-dismiss message after 3 seconds
        const messageDiv = document.querySelector('div[style*="align-items: center"]');
        if (messageDiv && messageDiv.querySelector('span:nth-child(2)')) {
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>