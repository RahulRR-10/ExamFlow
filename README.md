# ExamFlow

> **A complete assessment platform for schools and universities** — built for educators who want secure exams, smart grading, and tamper-proof certificates.

---

## What Is ExamFlow?

ExamFlow is an end-to-end assessment management system designed with educators in mind. It covers everything from creating and administering exams, to AI-assisted grading, to issuing blockchain-verified certificates — all in one place, with full school-level data isolation.

Whether you are running weekly quizzes, proctored mid-terms, or issuing teaching credentials, ExamFlow provides the tools to do it with confidence.

---

## Who Is This For?

| Role | What You Can Do |
|---|---|
| **Teacher / Professor** | Create exams, grade submissions, book teaching slots, issue certificates |
| **Student** | Take exams, submit answers, view results, receive and mint certificates |
| **Administrator** | Manage schools, verify teaching sessions, review audit logs and reports |

---

## Key Features

### 📝 Flexible Exam Types

- **Multiple Choice (MCQ)** — Fully automatic scoring. Results available instantly after submission.
- **Descriptive / Objective Exams** — Students upload handwritten or typed answer images. These are processed through an OCR pipeline and can be graded via AI assistance or manually by the teacher.
- **Mock Exams** — Auto-generated from existing question banks for practice sessions.

### 🔒 Exam Integrity and Proctoring

ExamFlow tracks potential violations during an exam session:

- Tab switches and window focus loss are detected automatically.
- Each attempt receives an **integrity score from 0 to 100** based on observed behaviour.
- Violation categories are recorded alongside the submission for teacher review.

### 🤖 AI-Assisted Grading

For descriptive exams:

1. Students upload answer images after the exam.
2. ExamFlow's OCR engine extracts the text.
3. If the exam is set to **AI grading mode**, the Groq AI model evaluates the answers automatically.
4. Teachers retain full control and can override any AI-assigned grade at any time.

### 🏫 Multi-School Support

- Each school's data is fully isolated — students, teachers, and exams are scoped to their institution.
- Teachers can be enrolled across multiple schools.
- Admins manage school-level settings and user access.

### 📅 Teaching Slot Management

For institutions that track teaching activity:

- Admins create available teaching slots per school.
- Teachers book slots and upload verification photos (start and end of session).
- GPS and EXIF metadata are checked automatically for location and time accuracy.
- Duration is validated against the booked slot.
- Admins perform a final review and approve or reject each session.

**Session States:**

```
pending → start_submitted → start_approved → end_submitted → approved / rejected / partial
```

### 🎓 Blockchain-Backed Certificates

Certificates issued through ExamFlow are:

- **Tamper-proof** — backed by an ERC-721 NFT contract on the Ethereum Sepolia network.
- **Verifiable** — metadata is stored on IPFS, making credentials independently verifiable.
- Available for both **students** (exam completion) and **teachers** (teaching activity).

**Activity Points for Teachers:**
Teaching certificates include an activity point tally calculated as:

```
Activity Points = Hours Taught × 2
```

---

## System Requirements

Before installation, ensure the following are available on your server or local machine:

| Requirement | Version |
|---|---|
| PHP | 7.4 or higher (8.x recommended) |
| MySQL or MariaDB | MySQL 5.7+ / MariaDB 10.4+ |
| Apache Web Server | XAMPP-compatible |
| Node.js | 14 or higher (for blockchain features) |
| Tesseract OCR | Latest stable, available in system PATH |

> **Optional:** Python (for local SMTP email helper script)

---

## Installation Guide

### Step 1 — Place the Project in Your Web Root

For XAMPP on Windows, copy the ExamFlow folder to:

```
C:\xampp\htdocs\ExamFlow
```

### Step 2 — Configure the Database Connection

Open `config.php` and update the connection details:

```php
$hostname = "localhost";
$username = "root";
$password = "";
$database = "db_eval";
```

### Step 3 — Create the Database and Import the Schema

Run the following commands in order. Each migration adds a layer of functionality — **do not skip any step**.

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS db_eval CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import base schema
mysql -u root -p db_eval < db/db_eval.sql

