# ExamFlow MERN Migration Plan

## Purpose

This document defines a phased plan to convert ExamFlow from the current procedural PHP/MySQL application into a typical MERN application:

- MongoDB for persistence
- Express.js and Node.js for the backend API
- React for the frontend
- Node-based services and jobs for OCR, AI grading, email, uploads, and blockchain workflows

The migration will happen piece by piece on the existing `node` branch so the current PHP application remains available while the MERN replacement is built.

## Current Project Summary

ExamFlow is currently a PHP/XAMPP application with separate portals for:

- Students
- Teachers
- Admins

The app supports:

- Student and teacher registration/login
- MCQ exams
- Mock exams
- Objective/descriptive exams with uploaded answer images
- OCR processing with Tesseract
- AI grading through Groq
- Teacher manual grading
- School-level isolation
- Teacher enrollment across schools
- Announcements/messages
- Study material uploads
- Teaching slot booking
- Teaching session verification with GPS/EXIF/photo checks
- Admin approval/rejection workflows
- Audit logs and reports
- Student and teacher certificate generation
- NFT certificate minting through Ethereum Sepolia/IPFS
- Email delivery with PHPMailer

The current implementation mixes routing, rendering, form handling, database queries, and business logic inside PHP page files. The MERN version should separate those concerns.

## Migration Principles

1. Keep the PHP app intact until a MERN feature reaches parity.
2. Build the MERN app alongside the existing app inside this repository.
3. Migrate by feature area, not by file-by-file translation.
4. Use role-based API access from the beginning.
5. Replace MD5 passwords with bcrypt.
6. Use secure HTTP-only cookies or JWT access/refresh token flow for auth.
7. Keep uploads outside source-controlled code paths where practical.
8. Treat OCR, AI grading, email, IPFS, and blockchain as backend services.
9. Add validation and authorization at every API boundary.
10. Create tests around business rules before retiring PHP equivalents.

## Proposed Target Structure

```text
ExamFlow/
  client/
    src/
      app/
      components/
      features/
        auth/
        student/
        teacher/
        admin/
        exams/
        objectiveExams/
        mockExams/
        messages/
        teachingSlots/
        certificates/
      hooks/
      lib/
      pages/
      routes/
      styles/
    package.json
    vite.config.js

  server/
    src/
      config/
      controllers/
      jobs/
      middleware/
      models/
      routes/
      services/
        ai/
        blockchain/
        email/
        ipfs/
        ocr/
        uploads/
        verification/
      utils/
      validators/
      app.js
      server.js
    tests/
    package.json

  migration/
    mysql-export/
    transforms/
    reports/

  legacy/
    notes/

  MERN_MIGRATION_PLAN.md
```

The existing PHP files can remain in place during the transition. We do not need to move them immediately.

## Recommended Stack

### Backend

- Node.js
- Express.js
- Mongoose
- MongoDB
- bcrypt for password hashing
- cookie-session or JWT with refresh tokens
- multer for uploads
- zod for request validation
- helmet for baseline headers
- cors with explicit origin allowlist
- express-rate-limit for sensitive routes
- dotenv for environment variables
- node-cron or BullMQ for background jobs
- nodemailer for email
- exifr for EXIF/GPS extraction
- tesseract.js or a child-process wrapper around local Tesseract
- Groq SDK or direct OpenAI-compatible API calls
- ethers for blockchain interaction
- Pinata SDK/API for IPFS uploads

### Frontend

- React
- Vite
- React Router
- TanStack Query for API data fetching
- React Hook Form
- zod for frontend validation
- Axios or fetch wrapper
- Chart.js or Recharts for dashboards
- A consistent component system built inside `client/src/components`

### Testing

- Vitest for frontend utilities/components
- React Testing Library
- Jest or Vitest for backend unit tests
- Supertest for API tests
- Playwright for important end-to-end flows later

## Environment Variables

The MERN app should introduce a new server environment file:

```text
server/.env
```

Initial variables:

