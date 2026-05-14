# ExamFlow MERN Migration - Phase 0 Baseline

## Phase 0 Status

Status: complete  
Branch: `node`  
Main branch touched: no  
Application code changed: no  

Phase 0 establishes the current system inventory and the safety boundaries for the MERN rewrite. The PHP application remains in place and should continue to be treated as the source of functional truth until each MERN feature reaches parity.

## Current Safety Snapshot

The active working branch is `node`.

Current uncommitted migration files:

- `MERN_MIGRATION_PLAN.md`
- `MERN_PHASE_0_BASELINE.md`

No PHP, SQL, JavaScript runtime, contract, upload, or configuration files were edited during Phase 0.

## Current Architecture

ExamFlow is currently a procedural PHP/MySQL application designed for XAMPP-style hosting.

The app is organized around role-specific portals:

- `students/`
- `teachers/`
- `admin/`

Shared behavior lives mostly in:

- `utils/`
- `cron/`
- root-level PHP entry points

Blockchain deployment support lives in:

- `contracts/`
- `scripts/`
- `hardhat.config.js`
- `package.json`

The current app mixes:

- routing
- HTML rendering
- form processing
- database access
- session checks
- uploads
- business rules
- third-party service calls

The MERN rewrite should split these into React pages/components, Express routes/controllers, Mongoose models, backend services, background jobs, and validation middleware.

## Runtime and Tooling Inventory

Current stack:

- PHP application runtime
- MySQL/MariaDB database named `db_eval`
- Apache/XAMPP web root deployment
- Node/Hardhat only for certificate NFT deployment
- Tesseract OCR for answer image processing
- Groq API for grading and analytics
- PHPMailer for SMTP email
- Pinata/IPFS for certificate metadata and image storage
- Ethereum Sepolia for NFT certificate minting

Current Node package usage is blockchain-focused:

- `hardhat`
- `@nomicfoundation/hardhat-toolbox`
- `@openzeppelin/contracts`
- `dotenv`

## Root-Level Entry Points

Important root files:

- `index.php`
- `config.php`
- `login_student.php`
- `login_teacher.php`
- `login_admin.php`
- `register_student.php`
- `register_teacher.php`
- `logout.php`
- `process_submission.php`
- `generate_mock_exam.php`
- `setup_objective_exams.php`
- `run_multi_school_migration.php`
- `run_db_update.php`
- `add_message_school_id.php`
- `add_sample_analytics_data.php`

Migration notes:

- `config.php` contains database connection setup and global greeting/background image behavior.
- Login/register files currently own important auth behavior and must be replaced by a centralized Node auth module.
- `process_submission.php` is a strong candidate for early Node service migration because it is already process-like and service-oriented.

## Student Portal Inventory

Student PHP pages:

- `students/dash.php`
- `students/exams.php`
- `students/examportal.php`
- `students/submit.php`
- `students/objective_exams.php`
- `students/objective_exam_portal.php`
- `students/submit_objective.php`
- `students/objective_results.php`
- `students/mock_exams.php`
- `students/mockexamportal.php`
- `students/submit_mock.php`
- `students/mock_test_result.php`
- `students/retry_mock_generation.php`
- `students/results.php`
- `students/messages.php`
- `students/study_material.php`
- `students/settings.php`
- `students/help.php`
- `students/generate_certificate.php`
- `students/mint_nft.php`
- `students/update_transaction.php`
- `students/get_env_variables.php`
- `students/log_violation.php`
- `students/log_mock_violation.php`
- `students/Web3Helper.php`
- `students/NFTContract.json`

Student feature surface:

- dashboard statistics
- school-scoped exam access
- MCQ exam taking
- proctoring violation logging
- objective/descriptive exam answer image upload
- objective result tracking
- mock exam taking
- result viewing
- announcements/messages
- study material access
- certificate generation
- NFT certificate minting and transaction update flow
- user settings/help pages

MERN route family:

