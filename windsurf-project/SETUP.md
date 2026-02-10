# School Management System - Timetable Management Setup Guide

## 📋 Overview

This is a comprehensive School Management System with advanced Timetable Management capabilities. The system includes role-based access for Admin, Teachers, and Students with synchronized scheduling features.

## 🚀 Quick Start

### Prerequisites
- PHP 8.0 or higher
- MySQL/MariaDB database
- Web server (Apache/NginX) or PHP built-in server
- Modern web browser

### 1. Database Setup

#### Create Database
```sql
CREATE DATABASE school_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Import Database Schema
```bash
mysql -u root -p school_management < database_schema.sql
```

#### Create Timetable Tables
```bash
mysql -u root -p school_management < create_timetable_tables.sql
```

### 2. Configuration

#### Database Configuration
Edit `config/database.php`:
```php
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = ''; // Your database password
const DB_NAME = 'school_management';
```

### 3. Start the Application

#### Option A: PHP Built-in Server (Development)
```bash
php -S localhost:8000
```

#### Option B: Apache/NginX (Production)
- Point document root to project directory
- Ensure `.htaccess` is enabled (Apache)

### 4. Access the System

Open your browser and navigate to: `http://localhost:8000`

## 🔐 Default Login Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | password |
| Teacher | teacher1 | password |
| Student | student1 | password |

## 📚 Complete Feature Guide

### 🛡️ Admin Features

#### Dashboard Access
- URL: `/admin/dashboard.php`
- Statistics overview
- Weekly schedule overview
- Quick actions for management

#### Timetable Management (CRUD)
- URL: `/admin/timetable.php`
- **Create**: Add new schedule entries
- **Read**: View all teaching schedules
- **Update**: Edit existing entries
- **Delete**: Remove schedule entries
- **Auto-sync**: Automatically updates student timetables

#### User Management
- **Students**: `/admin/students.php`
- **Teachers**: `/admin/teachers.php`
- **Classes**: `/admin/classes.php`
- **Subjects**: `/admin/subjects.php`

#### Additional Admin Features
- Exam Management: `/admin/exams.php`
- Notifications: `/admin/notifications.php`
- Reports: `/admin/reports.php`

### 🎓 Teacher Features

#### Dashboard
- URL: `/teacher/dashboard.php`
- Personal statistics
- Quick actions including "My Timetable" button
- Assigned subjects overview

#### Personal Timetable
- URL: `/teacher/timetable.php`
- Weekly schedule grid view
- Today's classes highlighted
- Detailed schedule list
- Subject and room information

#### Profile Integration
- URL: `/teacher/profile.php`
- Complete weekly schedule embedded
- Today's classes section
- Statistics summary

#### Other Teacher Features
- Results Management: `/teacher/results.php`
- Exam Creation: `/teacher/exams.php`
- View Results: `/teacher/view_results.php`

### 👨‍🎓 Student Features

#### Dashboard
- URL: `/student/dashboard.php`
- Personal information
- Quick access to timetable

#### Personal Timetable
- URL: `/student/timetable.php`
- Only shows enrolled subjects
- Weekly schedule grid
- Today's classes
- Subject enrollment list
- Automatic sync with teaching timetable

## 🔄 Synchronization System

### How It Works
1. **Admin creates/updates** a teaching timetable entry
2. **System automatically**:
   - Updates all students in the assigned class
   - Creates student timetable entries
   - Auto-enrolls students in the subject
   - Updates teacher schedules

### Database Tables
- `teaching_timetable` - Master schedule table
- `student_timetable` - Student-specific entries
- `student_subject_enrollment` - Subject enrollment tracking

## 🎨 UI/UX Features

### Design Elements
- **Bootstrap 5** for responsive design
- **Font Awesome** icons for visual clarity
- **Color-coded schedules** for easy identification
- **Card-based layouts** for modern appearance

### Responsive Design
- Mobile-friendly interface
- Tablet-optimized views
- Desktop full-featured experience

