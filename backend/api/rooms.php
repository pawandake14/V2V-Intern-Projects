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
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'list':
                getRoomTypes($conn);
                break;
            case 'availability':
                checkAvailability($conn);
                break;
            case 'details':
                getRoomDetails($conn);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'book':
                $auth->requireAuth();
                bookRoom($conn, $auth);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

function getRoomTypes($conn) {
    try {
        $query = "SELECT rt.*, 
                         COUNT(r.id) as total_rooms,
                         COUNT(CASE WHEN r.status = 'available' THEN 1 END) as available_rooms
                  FROM room_types rt
                  LEFT JOIN rooms r ON rt.id = r.room_type_id
                  GROUP BY rt.id
                  ORDER BY rt.base_price ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $room_types = $stmt->fetchAll();
        
        // Decode JSON amenities
        foreach ($room_types as &$room_type) {
            $room_type['amenities'] = json_decode($room_type['amenities'], true);
        }
        
        sendSuccessResponse($room_types);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch room types: ' . $e->getMessage());
    }
}

function checkAvailability($conn) {
    $check_in = $_GET['check_in'] ?? '';
    $check_out = $_GET['check_out'] ?? '';
    $room_type_id = $_GET['room_type_id'] ?? '';
    
    if (empty($check_in) || empty($check_out)) {
        sendErrorResponse('Check-in and check-out dates are required');
    }
    
    try {
        $query = "SELECT r.id, r.room_number, rt.name as room_type_name, rt.base_price
                  FROM rooms r
                  JOIN room_types rt ON r.room_type_id = rt.id
                  WHERE r.status = 'available'";
        
        $params = [];
        
        if (!empty($room_type_id)) {
            $query .= " AND r.room_type_id = :room_type_id";
            $params[':room_type_id'] = $room_type_id;
        }
        
        $query .= " AND r.id NOT IN (
                        SELECT room_id FROM bookings 
                        WHERE status IN ('confirmed', 'checked_in')
                        AND (
                            (check_in_date <= :check_in AND check_out_date > :check_in)
                            OR (check_in_date < :check_out AND check_out_date >= :check_out)
                            OR (check_in_date >= :check_in AND check_out_date <= :check_out)
                        )
                    )
                    ORDER BY rt.base_price ASC, r.room_number ASC";
        
        $params[':check_in'] = $check_in;
        $params[':check_out'] = $check_out;
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $available_rooms = $stmt->fetchAll();
        
        sendSuccessResponse($available_rooms);
    } catch (Exception $e) {
        sendErrorResponse('Failed to check availability: ' . $e->getMessage());
    }
}

function getRoomDetails($conn) {
    $room_id = $_GET['room_id'] ?? '';
    
    if (empty($room_id)) {
        sendErrorResponse('Room ID is required');
    }
    
    try {
        $query = "SELECT r.*, rt.name as room_type_name, rt.description, rt.base_price, 
                         rt.max_occupancy, rt.amenities, rt.image_url
                  FROM rooms r
                  JOIN room_types rt ON r.room_type_id = rt.id
                  WHERE r.id = :room_id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':room_id', $room_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $room = $stmt->fetch();
            $room['amenities'] = json_decode($room['amenities'], true);
            sendSuccessResponse($room);
        } else {
            sendErrorResponse('Room not found', 404);
        }
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch room details: ' . $e->getMessage());
    }
}

function bookRoom($conn, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $auth->getCurrentUser();
    
    $room_id = $input['room_id'] ?? '';
    $check_in_date = $input['check_in_date'] ?? '';
    $check_out_date = $input['check_out_date'] ?? '';
    $guests_count = $input['guests_count'] ?? 1;
    $special_requests = sanitizeInput($input['special_requests'] ?? '');
    
    if (empty($room_id) || empty($check_in_date) || empty($check_out_date)) {
        sendErrorResponse('Room ID, check-in date, and check-out date are required');
    }
    
    // Validate dates
    $check_in = new DateTime($check_in_date);
    $check_out = new DateTime($check_out_date);
    $today = new DateTime();
    
    if ($check_in < $today) {
        sendErrorResponse('Check-in date cannot be in the past');
    }
    
    if ($check_out <= $check_in) {
        sendErrorResponse('Check-out date must be after check-in date');
    }
    
    try {
        $conn->beginTransaction();
        
        // Check room availability
        $availability_query = "SELECT r.id, rt.base_price, rt.max_occupancy
                              FROM rooms r
                              JOIN room_types rt ON r.room_type_id = rt.id
                              WHERE r.id = :room_id AND r.status = 'available'
                              AND r.id NOT IN (
                                  SELECT room_id FROM bookings 
                                  WHERE status IN ('confirmed', 'checked_in')
                                  AND (
                                      (check_in_date <= :check_in AND check_out_date > :check_in)
                                      OR (check_in_date < :check_out AND check_out_date >= :check_out)
                                      OR (check_in_date >= :check_in AND check_out_date <= :check_out)
                                  )
                              )";
        
        $availability_stmt = $conn->prepare($availability_query);
        $availability_stmt->bindParam(':room_id', $room_id);
        $availability_stmt->bindParam(':check_in', $check_in_date);
        $availability_stmt->bindParam(':check_out', $check_out_date);
        $availability_stmt->execute();
        
        if ($availability_stmt->rowCount() == 0) {
            $conn->rollBack();
            sendErrorResponse('Room is not available for the selected dates');
        }
        
        $room_info = $availability_stmt->fetch();
        
        // Check guest count
        if ($guests_count > $room_info['max_occupancy']) {
            $conn->rollBack();
            sendErrorResponse('Guest count exceeds room capacity');
        }
        
        // Calculate total amount
        $nights = $check_in->diff($check_out)->days;
        $total_amount = $room_info['base_price'] * $nights;
        
        // Create booking
        $booking_query = "INSERT INTO bookings (user_id, room_id, check_in_date, check_out_date, 
                                               guests_count, total_amount, special_requests, status)
                          VALUES (:user_id, :room_id, :check_in_date, :check_out_date, 
                                  :guests_count, :total_amount, :special_requests, 'pending')";
        
        $booking_stmt = $conn->prepare($booking_query);
        $booking_stmt->bindParam(':user_id', $user['id']);
        $booking_stmt->bindParam(':room_id', $room_id);
        $booking_stmt->bindParam(':check_in_date', $check_in_date);
        $booking_stmt->bindParam(':check_out_date', $check_out_date);
        $booking_stmt->bindParam(':guests_count', $guests_count);
        $booking_stmt->bindParam(':total_amount', $total_amount);
        $booking_stmt->bindParam(':special_requests', $special_requests);
        
        if ($booking_stmt->execute()) {
            $booking_id = $conn->lastInsertId();
            $conn->commit();
            
            sendSuccessResponse([
                'booking_id' => $booking_id,
                'total_amount' => $total_amount,
                'nights' => $nights
            ], 'Booking created successfully');
        } else {
            $conn->rollBack();
            sendErrorResponse('Failed to create booking');
        }
    } catch (Exception $e) {
        $conn->rollBack();
        sendErrorResponse('Booking failed: ' . $e->getMessage());
    }
}
?>

