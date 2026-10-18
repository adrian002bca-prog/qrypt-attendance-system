# 🏆 QRYPT: QR Code Attendance & Visitor Management System

[![Best Capstone Project](https://img.shields.io/badge/Award-Best%20Capstone%20Project-gold?style=for-the-badge&logo=trophy)](https://qrypt.great-site.net)
[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org)
[![Live Demo](https://img.shields.io/badge/Demo-Live%20System-success?style=for-the-badge&logo=google-chrome&logoColor=white)](https://qrypt.great-site.net)

> **Awarded Best Capstone Project** (May 2026) at **Trent Information First Technical Career Institute** by Adrian Justin J. San Diego (BS Information Systems).

---

## 🌐 Live Production Demo
* **Live System URL:** [https://qrypt.great-site.net](https://qrypt.great-site.net)
* **Kiosk Scanner (Student & Visitor):** [https://qrypt.great-site.net/user.php](https://qrypt.great-site.net/user.php)
* **Admin Portal:** [https://qrypt.great-site.net/login.php](https://qrypt.great-site.net/login.php)
  * *Demo Username:* `Qrypt`
  * *Demo Password:* `password`

---

## 📌 Project Overview
**QRYPT** is an automated attendance and visitor entry tracking system built to streamline high-volume check-ins across academic campuses and institutional facilities. 

Traditional paper logbooks and manual sign-ins are prone to long queues, lost records, proxy attendance, and unreliable reporting. QRYPT replaces manual logging with instant camera-based QR code recognition, automated Time-In / Time-Out evaluation, grace period enforcement, and centralized administrative analytics.

---

## ✨ Key Features

### 🎓 1. Student Attendance Management
* **Unique QR Code Generation:** Automatically generates individual QR identifiers mapped to student numbers, departments, and sections.
* **Intelligent In/Out Detection:** Single-scan state logic automatically logs **Time In** if no open session exists for the day, and closes the session with **Time Out** upon exit.
* **Configurable Schedule Rules:** Admin-definable class start times, end times, late arrival grace periods, and early departure allowances.
* **Rapid Duplicate Scan Suppression:** Prevents accidental double-scanning with customizable cooldown timers.

### 👥 2. Single-Use Visitor Tracking
* **Premade QR Batches:** Instant visitor issuance without requiring on-the-spot registration queues.
* **Destination Tracking:** Tracks destination office/classroom, host personnel, and visit purpose.
* **Single-Visit Lifecycle:** Visitor QR codes automatically transition from *Active* $\rightarrow$ *Inside* $\rightarrow$ *Archived/Expired* upon check-out to eliminate unauthorized reuse.
* **Mobile Destination Portal:** Responsive companion interface for mobile security checkpoints.

### 📊 3. Administrative Control & Analytics
* **Live Operations Dashboard:** Real-time counters for students inside, total check-ins today, visitors currently on premises, and historical trends.
* **Granular Masterlists:** Student and visitor registries with filtering, search, and batch CSV export.
* **Audit Trail & Event Logs:** Every administrative modification, check-in override, or security event is permanently timestamped with actor IP tracking.
* **Database Backup & Maintenance:** Built-in SQL export and system restore capabilities.

---

## 🛠️ Technology Stack
* **Backend:** PHP 8+ (PDO, prepared statements, strict parameter binding)
* **Database:** MySQL / MariaDB (relational schemas, indexed keys, transaction-safe queries)
* **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 4, FontAwesome
* **Hardware Interfacing:** HTML5 WebRTC `navigator.mediaDevices` camera API, JSQR decoder
* **Security:** CSRF token validation, password hashing (`bcrypt`), brute-force login rate limiting, session management, `.htaccess` protected endpoints

---

## 📂 System Architecture & Directory Structure
```text
qr-code-attendance-system/
├── conn/                    # Core bootstrap & database handlers
│   ├── bootstrap.php        # Session, auth, rate limiting, timezone
│   ├── conn.php             # PDO MySQL connection & fallback logic
│   ├── db.config.php        # External hosting credentials
│   ├── student_table.php    # Student schema & CRUD logic
│   ├── visitor_table.php    # Visitor schema & lifecycle
│   └── attendance_tables.php# Attendance logs & cooldown rules
├── endpoint/                # Asynchronous API handlers
│   ├── add-attendance.php   # Camera scan processor (Student/Visitor)
│   ├── add-student.php      # Student registration endpoint
│   ├── add-visitor.php      # Visitor registration endpoint
│   └── get-student-by-qr.php# QR lookup & validation
├── img/                     # Brand logos & static UI graphics
├── uploads/                 # Photo captures and visitor assets
├── index.php                # Admin dashboard & analytics
├── login.php                # Secure administrative authentication
├── masterlist.php           # Student directory & management
├── visitorlist.php          # Visitor logs & active badges
├── schedules.php            # Department time rules & schedules
├── reports.php              # Attendance reports & export tools
└── user.php                 # Front-facing camera kiosk scanner
```

---

## 🚀 Local Installation (XAMPP / LAMP)

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/adrian002bca-prog/qrypt-attendance-system.git
   ```
2. **Move to Web Directory:**
   * Copy the folder into your web server directory (e.g. `C:\xampp\htdocs\qr-code-attendance-system`).
3. **Database Setup:**
   * Start Apache and MySQL in XAMPP.
   * Open `http://localhost/phpmyadmin`.
   * Create a database named `qr_attendance_db`.
   * Import the included `qr_attendance_db (1).sql` file.
4. **Run the Application:**
   * Open `http://localhost/qr-code-attendance-system/user.php` in your browser.
   * For Admin access, open `http://localhost/qr-code-attendance-system/login.php` (User: `Qrypt`, Pass: `password`).

---

## 👨‍💻 Author
**Adrian Justin J. San Diego**  
*BS Information Systems Graduate (May 2026)*  
*Best Capstone Project Awardee — Trent Information First Technical Career Institute*  
*TESDA NC II Certified in Computer Systems Servicing*  

* **LinkedIn:** [linkedin.com/in/adrian-justin-san-diego](https://linkedin.com/in/adrian-justin-san-diego)
* **Email:** [adrian002bca@gmail.com](mailto:adrian002bca@gmail.com)
* **Live Project:** [https://qrypt.great-site.net](https://qrypt.great-site.net)