- `/student/dashboard`
- `/student/exams`
- `/student/exams/:id`
- `/student/objective-exams`
- `/student/objective-exams/:id`
- `/student/mock-exams`
- `/student/results`
- `/student/messages`
- `/student/study-material`
- `/student/settings`

## Teacher Portal Inventory

Teacher PHP pages:

- `teachers/dash.php`
- `teachers/dashboard.php`
- `teachers/exams.php`
- `teachers/addexam.php`
- `teachers/addqp.php`
- `teachers/delexam.php`
- `teachers/adduser.php`
- `teachers/updateuser.php`
- `teachers/updateuserform.php`
- `teachers/del.php`
- `teachers/addmsg.php`
- `teachers/messages.php`
- `teachers/results.php`
- `teachers/records.php`
- `teachers/viewresults.php`
- `teachers/view_violations.php`
- `teachers/add_objective_exam.php`
- `teachers/objective_exams.php`
- `teachers/objective_questions.php`
- `teachers/edit_objective_exam.php`
- `teachers/delete_objective_exam.php`
- `teachers/grade_objective.php`
- `teachers/view_objective_results.php`
- `teachers/upload_answer_key.php`
- `teachers/ocr_status.php`
- `teachers/get_exam_analytics.php`
- `teachers/simple_analytics.php`
- `teachers/view_analytics.php`
- `teachers/mock_exam_helper.php`
- `teachers/upload_material.php`
- `teachers/school_management.php`
- `teachers/create_school.php`
- `teachers/enroll_school.php`
- `teachers/browse_slots.php`
- `teachers/book_slot.php`
- `teachers/cancel_booking.php`
- `teachers/my_slots.php`
- `teachers/upload_session_photo.php`
- `teachers/upload_activity.php`
- `teachers/delete_activity.php`
- `teachers/view_session.php`
- `teachers/generate_certificate.php`
- `teachers/mint_teacher_nft.php`
- `teachers/update_teacher_transaction.php`
- `teachers/view_certificate.php`
- `teachers/settings.php`
- `teachers/help.php`

Teacher feature surface:

- dashboard statistics
- student/user management in legacy flows
- MCQ exam creation
- MCQ question management
- results and records review
- violation review
- objective exam creation/edit/delete
- answer-key upload
- objective question management
- OCR status review
- AI/manual grading
- analytics
- message creation
- study material upload
- school enrollment/management workflows
- teaching slot browsing and booking
- slot cancellation
- teaching session start/end photo upload
- teaching activity verification flow
- teacher certificate generation and minting

MERN route family:

- `/teacher/dashboard`
- `/teacher/exams`
- `/teacher/exams/new`
- `/teacher/exams/:id`
- `/teacher/objective-exams`
- `/teacher/objective-exams/new`
- `/teacher/objective-exams/:id`
- `/teacher/results`
- `/teacher/messages`
- `/teacher/study-material`
- `/teacher/schools`
- `/teacher/teaching-slots`
- `/teacher/settings`

## Admin Portal Inventory

Admin PHP pages:

- `admin/index.php`
- `admin/dash.php`
- `admin/logout.php`
- `admin/manage_schools.php`
- `admin/teaching_slots.php`
- `admin/view_slot.php`
- `admin/pending_sessions.php`
- `admin/review_session.php`
- `admin/pending_verifications.php`
- `admin/verify_submission.php`
- `admin/all_submissions.php`
- `admin/teacher_stats.php`
- `admin/teacher_detail.php`
- `admin/force_unenroll.php`
- `admin/audit_log.php`
- `admin/reports.php`
- `admin/export_report.php`
- `admin/settings.php`
- `admin/db_health_check.php`
- `admin/includes/nav.php`

Admin feature surface:

- admin login/logout
- teaching verification dashboard
- school management
- teaching slot management
- slot detail views
- pending session review
- teaching activity submission review
- teacher statistics
- teacher detail pages
- force unenrollment
- audit logs
- reports/export
- verification settings
- database health check

MERN route family:

