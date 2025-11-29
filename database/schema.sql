-- Hotel Management System Database Schema

CREATE DATABASE IF NOT EXISTS hotel_management;
USE hotel_management;

-- Users table for customer authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'manager', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Room types table
CREATE TABLE room_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    max_occupancy INT NOT NULL,
    amenities TEXT,  -- changed from JSON to TEXT for compatibility
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rooms table
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    room_type_id INT NOT NULL,
    floor INT,
    status ENUM('available', 'occupied', 'maintenance', 'cleaning') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    guests_count INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
    special_requests TEXT,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Restaurant categories table
CREATE TABLE menu_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Restaurant menu items table
CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(8,2) NOT NULL,
    image_url VARCHAR(255),
    is_vegetarian BOOLEAN DEFAULT FALSE,
    is_vegan BOOLEAN DEFAULT FALSE,
    is_gluten_free BOOLEAN DEFAULT FALSE,
    is_available BOOLEAN DEFAULT TRUE,
    preparation_time INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Food orders table
CREATE TABLE food_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    room_number VARCHAR(20),
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    delivery_type ENUM('room_service', 'restaurant', 'takeaway') DEFAULT 'room_service',
    special_instructions TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Food order items table
CREATE TABLE food_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES food_orders(id),
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer ratings table
CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_id INT,
    rating_type ENUM('room', 'service', 'food', 'overall') NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customer feedback table
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100),
    email VARCHAR(100),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    category ENUM('complaint', 'suggestion', 'compliment', 'inquiry') DEFAULT 'inquiry',
    status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Feature requests table
CREATE TABLE feature_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100),
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('submitted', 'under_review', 'approved', 'in_development', 'completed', 'rejected') DEFAULT 'submitted',
    votes INT DEFAULT 0,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Analytics tracking table
CREATE TABLE analytics_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    user_id INT,
    session_id VARCHAR(100),
    page_url VARCHAR(255),
    event_data TEXT, -- changed from JSON to TEXT
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Website settings table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default room types
INSERT INTO room_types (name, description, base_price, max_occupancy, amenities, image_url) VALUES
('Standard Room', 'Comfortable room with basic amenities', 99.99, 2, '["WiFi", "TV", "Air Conditioning", "Private Bathroom"]', '/images/rooms/standard.jpg'),
('Deluxe Room', 'Spacious room with premium amenities', 149.99, 2, '["WiFi", "Smart TV", "Air Conditioning", "Mini Bar", "Balcony", "Premium Bathroom"]', '/images/rooms/deluxe.jpg'),
('Suite', 'Luxury suite with separate living area', 249.99, 4, '["WiFi", "Smart TV", "Air Conditioning", "Mini Bar", "Balcony", "Jacuzzi", "Living Room", "Kitchen"]', '/images/rooms/suite.jpg'),
('Presidential Suite', 'Ultimate luxury with panoramic views', 499.99, 6, '["WiFi", "Smart TV", "Air Conditioning", "Full Bar", "Terrace", "Jacuzzi", "Living Room", "Full Kitchen", "Butler Service"]', '/images/rooms/presidential.jpg');

-- Insert default menu categories
INSERT INTO menu_categories (name, description, display_order) VALUES
('Appetizers', 'Start your meal with our delicious appetizers', 1),
('Main Courses', 'Hearty and satisfying main dishes', 2),
('Desserts', 'Sweet endings to your perfect meal', 3),
('Beverages', 'Refreshing drinks and specialty cocktails', 4),
('Room Service Specials', 'Exclusive dishes available for room service', 5);

-- Insert sample menu items
INSERT INTO menu_items (category_id, name, description, price, is_vegetarian, is_vegan, preparation_time) VALUES
(1, 'Caesar Salad', 'Fresh romaine lettuce with parmesan and croutons', 12.99, TRUE, FALSE, 10),
(1, 'Bruschetta', 'Toasted bread with fresh tomatoes and basil', 9.99, TRUE, TRUE, 8),
(2, 'Grilled Salmon', 'Fresh Atlantic salmon with herbs and lemon', 28.99, FALSE, FALSE, 25),
(2, 'Vegetarian Pasta', 'Penne with seasonal vegetables in marinara sauce', 18.99, TRUE, TRUE, 20),
(2, 'Beef Tenderloin', 'Premium cut with garlic mashed potatoes', 35.99, FALSE, FALSE, 30),
(3, 'Chocolate Lava Cake', 'Warm chocolate cake with vanilla ice cream', 8.99, TRUE, FALSE, 15),
(3, 'Fresh Fruit Platter', 'Seasonal fruits beautifully arranged', 7.99, TRUE, TRUE, 5),
(4, 'House Wine', 'Selection of red and white wines', 8.99, TRUE, TRUE, 2),
(4, 'Craft Beer', 'Local brewery selection', 6.99, TRUE, TRUE, 2),
(4, 'Fresh Juice', 'Orange, apple, or cranberry', 4.99, TRUE, TRUE, 3);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, email, password_hash, role) VALUES
('admin', 'admin@hotel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('hotel_name', 'Grand Luxury Hotel', 'Name of the hotel'),
('hotel_address', '123 Luxury Avenue, City, State 12345', 'Hotel address'),
('hotel_phone', '+1 (555) 123-4567', 'Hotel contact phone'),
('hotel_email', 'info@grandluxuryhotel.com', 'Hotel contact email'),
('check_in_time', '15:00', 'Standard check-in time'),
('check_out_time', '11:00', 'Standard check-out time'),
('currency', 'USD', 'Hotel currency'),
('tax_rate', '0.10', 'Tax rate for bookings');
