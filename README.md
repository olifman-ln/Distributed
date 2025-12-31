# 🚦 TrafficSense - Distributed Traffic Monitoring System

![Project Status](https://img.shields.io/badge/Status-Active-brightgreen)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![Database](https://img.shields.io/badge/MySQL-MariaDB-orange)
![License](https://img.shields.io/badge/License-MIT-lightgrey)

**TrafficSense** is an advanced, distributed web-based platform designed to revolutionize urban traffic management. By integrating real-time surveillance, automated incident reporting, and a transparent citizen portal, it bridges the gap between traffic authorities and vehicle owners.

---

## 📖 Table of Contents
- [Project Overview](#-project-overview)
- [System Architecture](#-system-architecture)
- [Key Features](#-key-features)
  - [Admin Panel](#admin-panel-authorities)
  - [Owner Portal](#owner-portal-citizens)
- [Technology Stack](#-technology-stack)
- [Installation & Setup](#-installation--setup)
- [Directory Structure](#-directory-structure)
- [Future Roadmap](#-future-roadmap)

---

## 🔭 Project Overview
Traffic congestion and enforcement are critical challenges in modern cities. **TrafficSense** provides a centralized solution that:
1.  **Monitors** traffic flow through a distributed network of nodes and cameras.
2.  **Detects & Reports** incidents (accidents, violations) in real-time.
3.  **Automates** fine calculation and notification delivery.
4.  **Empowers** citizens with a dedicated portal to view and pay fines transparently.

---

## 🏗 System Architecture
The system operates on a **Client-Server** model with a distributed data collection layer.

1.  **Central Server**: Hosts the PHP application and MySQL database.
2.  **Surveillance Nodes**: IoT-enabled points (intersections, toll gates) containing cameras.
3.  **Client Layers**:
    *   **Admin Dashboard**: secure web interface for officials.
    *   **Public Portal**: responsive web interface for vehicle owners.

---

## 🌟 Key Features

### Admin Panel (Authorities)
*   **📊 Live Dashboard**: Real-time analytics on traffic density, revenue, and active alerts.
*   **📹 Surveillance Network**:
    *   Manage `Nodes` (locations) and `Cameras`.
    *   Simulated live feed status and connectivity monitoring.
*   **📝 Incident Management**:
    *   **Accident Logging**: Detailed reporting with severity classification (Low/Moderate/Critical).
    *   **Violation Ticketing**: issue citations for speeding, red lights, etc.
*   **🚨 Watchlist System**: Real-time alerts for stolen or wanted vehicles entering the network.
*   **📈 Advanced Reporting**: Exportable data summaries for decision-making.

### Owner Portal (Citizens)
*   **👤 Personal Dashboard**: Overview of vehicle status, total fines, and recent activities.
*   **📜 Violation History**: Full transparency with evidence photos and timestamps.
*   **💳 Integrated Payments**: Secure gateway to settle fines for violations and accident damages.
*   **🔔 Smart Notifications**: Instant alerts for new fines or status changes.

---

## 💻 Technology Stack

### Backend
*   **Language**: PHP 7.4+ (Procedural/Object-Oriented mix)
*   **Database**: MySQL / MariaDB (Relational structure)
*   **Server**: Apache (via XAMPP/WAMP)

### Frontend
*   **Structure**: HTML5, Semantic Markup
*   **Styling**: Modern CSS3 (Grid/Flexbox), Custom Variables, Responsive Design
*   **Scripting**: Vanilla JavaScript (ES6+) for DOM manipulation and AJAX
*   **Icons**: FontAwesome 6

---

## 🚀 Installation & Setup

1.  **Environment Preparation**:
    *   Install [XAMPP](https://www.apachefriends.org/) or a similar LAMP stack.
    *   Ensure Apache and MySQL services are running.

2.  **Code Deployment**:
    *   Clone or extract the project into your web root directory (e.g., `C:\xampp\htdocs\DBS`).

3.  **Database Configuration**:
    *   Open `phpMyAdmin` (usually `http://localhost/phpmyadmin`).
    *   Create a new database named `traffic_db`.
    *   Import the provided SQL schema file (e.g., `traffic_db.sql` or from the `database/` folder).
    *   *Note: If the SQL file is missing, you may need to run migration scripts provided in the package.*

4.  **Connection Setup**:
    *   Open `db.php` in a text editor.
    *   Update credentials if necessary:
        ```php
        $host = 'localhost';
        $dbname = 'traffic_db';
        $username = 'root';
        $password = ''; // Default XAMPP password is empty
        ```

5.  **Launch**:
    *   Visit `http://localhost/DBS` in your browser.
    *   **Admin Login**: Default credentials (check database or register a new admin if enabled).
    *   **Owner Login**: Register a new account to test the citizen portal.

---

## 📂 Directory Structure

```text
/DBS
├── assets/             # Static assets (images, icons)
├── auth/               # Authentication logic (Login, Signup, Forgot Password)
├── css/                # Global and page-specific stylesheets
├── js/                 # Client-side logic and form handling
├── owners/             # Owner-facing portal (Dashboard, Payments)
├── uploads/            # Encrypted storage for profile/vehicle images
├── accidents.php       # Accident reporting module
├── cameras.php         # Camera network management
├── db.php              # Database connection singleton
├── index.php           # Admin Dashboard (Entry point)
├── nodes.php           # Node configuration
├── traffic_data.php    # Analytics and reporting
└── violations.php      # Violation ticketing system
```

---

## 🔮 Future Roadmap
*   **AI Integration**: Automatic License Plate Recognition (ALPR) using ML models.
*   **IoT Sensors**: Integration with physical speed, weather, and density sensors.
*   **Mobile App**: Native Android/iOS application for push notifications.
*   **Cloud Migration**: containerization (Docker) and deployment to AWS/Azure.

---
*© 2025 TrafficSense Team. All Rights Reserved.*