```text
NODE_ENV=development
PORT=5000
CLIENT_ORIGIN=http://localhost:5173
MONGODB_URI=mongodb://127.0.0.1:27017/examflow
SESSION_SECRET=replace_me
JWT_ACCESS_SECRET=replace_me
JWT_REFRESH_SECRET=replace_me

GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_API_URL=https://api.groq.com/openai/v1/chat/completions

TESSERACT_PATH=tesseract

SMTP_SERVER=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=
SMTP_PASSWORD=
FROM_EMAIL=
FROM_NAME=ExamFlow

PINATA_JWT=
PINATA_API_KEY=
PINATA_SECRET_KEY=

SEPOLIA_RPC_URL=
WALLET_PRIVATE_KEY=
NFT_CONTRACT_ADDRESS=
ETHERSCAN_API_KEY=
```

Do not commit real secrets.

## Phase 0: Baseline Inventory and Safety

### Goal

Document the current feature surface and prepare the branch for a controlled rewrite.

### Deliverables

- `MERN_MIGRATION_PLAN.md`
- Current PHP feature inventory
- Current database table inventory
- Initial risk list
- Decision on whether to fully migrate data from MySQL to MongoDB or start fresh

### Tasks

- Confirm active branch is `node`.
- Keep `main` untouched.
- Review current PHP portals:
  - `students/`
  - `teachers/`
  - `admin/`
- Review shared utilities:
  - `utils/ocr_processor.php`
  - `utils/groq_grader.php`
  - `utils/session_validator.php`
  - `utils/location_validator.php`
  - `utils/image_upload_handler.php`
  - `utils/mailer.php`
- Review database files:
  - `db/db_eval.sql`
  - `db/migrate_multi_school.sql`
  - `db/create_objective_tables.sql`
  - `db/migrate_teaching_slots.sql`
  - `db/migrate_teaching_verification.sql`
  - `db/migrate_dual_photo_verification.sql`
  - `db/migrate_admin_role.sql`
- Identify flows that must be preserved exactly.
- Identify flows that should be redesigned instead of ported literally.

### Exit Criteria

- Plan file exists.
- Team agrees to proceed with side-by-side MERN app.
- No PHP files have been removed.

## Phase 1: MERN Scaffold

### Goal

Create a clean React client and Express server without replacing the PHP app yet.

### Deliverables

- `client/` Vite React app
- `server/` Express app
- MongoDB connection
- Basic health endpoint
- Shared development scripts
- Local run instructions

### Backend Tasks

- Create `server/package.json`.
- Install backend dependencies.
- Create `server/src/app.js`.
- Create `server/src/server.js`.
- Add `server/src/config/env.js`.
- Add `server/src/config/db.js`.
- Add health endpoint:
  - `GET /api/health`
- Add centralized error middleware.
- Add 404 middleware.
- Add request logging in development.

### Frontend Tasks

- Create `client/` with Vite React.
- Install frontend dependencies.
- Set up React Router.
- Add API client wrapper.
- Add basic app shell.
- Add placeholder routes:
  - `/login`
  - `/student`
  - `/teacher`
  - `/admin`

### Exit Criteria

- `npm run dev` works for server.
- `npm run dev` works for client.
- Client can call `GET /api/health`.
- PHP app still runs separately.

## Phase 2: Core Data Model Design

### Goal

Define MongoDB/Mongoose schemas that cover the current relational model without copying MySQL structure blindly.

### Primary Models

- `User`
- `School`
- `TeacherSchool`
- `Exam`
- `Question`
- `ExamAttempt`
- `MockExam`
- `MockAttempt`
- `ObjectiveExam`
- `ObjectiveSubmission`
- `Message`
- `StudyMaterial`
- `TeachingSlot`
- `TeachingSession`
- `Certificate`
- `AdminAuditLog`
- `UploadRateLimit`

### User Model

Roles:

- `student`
- `teacher`
- `admin`

Suggested fields:

```js
{
  role,
  firstName,
  email,
  username,
  passwordHash,
  dateOfBirth,
  gender,
  subject,
  schoolId,
  status,
  profileImage,
  createdAt,
  updatedAt
}
```

Student-specific fields can live in `studentProfile`.
Teacher-specific fields can live in `teacherProfile`.
Admin-specific fields can live in `adminProfile`.

### School Model

Suggested fields:

```js
{
  name,
  code,
  status,
  address,
  contactEmail,
  contactPhone,
  contactPerson,
  type,
  location: {
    latitude,
    longitude,
    validationRadiusMeters,
    address
  },
  verificationSettings,
  createdAt,
  updatedAt
}
```

### Exam Model

For MCQ exams:

