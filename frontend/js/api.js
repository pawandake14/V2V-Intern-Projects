// ===== API COMMUNICATION MODULE =====

// API Configuration
const API_CONFIG = {
    baseURL: '/hotel-website/backend/api',
    timeout: 10000,
    retryAttempts: 3
};

// API Endpoints
const API_ENDPOINTS = {
    auth: {
        login: '/auth.php?action=login',
        register: '/auth.php?action=register',
        logout: '/auth.php?action=logout',
        check: '/auth.php?action=check',
        user: '/auth.php?action=user',
        adminLogin: '/auth.php?action=admin-login',
        admin: '/auth.php?action=admin'
    },
    rooms: {
        list: '/rooms.php?action=list',
        availability: '/rooms.php?action=availability',
        details: '/rooms.php?action=details',
        book: '/rooms.php?action=book'
    },
    restaurant: {
        menu: '/restaurant.php?action=menu',
        categories: '/restaurant.php?action=categories',
        order: '/restaurant.php?action=order',
        orders: '/restaurant.php?action=orders',
        orderDetails: '/restaurant.php?action=order-details'
    },
    feedback: {
        rating: '/feedback.php?action=rating',
        ratings: '/feedback.php?action=ratings',
        userRatings: '/feedback.php?action=user-ratings',
        feedback: '/feedback.php?action=feedback',
        featureRequest: '/feedback.php?action=feature-request',
        featureRequests: '/feedback.php?action=feature-requests',
        voteFeature: '/feedback.php?action=vote-feature'
    },
    admin: {
        dashboard: '/admin.php?action=dashboard',
        bookings: '/admin.php?action=bookings',
        users: '/admin.php?action=users',
        orders: '/admin.php?action=orders',
        feedback: '/admin.php?action=feedback',
        analytics: '/admin.php?action=analytics',
        revenue: '/admin.php?action=revenue',
        updateBooking: '/admin.php?action=update-booking',
        updateOrder: '/admin.php?action=update-order',
        respondFeedback: '/admin.php?action=respond-feedback',
        updateUserStatus: '/admin.php?action=user-status'
    }
};

// ===== HTTP CLIENT =====
class HTTPClient {
    constructor(config) {
        this.baseURL = config.baseURL;
        this.timeout = config.timeout;
        this.retryAttempts = config.retryAttempts;
    }

    async request(endpoint, options = {}) {
        const url = this.baseURL + endpoint;
        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        let lastError;
        for (let attempt = 0; attempt < this.retryAttempts; attempt++) {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), this.timeout);
                
                config.signal = controller.signal;
                
                const response = await fetch(url, config);
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                lastError = error;
                if (attempt < this.retryAttempts - 1) {
                    await this.delay(1000 * (attempt + 1)); // Exponential backoff
                }
            }
        }
        
        throw lastError;
    }

    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url);
    }

    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: data
        });
    }

    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: data
        });
    }

    async delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Initialize HTTP client
const httpClient = new HTTPClient(API_CONFIG);

// ===== AUTHENTICATION API =====
class AuthAPI {
    static async login(username, password) {
        try {
            const response = await httpClient.post(API_ENDPOINTS.auth.login, {
                username,
                password
            });
            
            if (response.success) {
                localStorage.setItem('user', JSON.stringify(response.data.user));
                return response;
            }
            
            throw new Error(response.message || 'Login failed');
        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    }

    static async register(userData) {
        try {
            const response = await httpClient.post(API_ENDPOINTS.auth.register, userData);
            
            if (response.success) {
                return response;
            }
            
            throw new Error(response.message || 'Registration failed');
        } catch (error) {
            console.error('Registration error:', error);
            throw error;
        }
    }

    static async logout() {
        try {
            const response = await httpClient.post(API_ENDPOINTS.auth.logout);
            localStorage.removeItem('user');
            return response;
        } catch (error) {
            console.error('Logout error:', error);
            localStorage.removeItem('user'); // Clear local storage anyway
            throw error;
        }
    }

    static async checkAuthStatus() {
        try {
            return await httpClient.get(API_ENDPOINTS.auth.check);
        } catch (error) {
            console.error('Auth check error:', error);
            throw error;
        }
    }

    static async getCurrentUser() {
        try {
            return await httpClient.get(API_ENDPOINTS.auth.user);
        } catch (error) {
            console.error('Get user error:', error);
            throw error;
        }
    }

    static async adminLogin(username, password) {
        try {
            const response = await httpClient.post(API_ENDPOINTS.auth.adminLogin, {
                username,
                password
            });
            
            if (response.success) {
                localStorage.setItem('admin', JSON.stringify(response.data.admin));
                return response;
            }
            
            throw new Error(response.message || 'Admin login failed');
        } catch (error) {
            console.error('Admin login error:', error);
            throw error;
        }
    }

    static async getCurrentAdmin() {
        try {
            return await httpClient.get(API_ENDPOINTS.auth.admin);
        } catch (error) {
            console.error('Get admin error:', error);
            throw error;
        }
    }
}

// ===== ROOMS API =====
class RoomsAPI {
    static async getRoomTypes() {
        try {
            return await httpClient.get(API_ENDPOINTS.rooms.list);
        } catch (error) {
            console.error('Get room types error:', error);
            throw error;
        }
    }

