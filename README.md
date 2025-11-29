# Grand Luxury Hotel Website

A complete, modern hotel management website built with HTML, CSS, JavaScript, Three.js, Bootstrap, PHP, and MySQL. Features advanced 3D animations, real-time admin dashboard, customer booking system, restaurant management, and comprehensive feedback system.

## 🌟 Features

### Frontend Features
- **Responsive Design**: Mobile-first approach with Bootstrap 5
- **3D Animations**: Advanced Three.js scenes with particle effects
- **Modern UI/UX**: Luxury hotel aesthetic with golden accents
- **Interactive Elements**: Smooth animations and hover effects
- **Cross-browser Compatible**: Works on all modern browsers

### User Features
- **User Registration & Authentication**: Secure signup/signin system
- **Room Booking**: Interactive room selection and booking
- **Restaurant Menu**: Browse and order from restaurant menu
- **Customer Dashboard**: Manage bookings and orders
- **Feedback System**: Rate and review hotel services
- **Feature Requests**: Submit and vote on new features

### Admin Features
- **Real-time Dashboard**: Live analytics and statistics
- **Booking Management**: View and manage all bookings
- **User Management**: Manage customer accounts
- **Order Management**: Handle restaurant orders
- **Feedback Monitoring**: Review customer feedback and ratings
- **Revenue Analytics**: Track financial performance
- **Interactive Charts**: Visual data representation

### Technical Features
- **Secure Authentication**: PHP session management
- **RESTful API**: Clean API endpoints for all operations
- **Database Integration**: MySQL with optimized queries
- **Performance Optimized**: Lazy loading and caching
- **Security Features**: Input validation and SQL injection prevention

## 🛠️ Technology Stack

### Frontend
- **HTML5**: Semantic markup
- **CSS3**: Modern styling with animations
- **JavaScript ES6+**: Interactive functionality
- **Bootstrap 5**: Responsive framework
- **Three.js**: 3D graphics and animations
- **Chart.js**: Data visualization
- **Font Awesome**: Icon library
- **AOS**: Animate on scroll library

### Backend
- **PHP 8+**: Server-side logic
- **MySQL 8+**: Database management
- **Apache**: Web server
- **RESTful API**: Clean API architecture

### Development Tools
- **Git**: Version control
- **Responsive Design**: Mobile-first approach
- **Performance Optimization**: Minified assets

## 📁 Project Structure

```
hotel-website/
├── frontend/
│   ├── index.html              # Homepage
│   ├── css/
│   │   ├── main.css           # Main styles
│   │   └── animations.css     # Animation styles
│   ├── js/
│   │   ├── main.js           # Main functionality
│   │   ├── api.js            # API communication
│   │   └── three-scene.js    # Three.js scenes
│   ├── pages/
│   │   ├── login.html        # Authentication page
│   │   └── admin/
│   │       └── dashboard.html # Admin dashboard
│   └── images/               # Image assets
├── backend/
│   ├── config/
│   │   ├── database.php      # Database configuration
│   │   └── config.php        # General configuration
│   ├── utils/
│   │   └── auth.php          # Authentication utilities
│   └── api/
│       ├── auth.php          # Authentication endpoints
│       ├── rooms.php         # Room management
│       ├── restaurant.php    # Restaurant API
│       ├── feedback.php      # Feedback system
│       └── admin.php         # Admin endpoints
├── database/
│   └── schema.sql            # Database schema
└── docs/
    └── architecture.md       # Technical documentation
```

## 🚀 Installation & Setup

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache web server
- Modern web browser

### Step 1: Database Setup
```sql
-- Create database
CREATE DATABASE hotel_management;

-- Import schema
mysql -u root -p hotel_management < database/schema.sql
```

### Step 2: Configure Database Connection
Edit `backend/config/database.php`:
```php
$host = 'localhost';
$dbname = 'hotel_management';
$username = 'your_username';
$password = 'your_password';
```

### Step 3: Web Server Setup
1. Copy project to web server directory:
   ```bash
   cp -r hotel-website /var/www/html/
   ```

2. Set proper permissions:
   ```bash
   chown -R www-data:www-data /var/www/html/hotel-website
   chmod -R 755 /var/www/html/hotel-website
   ```

### Step 4: Access the Website
- **Frontend**: `http://localhost/hotel-website/frontend/`
- **Admin Dashboard**: `http://localhost/hotel-website/frontend/pages/admin/dashboard.html`

## 🔐 Default Admin Credentials
- **Username**: admin
- **Password**: admin123

*Note: Change these credentials immediately after first login*

## 📱 Responsive Design

The website is fully responsive and optimized for:
- **Desktop**: 1920px and above
- **Laptop**: 1024px - 1919px
- **Tablet**: 768px - 1023px
- **Mobile**: 320px - 767px

## 🎨 Design Features

### Color Palette
- **Primary**: #d4af37 (Gold)
- **Secondary**: #2c3e50 (Dark Blue)
- **Accent**: #1a1a1a (Black)
- **Success**: #27ae60 (Green)
- **Warning**: #f39c12 (Orange)
- **Danger**: #e74c3c (Red)