```js
{
  type: "mcq",
  name,
  subject,
  description,
  schoolId,
  teacherId,
  durationMinutes,
  totalQuestions,
  status,
  questions: [
    {
      text,
      options: [],
      correctOptionIndex,
      marks
    }
  ],
  createdAt,
  updatedAt
}
```

### Objective Exam Model

For descriptive/objective answer-sheet upload exams:

```js
{
  name,
  schoolId,
  teacherId,
  gradingMode,
  answerKeyText,
  answerKeyFile,
  totalMarks,
  passingMarks,
  instructions,
  examDate,
  submissionDeadline,
  durationMinutes,
  status,
  questions: [
    {
      questionNumber,
      text,
      maxMarks,
      answerKeyText
    }
  ],
  createdAt,
  updatedAt
}
```

### Objective Submission Model

```js
{
  examId,
  studentId,
  status,
  answerImages: [
    {
      path,
      order,
      ocrText,
      ocrStatus,
      ocrConfidence,
      ocrErrorMessage,
      uploadedAt,
      processedAt
    }
  ],
  grades: [
    {
      questionNumber,
      questionId,
      studentAnswerText,
      aiSuggestedMarks,
      aiFeedback,
      finalMarks,
      teacherFeedback,
      gradedBy
    }
  ],
  totalMarks,
  scoredMarks,
  percentage,
  passStatus,
  feedback,
  submittedAt,
  ocrCompletedAt,
  gradedAt
}
```

### Teaching Slot Model

```js
{
  schoolId,
  slotDate,
  startTime,
  endTime,
  teachersRequired,
  teachersEnrolled,
  status,
  description,
  createdBy,
  enrollments: [
    {
      teacherId,
      status,
      bookedAt,
      cancelledAt,
      cancellationReason
    }
  ],
  createdAt,
  updatedAt
}
```

### Teaching Session Model

```js
{
  slotId,
  teacherId,
  schoolId,
  sessionDate,
  status,
  startPhoto,
  endPhoto,
  validation,
  verifiedBy,
  verifiedAt,
  adminRemarks,
  createdAt,
  updatedAt
}
```

### Exit Criteria

- Mongoose models exist.
- Indexes are added for common queries.
- Schema decisions are documented.
- No API endpoints depend on incomplete model assumptions.

## Phase 3: Authentication and Authorization

### Goal

Replace PHP session login behavior with secure MERN auth.

### Deliverables

- Register/login/logout endpoints
- Auth middleware
- Role middleware
- Current-user endpoint
- React auth context or auth store
- Protected route components

### Backend Endpoints

```text
POST /api/auth/register/student
POST /api/auth/register/teacher
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
POST /api/auth/refresh
```

### Security Requirements

- Use bcrypt.
- Do not store plaintext passwords.
- Do not store MD5 passwords for new users.
- Use rate limiting for login/register.
- Normalize usernames and emails.
- Validate role-specific required fields.
- Admin creation should be controlled, not public by default.

### Frontend Screens

- Student registration
- Teacher registration
- Shared login
- Redirect by role after login:
  - student -> `/student/dashboard`
  - teacher -> `/teacher/dashboard`
  - admin -> `/admin/dashboard`

### Exit Criteria

- All three roles can log in.
- Protected pages cannot be accessed anonymously.
- Student cannot access teacher/admin routes.
- Teacher cannot access admin routes.
- Admin cannot access student-only exam-taking routes.

## Phase 4: School Management and Multi-School Access

### Goal

Rebuild school isolation and teacher-school enrollment.

### Deliverables

- Admin school CRUD
- Student school assignment
- Teacher enrollment in schools
- School-aware API filtering
- Frontend school management screens

### Backend Endpoints

```text
GET    /api/schools
POST   /api/schools
GET    /api/schools/:id
PATCH  /api/schools/:id
DELETE /api/schools/:id

GET    /api/teachers/:teacherId/schools
POST   /api/teachers/:teacherId/schools
PATCH  /api/teachers/:teacherId/schools/:schoolId
DELETE /api/teachers/:teacherId/schools/:schoolId
```

### Access Rules

- Students see only their assigned school data.
- Teachers see data for schools where they are actively enrolled.
- Admins can manage all schools.
- School status must be checked before allowing login or access.

### Exit Criteria

