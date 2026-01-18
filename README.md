# ExamFlow

<p align="center">
  <img src="img/logo.png" alt="ExamFlow Logo" width="200">
</p>

<p align="center">
  <b>The Ultimate Multi-School Assessment Platform with Blockchain-Verified Credentials, AI-Powered Grading & Teaching Slot Management</b>
</p>

<p align="center">
  <a href="#key-features">Features</a> •
  <a href="#blockchain-verified-credentials">Blockchain</a> •
  <a href="#ai-powered-features">AI Features</a> •
  <a href="#multi-school-architecture">Multi-School</a> •
  <a href="#teaching-slot-management">Teaching Slots</a> •
  <a href="#installation">Installation</a> •
  <a href="#tech-stack">Tech Stack</a>
</p>

---

## Overview

ExamFlow is a comprehensive educational assessment platform designed for multi-school deployments. It combines traditional exam management with cutting-edge features including blockchain-verified certificates, AI-powered grading, OCR-based answer processing, advanced proctoring, and geotagged teaching activity verification.

---

## Key Features

### 🎓 Three-Portal Architecture

| Portal | Description | Key Functions |
|--------|-------------|---------------|
| **Student Portal** | Clean, intuitive interface for exam-taking | MCQ exams, Objective exams, Mock tests, Certificate minting, Results viewing |
| **Teacher Portal** | Comprehensive teaching management | Exam creation, Grading, Analytics, School management, Slot booking |
| **Admin Portal** | System-wide oversight | Session verification, School management, Teaching slots, Audit logs |

### 📝 Multiple Exam Types

- **MCQ Exams**: Auto-graded multiple choice assessments with real-time scoring
- **Objective Exams**: Handwritten answer submission with OCR processing and AI grading
- **Mock Exams**: AI-generated practice tests from existing question banks

### 🔐 Advanced Proctoring System

- Real-time integrity scoring (0-100 scale)
- Tab switching detection with escalating penalties
- Window focus loss monitoring
- Combined violation detection
- Automatic exam termination at critical thresholds

### 📊 Comprehensive Analytics

- Question-level performance analysis
- Response distribution visualization
- Difficulty assessment based on actual results
- Integrity score correlation analysis
- Exportable reports

---

## Blockchain-Verified Credentials

### NFT Certificate System

ExamFlow mints examination certificates as ERC-721 NFTs on the Ethereum Sepolia network, ensuring tamper-proof credential verification.

| Component | Technology | Purpose |
|-----------|------------|---------|
| Smart Contract | Solidity (ERC-721) | Certificate minting and ownership |
| Storage | IPFS via Pinata | Decentralized certificate data |
| Network | Ethereum Sepolia | Blockchain transactions |
| Verification | Public explorer | Universal credential verification |

### Certificate Features

- **Immutable Records**: Once minted, certificates cannot be altered or deleted
- **Integrity Embedding**: Exam integrity scores permanently recorded on-chain
- **Instant Verification**: Anyone can verify authenticity via blockchain explorer
- **Student Ownership**: Certificates are true digital assets owned by students

---

## AI-Powered Features

### 🤖 Groq AI Grading

Objective exam answers are automatically graded using Groq's LLM API:

- Compares student answers against teacher-provided answer keys
- Supports per-question mark allocation
- Provides partial credit scoring
- Generates detailed feedback

### 📸 OCR Processing

Handwritten answer sheets are processed using Tesseract OCR:

- Automatic text extraction from uploaded images
- Support for multiple image formats
- Preprocessing for improved accuracy
- Queue-based processing via cron jobs

### 📚 AI Mock Test Generation

- Automatically generates practice exams from existing question banks
- Randomizes questions while maintaining topic balance
- Creates multiple unique versions for each exam
- Full proctoring simulation for realistic practice

---

## Multi-School Architecture

ExamFlow supports multi-school deployments with complete data isolation:

