# ExamFlow MERN Migration - Phase 3 Auth

## Phase 3 Status

Status: complete  
Branch: `node`  
Legacy PHP app changed: no  

Phase 3 adds the first MERN authentication and authorization layer. It does not migrate existing PHP users yet; that remains a later data-migration concern.

## Backend Auth Files Added

Controllers and routes:

- `server/src/controllers/auth.controller.js`
- `server/src/routes/auth.routes.js`

Middleware:

- `server/src/middleware/auth.js`
- `server/src/middleware/rateLimiters.js`
- `server/src/middleware/validateRequest.js`

Services:

- `server/src/services/auth/password.service.js`
- `server/src/services/auth/token.service.js`
- `server/src/services/auth/userPresenter.js`

Utilities:

- `server/src/utils/apiError.js`
- `server/src/utils/asyncHandler.js`

Validators:

- `server/src/validators/auth.validators.js`

Updated:

- `server/src/app.js`
- `server/src/middleware/errorHandler.js`

## Backend Endpoints

```text
POST /api/auth/register/student
POST /api/auth/register/teacher
POST /api/auth/login
POST /api/auth/refresh
POST /api/auth/logout
GET  /api/auth/me
```

## Auth Behavior

### Registration

Student registration:

- validates first name, email, username, password, optional school, date of birth, and gender
- checks selected school exists and is active when `schoolId` is provided
- hashes password with bcrypt
- creates a `User` with role `student`
- returns sanitized user data
- sets HTTP-only auth cookies

Teacher registration:

- validates first name, email, username, password, subject, and optional primary school
- checks selected school exists and is active when `primarySchoolId` is provided
- hashes password with bcrypt
- creates a `User` with role `teacher`
- creates a primary `TeacherSchoolMembership` when a primary school is provided
- returns sanitized user data
- sets HTTP-only auth cookies

Admin registration is intentionally not public in Phase 3.

### Login

Login accepts:

- `role`
- `identifier`
- `password`

The identifier can be username or email.

Login checks:

- matching role
- active user status
- bcrypt password match
- active school for students with an assigned school

### Tokens

The server issues:

- short-lived access token
- longer-lived refresh token

The access token is returned in the JSON response for development convenience and also written to an HTTP-only cookie.

Both tokens include:

- user id as `sub`
- user role
- token type

### Cookies

Cookies are:

- HTTP-only
- `sameSite=lax` in development
- `sameSite=none` and `secure=true` in production

### Authorization Middleware

Added middleware:

- `requireAuth`
- `requireRole(...roles)`

These will be used by school, exam, objective exam, teaching slot, certificate, and admin routes in later phases.

## Frontend Auth Files Added

- `client/src/auth/AuthContext.jsx`
- `client/src/auth/ProtectedRoute.jsx`

Updated:

- `client/src/lib/api.js`
- `client/src/main.jsx`
- `client/src/App.jsx`
- `client/src/styles.css`

## Frontend Behavior

The React app now has:

- an auth provider
- current-user session check
- login form
- logout button
- protected role dashboard shells
- role-aware redirects

Protected routes:

```text
/student/dashboard -> student only
/teacher/dashboard -> teacher only
/admin/dashboard   -> admin only
```

Unauthenticated users are redirected to:

```text
/login
```

## Security Notes

Phase 3 improves over the legacy PHP auth approach by introducing:

- bcrypt password hashing
- request validation with Zod
- HTTP-only cookies
- JWT access/refresh token split
- auth rate limiting
- centralized role middleware
- sanitized user responses

Still pending:

- CSRF protection decision for cookie auth
- admin bootstrap/seed mechanism
- password reset flow
- email verification
- legacy MD5 migration strategy
- refresh token persistence/revocation list

## Verification

Expected checks:

```bash
cd server
node -e "import('./src/app.js').then(({ createApp }) => { const app = createApp(); const server = app.listen(0, async () => { const port = server.address().port; const response = await fetch('http://127.0.0.1:' + port + '/api/auth/me'); console.log(response.status); server.close(); }); })"
```

Expected result:

```text
401
```

Invalid login validation smoke check:

```bash
cd server
node -e "import('./src/app.js').then(({ createApp }) => { const app = createApp(); const server = app.listen(0, async () => { const port = server.address().port; const response = await fetch('http://127.0.0.1:' + port + '/api/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }); console.log(response.status); server.close(); }); })"
```

Expected result:

```text
400
```

Client check:

```bash
cd client
npm run build
```

## Phase 4 Readiness

Phase 4 can now build on auth to add:

1. school CRUD routes
2. teacher-school enrollment routes
3. school-aware API filtering
4. admin-only route guards
5. basic school management UI