- Teacher can belong to multiple schools.
- Student can access only their school exams.
- Teacher can create exams only for enrolled schools.
- Admin can activate/deactivate schools.

## Phase 5: Student, Teacher, and Admin Dashboards

### Goal

Build the main dashboard shells and navigation for each role.

### Student Dashboard

Widgets:

- Available MCQ exams
- Available objective exams
- Mock exams
- Submitted objective exams
- Graded objective exams
- Pending results
- Unread messages
- Recent results

Routes:

```text
/student/dashboard
/student/exams
/student/objective-exams
/student/mock-exams
/student/results
/student/messages
/student/study-material
/student/settings
/student/help
```

### Teacher Dashboard

Widgets:

- Exams created
- Active exams
- Pending submissions
- Objective grading queue
- Booked teaching slots
- Upcoming sessions
- Recent results

Routes:

```text
/teacher/dashboard
/teacher/exams
/teacher/objective-exams
/teacher/results
/teacher/messages
/teacher/study-material
/teacher/schools
/teacher/teaching-slots
/teacher/settings
```

### Admin Dashboard

Widgets:

- Pending session reviews
- Submitted today
- Distance issues
- Verified by current admin today
- Total slots
- Upcoming slots
- Approved/rejected sessions
- Recent audit actions

Routes:

```text
/admin/dashboard
/admin/schools
/admin/teaching-slots
/admin/session-reviews
/admin/teacher-stats
/admin/force-unenroll
/admin/audit-log
/admin/reports
/admin/settings
```

### Exit Criteria

- Each role has a working shell.
- Navigation matches role permissions.
- Dashboard data comes from API endpoints.
- Layout is responsive.

## Phase 6: MCQ Exams

### Goal

Rebuild standard MCQ exam creation, taking, submission, scoring, and results.

### Teacher Features

- Create MCQ exam.
- Edit exam.
- Delete exam.
- Add/edit/delete questions.
- Add answer options.
- Mark correct option.
- Set duration.
- Assign school.
- Open/close exam.
- View attempts.

### Student Features

- See available MCQ exams.
- Start exam.
- Answer questions.
- Submit exam.
- See result if allowed.
- Prevent duplicate attempts unless configured.

### Proctoring Signals

Port current violation concepts:

- Tab switch
- Window blur/focus loss
- Combined events
- Integrity score
- Integrity category

### Backend Endpoints

```text
GET    /api/exams
POST   /api/exams
GET    /api/exams/:id
PATCH  /api/exams/:id
DELETE /api/exams/:id

POST   /api/exams/:id/start
POST   /api/exams/:id/submit
POST   /api/exams/:id/violations
GET    /api/exams/:id/attempts
GET    /api/results/mcq
```

### Exit Criteria

- Teacher can create an MCQ exam.
- Student can take and submit it.
- Score is calculated automatically.
- Attempt records preserve integrity score.
- Teacher can review attempts and violations.

## Phase 7: Mock Exams

### Goal

Rebuild mock/practice exam generation and attempt flow.

### Features

- Generate mock exams from existing question banks.
- Student can take mock exams.
- Mock attempts are separate from official exam attempts.
- Mock proctoring violations can be recorded separately.
- Results are visible as practice feedback.

### Backend Endpoints

```text
GET  /api/mock-exams
POST /api/mock-exams/generate
GET  /api/mock-exams/:id
POST /api/mock-exams/:id/start
POST /api/mock-exams/:id/submit
POST /api/mock-exams/:id/violations
GET  /api/mock-exams/results
```

### Exit Criteria

- Mock exams can be generated.
- Students can complete mock attempts.
- Mock results do not affect official exam results.

## Phase 8: Objective/Descriptive Exams

### Goal

Rebuild objective/descriptive exam creation, answer-sheet upload, OCR processing, AI grading, and manual grading.

### Teacher Features

- Create objective exam.
- Add questions with marks.
- Set answer key at exam or question level.
- Choose grading mode:
  - manual
  - ai
- Upload answer key file if needed.
- View submissions.
- Trigger OCR.
- Trigger AI grading.
- Review OCR text.
- Override AI marks.
- Add final feedback.
- Publish results.

### Student Features

- View available objective exams.
- Read instructions.
- Upload multiple answer-sheet images.
- Submit before deadline.
- Track status:
  - pending
  - OCR processing
  - OCR complete
  - grading
  - graded
  - error
