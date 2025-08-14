# 🏢 Zenevo HR & Finance Management Platform

A comprehensive, secure, and scalable HR & Finance management system built with PHP, MySQL, and modern web technologies.

## ✨ Features

### 🔐 Authentication & Security
- **OTP-based Password Reset**: Secure 6-digit OTP system with progressive blocking
- **Role-based Access Control**: Admin, HR Manager, Finance Manager, and Employee roles
- **CSRF Protection**: Token-based security for all forms
- **Session Security**: Enhanced session management with validation
- **Audit Logging**: Comprehensive security monitoring and compliance
- **Rate Limiting**: Protection against brute force attacks

### 👥 Employee Management
- **Profile Management**: Complete employee profiles with profile picture upload
- **Self-Service Portal**: Employees can update their own information
- **Department & Designation Tracking**: Organized employee hierarchy
- **Base64 Profile Pictures**: Secure image storage in database

### ⏰ Attendance System
- **Selfie-based Clock In/Out**: Photo verification for attendance
- **GPS Location Tracking**: Geofenced attendance marking
- **Mobile-friendly Interface**: Responsive design for mobile attendance
- **Attendance Reports**: Comprehensive attendance analytics
- **Fixed Image Dimensions**: Consistent UI for selfie display (48px x 48px)
- **Admin Dashboard**: Full attendance oversight for administrators

### 🏝️ Leave Management
- **Leave Requests**: Easy leave application system
- **Approval Workflow**: Manager approval process
- **Leave Balance Tracking**: Automatic leave calculation
- **Leave Calendar**: Visual leave planning

### 💰 Finance & Invoicing
- **Invoice Generation**: Professional invoice creation
- **Client Management**: Customer relationship management
- **Project Tracking**: Budget and timeline management
- **Financial Reports**: Revenue and expense analytics

### 📧 Communication
- **Email Templates**: Customizable email templates
- **Bulk Email**: Mass communication capabilities
- **Internal Messaging**: Mailbox system for internal communication

### 🛠️ DevOps & Collaboration
- **Sprint Management**: Agile project management
- **Task Assignment**: Team collaboration tools
- **Employee Search**: Smart name-based team member search
- **Progress Tracking**: Sprint and task monitoring

### 📊 Reporting & Analytics
- **Attendance Reports**: Detailed attendance analytics
- **Leave Reports**: Leave trend analysis
- **Financial Reports**: Revenue and expense tracking
- **Audit Reports**: Security and compliance monitoring

## 🚀 Recent Improvements

### Database Enhancements
- **Complete Schema**: Consolidated database with proper relationships
- **InnoDB Engine**: Better performance and transaction support
- **UTF-8mb4 Support**: Full Unicode and emoji support
- **Optimized Indexes**: Enhanced query performance

### UI/UX Improvements
- **Enhanced Color Contrast**: Better accessibility and readability
- **Fixed Image Sizing**: Consistent selfie image dimensions
- **Responsive Design**: Mobile-first approach
- **Modern CSS**: Custom styling with improved aesthetics
- **Loading States**: Better user feedback during operations

### Security Enhancements
- **Input Validation**: Comprehensive sanitization
- **XSS Protection**: Output encoding and input filtering
- **SQL Injection Prevention**: Prepared statements throughout
- **File Upload Security**: Image validation and size limits
- **Password Security**: Enhanced hashing and validation

### Performance & Scalability
- **Caching System**: File-based caching for database queries
- **Memory Optimization**: Efficient image processing
- **Connection Pooling**: Database connection management
- **Asset Optimization**: CSS minification and critical CSS
- **Performance Monitoring**: Real-time performance metrics

## 📋 Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher (8.0 recommended)
- **Web Server**: Apache or Nginx
- **Extensions**: 
  - php-mysqli
  - php-gd (for image processing)
  - php-json
  - php-session
  - php-fileinfo

