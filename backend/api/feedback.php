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
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'ratings':
                getRatings($conn);
                break;
            case 'user-ratings':
                $auth->requireAuth();
                getUserRatings($conn, $auth);
                break;
            case 'feedback':
                getFeedback($conn);
                break;
            case 'feature-requests':
                getFeatureRequests($conn);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'rating':
                $auth->requireAuth();
                submitRating($conn, $auth);
                break;
            case 'feedback':
                submitFeedback($conn, $auth);
                break;
            case 'feature-request':
                submitFeatureRequest($conn, $auth);
                break;
            case 'vote-feature':
                $auth->requireAuth();
                voteFeatureRequest($conn, $auth);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

function submitRating($conn, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $auth->getCurrentUser();
    
    $booking_id = $input['booking_id'] ?? null;
    $rating_type = sanitizeInput($input['rating_type'] ?? '');
    $rating = intval($input['rating'] ?? 0);
    $review = sanitizeInput($input['review'] ?? '');
    
    if (empty($rating_type) || $rating < 1 || $rating > 5) {
        sendErrorResponse('Valid rating type and rating (1-5) are required');
    }
    
    // Validate booking if provided
    if ($booking_id) {
        $booking_query = "SELECT id FROM bookings WHERE id = :booking_id AND user_id = :user_id";
        $booking_stmt = $conn->prepare($booking_query);
        $booking_stmt->bindParam(':booking_id', $booking_id);
        $booking_stmt->bindParam(':user_id', $user['id']);
        $booking_stmt->execute();
        
        if ($booking_stmt->rowCount() == 0) {
            sendErrorResponse('Invalid booking ID');
        }
    }
    
    try {
        // Check if user already rated this booking for this type
        if ($booking_id) {
            $existing_query = "SELECT id FROM ratings WHERE user_id = :user_id AND booking_id = :booking_id AND rating_type = :rating_type";
            $existing_stmt = $conn->prepare($existing_query);
            $existing_stmt->bindParam(':user_id', $user['id']);
            $existing_stmt->bindParam(':booking_id', $booking_id);
            $existing_stmt->bindParam(':rating_type', $rating_type);
            $existing_stmt->execute();
            
            if ($existing_stmt->rowCount() > 0) {
                sendErrorResponse('You have already rated this booking for ' . $rating_type);
            }
        }
        
        $query = "INSERT INTO ratings (user_id, booking_id, rating_type, rating, review)
                  VALUES (:user_id, :booking_id, :rating_type, :rating, :review)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':booking_id', $booking_id);
        $stmt->bindParam(':rating_type', $rating_type);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':review', $review);
        
        if ($stmt->execute()) {
            sendSuccessResponse(['rating_id' => $conn->lastInsertId()], 'Rating submitted successfully');
        } else {
            sendErrorResponse('Failed to submit rating');
        }
    } catch (Exception $e) {
        sendErrorResponse('Rating submission failed: ' . $e->getMessage());
    }
}

function getRatings($conn) {
    try {
        $rating_type = $_GET['rating_type'] ?? '';
        $limit = intval($_GET['limit'] ?? 10);
        
        $query = "SELECT r.*, u.first_name, u.last_name, b.check_in_date, b.check_out_date
                  FROM ratings r
                  JOIN users u ON r.user_id = u.id
                  LEFT JOIN bookings b ON r.booking_id = b.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($rating_type)) {
            $query .= " AND r.rating_type = :rating_type";
            $params[':rating_type'] = $rating_type;
        }
        
        $query .= " ORDER BY r.created_at DESC LIMIT :limit";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $ratings = $stmt->fetchAll();
        
        // Calculate average rating
        $avg_query = "SELECT rating_type, AVG(rating) as average_rating, COUNT(*) as total_ratings
                      FROM ratings";
        
        if (!empty($rating_type)) {
            $avg_query .= " WHERE rating_type = :rating_type";
        }
        
        $avg_query .= " GROUP BY rating_type";
        
        $avg_stmt = $conn->prepare($avg_query);
        if (!empty($rating_type)) {
            $avg_stmt->bindParam(':rating_type', $rating_type);
        }
        $avg_stmt->execute();
        $averages = $avg_stmt->fetchAll();
        
        sendSuccessResponse([
            'ratings' => $ratings,
            'averages' => $averages
        ]);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch ratings: ' . $e->getMessage());
    }
}