- View result and feedback.

### Backend Endpoints

```text
GET    /api/objective-exams
POST   /api/objective-exams
GET    /api/objective-exams/:id
PATCH  /api/objective-exams/:id
DELETE /api/objective-exams/:id

POST   /api/objective-exams/:id/questions
PATCH  /api/objective-exams/:id/questions/:questionId
DELETE /api/objective-exams/:id/questions/:questionId

POST   /api/objective-exams/:id/submissions
GET    /api/objective-submissions/:id
POST   /api/objective-submissions/:id/process-ocr
POST   /api/objective-submissions/:id/process-ai
PATCH  /api/objective-submissions/:id/grades
POST   /api/objective-submissions/:id/publish
```

### Upload Requirements

- Validate MIME type.
- Validate extension.
- Enforce file size limit.
- Store files under a controlled upload path.
- Do not trust client-provided filenames.
- Save image order.
- Save upload metadata.

### OCR Service

Responsibilities:

- Check Tesseract availability.
- Process one image.
- Process all images for a submission.
- Save OCR text and confidence.
- Mark failures with error messages.
- Combine OCR text for grading.

### AI Grading Service

Responsibilities:

- Build grading prompt.
- Compare OCR student answer with answer key.
- Award marks within question max marks.
- Return structured JSON when possible.
- Save AI marks and feedback.
- Allow teacher override.

### Exit Criteria

- Objective exam creation works.
- Student answer image upload works.
- OCR can process uploaded images.
- AI grading can grade an OCR-complete submission.
- Teacher can manually finalize marks.

## Phase 9: Messages and Study Material

### Goal

Rebuild announcements and learning-material distribution.

### Message Features

- Teacher/admin can create messages.
- Messages can be scoped by school.
- Students see school-relevant messages.
- Read/unread tracking.
- Unread count on dashboard.

### Study Material Features

- Teacher can upload material.
- Students can view/download material for their school/subject.
- File validation and access control.

### Backend Endpoints

```text
GET   /api/messages
POST  /api/messages
PATCH /api/messages/:id/read
GET   /api/messages/unread-count

GET   /api/study-material
POST  /api/study-material
GET   /api/study-material/:id/download
DELETE /api/study-material/:id
```

### Exit Criteria

- Messages are role/school scoped.
- Read state works per user.
- Study materials are uploadable and downloadable with authorization.

## Phase 10: Teaching Slots and Session Verification

### Goal

Rebuild the teaching activity workflow with stronger service boundaries.

### Admin Features

- Create teaching slots by school.
- Set date, start time, end time, and teachers required.
- View slot fill status.
- Review pending sessions.
- Inspect start/end photos.
- Inspect GPS and EXIF validation.
- Approve, reject, or mark partial.
- Add remarks.
- View audit trail.

### Teacher Features

- Browse available slots.
- Book slots.
- Cancel booking.
- View own slots.
- Upload start photo.
- Upload end photo.
- See validation status.

### Validation Rules

Port existing validation concepts:

- Start photo required.
- End photo required for full approval.
- GPS data can be required by settings.
- Distance from school must be within configured radius.
- Start photo cannot be too early or too late.
- End photo must match expected slot duration.
- Actual duration must meet minimum percentage threshold.
- Admin can manually review edge cases.

### Backend Endpoints

```text
GET    /api/teaching-slots
POST   /api/teaching-slots
GET    /api/teaching-slots/:id
PATCH  /api/teaching-slots/:id
DELETE /api/teaching-slots/:id

POST   /api/teaching-slots/:id/book
POST   /api/teaching-slots/:id/cancel

GET    /api/teaching-sessions
GET    /api/teaching-sessions/:id
POST   /api/teaching-sessions/:id/start-photo
POST   /api/teaching-sessions/:id/end-photo
POST   /api/teaching-sessions/:id/approve
POST   /api/teaching-sessions/:id/reject
```

### Services

- `ExifService`
- `LocationValidationService`
- `DurationValidationService`
- `SessionValidationService`
- `TeachingSessionAuditService`

### Exit Criteria

- Admin can create slots.
- Teacher can book and upload verification photos.
- GPS/EXIF/duration checks run server-side.
- Admin can approve/reject sessions.
- Audit logs are written.

## Phase 11: Certificates and NFT Minting

### Goal

