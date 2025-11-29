<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../utils/auth.php';

$database = new Database();
$conn = $database->getConnection();
$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

// Require admin authentication for all endpoints
$auth->requireAdminAuth();

switch ($method) {
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'dashboard':
                getDashboardStats($conn);
                break;
            case 'bookings':
                getBookings($conn);
                break;
            case 'users':
                getUsers($conn);
                break;
            case 'orders':
                getOrders($conn);
                break;
            case 'feedback':
                getFeedbackAdmin($conn);
                break;
            case 'analytics':
                getAnalytics($conn);
                break;
            case 'revenue':
                getRevenueStats($conn);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'update-booking':
                updateBooking($conn);
                break;
            case 'update-order':
                updateOrder($conn);
                break;
            case 'respond-feedback':
                respondToFeedback($conn);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    case 'PUT':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'user-status':
                updateUserStatus($conn);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getDashboardStats($conn) {
    try {
        $stats = [];
        
        // Total bookings today
        $today_bookings_query = "SELECT COUNT(*) as count FROM bookings WHERE DATE(booking_date) = CURDATE()";
        $stmt = $conn->prepare($today_bookings_query);
        $stmt->execute();
        $stats['today_bookings'] = $stmt->fetch()['count'];
        
        // Total revenue this month
        $month_revenue_query = "SELECT SUM(total_amount) as revenue FROM bookings 
                               WHERE MONTH(booking_date) = MONTH(CURDATE()) 
                               AND YEAR(booking_date) = YEAR(CURDATE())
                               AND status IN ('confirmed', 'checked_in', 'checked_out')";
        $stmt = $conn->prepare($month_revenue_query);
        $stmt->execute();
        $stats['month_revenue'] = floatval($stmt->fetch()['revenue'] ?? 0);
        
        // Current occupancy
        $occupancy_query = "SELECT 
                               COUNT(CASE WHEN r.status = 'occupied' THEN 1 END) as occupied_rooms,
                               COUNT(*) as total_rooms
                           FROM rooms r";
        $stmt = $conn->prepare($occupancy_query);
        $stmt->execute();
        $occupancy = $stmt->fetch();
        $stats['occupancy_rate'] = $occupancy['total_rooms'] > 0 ? 
            round(($occupancy['occupied_rooms'] / $occupancy['total_rooms']) * 100, 1) : 0;
        
        // Active users
        $active_users_query = "SELECT COUNT(*) as count FROM users WHERE is_active = 1";
        $stmt = $conn->prepare($active_users_query);
        $stmt->execute();
        $stats['active_users'] = $stmt->fetch()['count'];
        
        // Pending bookings
        $pending_bookings_query = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
        $stmt = $conn->prepare($pending_bookings_query);
        $stmt->execute();
        $stats['pending_bookings'] = $stmt->fetch()['count'];
        
        // Average rating
        $avg_rating_query = "SELECT AVG(rating) as avg_rating FROM ratings WHERE rating_type = 'overall'";
        $stmt = $conn->prepare($avg_rating_query);
        $stmt->execute();
        $stats['average_rating'] = round(floatval($stmt->fetch()['avg_rating'] ?? 0), 1);
        
        // Recent bookings
        $recent_bookings_query = "SELECT b.*, u.first_name, u.last_name, u.email, rt.name as room_type
                                 FROM bookings b
                                 JOIN users u ON b.user_id = u.id
                                 JOIN rooms r ON b.room_id = r.id
                                 JOIN room_types rt ON r.room_type_id = rt.id
                                 ORDER BY b.booking_date DESC
                                 LIMIT 5";
        $stmt = $conn->prepare($recent_bookings_query);
        $stmt->execute();
        $stats['recent_bookings'] = $stmt->fetchAll();
        
        // Unread feedback
        $unread_feedback_query = "SELECT COUNT(*) as count FROM feedback WHERE status = 'new'";
        $stmt = $conn->prepare($unread_feedback_query);
        $stmt->execute();
        $stats['unread_feedback'] = $stmt->fetch()['count'];
        
        sendSuccessResponse($stats);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch dashboard stats: ' . $e->getMessage());
    }
}

function getBookings($conn) {
    try {
        $status = $_GET['status'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone,
                         r.room_number, rt.name as room_type_name
                  FROM bookings b
                  JOIN users u ON b.user_id = u.id
                  JOIN rooms r ON b.room_id = r.id
                  JOIN room_types rt ON r.room_type_id = rt.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($status)) {
            $query .= " AND b.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($date_from)) {
            $query .= " AND b.check_in_date >= :date_from";
            $params[':date_from'] = $date_from;
        }
        
        if (!empty($date_to)) {
            $query .= " AND b.check_out_date <= :date_to";
            $params[':date_to'] = $date_to;
        }
        
        $query .= " ORDER BY b.booking_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $bookings = $stmt->fetchAll();
        
        // Get total count
        $count_query = str_replace("SELECT b.*, u.first_name, u.last_name, u.email, u.phone, r.room_number, rt.name as room_type_name", "SELECT COUNT(*)", $query);
        $count_query = str_replace("ORDER BY b.booking_date DESC LIMIT :limit OFFSET :offset", "", $count_query);
        
        $count_stmt = $conn->prepare($count_query);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total_count = $count_stmt->fetch()['COUNT(*)'];
        
        sendSuccessResponse([
            'bookings' => $bookings,
            'total_count' => $total_count,
            'page' => $page,
            'limit' => $limit
        ]);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch bookings: ' . $e->getMessage());
    }
}

function getUsers($conn) {
    try {
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT u.*, 
                         COUNT(b.id) as total_bookings,
                         SUM(CASE WHEN b.status IN ('confirmed', 'checked_in', 'checked_out') THEN b.total_amount ELSE 0 END) as total_spent
                  FROM users u
                  LEFT JOIN bookings b ON u.id = b.user_id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if ($status !== '') {
            $query .= " AND u.is_active = :status";
            $params[':status'] = $status;
        }
        
        $query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        sendSuccessResponse($users);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch users: ' . $e->getMessage());
    }
}

function getOrders($conn) {
    try {
        $status = $_GET['status'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT fo.*, u.first_name, u.last_name, u.email,
                         COUNT(foi.id) as item_count
                  FROM food_orders fo
                  LEFT JOIN users u ON fo.user_id = u.id
                  LEFT JOIN food_order_items foi ON fo.id = foi.order_id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($status)) {
            $query .= " AND fo.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($date_from)) {
            $query .= " AND DATE(fo.order_date) >= :date_from";
            $params[':date_from'] = $date_from;
        }
        
        $query .= " GROUP BY fo.id ORDER BY fo.order_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $orders = $stmt->fetchAll();
        
        sendSuccessResponse($orders);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch orders: ' . $e->getMessage());
    }
}

function getFeedbackAdmin($conn) {
    try {
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT f.*, u.first_name, u.last_name
                  FROM feedback f
                  LEFT JOIN users u ON f.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($status)) {
            $query .= " AND f.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($category)) {
            $query .= " AND f.category = :category";
            $params[':category'] = $category;
        }
        
        $query .= " ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $feedback = $stmt->fetchAll();
        
        sendSuccessResponse($feedback);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch feedback: ' . $e->getMessage());
    }
}