    static async checkAvailability(checkIn, checkOut, roomTypeId = null) {
        try {
            const params = {
                check_in: checkIn,
                check_out: checkOut
            };
            
            if (roomTypeId) {
                params.room_type_id = roomTypeId;
            }
            
            return await httpClient.get(API_ENDPOINTS.rooms.availability, params);
        } catch (error) {
            console.error('Check availability error:', error);
            throw error;
        }
    }

    static async getRoomDetails(roomId) {
        try {
            return await httpClient.get(API_ENDPOINTS.rooms.details, { room_id: roomId });
        } catch (error) {
            console.error('Get room details error:', error);
            throw error;
        }
    }

    static async bookRoom(bookingData) {
        try {
            return await httpClient.post(API_ENDPOINTS.rooms.book, bookingData);
        } catch (error) {
            console.error('Book room error:', error);
            throw error;
        }
    }
}

// ===== RESTAURANT API =====
class RestaurantAPI {
    static async getMenu(categoryId = null, dietaryFilter = null) {
        try {
            const params = {};
            if (categoryId) params.category_id = categoryId;
            if (dietaryFilter) params.dietary = dietaryFilter;
            
            return await httpClient.get(API_ENDPOINTS.restaurant.menu, params);
        } catch (error) {
            console.error('Get menu error:', error);
            throw error;
        }
    }

    static async getCategories() {
        try {
            return await httpClient.get(API_ENDPOINTS.restaurant.categories);
        } catch (error) {
            console.error('Get categories error:', error);
            throw error;
        }
    }

    static async placeOrder(orderData) {
        try {
            return await httpClient.post(API_ENDPOINTS.restaurant.order, orderData);
        } catch (error) {
            console.error('Place order error:', error);
            throw error;
        }
    }

    static async getUserOrders() {
        try {
            return await httpClient.get(API_ENDPOINTS.restaurant.orders);
        } catch (error) {
            console.error('Get user orders error:', error);
            throw error;
        }
    }

    static async getOrderDetails(orderId) {
        try {
            return await httpClient.get(API_ENDPOINTS.restaurant.orderDetails, { order_id: orderId });
        } catch (error) {
            console.error('Get order details error:', error);
            throw error;
        }
    }
}

// ===== FEEDBACK API =====
class FeedbackAPI {
    static async submitRating(ratingData) {
        try {
            return await httpClient.post(API_ENDPOINTS.feedback.rating, ratingData);
        } catch (error) {
            console.error('Submit rating error:', error);
            throw error;
        }
    }

    static async getRatings(ratingType = null, limit = 10) {
        try {
            const params = { limit };
            if (ratingType) params.rating_type = ratingType;
            
            return await httpClient.get(API_ENDPOINTS.feedback.ratings, params);
        } catch (error) {
            console.error('Get ratings error:', error);
            throw error;
        }
    }

    static async getUserRatings() {
        try {
            return await httpClient.get(API_ENDPOINTS.feedback.userRatings);
        } catch (error) {
            console.error('Get user ratings error:', error);
            throw error;
        }
    }

    static async submitFeedback(feedbackData) {
        try {
            return await httpClient.post(API_ENDPOINTS.feedback.feedback, feedbackData);
        } catch (error) {
            console.error('Submit feedback error:', error);
            throw error;
        }
    }

    static async getFeedback(category = null, status = null, limit = 20) {
        try {
            const params = { limit };
            if (category) params.category = category;
            if (status) params.status = status;
            
            return await httpClient.get(API_ENDPOINTS.feedback.feedback, params);
        } catch (error) {
            console.error('Get feedback error:', error);
            throw error;
        }
    }

    static async submitFeatureRequest(requestData) {
        try {
            return await httpClient.post(API_ENDPOINTS.feedback.featureRequest, requestData);
        } catch (error) {
            console.error('Submit feature request error:', error);
            throw error;
        }
    }

    static async getFeatureRequests(status = null, category = null, limit = 20) {
        try {
            const params = { limit };
            if (status) params.status = status;
            if (category) params.category = category;
            
            return await httpClient.get(API_ENDPOINTS.feedback.featureRequests, params);
        } catch (error) {
            console.error('Get feature requests error:', error);
            throw error;
        }
    }