- `/admin/dashboard`
- `/admin/schools`
- `/admin/teaching-slots`
- `/admin/teaching-slots/:id`
- `/admin/session-reviews`
- `/admin/session-reviews/:id`
- `/admin/teacher-stats`
- `/admin/teacher-stats/:id`
- `/admin/audit-log`
- `/admin/reports`
- `/admin/settings`

## Utility Inventory

Shared PHP utility files:

- `utils/admin_auth.php`
- `utils/duration_validator.php`
- `utils/email_config.php`
- `utils/enrollment_utils.php`
- `utils/env_loader.php`
- `utils/exif_extractor.php`
- `utils/groq_analytics.php`
- `utils/groq_grader.php`
- `utils/image_upload_handler.php`
- `utils/location_validator.php`
- `utils/mailer.php`
- `utils/message_utils.php`
- `utils/objective_exam_utils.php`
- `utils/ocr_processor.php`
- `utils/rate_limiter.php`
- `utils/school_access_control.php`
- `utils/session_validator.php`
- `utils/teaching_slots_compat.php`
- `utils/verification_alerts.php`

Node service mapping:

| PHP Utility | MERN Replacement |
|---|---|
| `admin_auth.php` | auth middleware, role middleware, audit service |
| `duration_validator.php` | `services/verification/DurationValidationService` |
| `email_config.php` | `config/email.js` |
| `enrollment_utils.php` | school/teacher enrollment service |
| `env_loader.php` | dotenv-based env config |
| `exif_extractor.php` | EXIF service using `exifr` or equivalent |
| `groq_analytics.php` | AI analytics service |
| `groq_grader.php` | AI grading service |
| `image_upload_handler.php` | upload service with multer and validation |
| `location_validator.php` | location validation service |
| `mailer.php` | nodemailer service |
| `message_utils.php` | message service |
| `objective_exam_utils.php` | objective exam service/repository |
| `ocr_processor.php` | OCR service and OCR job worker |
| `rate_limiter.php` | Express rate limiting middleware |
| `school_access_control.php` | authorization policies |
| `session_validator.php` | teaching session validation service |
| `teaching_slots_compat.php` | migration-only compatibility reference |
| `verification_alerts.php` | notification/alert service |

## Background Jobs Inventory

Current cron scripts:

- `cron/process_ocr_queue.php`
- `cron/process_ai_grading.php`

MERN replacements:

- `server/src/jobs/processOcrQueue.js`
- `server/src/jobs/processAiGradingQueue.js`

Recommended queue approach:

- simple `node-cron` for the first MERN pass
- BullMQ/Redis later if retries, concurrency, and observability become important

## Database Inventory

Base schema file:

- `db/db_eval.sql`

Migration files:

- `db/migrate_multi_school.sql`
- `db/create_objective_tables.sql`
- `db/migrate_teaching_slots.sql`
- `db/migrate_teaching_slots_triggers.sql`
- `db/migrate_teaching_verification.sql`
- `db/migrate_enhanced_validation.sql`
- `db/migrate_dual_photo_verification.sql`
- `db/migrate_admin_role.sql`
- `db/rollback_dual_photo_verification.sql`

Current tables identified:

| Table | Purpose |
|---|---|
| `student` | student accounts |
| `teacher` | teacher accounts |
| `admin` | admin accounts |
| `schools` | school/institution records |
| `teacher_schools` | teacher-school enrollment |
| `exm_list` | MCQ exam records |
| `qstn_list` | MCQ questions |
| `question_options` | MCQ answer options |
| `atmpt_list` | MCQ attempts/results |
| `cheat_violations` | MCQ proctoring events |
| `mock_exm_list` | mock exam records |
| `mock_qstn_list` | mock questions |
| `mock_qstn_ans` | mock answer data |
| `mock_atmpt_list` | mock attempts/results |
| `mock_cheat_violations` | mock proctoring events |
| `objective_exm_list` | objective/descriptive exams |
| `objective_questions` | objective exam questions |
| `objective_submissions` | objective student submissions |
| `objective_scan_pages` | objective scan page records |
| `objective_answer_images` | uploaded answer images and OCR state |
| `objective_answer_grades` | AI/manual grading records |
| `student_answers` | legacy/descriptive answer records |
| `message` | announcements/messages |
| `read_messages` | message read tracking |
| `school_teaching_slots` | admin-created teaching slots |
| `slot_teacher_enrollments` | teacher bookings for slots |
| `teaching_sessions` | slot/session proof and review status |
| `teaching_activity_submissions` | teaching activity photo submissions |
| `school_locations` | GPS validation location per school |
| `verification_settings` | upload/location/duration verification settings |
| `upload_rate_limits` | upload throttling |
| `admin_audit_log` | admin action audit log |
| `certificate_nfts` | student certificate NFT records |

