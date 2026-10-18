# CardioRisk - Medical Decision Support System

## Overview

CardioRisk is a professional, high-level cardiovascular risk assessment platform designed for healthcare professionals. The system uses historical medical data to calculate patient risk scores, visualize deviations from healthy baselines, and provide AI-generated clinical recommendations.

## Features

### Core Functionality
- **Patient Management**: Register and manage patient records with demographics
- **Visit Tracking**: Record medical examination data with 13 cardiac parameters
- **Risk Calculation**: Weighted algorithm analyzing multiple cardiac indicators
- **Comparative Analysis**: Compare patient metrics against healthy reference data
- **AI Recommendations**: Rule-based expert system generating prioritized clinical advice
- **PDF Reports**: Professional medical reports with full visit data and recommendations

### Advanced UI/UX
- **Interactive Data Entry**: Range sliders with real-time value display
- **Medical Tooltips**: Explanatory help text for all medical terms
- **Radar Charts**: Visual comparison of patient vs. healthy averages
- **Risk Gauge**: Semi-circular gauge with color-coded risk levels
- **Body Heatmap**: SVG-based symptom localization visualization
- **What-If Simulator**: Interactive scenario analysis with AJAX recalculation

### Technical Highlights
- **Native PHP OOP**: Clean, MVC-like architecture without frameworks
- **PDO Database**: Secure prepared statements preventing SQL injection
- **Bootstrap 5**: Modern, responsive medical-themed design
- **Chart.js**: Interactive data visualizations
- **SweetAlert2**: Professional notification dialogs
- **FPDF**: PDF report generation

## Tech Stack

- **Backend**: PHP 7.4+ (Object-Oriented Programming)
- **Database**: MySQL 5.7+ / MariaDB
- **Frontend**: HTML5, Bootstrap 5.3, Custom CSS
- **JavaScript**: jQuery 3.7, Chart.js 4.4, SweetAlert2
- **PDF**: FPDF library

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB
- Apache/Nginx web server
- Web browser (Chrome, Firefox, Edge)

### Step 1: Database Setup

1. Create a new MySQL database:
```sql
CREATE DATABASE cardiorisk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema:
```bash
mysql -u root -p cardiorisk < cardiorisk.sql
```

This will create:
- 4 tables (users, patients, visits, reference_data)
- Sample user accounts
- 15 reference data rows for immediate chart functionality
- 5 sample patients with visits

### Step 2: Configure Database Connection

Edit `config/db.php` and update with your credentials:

```php
private $host = 'localhost';
private $db_name = 'cardiorisk';
private $username = 'root';      // Your MySQL username
private $password = '';          // Your MySQL password
```

### Step 3: Install FPDF (for PDF reports)

1. Download FPDF from: http://www.fpdf.org/
2. Extract and copy `fpdf.php` to: `vendor/fpdf/fpdf.php`

### Step 4: Web Server Configuration

**Option A: Using PHP Built-in Server (Development)**
```bash
cd CardioRisk
php -S localhost:8000
```

**Option B: Using Apache**
- Place the `CardioRisk` folder in your web server root (e.g., `htdocs`, `www`, `public_html`)
- Access via: `http://localhost/CardioRisk`

## Default Login Credentials

After importing the database, use these credentials to log in:

**Doctor Account:**
- Email: `dr.mehmet@cardiorisk.com`
- Password: `password123`

**Alternative Doctor:**
- Email: `dr.ayse@cardiorisk.com`
- Password: `password123`

## Project Structure

```
CardioRisk/
│
├── assets/
│   ├── css/
│   │   └── style.css              # Medical-themed custom styles
│   └── js/
│       ├── main.js                # Core JavaScript functionality
│       └── charts.js              # Chart.js visualizations
│
├── config/
│   ├── db.php                     # Database connection (PDO)
│   └── functions.php              # Global helper functions
│
├── core/                          # Business Logic Classes
│   ├── RiskCalculator.php         # Risk assessment algorithm
│   ├── Stats.php                  # Statistical analysis
│   └── Recommendation.php         # Expert system
│
├── includes/                      # Reusable UI Components
│   ├── header.php                 # Navigation bar
│   ├── sidebar.php                # Dashboard menu
│   └── footer.php                 # Footer
│
├── modules/                       # Application Pages
│   ├── login.php                  # Authentication
│   ├── dashboard.php              # Overview statistics
│   ├── patients.php               # Patient listing
│   ├── add_patient.php            # Patient registration
│   ├── new_visit.php              # Medical data entry form
│   ├── patient_detail.php         # Analytics dashboard ⭐
│   └── report.php                 # PDF generation
│
├── vendor/
│   └── fpdf/
│       └── fpdf.php               # PDF library (download separately)
│
├── cardiorisk.sql                 # Database schema + data
├── index.php                      # Application router
└── logout.php                     # Session termination
```