### Typography
- **Headings**: Playfair Display (Serif)
- **Body Text**: Inter (Sans-serif)
- **UI Elements**: Inter (Sans-serif)

### Animations
- **Three.js Scenes**: 3D hotel lobby with particles
- **CSS Animations**: Smooth transitions and hover effects
- **Scroll Animations**: AOS library integration
- **Loading Animations**: Custom spinners and loaders

## 🔧 API Endpoints

### Authentication
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=register` - User registration
- `POST /api/auth.php?action=logout` - User logout
- `GET /api/auth.php?action=check` - Check auth status

### Rooms
- `GET /api/rooms.php?action=list` - Get room types
- `GET /api/rooms.php?action=availability` - Check availability
- `POST /api/rooms.php?action=book` - Book a room

### Restaurant
- `GET /api/restaurant.php?action=menu` - Get menu items
- `POST /api/restaurant.php?action=order` - Place order
- `GET /api/restaurant.php?action=orders` - Get user orders

### Feedback
- `POST /api/feedback.php?action=rating` - Submit rating
- `GET /api/feedback.php?action=ratings` - Get ratings
- `POST /api/feedback.php?action=feedback` - Submit feedback

### Admin
- `GET /api/admin.php?action=dashboard` - Dashboard stats
- `GET /api/admin.php?action=bookings` - All bookings
- `GET /api/admin.php?action=analytics` - Analytics data

## 🛡️ Security Features

### Authentication
- Secure password hashing (PHP password_hash)
- Session management
- CSRF protection
- Input validation and sanitization

### Database Security
- Prepared statements (SQL injection prevention)
- Data validation
- Secure database configuration

### Frontend Security
- XSS prevention
- Secure API communication
- Input validation

## 📊 Database Schema

### Core Tables
- **users**: User accounts and profiles
- **room_types**: Available room categories
- **bookings**: Room reservations
- **menu_items**: Restaurant menu
- **orders**: Restaurant orders
- **ratings**: Customer ratings and reviews
- **feedback**: Customer feedback and suggestions
- **feature_requests**: User-requested features

### Relationships
- Users can have multiple bookings
- Bookings reference room types
- Orders contain multiple menu items
- Users can submit ratings and feedback

## 🎯 Performance Optimization

### Frontend Optimization
- Minified CSS and JavaScript
- Optimized images
- Lazy loading for images
- Efficient Three.js rendering
- Browser caching

### Backend Optimization
- Database query optimization
- Efficient API endpoints
- Session management
- Error handling

## 🧪 Testing

### Manual Testing Completed
- ✅ User registration and login
- ✅ Room booking functionality
- ✅ Restaurant ordering system
- ✅ Admin dashboard access
- ✅ Responsive design on all devices
- ✅ Cross-browser compatibility
- ✅ Three.js animations performance

### Browser Compatibility
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

## 🚀 Deployment

### Production Deployment
1. **Server Requirements**:
   - PHP 8.0+
   - MySQL 8.0+
   - Apache/Nginx
   - SSL certificate (recommended)

2. **Environment Configuration**:
   - Update database credentials
   - Configure email settings
   - Set up backup procedures
   - Enable error logging

3. **Security Hardening**:
   - Change default admin credentials
   - Configure firewall rules
   - Enable HTTPS
   - Regular security updates

## 📈 Future Enhancements

### Planned Features
- **Payment Integration**: Stripe/PayPal integration
- **Email Notifications**: Booking confirmations
- **Multi-language Support**: Internationalization
- **Mobile App**: React Native companion app
- **Advanced Analytics**: More detailed reporting
- **AI Chatbot**: Customer service automation

### Technical Improvements
- **Caching Layer**: Redis implementation
- **CDN Integration**: Asset delivery optimization
- **API Rate Limiting**: Prevent abuse
- **Automated Testing**: Unit and integration tests
- **CI/CD Pipeline**: Automated deployment

## 🤝 Contributing

### Development Guidelines
1. Follow PSR-12 coding standards for PHP
2. Use semantic HTML5 markup
3. Follow BEM methodology for CSS
4. Write clean, documented JavaScript
5. Test on multiple browsers and devices

### Code Style
- **PHP**: PSR-12 standard
- **JavaScript**: ES6+ with proper documentation
- **CSS**: BEM methodology
- **HTML**: Semantic markup

## 📞 Support

### Technical Support
- **Documentation**: Check this README and docs/ folder
- **Issues**: Report bugs and feature requests
- **Updates**: Regular maintenance and updates

### Contact Information
- **Developer**: Hotel Website Development Team
- **Email**: support@grandluxuryhotel.com
- **Website**: https://grandluxuryhotel.com

## 📄 License

This project is proprietary software developed for Grand Luxury Hotel. All rights reserved.

## 🙏 Acknowledgments

### Libraries and Frameworks
- **Bootstrap**: Responsive framework
- **Three.js**: 3D graphics library
- **Chart.js**: Data visualization
- **AOS**: Animation library
- **Font Awesome**: Icon library

### Design Inspiration
- Modern luxury hotel websites
- Material Design principles
- Contemporary web design trends

---

**Built with ❤️ for Grand Luxury Hotel**

*Last updated: August 2025*

