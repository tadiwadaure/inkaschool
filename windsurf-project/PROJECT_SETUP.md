# School Management System - Project Setup Guide

## Overview
This is a comprehensive School Management System built with PHP, MySQL, Bootstrap 5, and modern web technologies. The system provides role-based access for administrators, teachers, students, and accountants.

## System Requirements

### Server Requirements
- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher / MariaDB 10.2 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 512MB RAM (1GB recommended)
- **Storage**: Minimum 500MB available space

### PHP Extensions Required
- `pdo_mysql` (for database connectivity)
- `session` (for session management)
- `json` (for AJAX responses)
- `mbstring` (for string handling)
- `openssl` (for security features)
- `curl` (for external API calls)
- `gd` (for image processing)
- `fileinfo` (for file uploads)

## Project Structure

```
windsurf-project/
├── config/
│   └── database.php          # Database configuration and core functions
├── includes/
│   ├── auth.php              # Authentication and authorization system
│   ├── footer.php            # Site footer
│   ├── functions.php         # Utility functions
│   └── header.php            # Site header with navigation
├── assets/
│   ├── css/
│   │   └── style.css         # Custom CSS styles
│   ├── js/
│   │   └── script.js         # Custom JavaScript functions
│   └── images/               # Image assets
├── logs/                     # Application logs directory
├── database_schema.sql       # Complete database schema
└── PROJECT_SETUP.md          # This setup guide
```

### Quick Start with XAMPP

1. **Install XAMPP** (if not already installed)
2. **Start Apache and MySQL** services from XAMPP Control Panel
3. **Create Database**: Access `http://localhost/phpmyadmin` and create `school_management` database
4. **Import Schema**: Import `database_schema.sql` into the database
5. **Access Application**: `http://localhost/windsurf-project/login.php`
   - Username: `admin`
   - Password: `password`

## Installation Steps

### 1. Database Setup

1. **Create Database**
   ```sql
   CREATE DATABASE school_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**
   - Import the `database_schema.sql` file into your newly created database
   - This will create all necessary tables and a default admin user

3. **Default Admin Account**
   - Username: `admin`
   - Password: `password`
   - **Important**: Change this password immediately after first login
   - **XAMPP Login URL**: `http://localhost/windsurf-project/login.php`

### 2. Configuration

1. **Database Configuration**
   - Edit `config/database.php`
   - Update the database constants:
   ```php
   const DB_HOST = 'localhost';     // Your database host
   const DB_USER = 'root';          // Your database username
   const DB_PASS = '';              // Your database password
   const DB_NAME = 'school_management'; // Your database name
   ```

2. **Security Settings**
   - In production, set `display_errors` to `0` in `config/database.php`
   - Ensure HTTPS is enabled for secure sessions
   - Configure proper file permissions (755 for directories, 644 for files)

### 3. Web Server Configuration

#### Apache Configuration
Create a `.htaccess` file in the project root:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options SAMEORIGIN
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/windsurf-project;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
}
```

### 4. File Permissions

Set appropriate permissions for security:

```bash
# For Linux/Mac
chmod 755 assets/ logs/
chmod 644 assets/css/style.css assets/js/script.js
chmod 600 config/database.php
```

### 5. Directory Structure for Pages

Create the following directory structure for role-based pages:

```
public/
├── admin/
│   ├── dashboard.php
│   ├── students/
│   ├── teachers/
│   ├── classes/
│   └── subjects/
├── teacher/
│   ├── dashboard.php
│   └── results/
├── student/
│   ├── dashboard.php
│   ├── results.php
│   └── fees.php
├── accountant/
│   ├── dashboard.php
│   └── fees/
├── login.php
├── logout.php
├── profile.php
└── index.php
```

### 6. Access the Application

**XAMPP URL Structure:**
- **Login Page**: `http://localhost/windsurf-project/login.php`
- **Dashboard**: `http://localhost/windsurf-project/admin/dashboard.php` (after login)
- **phpMyAdmin**: `http://localhost/phpmyadmin` (for database management)

**Default Credentials:**
- Username: `admin`
- Password: `password`

## Features

### Authentication & Security
- Secure password hashing with PHP's `password_hash()`
- CSRF protection on all forms
- Session management with secure cookies
- Brute force protection with login attempt tracking
- Role-based access control
- SQL injection prevention with prepared statements

### User Roles
1. **Admin**: Full system access
   - Manage students, teachers, classes, subjects
   - View system statistics
   - User management

2. **Teacher**: Academic management
   - Manage student results
   - View assigned classes and subjects

3. **Student**: Personal information access
   - View results and grades
   - View fee details
   - Update profile

4. **Accountant**: Financial management
   - Manage fee structures
   - Process payments
   - Generate financial reports

### Frontend Features
- Responsive Bootstrap 5 design
- Modern UI with Font Awesome icons
- Interactive data tables with DataTables.net
- AJAX-powered interactions
- Print-friendly layouts
- Mobile-responsive navigation

## Development Guidelines

### Code Standards
- Use strict typing (`declare(strict_types=1)`)
- Follow PSR-12 coding standards
- Use prepared statements for all database queries
- Implement proper error handling and logging
- Use meaningful variable and function names

### Security Best Practices
- Always sanitize user input
- Validate all form data
- Use parameterized queries
- Implement proper session management
- Set appropriate HTTP headers
- Regular security updates

### Testing
- Test all user roles and permissions
- Verify database operations
- Test form submissions and validations
- Check responsive design on different devices
- Validate security measures

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `config/database.php`
   - Verify database server is running
   - Ensure database exists and user has permissions

2. **Session Issues**
   - Check PHP session configuration
   - Verify directory permissions for session storage
   - Ensure cookies are enabled in browser

3. **CSS/JS Not Loading**
   - Verify asset files exist in correct directories
   - Check web server configuration for static files
   - Ensure proper file permissions

4. **Login Not Working**
   - Check if `login_attempts` table exists
   - Verify password hashing is working
   - Check session configuration

### Error Logging
- Application errors are logged in `logs/` directory
- Check PHP error logs for server-level issues
- Monitor database query logs if needed

## Production Deployment

### Environment Variables
For production, consider using environment variables:

```php
// In config/database.php
const DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
const DB_USER = $_ENV['DB_USER'] ?? 'root';
const DB_PASS = $_ENV['DB_PASS'] ?? '';
const DB_NAME = $_ENV['DB_NAME'] ?? 'school_management';
```

### Performance Optimization
- Enable PHP OPcache
- Use Redis for session storage
- Implement database query caching
- Optimize images and assets
- Use CDN for static assets

### Backup Strategy
- Regular database backups
- File system backups
- Configuration backups
- Disaster recovery plan

## Support

For technical support or questions:
1. Check this documentation first
2. Review error logs
3. Verify all installation steps
4. Test with minimal configuration

## License

This project is provided as-is for educational and development purposes.


Working Login Credentials:
Admin: username=admin, password=password
Teacher: username=teacher1, password=teacher
Student: username=student1, password=student
Accountant: username=accountant1, password=accountant


Done! The server is now accessible from your local network.

Access URLs:

http://localhost/windsurf-project/login.php