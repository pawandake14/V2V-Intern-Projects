<?php
require_once '../config/database.php';
require_once '../config/config.php';

class Auth
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Register a new user
     */
    public function register($username, $email, $password, $first_name, $last_name, $phone = '', $address = '')
    {
        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            return ['success' => false, 'message' => 'All required fields must be filled'];
        }

        if (!validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }

        // Check if user already exists
        $query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $query = "INSERT INTO users (username, email, password_hash, first_name, last_name, phone, address) 
                  VALUES (:username, :email, :password_hash, :first_name, :last_name, :phone, :address)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);

        if ($stmt->execute()) {
            $user_id = $this->conn->lastInsertId();
            return ['success' => true, 'message' => 'User registered successfully', 'user_id' => $user_id];
        } else {
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    /**
     * Login user
     */
    public function login($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        // Get user by username or email
        $query = "SELECT id, username, email, password_hash, first_name, last_name, is_active 
                  FROM users WHERE (username = :username OR email = :username) AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();

            if (password_verify($password, $user['password_hash'])) {
                // Create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['logged_in'] = true;

                // Update last login
                $update_query = "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':id', $user['id']);
                $update_stmt->execute();

                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name']
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password'];
            }
        } else {
            return ['success' => false, 'message' => 'User not found'];
        }
    }

    /**
     * Admin login
     */
    public function adminLogin($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $query = "SELECT id, username, email, password_hash, role, is_active 
                  FROM admin_users WHERE (username = :username OR email = :username) AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $admin = $stmt->fetch();

            if (password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_logged_in'] = true;

                return [
                    'success' => true,
                    'message' => 'Admin login successful',
                    'admin' => [
                        'id' => $admin['id'],
                        'username' => $admin['username'],
                        'email' => $admin['email'],
                        'role' => $admin['role']
                    ]
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid password'];
            }
        } else {
            return ['success' => false, 'message' => 'Admin not found'];
        }
    }

    /**
     * Logout user
     */
    public function logout()
    {
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Check if admin is logged in
     */
    public function isAdminLoggedIn()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Get current user info
     */
    public function getCurrentUser()
    {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'first_name' => $_SESSION['first_name'],
                'last_name' => $_SESSION['last_name']
            ];
        }
        return null;
    }

    /**
     * Get current admin info
     */
    public function getCurrentAdmin()
    {
        if ($this->isAdminLoggedIn()) {
            return [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'],
                'email' => $_SESSION['admin_email'],
                'role' => $_SESSION['admin_role']
            ];
        }
        return null;
    }

    /**
     * Require user authentication
     */
    public function requireAuth()
    {
        if (!$this->isLoggedIn()) {
            sendErrorResponse('Authentication required', 401);
        }
    }

    /**
     * Require admin authentication
     */
    public function requireAdminAuth()
    {
        if (!$this->isAdminLoggedIn()) {
            sendErrorResponse('Admin authentication required', 401);
        }
    }
}
?>