## Key Features Guide

### 1. Patient Registration
Navigate to **Add Patient** to register new patients with:
- TC Identity Number (Turkish ID format)
- Full name, birth date, gender
- Contact phone number

### 2. New Visit Entry
The **New Visit** page features:
- **Card-based layout** grouping related parameters
- **Range sliders** for vital signs (BP, cholesterol, heart rate)
- **Tooltips** explaining each medical term
- **Automatic risk calculation** on submission

Cardiac parameters collected:
- Chest pain type, blood pressure, cholesterol
- Fasting blood sugar, ECG results
- Maximum heart rate, exercise tolerance
- ST depression, vessel blockage indicators

### 3. Patient Analytics Dashboard
The **Patient Detail** page is the centerpiece featuring:

**Risk Gauge**
- Semi-circular visualization
- Color-coded (Green/Orange/Red)
- Displays 0-100 risk score

**Comparative Radar Chart**
- 5-axis comparison
- Patient values vs. healthy averages
- Interactive Chart.js visualization

**Body Heatmap**
- SVG-based human body outline
- Highlights symptomatic areas
- Animated indicators for active symptoms

**What-If Simulator**
- Adjust cholesterol, BP, heart rate
- Real-time AJAX risk recalculation
- Shows projected improvement/deterioration

**AI Recommendations**
- Priority-based clinical advice
- Categorized by urgency (Critical/High/Moderate/Routine)
- Based on rule-based expert system

### 4. PDF Reports
Generate professional medical reports including:
- Patient demographics
- All measured parameters
- Risk assessment summary
- Clinical recommendations
- Disclaimer and signature area

## Security Features

- **Password Hashing**: Bcrypt with `password_hash()`
- **SQL Injection Prevention**: PDO prepared statements
- **XSS Protection**: Input sanitization with `htmlspecialchars()`
- **Session Management**: Secure authentication checks
- **Role-Based Access**: Doctor/patient role separation

## Color Palette (Medical Theme)

- **Primary**: `#0891B2` (Teal Medical Blue)
- **Success/Low Risk**: `#10B981` (Green)
- **Warning/Moderate**: `#F59E0B` (Orange)
- **Danger/High Risk**: `#EF4444` (Red)
- **Background**: `#F8FAFC` (Light Grey)
- **Text**: `#1E293B` (Dark Slate)

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Troubleshooting

**Database Connection Errors**
- Verify MySQL service is running
- Check credentials in `config/db.php`
- Ensure database exists and schema is imported

**Charts Not Displaying**
- Check browser console for JavaScript errors
- Verify Chart.js CDN is accessible
- Ensure patient data exists in `#patientData` div

**PDF Generation Fails**
- Confirm FPDF is installed in `vendor/fpdf/fpdf.php`
- Check PHP error logs for detailed messages

**Sliders Not Updating Values**
- Verify jQuery is loaded before `main.js`
- Check browser console for errors
- Ensure slider IDs match JavaScript selectors

## Future Enhancements

- Multi-language support (Turkish/English)
- Advanced statistical analysis and trend tracking
- Integration with medical device APIs
- Machine learning risk prediction models
- Mobile app companion
- DICOM image viewer integration
- HL7 FHIR compliance

## License

This project is created for educational purposes. For production medical use, ensure compliance with:
- HIPAA (Health Insurance Portability and Accountability Act)
- GDPR (General Data Protection Regulation)
- Local healthcare data protection regulations

## Support

For issues or questions:
1. Check this README
2. Review code comments
3. Inspect browser console for errors
4. Check PHP error logs

## Credits

**Developed By**: Senior Full Stack Web Developer & UI/UX Designer
**Medical Design Inspiration**: Modern HealthTech SaaS platforms
**Dataset Reference**: Heart Disease Prediction (Kaggle-style dataset)

---

**⚠️ Medical Disclaimer**: This system is for demonstration and educational purposes only. It should not be used as the sole basis for clinical decisions. Always consult qualified medical professionals for patient care.
