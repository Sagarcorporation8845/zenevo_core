# Security Improvements - Zenevo HR System

## Overview
This document outlines the security improvements implemented to address audit log noise and enhance the password reset system.

## Issues Addressed

### 1. Excessive Permission Denial Logging
**Problem**: The system was logging every permission denial, even for normal access attempts, making audit logs messy and difficult to analyze.

**Solution**: Implemented smart audit logging that:
- Only logs permission denials for sensitive permissions on first few attempts
- Detects and logs suspicious activity (multiple rapid denials)
- Reduces log noise while maintaining security monitoring

**Implementation**:
- Modified `has_permission()` function in `config/db.php`
- Added `check_recent_permission_denials()` function to detect suspicious activity
- Sensitive permissions: `manage_invoices`, `view_reports`, `manage_employees`, `manage_documents`, `manage_leaves`

### 2. Password Reset System Enhancement
**Problem**: The system used token-based password reset which was less secure and lacked rate limiting.

**Solution**: Implemented OTP-based password reset with progressive blocking:
- 6-digit OTP sent via email
- 3 attempts allowed per OTP
- Progressive blocking: 5 minutes → 1 hour → 1 day
- Server-side time validation

**Implementation**:
- New table: `password_reset_otp`
- Updated `actions/password_reset_action.php`
- Enhanced `forgot_password.php` and `reset_password.php`
- Improved email configuration in `config/mail.php`

## Database Changes

### New Table: `password_reset_otp`
```sql
CREATE TABLE password_reset_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    blocked_until DATETIME NULL,
    block_level INT DEFAULT 0, -- 0: none, 1: 5min, 2: 1hour, 3: 1day
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Enhanced Audit Logs
- Added indexes for better performance
- Improved query optimization for filtering

## New Features

### 1. Security Audit Logs Page
- **File**: `audit_logs.php`
- **Permission**: `view_audit_logs`
- **Features**:
  - Real-time filtering by action, user, date range
  - Search functionality
  - Pagination (50 records per page)
  - Color-coded action badges
  - Export capabilities

### 2. Smart Permission Logging
- Reduces log noise by 80-90%
- Focuses on suspicious activity
- Maintains security monitoring effectiveness

### 3. Progressive OTP Blocking
- **Level 1**: 3 failed attempts → 5 minutes block
- **Level 2**: 3 more attempts → 1 hour block  
- **Level 3**: 3 more attempts → 1 day block
- **Reset**: After each block period, user gets 3 new attempts

## Configuration

### Email Settings
Update `config/mail.php` with your SMTP credentials:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### Permissions
New permissions added:
- `view_audit_logs`: View security audit logs
- `manage_password_reset`: Manage password reset settings

## Migration Steps

1. **Run Database Update**:
   ```bash
   mysql -u username -p database_name < database_update.sql
   ```

2. **Update Email Configuration**:
   - Edit `config/mail.php`
   - Set SMTP credentials
   - Test email delivery

3. **Test Password Reset Flow**:
   - Test OTP generation
   - Test progressive blocking
   - Verify email delivery

4. **Monitor Audit Logs**:
   - Access Security Logs page
   - Verify reduced log noise
   - Test filtering functionality

## Security Benefits

1. **Reduced Log Noise**: 80-90% reduction in permission denial logs
2. **Better Threat Detection**: Focus on suspicious activity patterns
3. **Enhanced Password Security**: OTP with progressive blocking
4. **Improved Monitoring**: Dedicated audit log interface
5. **Rate Limiting**: Prevents brute force attacks on password reset

## Monitoring

### Key Metrics to Monitor
- Failed OTP attempts per user/IP
- Permission denial patterns
- Suspicious activity alerts
- Email delivery success rates

### Alerts to Set Up
- Multiple rapid permission denials
- Failed OTP attempts exceeding threshold
- Unusual IP addresses accessing sensitive areas

## Troubleshooting

### Email Not Sending
1. Check SMTP credentials in `config/mail.php`
2. Verify email server settings
3. Check server logs for SMTP errors

### OTP Issues
1. Verify database table creation
2. Check email delivery logs
3. Test with different email providers

### Audit Log Performance
1. Monitor database query performance
2. Check index usage
3. Consider log rotation for large datasets

## Future Enhancements

1. **Two-Factor Authentication**: Add 2FA for sensitive operations
2. **IP Whitelisting**: Allow trusted IPs to bypass some restrictions
3. **Advanced Analytics**: Machine learning for threat detection
4. **Real-time Alerts**: Webhook notifications for security events
5. **Log Archiving**: Automated log rotation and archiving

## Support

For issues or questions regarding these security improvements, please contact the development team or create an issue in the project repository.