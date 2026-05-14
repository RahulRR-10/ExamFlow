# ExamFlow MERN Migration - Phase 2 Models

## Phase 2 Status

Status: complete  
Branch: `node`  
Legacy PHP app changed: no  

Phase 2 adds the initial Mongoose model layer for the MERN rewrite. These models are not wired to API routes yet; they define the first stable persistence shape for later auth, school access, exams, submissions, teaching sessions, certificates, and audit logs.

## Added Model Files

Shared model support:

- `server/src/models/constants.js`
- `server/src/models/shared.schema.js`
- `server/src/models/index.js`

Core collections:

- `server/src/models/user.model.js`
- `server/src/models/school.model.js`
- `server/src/models/teacherSchoolMembership.model.js`
- `server/src/models/exam.model.js`
- `server/src/models/examAttempt.model.js`
- `server/src/models/mockExam.model.js`
- `server/src/models/mockAttempt.model.js`
- `server/src/models/objectiveExam.model.js`
- `server/src/models/objectiveSubmission.model.js`
- `server/src/models/message.model.js`
- `server/src/models/studyMaterial.model.js`
- `server/src/models/teachingSlot.model.js`
- `server/src/models/teachingSession.model.js`
- `server/src/models/certificate.model.js`
- `server/src/models/adminAuditLog.model.js`
- `server/src/models/uploadRateLimit.model.js`

## Collection Plan

| Model | Collection Purpose |
|---|---|
| `User` | Student, teacher, and admin accounts |
| `School` | Institution records and verification settings |
| `TeacherSchoolMembership` | Teacher enrollment across schools |
| `Exam` | Official MCQ exams |
| `ExamAttempt` | Student official MCQ attempts and violations |
| `MockExam` | Generated or teacher-created practice exams |
| `MockAttempt` | Student mock exam attempts and mock violations |
| `ObjectiveExam` | Descriptive/objective upload-based exams |
| `ObjectiveSubmission` | Answer images, OCR state, and grading results |
| `Message` | Announcements with read tracking |
| `StudyMaterial` | Teacher-uploaded study files |
| `TeachingSlot` | Admin-created teaching slots with embedded bookings |
| `TeachingSession` | Teacher proof photos, validation, and admin review |
| `Certificate` | Student and teacher certificate records plus NFT state |
| `AdminAuditLog` | Admin action history |
| `UploadRateLimit` | Upload throttling counters |

## Key Schema Decisions

### User Accounts

All roles share one `User` collection with a required `role`:

- `student`
- `teacher`
- `admin`

Role-specific fields live in:

- `studentProfile`
- `teacherProfile`
- `adminProfile`

This avoids three separate auth systems while still preserving role-specific details.

### Passwords

The MERN model stores only `passwordHash`, with `select: false`.

Legacy MD5 hashes are not modeled as first-class fields. If legacy user migration is needed, a transitional migration/login strategy should be implemented in Phase 13 and removed after password rehashing.

### Legacy Mapping

Most models include:

- `legacyId`
- `legacyTable`

This keeps the MongoDB design flexible while preserving a path for MySQL migration.

### School Isolation

School-scoped models include `schoolId` indexes:

- `User.studentProfile.schoolId`
- `TeacherSchoolMembership.schoolId`
- `Exam.schoolId`
- `MockExam.schoolId`
- `ObjectiveExam.schoolId`
- `Message.schoolId`
- `StudyMaterial.schoolId`
- `TeachingSlot.schoolId`
- `TeachingSession.schoolId`
- `Certificate.schoolId`

School access must still be enforced in service/controller middleware during later phases. Indexes alone do not provide authorization.

### Teacher Membership

Teacher-school access is modeled as a separate collection instead of embedding all schools on `User`.

Reason:

- Easier status tracking
- Easier auditability
- Supports future enrollment metadata
- Avoids frequently rewriting the user document

### MCQ Exams

Official MCQ questions and answer options are embedded in `Exam`.

Reason:

- Exams are usually read as a complete unit during attempt-taking.
- Embedded options simplify scoring and reduce cross-collection joins.
- `legacyId` remains available on embedded questions for migration.

### Attempts and Violations

Official exam attempts store answers and violations in `ExamAttempt`.

Mock attempts use a separate `MockAttempt` collection so practice results remain isolated from official results.

### Objective Exams

`ObjectiveExam` embeds descriptive questions. `ObjectiveSubmission` embeds answer images and grade records.

Reason:

- Submission lifecycle is document-centered.
- OCR status and grading status are naturally tied to the submission.
- Teacher overrides can update the same submission document.

### Teaching Slots

`TeachingSlot` embeds bookings/enrollments.

Reason:

- Slot fill status and enrollment checks are tightly coupled.
- It mirrors how admins and teachers interact with one slot at a time.

`TeachingSession` remains separate because proof photos, validation details, and admin review can become large and operationally independent.

### Certificates

`Certificate` handles both:

- student exam certificates
- teacher activity certificates

NFT mint state is embedded under `blockchain`:

- status
- transaction hash
- token ID
- contract address
- metadata URL
- image URL
- error message

All future minting should happen server-side.

## Important Indexes

Examples added:

- unique `User` username per role
- unique `User` email per role
- unique `School.code`
- unique `School.name`
- unique `TeacherSchoolMembership` teacher/school pair
- unique official `ExamAttempt` exam/student pair
- unique `ObjectiveSubmission` exam/student pair
- school/status indexes for dashboards and lists
- admin/action/date indexes for audit log review
- upload rate limit unique user/scope/date key

## Known Follow-Up Work

Phase 2 intentionally does not include:

- API controllers
- validation schemas
- auth middleware
- seed scripts
- migration scripts
- service-layer authorization
- frontend data fetching beyond health check

These arrive in later phases.

## Verification

Expected verification commands:

```bash
cd server
node -e "import('./src/models/index.js').then((models) => console.log(Object.keys(models).sort().join('\n')))"
```

```bash
cd client
npm run build
```

```bash
cd server
node -e "import('./src/app.js').then(({ createApp }) => { const app = createApp(); const server = app.listen(0, async () => { const port = server.address().port; const response = await fetch('http://127.0.0.1:' + port + '/api/health'); console.log(response.status); server.close(); }); })"
```

## Phase 3 Readiness

Phase 3 can now begin with authentication and authorization:

1. Add auth validators.
2. Add password hashing helpers.
3. Add register/login/logout/current-user routes.
4. Add role middleware.
5. Add protected route handling in React.