Rebuild certificate generation, IPFS upload, NFT minting, and certificate records.

### Student Certificates

- Generate certificate after eligible exam completion.
- Store certificate metadata.
- Upload metadata/image to IPFS.
- Mint NFT against deployed contract.
- Save transaction hash, token id, contract address, metadata URL, image URL.
- Allow demo mode if blockchain config is missing.

### Teacher Certificates

- Generate teaching certificate for approved activity.
- Calculate activity points:
  - activity points = hours taught * 2
- Store and mint certificate.

### Backend Endpoints

```text
POST /api/certificates/student/:attemptId/generate
POST /api/certificates/student/:attemptId/mint

POST /api/certificates/teacher/:teacherId/generate
POST /api/certificates/teacher/:certificateId/mint

GET  /api/certificates
GET  /api/certificates/:id
```

### Services

- `CertificateRenderService`
- `PinataService`
- `BlockchainService`
- `CertificateEmailService`

### Blockchain Requirements

- Use `ethers`.
- Never expose private keys to the frontend.
- All signing must happen server-side.
- Validate contract address and RPC URL at startup if minting is enabled.
- Keep Sepolia support first.

### Exit Criteria

- Certificate can be generated.
- IPFS upload works with configured Pinata credentials.
- NFT minting works with server-side wallet.
- Certificate records are saved.
- Certificate email can be sent.

## Phase 12: Admin Reports, Analytics, and Audit Logs

### Goal

Rebuild operational reporting and audit visibility.

### Reports

- Exam reports by school.
- Student performance reports.
- Objective grading status reports.
- Teaching slot fulfillment.
- Approved/rejected teaching sessions.
- Teacher activity points.
- Certificate issuance records.

### Audit Logs

Track actions such as:

- Admin login
- School create/update/deactivate
- Slot create/update/delete
- Session approve/reject
- Force unenroll
- Settings update

### Backend Endpoints

```text
GET /api/admin/reports/overview
GET /api/admin/reports/exams
GET /api/admin/reports/teaching-sessions
GET /api/admin/reports/certificates
GET /api/admin/audit-log
```

### Exit Criteria

- Admin dashboard reports match or improve PHP behavior.
- Audit log writes are centralized.
- Export support is planned or implemented.

## Phase 13: Data Migration

### Goal

Move existing MySQL data into MongoDB if preserving current data is required.

### Important Decision

Before this phase, decide one of these:

1. Fresh MERN launch with empty MongoDB.
2. Partial migration of users/schools/exams only.
3. Full migration including attempts, submissions, certificates, and logs.

### Migration Tasks

- Export MySQL tables.
- Create transformation scripts in `migration/transforms`.
- Map MySQL integer IDs to MongoDB ObjectIds.
- Preserve original IDs in `legacyId` fields.
- Migrate users.
- Migrate schools.
- Migrate teacher-school relationships.
- Migrate MCQ exams and questions.
- Migrate attempts and results.
- Migrate objective exams and submissions.
- Migrate teaching sessions.
- Migrate certificates.
- Generate migration report.

### Password Migration

Current PHP uses MD5 for some users. Recommended options:

- Force password reset for migrated users.
- Or support one-time transitional login:
  - Compare submitted password to old MD5 hash.
  - On success, rehash with bcrypt.
  - Remove legacy hash after migration.

### Exit Criteria

- Migration script can run repeatedly in a safe test database.
- Record counts match expected totals.
- Random migrated records are manually verified.
- Login strategy for legacy users is confirmed.

## Phase 14: Security Hardening

### Goal

Prepare MERN app for safer real-world use.

### Tasks

- Add CSRF protection if using cookies.
- Add input validation to every write endpoint.
- Add rate limiting to auth and upload endpoints.
- Add file upload scanning/validation.
- Restrict CORS.
- Add Helmet.
- Add secure cookie options.
- Sanitize user-generated display content.
- Centralize authorization checks.
- Ensure private keys never leave server.
- Add audit logging around admin actions.
- Add error responses that do not leak internals.

### Exit Criteria

- Security checklist completed.
- Sensitive endpoints have tests.
- Secrets are not committed.
- Upload paths are protected.

## Phase 15: Test Coverage and QA

### Goal

Build confidence before retiring PHP flows.

### Backend Tests

