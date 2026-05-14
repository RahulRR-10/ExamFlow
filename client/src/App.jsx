import { useEffect, useState } from 'react';
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom';

import { getHealth } from './lib/api.js';

const roleRoutes = [
  { label: 'Student', path: '/student/dashboard' },
  { label: 'Teacher', path: '/teacher/dashboard' },
  { label: 'Admin', path: '/admin/dashboard' }
];

function Shell({ children }) {
  const location = useLocation();

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark">EF</span>
          <div>
            <strong>ExamFlow</strong>
            <span>MERN migration</span>
          </div>
        </div>

        <nav className="nav-list" aria-label="Primary">
          <Link className={location.pathname === '/login' ? 'active' : ''} to="/login">
            Login
          </Link>
          {roleRoutes.map((route) => (
            <Link
              className={location.pathname === route.path ? 'active' : ''}
              key={route.path}
              to={route.path}
            >
              {route.label}
            </Link>
          ))}
        </nav>
      </aside>

      <main className="main-panel">{children}</main>
    </div>
  );
}

function HealthPanel() {
  const [health, setHealth] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let mounted = true;

    getHealth()
      .then((data) => {
        if (mounted) {
          setHealth(data);
        }
      })
      .catch((requestError) => {
        if (mounted) {
          setError(requestError.message);
        }
      });

    return () => {
      mounted = false;
    };
  }, []);

  return (
    <section className="panel">
      <div className="section-heading">
        <span className="eyebrow">Phase 1</span>
        <h1>MERN scaffold is running</h1>
      </div>
      <p className="lede">
        This React shell is wired to the new Express API health endpoint. The
        legacy PHP application remains untouched while the MERN app grows beside
        it.
      </p>

      <div className="status-grid">
        <div className="status-tile">
          <span>API</span>
          <strong>{health ? health.status : error ? 'offline' : 'checking'}</strong>
        </div>
        <div className="status-tile">
          <span>Database</span>
          <strong>{health?.database?.status || 'pending'}</strong>
        </div>
        <div className="status-tile">
          <span>Branch</span>
          <strong>node</strong>
        </div>
      </div>

      {error ? <p className="error-text">{error}</p> : null}
    </section>
  );
}

function LoginPage() {
  return (
    <section className="panel compact">
      <span className="eyebrow">Auth</span>
      <h1>Login placeholder</h1>
      <p>
        Phase 3 will replace the legacy PHP login flows with secure MERN auth,
        role checks, and password hashing.
      </p>
    </section>
  );
}

function RoleDashboard({ role }) {
  return (
    <section className="panel compact">
      <span className="eyebrow">{role}</span>
      <h1>{role} dashboard shell</h1>
      <p>
        This route is reserved for the {role.toLowerCase()} portal. Dashboard
        data and protected routing will arrive after the auth and model phases.
      </p>
    </section>
  );
}

function App() {
  return (
    <Shell>
      <Routes>
        <Route path="/" element={<HealthPanel />} />
        <Route path="/login" element={<LoginPage />} />
        <Route
          path="/student/dashboard"
          element={<RoleDashboard role="Student" />}
        />
        <Route
          path="/teacher/dashboard"
          element={<RoleDashboard role="Teacher" />}
        />
        <Route path="/admin/dashboard" element={<RoleDashboard role="Admin" />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Shell>
  );
}

export default App;