## 📁 Project Structure

```
windsurf-project/
├── admin/                  # Admin panel
│   ├── dashboard.php       # Admin dashboard
│   ├── timetable.php       # Timetable CRUD
│   ├── students.php        # Student management
│   ├── teachers.php        # Teacher management
│   ├── classes.php         # Class management
│   ├── subjects.php        # Subject management
│   ├── exams.php           # Exam management
│   ├── notifications.php   # Notification system
│   └── reports.php         # Report generation
├── teacher/                # Teacher panel
│   ├── dashboard.php       # Teacher dashboard
│   ├── timetable.php       # Teacher schedule
│   ├── profile.php         # Teacher profile
│   ├── results.php         # Results management
│   ├── exams.php           # Exam creation
│   └── view_results.php    # Results viewing
├── student/                # Student panel
│   ├── dashboard.php       # Student dashboard
│   └── timetable.php       # Student schedule
├── config/                 # Configuration
│   └── database.php        # Database connection
├── includes/               # Shared components
│   ├── auth.php            # Authentication system
│   ├── header.php          # Page header
│   ├── footer.php          # Page footer
│   └── notifications.php   # Notification functions
├── assets/                 # Static assets
│   └── css/                # Stylesheets
├── database_schema.sql     # Main database schema
├── create_timetable_tables.sql # Timetable tables
└── index.php               # Application entry point
```

## 🔧 Technical Details

### Security Features
- **CSRF Protection** on all forms
- **Password Hashing** with bcrypt
- **SQL Injection Prevention** with prepared statements
- **Session Security** with secure configurations
- **Role-based Access Control**

### Database Relationships
- Users → Teachers/Students (One-to-One)
- Classes → Students (One-to-Many)
- Subjects → Classes (Many-to-Many)
- Teaching Timetable → Classes/Subjects/Teachers
- Student Timetable → Teaching Timetable (Sync)

### API Endpoints
All pages are server-rendered with PHP. No separate API is required.

## 🚀 Deployment Guide

### Development Environment
```bash
# Clone/Download project
cd windsurf-project

# Setup database
mysql -u root -p < database_schema.sql
mysql -u root -p < create_timetable_tables.sql

# Start development server
php -S localhost:8000
```

### Production Environment (Apache)
1. Configure virtual host to point to project directory
2. Enable `.htaccess` and mod_rewrite
3. Set proper file permissions
4. Configure database credentials
5. Run database migrations

### Production Environment (NginX)
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
}
```

## 🐛 Troubleshooting

### Common Issues

#### "Not Found" Errors
- Ensure PHP server is running with router
- Check file permissions
- Verify URL paths in navigation

#### Database Connection Errors
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database exists and schema is imported

#### Blank Pages
- Check PHP error logs
- Ensure all required files exist
- Verify file permissions

#### Session Issues
- Clear browser cookies
- Check session storage permissions
- Verify session configuration

### Debug Mode
Enable error reporting in `config/database.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

## 📞 Support

For issues and questions:
1. Check this setup guide
2. Review error logs
3. Verify database connections
4. Test with default credentials

## 🔄 Updates and Maintenance

### Regular Tasks
- Database backups
- User account management
- Schedule updates (via admin panel)
- System monitoring

### Backup Commands
```bash
# Database backup
mysqldump -u root -p school_management > backup.sql

# File backup
tar -czf project_backup.tar.gz windsurf-project/
```

---

## 🎯 Quick Test Checklist

After setup, verify these work:

- [ ] Admin login: `admin` / `password`
- [ ] Teacher login: `teacher1` / `password`
- [ ] Student login: `student1` / `password`
- [ ] Admin can create timetable entries
- [ ] Teacher sees their schedule
- [ ] Student sees only their classes
- [ ] Synchronization works automatically
- [ ] All navigation links work
- [ ] Responsive design on mobile

**Your School Management System with Timetable Management is now ready!** 🎉