# Apply all migrations in order
mysql -u root -p db_eval < db/migrate_multi_school.sql
mysql -u root -p db_eval < db/create_objective_tables.sql
mysql -u root -p db_eval < db/migrate_teaching_slots.sql
mysql -u root -p db_eval < db/migrate_teaching_slots_triggers.sql
mysql -u root -p db_eval < db/migrate_teaching_verification.sql
mysql -u root -p db_eval < db/migrate_enhanced_validation.sql
mysql -u root -p db_eval < db/migrate_dual_photo_verification.sql
mysql -u root -p db_eval < db/migrate_admin_role.sql
```

> **Note:** Helper scripts `run_multi_school_migration.php` and `setup_objective_exams.php` are available for upgrade and setup flows if you prefer a browser-based approach.

### Step 4 — Set Up Environment Variables

Copy the example environment file and fill in your credentials:

```bash
copy .env.example .env
```

See the [Environment Variables](#environment-variables) section below for required keys.

### Step 5 — Install Node Dependencies (Blockchain Features)

```bash
npm install
npm run compile
```

To deploy the certificate contract to Sepolia (only needed for live NFT minting):

```bash
npm run deploy
```

### Step 6 — Create Required Upload Directories

Ensure the following directories exist and are writable by the web server:

```
uploads/
uploads/student_answers/
uploads/ocr_temp/
uploads/session_photos/
certificates/
```

### Step 7 — Launch the Application

Start Apache and MySQL via XAMPP, then open:

```
http://localhost/ExamFlow/
```

---

## Background Jobs (Cron / Task Scheduler)

ExamFlow uses background scripts for OCR processing and AI grading. Set these up on your server:

| Job | Schedule | Command |
|---|---|---|
| OCR Queue Processor | Every 5 minutes | `php /path/to/ExamFlow/cron/process_ocr_queue.php --limit=10` |
| AI Grading Processor | Every 2 minutes | `php /path/to/ExamFlow/cron/process_ai_grading.php --limit=5` |

> **Windows / XAMPP users:** Use Windows Task Scheduler to run these PHP commands at the equivalent intervals.

---

## Environment Variables

Configure these in your `.env` file:

### Blockchain

| Variable | Description |
|---|---|
| `WALLET_PRIVATE_KEY` | Private key for the certificate minting wallet |
| `SEPOLIA_RPC_URL` | Sepolia testnet RPC endpoint |
| `INFURA_PROJECT_ID` | Infura project ID |
| `NFT_CONTRACT_ADDRESS` | Deployed certificate NFT contract address |
| `ETHERSCAN_API_KEY` | *(Optional)* For contract verification on Etherscan |

### AI and OCR

| Variable | Description |
|---|---|
| `GROQ_API_KEY` | API key for Groq AI grading service |
| `GROQ_MODEL` | Model identifier (e.g. `llama3-8b-8192`) |
| `GROQ_API_URL` | Groq API endpoint URL |

### IPFS (Certificate Metadata)

| Variable | Description |
|---|---|
| `PINATA_JWT` | Pinata JWT for IPFS uploads |
| `PINATA_API_KEY` | Pinata API key |
| `PINATA_SECRET_KEY` | Pinata secret key |

### Email (SMTP)

| Variable | Description |
|---|---|
| `SMTP_SERVER` | Mail server hostname |
| `SMTP_PORT` | Mail server port |
| `SMTP_SECURE` | Security protocol (e.g. `tls`) |
| `SMTP_USERNAME` | SMTP login username |
| `SMTP_PASSWORD` | SMTP login password |

---

## Project Structure

```
ExamFlow/
├── admin/              # Admin portal
├── students/           # Student portal
├── teachers/           # Teacher portal
├── utils/              # Shared utilities
├── cron/               # Background job scripts
├── db/                 # Database schema and migrations
├── contracts/          # Solidity smart contracts (ERC-721)
├── scripts/            # Deployment and helper scripts
├── assets/             # Static assets
├── config.php          # Database configuration
├── .env.example        # Environment variable template
├── package.json        # Node dependencies
└── hardhat.config.js   # Hardhat blockchain config
```

---

## Security Notes

ExamFlow implements several security practices throughout the codebase:

- **Session-based authentication** across all three portals.
- **Prepared statements** used in all critical database query paths to prevent SQL injection.
- **Upload validation** — file type, size, and processing checks on all student and teacher uploads.
- **Admin audit logging** — all admin actions are logged for accountability.

> ⚠️ **Known Limitation:** CSRF token protection is not yet implemented as a consistent framework-level mechanism. This is recommended for hardening before production deployment in a public-facing environment.

---

## Troubleshooting

| Symptom | Likely Cause | Fix |
|---|---|---|
| OCR processing is failing | Tesseract not installed or not in PATH | Verify `tesseract --version` works in your terminal |
| AI grading queue is stuck | Missing or invalid Groq API key | Check `GROQ_API_KEY` in `.env`, then run the AI cron script manually once |
| Certificate minting fails | Blockchain config issue | Verify `SEPOLIA_RPC_URL`, `WALLET_PRIVATE_KEY`, and `NFT_CONTRACT_ADDRESS` |
| Session verification looks inconsistent | Missing database migrations | Re-run all teaching-related migrations in the correct order |

---

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript, Chart.js |
| OCR | Tesseract |
| AI Grading | Groq API |
| Blockchain | Solidity, Hardhat, OpenZeppelin, Ethereum Sepolia |

---