- Auth
- Role access
- School isolation
- MCQ scoring
- Violation/integrity scoring
- Objective submission state transitions
- OCR service with mocked Tesseract
- AI grader with mocked Groq API
- Teaching session validation
- Certificate minting with mocked ethers

### Frontend Tests

- Login flow
- Protected route redirects
- Student dashboard rendering
- Teacher exam creation forms
- Admin session review screens
- Upload form validation

### E2E Tests

- Student takes MCQ exam.
- Teacher creates objective exam.
- Student submits answer images.
- Teacher reviews grading.
- Teacher books slot and uploads photos.
- Admin approves session.

### Exit Criteria

- Critical backend tests pass.
- Main user flows pass in browser.
- No known blocker bugs remain for the migrated feature set.

## Phase 16: Cutover Plan

### Goal

Switch from PHP to MERN safely.

### Tasks

- Choose cutover mode:
  - local demo
  - staging
  - production
- Freeze PHP data writes if doing final migration.
- Run final migration.
- Verify MongoDB data.
- Start Node server.
- Build React client.
- Configure Apache/Nginx proxy if needed.
- Verify all role logins.
- Verify uploads.
- Verify OCR/AI jobs.
- Verify certificate generation.
- Keep PHP backup available until signoff.

### Exit Criteria

- MERN app handles all required flows.
- PHP app is no longer required for active use.
- Rollback plan exists.

## Suggested Implementation Order

1. Scaffold MERN app.
2. Auth and role routing.
3. Schools and user management.
4. Dashboards.
5. MCQ exams.
6. Mock exams.
7. Objective exams.
8. OCR and AI grading.
9. Messages and study material.
10. Teaching slots.
11. Session verification.
12. Certificates and NFT minting.
13. Reports and audit logs.
14. Data migration.
15. Security and tests.
16. Cutover.

## Initial API Map

```text
/api/health
/api/auth
/api/users
/api/schools
/api/exams
/api/mock-exams
/api/objective-exams
/api/objective-submissions
/api/messages
/api/study-material
/api/teaching-slots
/api/teaching-sessions
/api/certificates
/api/admin
/api/reports
```

## Initial Frontend Route Map

```text
/
/login
/register/student
/register/teacher

/student/dashboard
/student/exams
/student/exams/:id
/student/objective-exams
/student/objective-exams/:id
/student/mock-exams
/student/results
/student/messages
/student/study-material
/student/settings

/teacher/dashboard
/teacher/exams
/teacher/exams/new
/teacher/exams/:id
/teacher/objective-exams
/teacher/objective-exams/new
/teacher/objective-exams/:id
/teacher/results
/teacher/messages
/teacher/study-material
/teacher/schools
/teacher/teaching-slots
/teacher/settings

/admin/dashboard
/admin/schools
/admin/teaching-slots
/admin/session-reviews
/admin/session-reviews/:id
/admin/teacher-stats
/admin/audit-log
/admin/reports
/admin/settings
```

## Migration Risk Register

### High Risk

- Data migration from MySQL relational tables into MongoDB documents.
- Objective exam grading parity.
- OCR reliability on local Windows/XAMPP environments.
- Teaching-session validation parity.
- Blockchain minting security.
- Legacy password migration.

### Medium Risk

- Recreating dashboards accurately.
- File upload path changes.
- Admin report parity.
- Certificate rendering parity.
- Multi-school access edge cases.

### Low Risk

- Basic React routing.
- Health checks.
- Static dashboard shells.
- New user registration with bcrypt.

## Definition of Done for Each Phase

Each phase should be considered complete only when:

- Backend routes are implemented.
- Frontend screens exist where needed.
- Role-based access is enforced.
- Inputs are validated.
- Error states are handled.
- Relevant tests are added or a test gap is documented.
- Manual verification steps are written.
- Existing PHP app is not broken by the changes.

## Commit Strategy

Use small commits by phase or sub-feature.

Suggested commit examples:

```text
docs: add MERN migration plan
feat(server): scaffold express app
feat(client): scaffold react shell
feat(auth): add role based login
feat(schools): add school management api
feat(exams): add mcq exam creation flow
```

## Immediate Next Step

After this plan is reviewed, the first implementation step should be Phase 1:

1. Create `server/`.
2. Create `client/`.
3. Add Express health endpoint.
4. Add Vite React app shell.
5. Verify both apps run locally.