```
┌─────────────────────────────────────────────────────────────┐
│                      ADMIN DASHBOARD                        │
│         (Cross-school oversight & verification)             │
└─────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│    School A     │  │    School B     │  │    School C     │
│  ┌───────────┐  │  │  ┌───────────┐  │  │  ┌───────────┐  │
│  │ Teachers  │  │  │  │ Teachers  │  │  │  │ Teachers  │  │
│  │ Students  │  │  │  │ Students  │  │  │  │ Students  │  │
│  │   Exams   │  │  │  │   Exams   │  │  │  │   Exams   │  │
│  └───────────┘  │  │  └───────────┘  │  │  └───────────┘  │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

### School Access Control

- Teachers and students are enrolled to specific schools
- Data isolation between schools
- Cross-school messaging with school_id filtering
- School-specific analytics and reporting

---

## Teaching Slot Management

### Geotagged Activity Verification

Teachers can book teaching slots at partner schools and submit geotagged photos as proof of activity:

1. **Admin Creates Slots**: Define available teaching slots at schools
2. **Teachers Book Slots**: Browse and reserve available time slots
3. **Session Documentation**: Upload geotagged photos during teaching sessions
4. **Admin Verification**: Review submissions with location validation
5. **Audit Trail**: Complete history of all teaching activities

### Location Verification

- EXIF data extraction from uploaded photos
- GPS coordinate validation against school location
- Distance calculation and threshold enforcement
- Automatic flagging of suspicious submissions

---

## Dual Photo Verification System

### Enhanced Session Verification

ExamFlow implements a robust dual photo verification system to ensure teachers complete their full teaching sessions:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     DUAL PHOTO VERIFICATION FLOW                        │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
    ┌───────────────────────────────┴───────────────────────────┐
    ▼                                                           ▼
┌─────────────────────┐                             ┌─────────────────────┐
│   📸 START PHOTO    │                             │    📸 END PHOTO     │
│   (Arrival)         │         ──────────►         │   (Completion)      │
│                     │                             │                     │
│ • GPS coordinates   │                             │ • GPS coordinates   │
│ • Timestamp         │                             │ • Timestamp         │
│ • Distance check    │                             │ • Distance check    │
│ • Device info       │                             │ • Duration calc     │
└─────────────────────┘                             └─────────────────────┘
         │                                                   │
         ▼                                                   ▼
┌─────────────────────┐                             ┌─────────────────────┐
│  Status:            │                             │  Status:            │
│  start_submitted    │                             │  end_submitted      │
└─────────────────────┘                             └─────────────────────┘
                                    │
                                    ▼
                        ┌───────────────────────┐
                        │  ADMIN VERIFICATION   │
                        │                       │
                        │ • Both photos valid   │
                        │ • Duration verified   │
                        │ • Location confirmed  │
                        └───────────────────────┘
                                    │
                                    ▼
                        ┌───────────────────────┐
                        │  Status: APPROVED ✓   │
                        └───────────────────────┘
```

### Session Status Workflow

| Status | Description |
|--------|-------------|
| `pending` | No photos uploaded yet |
| `start_submitted` | Arrival photo uploaded, awaiting end photo |
| `start_approved` | Start photo verified, awaiting end photo |
| `end_submitted` | Both photos uploaded, awaiting final review |
| `approved` | Fully verified and approved |
| `rejected` | Rejected at any stage |
| `partial` | Start approved but end photo missing/invalid |

### Duration Verification

The system automatically validates that teachers stayed for the full session:

- **Expected Duration**: Calculated from slot start/end times
- **Actual Duration**: Calculated from photo timestamps (end - start)
- **Tolerance**: Configurable tolerance window (default: 15 minutes)
- **Compliance Rate**: Percentage of expected duration completed

### Teacher Statistics Dashboard

Admins can monitor teaching activity through comprehensive statistics:

- **Total Sessions**: Number of booked teaching slots
- **Completed Sessions**: Successfully verified sessions
- **Completion Rate**: Percentage of sessions approved
- **Total Teaching Hours**: Cumulative teaching time
- **Duration Compliance**: Average adherence to expected session length
- **School Coverage**: Number of different schools taught at