function getAnalytics($conn) {
    try {
        $period = $_GET['period'] ?? '30'; // days
        
        // Booking trends
        $booking_trends_query = "SELECT DATE(booking_date) as date, COUNT(*) as bookings, SUM(total_amount) as revenue
                                FROM bookings 
                                WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
                                GROUP BY DATE(booking_date)
                                ORDER BY date ASC";
        
        $stmt = $conn->prepare($booking_trends_query);
        $stmt->bindParam(':period', $period, PDO::PARAM_INT);
        $stmt->execute();
        $booking_trends = $stmt->fetchAll();
        
        // Room type popularity
        $room_popularity_query = "SELECT rt.name, COUNT(b.id) as bookings
                                 FROM room_types rt
                                 LEFT JOIN rooms r ON rt.id = r.room_type_id
                                 LEFT JOIN bookings b ON r.id = b.room_id
                                 WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
                                 GROUP BY rt.id
                                 ORDER BY bookings DESC";
        
        $stmt = $conn->prepare($room_popularity_query);
        $stmt->bindParam(':period', $period, PDO::PARAM_INT);
        $stmt->execute();
        $room_popularity = $stmt->fetchAll();
        
        // Rating distribution
        $rating_distribution_query = "SELECT rating, COUNT(*) as count
                                     FROM ratings 
                                     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
                                     GROUP BY rating
                                     ORDER BY rating ASC";
        
        $stmt = $conn->prepare($rating_distribution_query);
        $stmt->bindParam(':period', $period, PDO::PARAM_INT);
        $stmt->execute();
        $rating_distribution = $stmt->fetchAll();
        
        sendSuccessResponse([
            'booking_trends' => $booking_trends,
            'room_popularity' => $room_popularity,
            'rating_distribution' => $rating_distribution
        ]);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch analytics: ' . $e->getMessage());
    }
}