    static async voteFeatureRequest(requestId) {
        try {
            return await httpClient.post(API_ENDPOINTS.feedback.voteFeature, { request_id: requestId });
        } catch (error) {
            console.error('Vote feature request error:', error);
            throw error;
        }
    }
}

// ===== ADMIN API =====
class AdminAPI {
    static async getDashboardStats() {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.dashboard);
        } catch (error) {
            console.error('Get dashboard stats error:', error);
            throw error;
        }
    }

    static async getBookings(filters = {}) {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.bookings, filters);
        } catch (error) {
            console.error('Get bookings error:', error);
            throw error;
        }
    }

    static async getUsers(filters = {}) {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.users, filters);
        } catch (error) {
            console.error('Get users error:', error);
            throw error;
        }
    }

    static async getOrders(filters = {}) {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.orders, filters);
        } catch (error) {
            console.error('Get orders error:', error);
            throw error;
        }
    }

    static async getFeedback(filters = {}) {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.feedback, filters);
        } catch (error) {
            console.error('Get admin feedback error:', error);
            throw error;
        }
    }

    static async getAnalytics(period = 30) {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.analytics, { period });
        } catch (error) {
            console.error('Get analytics error:', error);
            throw error;
        }
    }

    static async getRevenueStats() {
        try {
            return await httpClient.get(API_ENDPOINTS.admin.revenue);
        } catch (error) {
            console.error('Get revenue stats error:', error);
            throw error;
        }
    }

    static async updateBooking(bookingId, status) {
        try {
            return await httpClient.post(API_ENDPOINTS.admin.updateBooking, {
                booking_id: bookingId,
                status
            });
        } catch (error) {
            console.error('Update booking error:', error);
            throw error;
        }
    }

    static async updateOrder(orderId, status) {
        try {
            return await httpClient.post(API_ENDPOINTS.admin.updateOrder, {
                order_id: orderId,
                status
            });
        } catch (error) {
            console.error('Update order error:', error);
            throw error;
        }
    }

    static async respondToFeedback(feedbackId, response, status = 'in_progress') {
        try {
            return await httpClient.post(API_ENDPOINTS.admin.respondFeedback, {
                feedback_id: feedbackId,
                response,
                status
            });
        } catch (error) {
            console.error('Respond to feedback error:', error);
            throw error;
        }
    }

    static async updateUserStatus(userId, isActive) {
        try {
            return await httpClient.put(API_ENDPOINTS.admin.updateUserStatus, {
                user_id: userId,
                is_active: isActive
            });
        } catch (error) {
            console.error('Update user status error:', error);
            throw error;
        }
    }
}

// ===== API ERROR HANDLER =====
class APIErrorHandler {
    static handle(error, context = '') {
        console.error(`API Error ${context}:`, error);
        
        let userMessage = 'An unexpected error occurred. Please try again.';
        
        if (error.message) {
            if (error.message.includes('401')) {
                userMessage = 'Authentication required. Please log in.';
                this.redirectToLogin();
            } else if (error.message.includes('403')) {
                userMessage = 'Access denied. You do not have permission to perform this action.';
            } else if (error.message.includes('404')) {
                userMessage = 'The requested resource was not found.';
            } else if (error.message.includes('500')) {
                userMessage = 'Server error. Please try again later.';
            } else if (error.message.includes('timeout')) {
                userMessage = 'Request timed out. Please check your connection and try again.';
            } else {
                userMessage = error.message;
            }
        }
        
        this.showError(userMessage);
        return userMessage;
    }

    static showError(message) {
        // Show error notification
        if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        } else {
            alert(message);
        }
    }

    static redirectToLogin() {
        setTimeout(() => {
            window.location.href = '/hotel-website/frontend/pages/login.html';
        }, 2000);
    }
}

// ===== API CACHE =====
class APICache {
    constructor(ttl = 300000) { // 5 minutes default TTL
        this.cache = new Map();
        this.ttl = ttl;
    }

    set(key, data) {
        const expiry = Date.now() + this.ttl;
        this.cache.set(key, { data, expiry });
    }

    get(key) {
        const item = this.cache.get(key);
        if (!item) return null;
        
        if (Date.now() > item.expiry) {
            this.cache.delete(key);
            return null;
        }
        
        return item.data;
    }

    clear() {
        this.cache.clear();
    }

    delete(key) {
        this.cache.delete(key);
    }
}

// Initialize cache
const apiCache = new APICache();

// ===== CACHED API METHODS =====
class CachedAPI {
    static async getRoomTypes() {
        const cacheKey = 'room_types';
        let data = apiCache.get(cacheKey);
        
        if (!data) {
            const response = await RoomsAPI.getRoomTypes();
            data = response;
            apiCache.set(cacheKey, data);
        }
        
        return data;
    }

    static async getMenuCategories() {
        const cacheKey = 'menu_categories';
        let data = apiCache.get(cacheKey);
        
        if (!data) {
            const response = await RestaurantAPI.getCategories();
            data = response;
            apiCache.set(cacheKey, data);
        }
        
        return data;
    }
}

// ===== EXPORT API MODULES =====
window.AuthAPI = AuthAPI;
window.RoomsAPI = RoomsAPI;
window.RestaurantAPI = RestaurantAPI;
window.FeedbackAPI = FeedbackAPI;
window.AdminAPI = AdminAPI;
window.CachedAPI = CachedAPI;
window.APIErrorHandler = APIErrorHandler;

console.log('API module loaded successfully');

