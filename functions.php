<?php
require_once 'db.php';

/**
 * Get all items with category information
 */
function getAllItems($pdo)
{
    try {
        $stmt = $pdo->query("
            SELECT i.*, c.categoryName 
            FROM tblitems i 
            JOIN tblcategories c ON i.categoryID = c.categoryID 
            ORDER BY i.itemName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get items by category
 */
function getItemsByCategory($pdo, $categoryID)
{
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM tblitems 
            WHERE categoryID = :categoryID 
            ORDER BY itemName ASC
        ");
        $stmt->execute([':categoryID' => $categoryID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get low stock items (quantity below reorderLevel)
 */
function getLowStockItems($pdo)
{
    try {
        $stmt = $pdo->query("
            SELECT i.*, c.categoryName 
            FROM tblitems i 
            JOIN tblcategories c ON i.categoryID = c.categoryID 
            WHERE i.itemQuantity < i.reorderLevel 
            ORDER BY i.itemQuantity ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get items expiring soon (within 7 days)
 */
function getExpiringItems($pdo, $days = 7)
{
    try {
        $expiryDate = date('Y-m-d', strtotime("+$days days"));
        $stmt = $pdo->prepare("
            SELECT i.*, c.categoryName 
            FROM tblitems i 
            JOIN tblcategories c ON i.categoryID = c.categoryID 
            WHERE i.itemExpiryDate <= :expiryDate 
            AND i.itemExpiryDate >= CURDATE()
            ORDER BY i.itemExpiryDate ASC
        ");
        $stmt->execute([':expiryDate' => $expiryDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all orders with details
 */
function getAllOrders($pdo)
{
    try {
        $stmt = $pdo->query("
            SELECT * FROM tblorders 
            ORDER BY orderDate DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get order details for a specific order
 */
function getOrderDetails($pdo, $orderID)
{
    try {
        $stmt = $pdo->prepare("
            SELECT od.*, i.itemName, i.itemPrice 
            FROM tblorderdetails od 
            JOIN tblitems i ON od.itemID = i.itemID 
            WHERE od.orderID = :orderID
        ");
        $stmt->execute([':orderID' => $orderID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all purchases with user information
 */
function getAllPurchases($pdo)
{
    try {
        $stmt = $pdo->query("
            SELECT p.*, u.fullName 
            FROM tblpurchases p 
            JOIN tblusers u ON p.userID = u.userID 
            ORDER BY p.purchaseDate DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get purchase details for a specific purchase
 */
function getPurchaseDetails($pdo, $purchaseID)
{
    try {
        $stmt = $pdo->prepare("
            SELECT pd.*, i.itemName 
            FROM tblpurchasedetails pd 
            JOIN tblitems i ON pd.itemID = i.itemID 
            WHERE pd.purchaseID = :purchaseID
        ");
        $stmt->execute([':purchaseID' => $purchaseID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all stock transactions
 */
function getAllTransactions($pdo)
{
    try {
        $stmt = $pdo->query("
            SELECT st.*, i.itemName, u.fullName 
            FROM tblstocktransactions st 
            JOIN tblitems i ON st.itemID = i.itemID 
            JOIN tblusers u ON st.userID = u.userID 
            ORDER BY st.transactionDate DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get transaction history for a specific item
 */
function getItemTransactions($pdo, $itemID)
{
    try {
        $stmt = $pdo->prepare("
            SELECT st.*, u.fullName 
            FROM tblstocktransactions st 
            JOIN tblusers u ON st.userID = u.userID 
            WHERE st.itemID = :itemID 
            ORDER BY st.transactionDate DESC
        ");
        $stmt->execute([':itemID' => $itemID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all categories
 */
function getAllCategories($pdo)
{
    try {
        $stmt = $pdo->query("SELECT * FROM tblcategories ORDER BY categoryName ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all users
 */
function getAllUsers($pdo)
{
    try {
        $stmt = $pdo->query("SELECT * FROM tblusers ORDER BY fullName ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Calculate total inventory value
 */
function getTotalInventoryValue($pdo)
{
    try {
        $stmt = $pdo->query("SELECT SUM(itemPrice * itemQuantity) as total FROM tblitems");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get dashboard statistics
 */
function getDashboardStats($pdo)
{
    try {
        // Total Orders
        $orderStmt = $pdo->query("SELECT COUNT(*) as total FROM tblorders");
        $totalOrders = $orderStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pending Orders
        $pendingStmt = $pdo->query("SELECT COUNT(*) as total FROM tblorders WHERE status = 'pending'");
        $pendingOrders = $pendingStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total Items
        $itemsStmt = $pdo->query("SELECT COUNT(*) as total FROM tblitems");
        $totalItems = $itemsStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Low Stock Items
        $lowStockStmt = $pdo->query("SELECT COUNT(*) as total FROM tblitems WHERE itemQuantity < reorderLevel");
        $lowStockCount = $lowStockStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total Users
        $usersStmt = $pdo->query("SELECT COUNT(*) as total FROM tblusers");
        $totalUsers = $usersStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Inventory Value
        $inventoryValue = getTotalInventoryValue($pdo);

        return [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'totalItems' => $totalItems,
            'lowStockCount' => $lowStockCount,
            'totalUsers' => $totalUsers,
            'inventoryValue' => $inventoryValue
        ];
    } catch (PDOException $e) {
        return [];
    }
}

function generateBillNumber($pdo, $date) {
    $yearMonth = date('Y-m', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tblBills WHERE billNumber LIKE :prefix");
    $stmt->execute(['prefix' => 'BILL-' . $yearMonth . '%']);
    $count = $stmt->fetchColumn() + 1;
    return 'BILL-' . $yearMonth . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

/**
 * Get recent orders (last 5)
 */
function getRecentOrders($pdo, $limit = 5)
{
    $stmt = $pdo->prepare("
        SELECT tblOrders.orderID, tblOrders.orderDate, tblOrders.totalAmount, tblOrders.status,
               tblusers.fullName AS customerName
        FROM tblOrders
        INNER JOIN tblusers ON tblOrders.userID = tblusers.userID
        ORDER BY tblOrders.orderDate DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