## Proposed MongoDB Collections

Initial collection plan:

- `users`
- `schools`
- `teacherSchoolMemberships`
- `exams`
- `examAttempts`
- `mockExams`
- `mockAttempts`
- `objectiveExams`
- `objectiveSubmissions`
- `messages`
- `studyMaterials`
- `teachingSlots`
- `teachingSessions`
- `certificates`
- `adminAuditLogs`
- `uploadRateLimits`

Recommended legacy mapping:

- Add `legacyId` fields where migrated MySQL records are imported.
- Add `legacyTable` only where records from multiple tables collapse into one collection.
- Keep a migration ID map while transforming relational rows into MongoDB documents.

## Upload and Generated Asset Inventory

Current upload/generated folders:

- `uploads/student_answers/`
- `uploads/ocr_temp/`
- `uploads/session_photos/`
- `certificates/`

Other asset folders:

- `img/`
- `assets/`
- `screenshots/`

MERN upload principles:

- Store upload metadata in MongoDB.
- Store physical files under a server-controlled upload root.
- Do not expose arbitrary local paths to clients.
- Generate stable public download URLs through authenticated API routes.
- Avoid committing generated certificates and uploaded user files.

## External Integration Inventory

### OCR

Current:

- Tesseract is invoked from PHP through `exec`.
- OCR output and confidence are saved to objective image/submission records.

MERN target:

- Backend OCR service wrapping local Tesseract.
- Job worker for queue processing.
- API endpoint for manual retry/status.

### AI Grading and Analytics

Current:

- Groq API is called via cURL.
- `utils/groq_grader.php` grades objective answers.
- `utils/groq_analytics.php` generates analytics.

MERN target:

- `GroqGradingService`
- `GroqAnalyticsService`
- Structured response parsing and validation.
- Retry/backoff handling.

### Email

Current:

- PHPMailer sends certificate emails and attachments.
- SMTP config is loaded from `.env`.

MERN target:

- Nodemailer service.
- Dedicated certificate email templates.
- No direct email sending from frontend.

### Blockchain and IPFS

Current:

- Solidity ERC-721 contract in `contracts/CertificateNFT.sol`.
- Hardhat deploy script in `scripts/deploy.js`.
- Student certificate flow includes Pinata/IPFS upload and ethers usage.
- Some sensitive minting-related behavior appears client-heavy and should be moved server-side.

MERN target:

- `BlockchainService` with `ethers`.
- `PinataService`.
- All signing server-side.
- Private keys never returned to the frontend.
- Certificate metadata upload and NFT minting initiated through secured backend endpoints.

### GPS/EXIF Verification

Current:

- EXIF extraction is implemented in PHP.
- GPS distance uses Haversine calculation.
- Validation includes timing, location, duration, and admin manual review.

MERN target:

- `ExifService`
- `LocationValidationService`
- `DurationValidationService`
- `SessionValidationService`

## Auth and Security Inventory

Current observations:

- Role portals use PHP session checks.
- Student, teacher, and admin login flows are separate.
- Legacy password hashing uses MD5 in login/register/user-add flows.
- Some newer code uses prepared statements.
- Older query paths still use interpolated SQL.
- README already notes that consistent CSRF protection is not implemented.
- Admin audit logging exists for many admin workflows.