## 🛠️ Installation

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd hr-finance-platform
   ```

2. **Database Setup**
   ```bash
   # Import the complete database schema
   mysql -u your_username -p your_database < final_database_schema.sql
   ```

3. **Configuration**
   ```bash
   # Copy and configure database settings
   cp config/db.example.php config/db.php
   # Edit config/db.php with your database credentials
   ```

4. **Permissions**
   ```bash
   # Set proper permissions
   chmod 755 cache/
   chmod 755 assets/
   ```

5. **Web Server Configuration**
   - Point your web server to the project root
   - Ensure mod_rewrite is enabled (for Apache)

## 🔧 Configuration

### Database Configuration
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'your_database');
```

### Email Configuration
Edit `config/mail.php` for OTP and notifications:
```php
// SMTP settings for email delivery
$mail_config = [
    'host' => 'smtp.your-provider.com',
    'username' => 'your-email@domain.com',
    'password' => 'your-password',
    'port' => 587
];
```

## 👤 Default Admin Account

- **Email**: admin@company.com
- **Password**: admin123
- **Role**: System Administrator

*Note: Change these credentials immediately after installation*

## 📱 Mobile Features

- **Responsive Design**: Works on all device sizes
- **Touch-friendly Interface**: Optimized for mobile interaction
- **Camera Integration**: Selfie capture for attendance
- **GPS Integration**: Location-based attendance marking
- **Offline Capability**: Basic functionality without internet

## 🔒 Security Features

### Password Reset System
- 6-digit OTP via email
- Progressive blocking (5min → 1hr → 1day)
- Server-side validation
- Audit trail logging

### File Upload Security
- MIME type validation
- File size restrictions
- Image dimension limits
- Secure filename generation

### Session Management
- Session regeneration
- User agent validation
- IP address tracking
- Automatic timeout

## 📈 Performance Features

### Caching
- Query result caching
- Employee list caching
- Attendance data caching
- Cache invalidation strategies

### Memory Optimization
- Image compression
- Memory usage monitoring
- Garbage collection
- Efficient data processing

### Database Optimization
- Prepared statements
- Connection pooling
- Query optimization
- Index optimization

## 🎨 UI Components

### Enhanced Styling
- **Custom CSS**: `/assets/css/custom-improvements.css`
- **Responsive Grid**: Mobile-first layout
- **Status Badges**: Color-coded status indicators
- **Enhanced Forms**: Improved input styling
- **Loading States**: User feedback during operations

### Accessibility
- **High Contrast**: WCAG compliant color schemes
- **Keyboard Navigation**: Full keyboard accessibility
- **Screen Reader Support**: Proper ARIA labels
- **Mobile Accessibility**: Touch-friendly interface

## 🔧 API Endpoints

### Authentication
- `POST /api/login.php` - User authentication
- `POST /actions/password_reset_action.php` - Password reset

### Attendance
- `POST /api/mark_attendance.php` - Clock in/out
- `GET /api/attendance_status.php` - Current status

### Employee Search
- `GET /actions/search_employees.php` - Team member search

## 📊 Database Schema

### Core Tables
- **users**: User accounts and authentication
- **employees**: Employee profiles and details
- **attendance**: Clock in/out records with photos
- **leaves**: Leave requests and approvals
- **roles**: User roles and permissions
- **audit_logs**: Security and activity logging

### Extended Tables
- **password_reset_otp**: OTP-based password reset
- **mail_templates**: Email template management
- **sprints**: Agile project management
- **tasks**: Task assignment and tracking
- **attendance_config**: System configuration

## 🚀 Deployment

### Production Checklist
- [ ] Change default admin credentials
- [ ] Configure email settings
- [ ] Set up HTTPS
- [ ] Configure backup strategy
- [ ] Enable error logging
- [ ] Set up monitoring
- [ ] Configure caching
- [ ] Optimize database

### Performance Tuning
- Enable PHP OpCache
- Configure MySQL query cache
- Set up CDN for assets
- Enable gzip compression
- Optimize images

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation
- Review the security guidelines

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🙏 Acknowledgments

- Built with modern PHP practices
- Responsive design with Tailwind CSS
- Security best practices implementation
- Performance optimization techniques

---

**Last Updated**: January 2025
**Version**: 2.0.0
**Status**: Production Ready ✅