Features include:
- Filtering by school, subject, and date range
- Sortable columns for all metrics
- CSV export for reporting
- Drill-down to individual teacher details

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                           WEB SERVER                                │
│                     (Apache + PHP 7.4+/8.x)                         │
└─────────────────────────────────────────────────────────────────────┘
         │                    │                    │
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  Student Portal │  │  Teacher Portal │  │  Admin Portal   │
│  /students/*    │  │  /teachers/*    │  │  /admin/*       │
└─────────────────┘  └─────────────────┘  └─────────────────┘
         │                    │                    │
         └────────────────────┼────────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         MYSQL DATABASE                              │
│                          (db_eval)                                  │
│  Tables: student, teacher, admin, exm_list, atmpt_list,             │
│  certificate_nfts, cheat_violations, objective_exm_list,            │
│  objective_submissions, school_teaching_slots, teaching_sessions... │
└─────────────────────────────────────────────────────────────────────┘
         │                    │                    │
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  Groq AI API    │  │  Tesseract OCR  │  │  Blockchain     │
│  (AI Grading)   │  │  (Text Extract) │  │  (Ethereum +    │
│                 │  │                 │  │   IPFS/Pinata)  │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

---

## Database Schema

### Core Tables

| Table | Purpose |
|-------|---------|
| `student` | Student accounts and profiles |
| `teacher` | Teacher accounts with subject specialization |
| `admin` | Administrator accounts for verification tasks |
| `exm_list` | MCQ examination definitions |
| `qstn_list` | Question bank for MCQ exams |
| `atmpt_list` | Student exam attempts with integrity scores |
| `certificate_nfts` | Minted certificate records |
| `cheat_violations` | Detailed violation logs |

### Objective Exam Tables

| Table | Purpose |
|-------|---------|
| `objective_exm_list` | Objective exam definitions |
| `objective_submissions` | Student answer sheet uploads |
| `ocr_queue` | Pending OCR processing jobs |

### Teaching Slots Tables

| Table | Purpose |
|-------|---------|
| `schools` | Partner school information with GPS |
| `school_teaching_slots` | Available teaching slots |
| `slot_teacher_enrollments` | Teacher slot bookings |
| `teaching_sessions` | Session documentation with dual photo verification |
| `admin_audit_log` | Admin action history |
| `teacher_session_stats` | View for aggregated teacher statistics |

---

## Tech Stack

| Category | Technology |
|----------|------------|
| **Backend** | PHP 7.4+ / 8.x |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ |
| **Frontend** | HTML5, CSS3, JavaScript, Chart.js |
| **Blockchain** | Ethereum (Sepolia), Solidity, Hardhat |
| **Smart Contract** | ERC-721 (OpenZeppelin) |
| **IPFS** | Pinata Gateway |
| **AI/ML** | Groq API (LLaMA 3.3 70B) |
| **OCR** | Tesseract 5.x |
| **Web Server** | Apache (XAMPP compatible) |

---

## Installation

### Prerequisites

- PHP 7.4+ with mysqli, curl, gd, json extensions
- MySQL 5.7+ or MariaDB 10.4+
- Node.js 14+ (for blockchain features)
- Tesseract OCR 5.x (for objective exam processing)
- Composer (for PHP dependencies)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/examflow.git
   cd examflow
   ```

2. **Configure database**
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE db_eval CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   # Import schema
   mysql -u root -p db_eval < db/db_eval.sql
   
   # Run migrations for full feature support
   mysql -u root -p db_eval < db/migrate_dual_photo_verification.sql
   ```

3. **Set up environment**
   ```bash
   # Copy example environment file
   cp .env.example .env
   
   # Edit with your credentials
   # Required: Database, Groq API, Pinata API, Ethereum wallet
   ```

4. **Configure PHP**
   ```bash
   # Edit config.php with your database credentials
   $hostname = "localhost";
   $username = "root";
   $password = "";
   $database = "db_eval";
   ```

5. **Install Node dependencies** (for blockchain)
   ```bash
   npm install
   ```

6. **Deploy smart contract** (optional)
   ```bash
   # Using Remix IDE (recommended) or Hardhat
   npx hardhat run scripts/deploy.js --network sepolia
   ```

7. **Set permissions**
   ```bash
   chmod -R 755 .
   chmod -R 777 uploads/
   chmod -R 777 certificates/
   ```

### Environment Variables (.env)

```env
# Database (configured in config.php)

# Groq AI
GROQ_API_KEY=your_groq_api_key
GROQ_MODEL=llama-3.3-70b-versatile

# Pinata (IPFS)
PINATA_API_KEY=your_pinata_api_key
PINATA_SECRET_KEY=your_pinata_secret_key

# Ethereum
NFT_CONTRACT_ADDRESS=your_deployed_contract
ETHEREUM_WALLET_ADDRESS=your_wallet_address
ETHEREUM_PRIVATE_KEY=your_private_key

# Infura (RPC)
INFURA_API_KEY=your_infura_key

# Teaching Session Verification (optional)
DURATION_TOLERANCE_MINUTES=15
MIN_DURATION_PERCENT=80
```

---

## Cron Jobs

Set up these cron jobs for background processing:

```bash
# Process OCR queue (every 5 minutes)
*/5 * * * * php /path/to/examflow/cron/process_ocr_queue.php

