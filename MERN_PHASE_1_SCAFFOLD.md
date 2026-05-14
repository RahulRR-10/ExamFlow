# ExamFlow MERN Migration - Phase 1 Scaffold

## Phase 1 Status

Status: complete  
Branch: `node`  
Legacy PHP app changed: no  

Phase 1 adds a side-by-side MERN scaffold:

- `server/` Express API
- `client/` Vite React app
- API health endpoint
- MongoDB connection configuration
- React app shell with placeholder role routes

## Added Backend Files

- `server/package.json`
- `server/.env.example`
- `server/src/app.js`
- `server/src/server.js`
- `server/src/config/env.js`
- `server/src/config/db.js`
- `server/src/middleware/errorHandler.js`
- `server/src/middleware/notFound.js`
- `server/src/routes/health.routes.js`

## Added Frontend Files

- `client/package.json`
- `client/.env.example`
- `client/index.html`
- `client/vite.config.js`
- `client/src/main.jsx`
- `client/src/App.jsx`
- `client/src/lib/api.js`
- `client/src/styles.css`

## API Endpoints

```text
GET /api
GET /api/health
```

The health endpoint returns:

- API status
- timestamp
- MongoDB connection status

The server is intentionally tolerant of MongoDB being unavailable during early local setup. If MongoDB is not running, the API still starts and reports the database status as `error`.

## Frontend Routes

```text
/
/login
/student/dashboard
/teacher/dashboard
/admin/dashboard
```

These are placeholder shells only. Auth, protected routing, dashboards, and real data come in later phases.

## Local Setup

Backend:

```bash
cd server
copy .env.example .env
npm install
npm run dev
```

Frontend:

```bash
cd client
copy .env.example .env
npm install
npm run dev
```

Default URLs:

```text
API:    http://localhost:5000/api/health
Client: http://localhost:5173
```

## Phase 1 Assumptions

- MongoDB local URI defaults to `mongodb://127.0.0.1:27017/examflow`.
- The first scaffold uses a custom React shell rather than a component library.
- Auth strategy is not locked in during Phase 1.
- The PHP app remains the source of truth until feature parity is reached.

## Phase 1 Exit Criteria

- `server/` exists.
- `client/` exists.
- Health endpoint exists.
- React app shell exists.
- PHP app remains untouched.
- Dependencies are installed.
- Client production build passes.
- API health endpoint returns `200` in a smoke test.
