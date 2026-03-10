<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$category  = trim($_GET['category'] ?? '');
$search    = trim($_GET['search'] ?? '');
$minPrice  = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice  = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$sort         = trim($_GET['sort'] ?? 'newest');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = min(100, max(1, (int)($_GET['limit'] ?? 12)));
$offset       = ($page - 1) * $limit;
$availableOnly = !empty($_GET['available_only']);

$allowedSorts = ['price_asc', 'price_desc', 'newest', 'popular'];
if (!in_array($sort, $allowedSorts)) {
    $sort = 'newest';
}

$db         = getDB();
$conditions = ['p.status = ?'];
$bindTypes  = 's';
$bindParams = ['active'];

if ($availableOnly) {
    $conditions[] = 'p.stock > 0';
}

if (!empty($category)) {
    $conditions[] = 'c.type = ?';
    $bindTypes   .= 's';
    $bindParams[] = $category;
}

if (!empty($search)) {
    $conditions[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $bindTypes   .= 'ss';
    $searchTerm   = '%' . $search . '%';
    $bindParams[] = $searchTerm;
    $bindParams[] = $searchTerm;
}

if ($minPrice !== null) {
    $conditions[] = 'p.rental_price >= ?';
    $bindTypes   .= 'd';
    $bindParams[] = $minPrice;
}

if ($maxPrice !== null) {
    $conditions[] = 'p.rental_price <= ?';
    $bindTypes   .= 'd';
    $bindParams[] = $maxPrice;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

$orderClause = match($sort) {
    'price_asc'  => 'ORDER BY p.rental_price ASC',
    'price_desc' => 'ORDER BY p.rental_price DESC',
    'popular'    => 'ORDER BY booking_count DESC, p.created_at DESC',
    default      => 'ORDER BY p.created_at DESC'
};

// Count total
$countSql  = "SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause";
$countStmt = $db->prepare($countSql);
if (!$countStmt) {
    error_log('Prepare failed (count): ' . $db->error);
    $db->close();
    sendError('Failed to retrieve products', 500);
}
if (!$countStmt->bind_param($bindTypes, ...$bindParams)) {
    error_log('Bind failed (count): ' . $countStmt->error);
    $countStmt->close();
    $db->close();
    sendError('Failed to retrieve products', 500);
}
if (!$countStmt->execute()) {
    error_log('Execute failed (count): ' . $countStmt->error);
    $countStmt->close();
    $db->close();
    sendError('Failed to retrieve products', 500);
}
$countResult = $countStmt->get_result();
$totalRow    = $countResult->fetch_assoc();
$total       = (int)$totalRow['total'];
$countStmt->close();

// Fetch paginated results
$dataSql  = "SELECT p.id, p.name, p.description, p.size, p.color, p.rental_price, p.deposit_amount,
                    p.stock, p.images, p.status, c.name AS category_name, c.type AS category_type,
                    (SELECT COUNT(*) FROM bookings b
                     WHERE b.product_id = p.id
                     AND b.status IN ('confirmed', 'active', 'returned')) AS booking_count
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             $whereClause
             $orderClause
             LIMIT ? OFFSET ?";

$dataTypes  = $bindTypes . 'ii';
$dataParams = array_merge($bindParams, [$limit, $offset]);

$dataStmt = $db->prepare($dataSql);
if (!$dataStmt) {
    error_log('Prepare failed (data): ' . $db->error);
    $db->close();
    sendError('Failed to retrieve products', 500);
}
if (!$dataStmt->bind_param($dataTypes, ...$dataParams)) {
    error_log('Bind failed (data): ' . $dataStmt->error);
    $dataStmt->close();
    $db->close();
    sendError('Failed to retrieve products', 500);
}
if (!$dataStmt->execute()) {
    error_log('Execute failed (data): ' . $dataStmt->error);
    $dataStmt->close();
    $db->close();
    sendError('Failed to retrieve products', 500);
}
$dataResult = $dataStmt->get_result();

$products = [];
while ($row = $dataResult->fetch_assoc()) {
    $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
    $products[]    = $row;
}
$dataStmt->close();
$db->close();

sendResponse([
    'success'    => true,
    'products'   => $products,
    'total'      => $total,
    'pagination' => [
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit)
    ]
]);
