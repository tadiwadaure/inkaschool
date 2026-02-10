# 🎓 School Management System with Timetable Management

A comprehensive school management system featuring advanced timetable management with role-based access for administrators, teachers, and students.

## ✨ Key Features

### 🛡️ Admin Capabilities
- **Complete CRUD Operations** for timetable management
- **User Management** (Students, Teachers, Classes, Subjects)
- **Automatic Synchronization** - Changes instantly update all user schedules
- **Dashboard Overview** with weekly schedule statistics
- **Exam & Notification Management**

### 🎓 Teacher Features  
- **Personal Timetable** showing only assigned classes
- **Profile Integration** with embedded weekly schedule
- **Results & Exam Management**
- **Quick Access** via dashboard "My Timetable" button

### 👨‍🎓 Student Benefits
- **Personalized Schedule** showing only enrolled subjects
- **Automatic Updates** when admin modifies teaching timetable
- **Clean Interface** with today's classes highlighted
- **Subject Enrollment** automatically managed

## 🚀 Quick Start

### Windows Users
```bash
# Double-click this file or run in command prompt
start.bat
```

### Manual Setup
```bash
# Start PHP development server
php -S localhost:8000

# First time? Visit:
http://localhost:8000/install.php

# Already installed? Go to:
http://localhost:8000
```

## 🔐 Default Login

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | password |
| Teacher | teacher1 | password |
| Student | student1 | password |

## 📱 Access Points

- **Main Application**: http://localhost:8000
- **Admin Dashboard**: http://localhost:8000/admin/dashboard.php
- **Teacher Timetable**: http://localhost:8000/teacher/timetable.php
- **Student Timetable**: http://localhost:8000/student/timetable.php

## 🔄 Magic Synchronization

When an admin creates/modifies a schedule:
1. ✅ All students in that class get updated automatically
2. ✅ Subject enrollment is created/updated automatically  
3. ✅ Teacher schedules reflect changes immediately
4. ✅ Dashboard statistics update in real-time

## 📋 System Requirements

- PHP 8.0+
- MySQL/MariaDB
- Modern web browser
- 2GB+ RAM recommended

## 📁 Project Structure

```
├── admin/           # Admin panel (CRUD, management)
├── teacher/         # Teacher portal (schedule, results)
├── student/         # Student portal (personal timetable)
├── config/          # Database configuration
├── includes/        # Authentication & shared components
└── assets/          # CSS, JS, images
```

## 🎯 Quick Test Checklist

After starting:
- [ ] Login as admin → Create a timetable entry
- [ ] Login as teacher → See your schedule
- [ ] Login as student → See only your classes
- [ ] Verify synchronization works automatically

## 📚 Documentation

- **Complete Setup Guide**: [SETUP.md](SETUP.md)
- **Installation Wizard**: `install.php` (first time only)
- **Quick Start**: `start.bat` (Windows)

## 🛠️ Troubleshooting

**"Not Found" errors?**
- Ensure PHP server is running
- Check all files are present

**Database issues?**
- Run the installation wizard: `install.php`
- Verify MySQL credentials in `config/database.php`

**Blank pages?**
- Check PHP error logs
- Ensure file permissions are correct

---

## 🎉 Ready to Go!

Your School Management System with advanced timetable management is ready to use!

**Start the system** by running `start.bat` or `php -S localhost:8000`

*Built with ❤️ for educational institutions*