# Process AI grading queue (every 5 minutes)
*/5 * * * * php /path/to/examflow/cron/process_ai_grading.php
```

---

## User Roles

### Student
- Register/Login with school enrollment
- Take MCQ and objective exams
- Attempt AI-generated mock tests
- View results and integrity scores
- Generate and mint NFT certificates
- Access messages from teachers

### Teacher
- Create and manage MCQ exams
- Upload objective exam answer keys
- Review AI-graded submissions
- View analytics and reports
- Browse and book teaching slots
- Upload dual verification photos (start & end)
- View session history and statistics

### Admin
- Manage schools and teaching slots
- Review pending session verifications
- Validate geotagged submissions (dual photo verification)
- Monitor teacher statistics and completion rates
- Access audit logs
- Generate system reports
- Force enrollment/unenrollment

---

## Directory Structure

```
examflow/
├── admin/              # Admin portal
├── students/           # Student portal
├── teachers/           # Teacher portal
├── contracts/          # Solidity smart contracts
├── cron/               # Background job processors
├── db/                 # Database schemas
├── utils/              # Helper utilities
│   ├── groq_grader.php       # AI grading
│   ├── ocr_processor.php     # OCR handling
│   ├── env_loader.php        # Environment config
│   ├── exif_extractor.php    # Photo EXIF data extraction
│   ├── location_validator.php # GPS validation
│   ├── duration_validator.php # Session duration verification
│   └── ...
├── uploads/            # User uploads
│   ├── student_answers/   # Objective exam submissions
│   └── ocr_temp/          # OCR processing temp
├── certificates/       # Generated certificates
├── assets/             # Static assets
├── js/                 # JavaScript files
├── img/                # Images
├── config.php          # Database configuration
├── .env                # Environment variables
└── package.json        # Node.js dependencies
```

---

## Security Features

| Feature | Implementation |
|---------|----------------|
| **Authentication** | Session-based with password hashing |
| **Authorization** | Role-based access control (RBAC) |
| **SQL Injection** | Prepared statements throughout |
| **XSS Prevention** | Output escaping with htmlspecialchars |
| **CSRF Protection** | Session token validation |
| **File Upload** | Type validation and secure storage |
| **Blockchain** | Immutable credential verification |
| **Audit Logging** | Complete admin action history |
| **Dual Photo Verification** | GPS + timestamp validation for teaching sessions |
| **Duration Compliance** | Automatic session duration verification |

---

## API Integrations

| Service | Purpose | Documentation |
|---------|---------|---------------|
| Groq | AI-powered answer grading | [docs.groq.com](https://docs.groq.com) |
| Pinata | IPFS storage for certificates | [pinata.cloud](https://pinata.cloud) |
| Infura | Ethereum RPC provider | [infura.io](https://infura.io) |
| Etherscan | Transaction verification | [sepolia.etherscan.io](https://sepolia.etherscan.io) |

---

## Integrity Scoring

| Category | Score | Action |
|----------|-------|--------|
| Exemplary | 90-100 | Normal certificate |
| Good | 75-89 | Certificate with note |
| At-Risk | 50-74 | Certificate with warning |
| Violation | 0-49 | Potential invalidation |

### Violation Penalties

- **Tab Switch**: 3-15 points (escalating)
- **Window Blur**: 2-8 points (escalating)
- **Combined**: 10-20 points (pattern detection)

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Acknowledgments

- **Team Cerebro** - Development team
- **OpenZeppelin** - Smart contract libraries
- **Groq** - AI inference API
- **Pinata** - IPFS pinning service
- **Tesseract** - OCR engine

---

<p align="center">
  <b>ExamFlow: Redefining Academic Assessment with Blockchain Integrity & AI Intelligence</b><br>
  <sub>Built with ❤️ by Team Cerebro</sub>
</p>

<p>
  Also added the activity points for volunteers who get 2 activity points for every hour taught
</p>