MERN requirements:

- Replace MD5 with bcrypt.
- Centralize auth into one module.
- Use role-based authorization middleware.
- Add CSRF protection if cookie auth is used.
- Add rate limits to auth, upload, and grading-trigger endpoints.
- Validate all write requests.
- Keep school isolation checks server-side.
- Ensure private blockchain/API secrets stay server-only.

## Feature Parity Requirements

These flows should be preserved closely:

- Student login, dashboard, and role isolation.
- Teacher login, dashboard, and school-scoped access.
- Admin login and restricted admin panel.
- School activation/deactivation behavior.
- Teacher enrollment across schools.
- MCQ exam creation, taking, scoring, and results.
- Proctoring violation logging and integrity score behavior.
- Objective exam submission status lifecycle.
- OCR queue processing and retry behavior.
- AI grading with teacher override.
- Teaching slot booking and cancellation.
- Start/end photo verification lifecycle.
- Admin approval/rejection/remarks for sessions.
- Admin audit logs.
- Certificate record keeping.

These flows should be redesigned rather than copied literally:

- Auth and password storage.
- CSRF and form handling.
- Mixed PHP page/controller files.
- Client-side exposure of sensitive certificate/NFT configuration.
- Direct SQL query composition.
- Upload validation and file serving.
- Background job invocation and retry behavior.
- Dashboard data fetching.

## Initial Data Migration Decision

Working decision for early phases:

Build the MERN app against a fresh MongoDB database first, while designing models with `legacyId` support so real MySQL data can be migrated later.

Reasoning:

- It lets Phase 1 through Phase 12 move quickly without being blocked by complex data transformation.
- It keeps the PHP app available as the live reference.
- It avoids prematurely locking MongoDB document shapes before feature behavior is proven.
- It preserves a path to partial or full migration in Phase 13.

Decision still required before Phase 13:

- fresh launch with empty MongoDB
- partial migration of users/schools/exams
- full migration of users, exams, attempts, submissions, sessions, certificates, and logs

Recommended default:

Use partial migration for the first real MERN cutover unless historical attempts/submissions/certificates are required in the new app on day one.

## Risk Register

### High Risk

- Full MySQL-to-MongoDB data migration.
- Password migration from MD5 to bcrypt.
- School isolation regressions.
- Objective exam OCR and AI grading parity.
- Teaching session validation parity.
- NFT minting security and private key handling.
- Upload security and path handling.
- Recreating admin reports accurately.

### Medium Risk

- Dashboard statistic parity.
- Message read/unread behavior.
- Mock exam generation behavior.
- Certificate rendering parity.
- Email attachment behavior.
- Local Tesseract path differences across machines.
- Existing generated/user-uploaded files not mapping cleanly into a new upload service.

### Low Risk

- React route scaffolding.
- Express health endpoint.
- Static dashboard shells.
- New bcrypt-based user registration.
- New school CRUD after schema is established.

## Phase 0 Exit Criteria

Completed:

- Active branch confirmed as `node`.
- Current portal files inventoried.
- Shared utilities inventoried.
- Database tables inventoried.
- Background jobs inventoried.
- External integrations documented.
- Feature preservation/redesign notes recorded.
- Initial migration stance recorded.
- Risk register recorded.

Remaining before Phase 1:

- Review this baseline.
- Confirm whether MongoDB should run locally through a local install, Docker, or a hosted development database.
- Confirm preferred auth strategy:
  - HTTP-only cookie sessions
  - JWT access/refresh tokens
- Confirm frontend UI approach:
  - custom CSS/components
  - component library

## Phase 1 Readiness

Phase 1 can begin after review.

First implementation tasks:

1. Create `server/` Express app.
2. Create `client/` Vite React app.
3. Add MongoDB connection config.
4. Add `GET /api/health`.
5. Add React app shell and placeholder role routes.
6. Verify both apps run locally without affecting the PHP app.

