<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../helpers/upload.php');
require_once(__DIR__ . '/../../middleware/auth.php');

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $status     = trim($_GET['status'] ?? '');
    $search     = trim($_GET['search'] ?? '');
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $limit      = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset     = ($page - 1) * $limit;

    $conditions = [];
    $bindTypes  = '';
    $bindParams = [];

    if ($categoryId > 0) {
        $conditions[] = 'p.category_id = ?';
        $bindTypes   .= 'i';
        $bindParams[] = $categoryId;
    }

    if (!empty($status)) {
        $conditions[] = 'p.status = ?';
        $bindTypes   .= 's';
        $bindParams[] = $status;
    }

    if (!empty($search)) {
        $conditions[] = '(p.name LIKE ? OR p.description LIKE ?)';
        $bindTypes   .= 'ss';
        $term         = '%' . $search . '%';
        $bindParams[] = $term;
        $bindParams[] = $term;
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $db = getDB();

    $countSql  = "SELECT COUNT(*) AS total FROM products p $whereClause";
    $countStmt = $db->prepare($countSql);
    if ($bindTypes) {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $dataSql  = "SELECT p.*, c.name AS category_name, c.type AS category_type
                 FROM products p
                 LEFT JOIN categories c ON p.category_id = c.id
                 $whereClause
                 ORDER BY p.created_at DESC
                 LIMIT ? OFFSET ?";
    $dataStmt = $db->prepare($dataSql);
    $dataTypes  = $bindTypes . 'ii';
    $dataParams = array_merge($bindParams, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $result   = $dataStmt->get_result();
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
        $products[]    = $row;
    }
    $dataStmt->close();
    $db->close();

    sendResponse([
        'products'   => $products,
        'pagination' => [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ]
    ]);
}

// ─── POST ────────────────────────────────────────────────────────────────────
elseif ($method === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $categoryId    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $size          = trim($_POST['size'] ?? '');
    $color         = trim($_POST['color'] ?? '');
    $rentalPrice   = isset($_POST['rental_price']) ? (float)$_POST['rental_price'] : 0;
    $depositAmount = isset($_POST['deposit_amount']) ? (float)$_POST['deposit_amount'] : 0;
    $stock         = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $status        = trim($_POST['status'] ?? 'active');

    if (empty($name)) {
        sendError('Product name is required');
    }
    if ($categoryId <= 0) {
        sendError('Valid category_id is required');
    }
    if ($rentalPrice <= 0) {
        sendError('rental_price must be positive');
    }
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    $imagePaths = [];
    if (!empty($_FILES['images'])) {
        $fileCount = count($_FILES['images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $singleFile = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i]
            ];
            $path = handleFileUpload($singleFile, 'products');
            if ($path) {
                $imagePaths[] = $path;
            }
        }
    }

    $imagesJson = json_encode($imagePaths);
    $db         = getDB();

    $stmt = $db->prepare(
        'INSERT INTO products (name, description, category_id, size, color, rental_price,
                               deposit_amount, stock, images, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    // s=name, s=description, i=category_id, s=size, s=color, d=rental_price,
    // d=deposit_amount, i=stock, s=images(json), s=status
    $stmt->bind_param('ssissddiss', $name, $description, $categoryId, $size, $color,
                      $rentalPrice, $depositAmount, $stock, $imagesJson, $status);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendError('Failed to create product', 500);
    }

    $productId = $stmt->insert_id;
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'product_id' => $productId], 201);
}

// ─── PUT ─────────────────────────────────────────────────────────────────────
elseif ($method === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendError('Valid product ID is required');
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, images FROM products WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $db->close();
        sendError('Product not found', 404);
    }

    // Support both JSON body and multipart
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $body = $_POST;
    }

    $fields = [];
    $types  = '';
    $params = [];

    $stringFields = ['name', 'description', 'size', 'color', 'status'];
    foreach ($stringFields as $field) {
        if (isset($body[$field]) && $body[$field] !== '') {
            $fields[] = "$field = ?";
            $types   .= 's';
            $params[] = trim($body[$field]);
        }
    }

    if (isset($body['category_id']) && (int)$body['category_id'] > 0) {
        $fields[] = 'category_id = ?';
        $types   .= 'i';
        $params[] = (int)$body['category_id'];
    }

    if (isset($body['rental_price']) && (float)$body['rental_price'] > 0) {
        $fields[] = 'rental_price = ?';
        $types   .= 'd';
        $params[] = (float)$body['rental_price'];
    }

    if (isset($body['deposit_amount'])) {
        $fields[] = 'deposit_amount = ?';
        $types   .= 'd';
        $params[] = (float)$body['deposit_amount'];
    }

    if (isset($body['stock'])) {
        $fields[] = 'stock = ?';
        $types   .= 'i';
        $params[] = (int)$body['stock'];
    }

    // Handle image removal
    $existingImages = json_decode($product['images'] ?? '[]', true) ?: [];
    $removeImages   = isset($body['remove_images']) && is_array($body['remove_images'])
                      ? $body['remove_images'] : [];
    foreach ($removeImages as $imgPath) {
        deleteFile($imgPath);
        $existingImages = array_values(array_filter($existingImages, fn($img) => $img !== $imgPath));
    }

    // Handle new image uploads
    if (!empty($_FILES['images'])) {
        $fileCount = count($_FILES['images']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $singleFile = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i]
            ];
            $path = handleFileUpload($singleFile, 'products');
            if ($path) {
                $existingImages[] = $path;
            }
        }
    }

    if (!empty($removeImages) || !empty($_FILES['images'])) {
        $fields[] = 'images = ?';
        $types   .= 's';
        $params[] = json_encode($existingImages);
    }

    if (empty($fields)) {
        $db->close();
        sendError('No fields to update');
    }

    $params[] = $id;
    $types   .= 'i';
    $sql      = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendError('Failed to update product', 500);
    }
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'message' => 'Product updated']);
}

// ─── DELETE ──────────────────────────────────────────────────────────────────
elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendError('Valid product ID is required');
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT images FROM products WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $db->close();
        sendError('Product not found', 404);
    }

    // Delete image files
    $images = json_decode($product['images'] ?? '[]', true) ?: [];
    foreach ($images as $imgPath) {
        deleteFile($imgPath);
    }

    $delStmt = $db->prepare('DELETE FROM products WHERE id = ?');
    $delStmt->bind_param('i', $id);
    $delStmt->execute();
    $delStmt->close();
    $db->close();

    sendResponse(['success' => true, 'message' => 'Product deleted']);
}

else {
    sendError('Method not allowed', 405);
}