function getUserRatings($conn, $auth) {
    $user = $auth->getCurrentUser();
    
    try {
        $query = "SELECT r.*, b.check_in_date, b.check_out_date
                  FROM ratings r
                  LEFT JOIN bookings b ON r.booking_id = b.id
                  WHERE r.user_id = :user_id
                  ORDER BY r.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->execute();
        $ratings = $stmt->fetchAll();
        
        sendSuccessResponse($ratings);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch user ratings: ' . $e->getMessage());
    }
}

function submitFeedback($conn, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($input['name'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $subject = sanitizeInput($input['subject'] ?? '');
    $message = sanitizeInput($input['message'] ?? '');
    $category = sanitizeInput($input['category'] ?? 'inquiry');
    
    $user_id = null;
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        $user_id = $user['id'];
        
        // Use user's info if not provided
        if (empty($name)) {
            $name = $user['first_name'] . ' ' . $user['last_name'];
        }
        if (empty($email)) {
            $email = $user['email'];
        }
    }
    
    if (empty($message)) {
        sendErrorResponse('Message is required');
    }
    
    if (!empty($email) && !validateEmail($email)) {
        sendErrorResponse('Invalid email format');
    }
    
    try {
        $query = "INSERT INTO feedback (user_id, name, email, subject, message, category)
                  VALUES (:user_id, :name, :email, :subject, :message, :category)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':category', $category);
        
        if ($stmt->execute()) {
            sendSuccessResponse(['feedback_id' => $conn->lastInsertId()], 'Feedback submitted successfully');
        } else {
            sendErrorResponse('Failed to submit feedback');
        }
    } catch (Exception $e) {
        sendErrorResponse('Feedback submission failed: ' . $e->getMessage());
    }
}

function getFeedback($conn) {
    try {
        $category = $_GET['category'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = intval($_GET['limit'] ?? 20);
        
        $query = "SELECT * FROM feedback WHERE 1=1";
        $params = [];
        
        if (!empty($category)) {
            $query .= " AND category = :category";
            $params[':category'] = $category;
        }
        
        if (!empty($status)) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $feedback = $stmt->fetchAll();
        
        sendSuccessResponse($feedback);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch feedback: ' . $e->getMessage());
    }
}

function submitFeatureRequest($conn, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $category = sanitizeInput($input['category'] ?? '');
    
    $user_id = null;
    if ($auth->isLoggedIn()) {
        $user = $auth->getCurrentUser();
        $user_id = $user['id'];
    }
    
    if (empty($title) || empty($description)) {
        sendErrorResponse('Title and description are required');
    }
    
    try {
        $query = "INSERT INTO feature_requests (user_id, title, description, category)
                  VALUES (:user_id, :title, :description, :category)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category', $category);
        
        if ($stmt->execute()) {
            sendSuccessResponse(['request_id' => $conn->lastInsertId()], 'Feature request submitted successfully');
        } else {
            sendErrorResponse('Failed to submit feature request');
        }
    } catch (Exception $e) {
        sendErrorResponse('Feature request submission failed: ' . $e->getMessage());
    }
}

function getFeatureRequests($conn) {
    try {
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        $limit = intval($_GET['limit'] ?? 20);
        
        $query = "SELECT fr.*, u.first_name, u.last_name
                  FROM feature_requests fr
                  LEFT JOIN users u ON fr.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($status)) {
            $query .= " AND fr.status = :status";
            $params[':status'] = $status;
        }
        
        if (!empty($category)) {
            $query .= " AND fr.category = :category";
            $params[':category'] = $category;
        }
        
        $query .= " ORDER BY fr.votes DESC, fr.created_at DESC LIMIT :limit";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $requests = $stmt->fetchAll();
        
        sendSuccessResponse($requests);
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch feature requests: ' . $e->getMessage());
    }
}

function voteFeatureRequest($conn, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $auth->getCurrentUser();
    
    $request_id = $input['request_id'] ?? '';
    
    if (empty($request_id)) {
        sendErrorResponse('Request ID is required');
    }
    
    try {
        $query = "UPDATE feature_requests SET votes = votes + 1 WHERE id = :request_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':request_id', $request_id);
        
        if ($stmt->execute()) {
            sendSuccessResponse([], 'Vote recorded successfully');
        } else {
            sendErrorResponse('Failed to record vote');
        }
    } catch (Exception $e) {
        sendErrorResponse('Vote failed: ' . $e->getMessage());
    }
}
?>