function getRevenueStats($conn) {
    try {
        // Monthly revenue for the last 12 months
        $monthly_revenue_query = "SELECT 
                                     YEAR(booking_date) as year,
                                     MONTH(booking_date) as month,
                                     SUM(total_amount) as revenue,
                                     COUNT(*) as bookings
                                 FROM bookings 
                                 WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                 AND status IN ('confirmed', 'checked_in', 'checked_out')
                                 GROUP BY YEAR(booking_date), MONTH(booking_date)
                                 ORDER BY year ASC, month ASC";
        
        $stmt = $conn->prepare($monthly_revenue_query);
        $stmt->execute();
        $monthly_revenue = $stmt->fetchAll();
        
        sendSuccessResponse($monthly_revenue);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch revenue stats: ' . $e->getMessage());
    }
}

function updateBooking($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $booking_id = $input['booking_id'] ?? '';
    $status = sanitizeInput($input['status'] ?? '');
    
    if (empty($booking_id) || empty($status)) {
        sendErrorResponse('Booking ID and status are required');
    }
    
    try {
        $query = "UPDATE bookings SET status = :status WHERE id = :booking_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':booking_id', $booking_id);
        
        if ($stmt->execute()) {
            // Update room status based on booking status
            if ($status === 'checked_in') {
                $room_update_query = "UPDATE rooms SET status = 'occupied' 
                                     WHERE id = (SELECT room_id FROM bookings WHERE id = :booking_id)";
                $room_stmt = $conn->prepare($room_update_query);
                $room_stmt->bindParam(':booking_id', $booking_id);
                $room_stmt->execute();
            } elseif ($status === 'checked_out') {
                $room_update_query = "UPDATE rooms SET status = 'cleaning' 
                                     WHERE id = (SELECT room_id FROM bookings WHERE id = :booking_id)";
                $room_stmt = $conn->prepare($room_update_query);
                $room_stmt->bindParam(':booking_id', $booking_id);
                $room_stmt->execute();
            }
            
            sendSuccessResponse([], 'Booking updated successfully');
        } else {
            sendErrorResponse('Failed to update booking');
        }
    } catch (Exception $e) {
        sendErrorResponse('Update failed: ' . $e->getMessage());
    }
}

function updateOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_id = $input['order_id'] ?? '';
    $status = sanitizeInput($input['status'] ?? '');
    
    if (empty($order_id) || empty($status)) {
        sendErrorResponse('Order ID and status are required');
    }
    
    try {
        $query = "UPDATE food_orders SET status = :status WHERE id = :order_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':order_id', $order_id);
        
        if ($stmt->execute()) {
            sendSuccessResponse([], 'Order updated successfully');
        } else {
            sendErrorResponse('Failed to update order');
        }
    } catch (Exception $e) {
        sendErrorResponse('Update failed: ' . $e->getMessage());
    }
}

function respondToFeedback($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $feedback_id = $input['feedback_id'] ?? '';
    $response = sanitizeInput($input['response'] ?? '');
    $status = sanitizeInput($input['status'] ?? 'in_progress');
    
    if (empty($feedback_id) || empty($response)) {
        sendErrorResponse('Feedback ID and response are required');
    }
    
    try {
        $query = "UPDATE feedback SET admin_response = :response, status = :status WHERE id = :feedback_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':response', $response);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':feedback_id', $feedback_id);
        
        if ($stmt->execute()) {
            sendSuccessResponse([], 'Response added successfully');
        } else {
            sendErrorResponse('Failed to add response');
        }
    } catch (Exception $e) {
        sendErrorResponse('Response failed: ' . $e->getMessage());
    }
}

function updateUserStatus($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $input['user_id'] ?? '';
    $is_active = $input['is_active'] ?? '';
    
    if (empty($user_id) || $is_active === '') {
        sendErrorResponse('User ID and status are required');
    }
    
    try {
        $query = "UPDATE users SET is_active = :is_active WHERE id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            sendSuccessResponse([], 'User status updated successfully');
        } else {
            sendErrorResponse('Failed to update user status');
        }
    } catch (Exception $e) {
        sendErrorResponse('Update failed: ' . $e->getMessage());
    }
}
?>

