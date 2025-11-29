<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../utils/auth.php';

$database = new Database();
$conn = $database->getConnection();
$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : 'menu';
        
        switch ($action) {
            case 'menu':
                getMenu($conn);
                break;
            case 'categories':
                getCategories($conn);
                break;
            case 'orders':
                $auth->requireAuth();
                getUserOrders($conn, $auth);
                break;
            case 'order-details':
                $auth->requireAuth();
                getOrderDetails($conn, $auth);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'order':
                $auth->requireAuth();
                createOrder($conn, $auth);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getMenu($conn) {
    try {
        $category_id = $_GET['category_id'] ?? '';
        $dietary_filter = $_GET['dietary'] ?? '';
        
        $query = "SELECT mi.*, mc.name as category_name
                  FROM menu_items mi
                  JOIN menu_categories mc ON mi.category_id = mc.id
                  WHERE mi.is_available = 1 AND mc.is_active = 1";
        
        $params = [];
        
        if (!empty($category_id)) {
            $query .= " AND mi.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        
        if (!empty($dietary_filter)) {
            switch ($dietary_filter) {
                case 'vegetarian':
                    $query .= " AND mi.is_vegetarian = 1";
                    break;
                case 'vegan':
                    $query .= " AND mi.is_vegan = 1";
                    break;
                case 'gluten_free':
                    $query .= " AND mi.is_gluten_free = 1";
                    break;
            }
        }
        
        $query .= " ORDER BY mc.display_order ASC, mi.name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $menu_items = $stmt->fetchAll();
        
        sendSuccessResponse($menu_items);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch menu: ' . $e->getMessage());
    }
}

function getCategories($conn) {
    try {
        $query = "SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY display_order ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        sendSuccessResponse($categories);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch categories: ' . $e->getMessage());
    }
}

function createOrder($conn, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $auth->getCurrentUser();
    
    $items = $input['items'] ?? [];
    $room_number = sanitizeInput($input['room_number'] ?? '');
    $delivery_type = sanitizeInput($input['delivery_type'] ?? 'room_service');
    $special_instructions = sanitizeInput($input['special_instructions'] ?? '');
    
    if (empty($items)) {
        sendErrorResponse('Order items are required');
    }
    
    if ($delivery_type === 'room_service' && empty($room_number)) {
        sendErrorResponse('Room number is required for room service');
    }
    
    try {
        $conn->beginTransaction();
        
        $total_amount = 0;
        $order_items = [];
        
        // Validate items and calculate total
        foreach ($items as $item) {
            $menu_item_id = $item['menu_item_id'] ?? '';
            $quantity = $item['quantity'] ?? 0;
            
            if (empty($menu_item_id) || $quantity <= 0) {
                $conn->rollBack();
                sendErrorResponse('Invalid item data');
            }
            
            // Get menu item details
            $item_query = "SELECT id, name, price, is_available FROM menu_items WHERE id = :id AND is_available = 1";
            $item_stmt = $conn->prepare($item_query);
            $item_stmt->bindParam(':id', $menu_item_id);
            $item_stmt->execute();
            
            if ($item_stmt->rowCount() == 0) {
                $conn->rollBack();
                sendErrorResponse('Menu item not available: ' . $menu_item_id);
            }
            
            $menu_item = $item_stmt->fetch();
            $subtotal = $menu_item['price'] * $quantity;
            $total_amount += $subtotal;
            
            $order_items[] = [
                'menu_item_id' => $menu_item_id,
                'quantity' => $quantity,
                'unit_price' => $menu_item['price'],
                'subtotal' => $subtotal
            ];
        }
        
        // Create order
        $order_query = "INSERT INTO food_orders (user_id, room_number, total_amount, delivery_type, special_instructions, status)
                        VALUES (:user_id, :room_number, :total_amount, :delivery_type, :special_instructions, 'pending')";
        
        $order_stmt = $conn->prepare($order_query);
        $order_stmt->bindParam(':user_id', $user['id']);
        $order_stmt->bindParam(':room_number', $room_number);
        $order_stmt->bindParam(':total_amount', $total_amount);
        $order_stmt->bindParam(':delivery_type', $delivery_type);
        $order_stmt->bindParam(':special_instructions', $special_instructions);
        
        if (!$order_stmt->execute()) {
            $conn->rollBack();
            sendErrorResponse('Failed to create order');
        }
        
        $order_id = $conn->lastInsertId();
        
        // Add order items
        $item_query = "INSERT INTO food_order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
                       VALUES (:order_id, :menu_item_id, :quantity, :unit_price, :subtotal)";
        $item_stmt = $conn->prepare($item_query);
        
        foreach ($order_items as $order_item) {
            $item_stmt->bindParam(':order_id', $order_id);
            $item_stmt->bindParam(':menu_item_id', $order_item['menu_item_id']);
            $item_stmt->bindParam(':quantity', $order_item['quantity']);
            $item_stmt->bindParam(':unit_price', $order_item['unit_price']);
            $item_stmt->bindParam(':subtotal', $order_item['subtotal']);
            
            if (!$item_stmt->execute()) {
                $conn->rollBack();
                sendErrorResponse('Failed to add order items');
            }
        }
        
        $conn->commit();
        
        sendSuccessResponse([
            'order_id' => $order_id,
            'total_amount' => $total_amount,
            'estimated_time' => 30 // minutes
        ], 'Order placed successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        sendErrorResponse('Order failed: ' . $e->getMessage());
    }
}

function getUserOrders($conn, $auth) {
    $user = $auth->getCurrentUser();
    
    try {
        $query = "SELECT fo.*, 
                         COUNT(foi.id) as item_count,
                         GROUP_CONCAT(CONCAT(mi.name, ' (', foi.quantity, ')') SEPARATOR ', ') as items_summary
                  FROM food_orders fo
                  LEFT JOIN food_order_items foi ON fo.id = foi.order_id
                  LEFT JOIN menu_items mi ON foi.menu_item_id = mi.id
                  WHERE fo.user_id = :user_id
                  GROUP BY fo.id
                  ORDER BY fo.order_date DESC
                  LIMIT 20";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();
        $orders = $stmt->fetchAll();
        
        sendSuccessResponse($orders);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch orders: ' . $e->getMessage());
    }
}

function getOrderDetails($conn, $auth) {
    $user = $auth->getCurrentUser();
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        sendErrorResponse('Order ID is required');
    }
    
    try {
        // Get order details
        $order_query = "SELECT * FROM food_orders WHERE id = :order_id AND user_id = :user_id";
        $order_stmt = $conn->prepare($order_query);
        $order_stmt->bindParam(':order_id', $order_id);
        $order_stmt->bindParam(':user_id', $user['id']);
        $order_stmt->execute();
        
        if ($order_stmt->rowCount() == 0) {
            sendErrorResponse('Order not found', 404);
        }
        
        $order = $order_stmt->fetch();
        
        // Get order items
        $items_query = "SELECT foi.*, mi.name, mi.description, mi.image_url
                        FROM food_order_items foi
                        JOIN menu_items mi ON foi.menu_item_id = mi.id
                        WHERE foi.order_id = :order_id";
        
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bindParam(':order_id', $order_id);
        $items_stmt->execute();
        $items = $items_stmt->fetchAll();
        
        $order['items'] = $items;
        
        sendSuccessResponse($order);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch order details: ' . $e->getMessage());
    }
}
?>

