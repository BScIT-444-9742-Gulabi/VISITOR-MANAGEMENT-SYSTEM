# Visitor Management System (VMS)

A comprehensive web-based visitor management system for colleges and organizations using HTML, CSS, JavaScript, PHP & MySQL.

## Features

- **Online Visitor Registration**: Visitors can register from home with detailed information
- **Admin Approval System**: Admins can approve/reject visitor requests with email notifications
- **QR Code Generation**: Automatic QR code generation for approved visits
- **Email Notifications**: Automated emails with QR codes sent to visitors
- **Gate Scanner Interface**: QR code scanning for check-in/check-out at the gate
- **Manual Entry Option**: Security staff can manually register visitors at the gate
- **Real-time Dashboard**: Live monitoring of visitor statistics and activities
- **Activity Logging**: Complete audit trail of all visitor activities
- **Security Features**: QR code expiry, validation, and user authentication

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Email**: PHPMailer
- **Security**: Password hashing, session management, input validation

## Installation

### Prerequisites

- XAMPP/WAMP/MAMP or similar PHP-MySQL environment
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for PHPMailer)

### Setup Instructions

1. **Clone/Download the Project**
   ```bash
   cd /xampp/htdocs
   git clone <repository-url> vms
   # or extract the ZIP file to htdocs/vms
   ```

2. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the `database.sql` file to create the database and tables
   - Or run the SQL commands manually

3. **Install PHPMailer**
   ```bash
   cd c:/xampp/htdocs/vms
   composer require phpmailer/phpmailer
   ```

4. **Configure Email Settings**
   - Edit `config/config.php` with your email credentials
   - Update SMTP settings and Gmail app password if needed

5. **Set Permissions**
   - Ensure the web server can write to necessary directories

6. **Access the Application**
   - Registration Form: http://localhost/vms/
   - Admin Login: http://localhost/vms/admin/login.php
   - Gate Scanner: http://localhost/vms/gate/scanner.php

## Default Credentials

### Admin Login
- **Username**: admin
- **Password**: admin123

### Security Staff Login
- **Username**: security
- **Password**: security123

## System Workflow

1. **Visitor Registration**
   - Visitor fills out the registration form online
   - System sends notification email to admin
   - Visit status: "Pending"

2. **Admin Approval**
   - Admin reviews visitor requests in dashboard
   - Approves or rejects the visit
   - If approved: QR code generated and emailed to visitor
   - Visit status: "Approved"

3. **Gate Entry**
   - Visitor arrives at gate with QR code
   - Security staff scans QR code using scanner interface
   - System validates QR code and checks expiry
   - Visitor checked in successfully
   - Visit status: "Checked In"

4. **Manual Entry (Optional)**
   - For visitors without QR codes
   - Security staff can manually register and check-in visitors
   - System generates record and tracks entry

5. **Gate Exit**
   - Visitor scans QR code again for check-out
   - System records departure time
   - Visit status: "Checked Out"

## Database Schema

### Main Tables

- **users**: Admin and security staff accounts
- **visitors**: Visitor information and contact details
- **visits**: Visit records with status, QR codes, and timestamps
- **activity_logs**: Complete audit trail of all activities

### Relationships

- One visitor can have multiple visits
- Each visit is linked to exactly one visitor
- Activities are logged with user, visitor, and visit references

## API Endpoints

### Registration & Approval
- `POST /api/register.php` - Register new visitor
- `POST /api/approve.php` - Approve/reject visit

### Check-in/Check-out
- `POST /api/checkin.php` - Check-in visitor
- `POST /api/checkout.php` - Check-out visitor

### Utilities
- `GET /api/generate_qr.php?code=XYZ` - Generate QR code image

## Security Features

- **Password Hashing**: All passwords are securely hashed using PHP's password_hash()
- **Session Management**: Secure session handling with timeout
- **Input Validation**: All user inputs are sanitized and validated
- **SQL Injection Protection**: Prepared statements used throughout
- **QR Code Expiry**: QR codes automatically expire after 24 hours
- **Access Control**: Role-based access control (Admin/Security)

## Email Configuration

The system uses PHPMailer for sending emails. Configure in `config/config.php`:

```php
// Email Settings (PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_USERNAME', 'your-email@gmail.com');
define('EMAIL_PASSWORD', 'your-app-password');
```

**Important**: For Gmail, use an App Password instead of your regular password:
1. Enable 2-factor authentication
2. Generate an App Password
3. Use the App Password in the configuration

## File Structure

```
vms/
|-- admin/                  # Admin interface
|   |-- login.php          # Admin login page
|   |-- dashboard.php      # Main admin dashboard
|   |-- approve.php        # Visit approval page
|   |-- logout.php         # Logout handler
|-- api/                   # REST API endpoints
|   |-- register.php       # Visitor registration API
|   |-- approve.php        # Visit approval API
|   |-- checkin.php        # Check-in API
|   |-- checkout.php       # Check-out API
|   |-- generate_qr.php    # QR code generation
|-- assets/                # Static assets
|   |-- css/              # Stylesheets
|   |-- js/               # JavaScript files
|   |-- images/           # Image files
|-- config/                # Configuration files
|   |-- database.php      # Database connection
|   |-- config.php        # Application config
|-- gate/                  # Gate interface
|   |-- scanner.php       # QR code scanner
|   |-- manual_entry.php  # Manual entry form
|-- includes/              # Helper functions
|   |-- functions.php     # Utility functions
|   |-- Database.php      # Database class
|-- index.php             # Visitor registration form
|-- database.sql          # Database schema
|-- README.md             # This file
```

## Customization

### Adding New Fields
1. Update the database schema in `database.sql`
2. Modify the registration form in `index.php`
3. Update the API endpoints in `/api/`
4. Adjust the admin dashboard as needed

### Changing QR Code Expiry
Edit `config/config.php`:
```php
define('QR_CODE_EXPIRY_HOURS', 24); // Change to desired hours
```

### Customizing Email Templates
Modify the email content in `includes/functions.php` in the `sendQREmail()` and `sendAdminNotification()` functions.

## Troubleshooting

### Common Issues

1. **Email Not Sending**
   - Check SMTP credentials in config.php
   - Ensure Gmail App Password is used (not regular password)
   - Verify SMTP settings and port

2. **Database Connection Error**
   - Check MySQL server is running
   - Verify database credentials in config/database.php
   - Ensure database exists and user has permissions

3. **QR Code Not Working**
   - Check if QR code has expired
   - Verify QR code format in database
   - Ensure QR code generation script is accessible

4. **Login Issues**
   - Verify user exists in database
   - Check password hashing
   - Ensure session is properly configured

### Debug Mode
Enable error reporting in `config/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Support

For support and questions:
1. Check the troubleshooting section above
2. Review the error logs in your web server
3. Verify all configuration settings
4. Ensure all prerequisites are met

## License

This project is open-source and available under the MIT License.

---

**Note**: This system is designed for educational and demonstration purposes. For production use, additional security measures and testing are recommended.
# VISITOR-MANAGEMENT-SYSTEM
