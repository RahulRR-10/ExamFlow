import { useEffect, useState } from 'react';
import {
  Link,
  Navigate,
  Route,
  Routes,
  useLocation,
  useNavigate
} from 'react-router-dom';

import { ProtectedRoute } from './auth/ProtectedRoute.jsx';
import { useAuth } from './auth/AuthContext.jsx';
import { getHealth } from './lib/api.js';

const roleRoutes = [
  { label: 'Student', path: '/student/dashboard' },
  { label: 'Teacher', path: '/teacher/dashboard' },
  { label: 'Admin', path: '/admin/dashboard' }
];

function Shell({ children }) {
  const location = useLocation();
  const { user, logout } = useAuth();

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

        <div className="session-card">
          {user ? (
            <>
              <span>{user.role}</span>
              <strong>{user.firstName}</strong>
              <button type="button" onClick={logout}>
                Log out
              </button>
            </>
          ) : (
            <>
              <span>Session</span>
              <strong>Signed out</strong>
            </>
          )}
        </div>
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
  const navigate = useNavigate();
  const location = useLocation();
  const { login, user } = useAuth();
  const [form, setForm] = useState({
    role: 'student',
    identifier: '',
    password: ''
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (user) {
      navigate(`/${user.role}/dashboard`, { replace: true });
    }
  }, [navigate, user]);

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');

    try {
      const loggedInUser = await login(form);
      const redirectTo =
        location.state?.from?.pathname || `/${loggedInUser.role}/dashboard`;
      navigate(redirectTo, { replace: true });
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="panel compact auth-panel">
      <span className="eyebrow">Auth</span>
      <h1>Login</h1>
      <p>
        This is the first MERN auth surface. It uses the new Express auth API,
        HTTP-only cookies, and role-aware redirects.
      </p>

      <form className="auth-form" onSubmit={handleSubmit}>
        <label>
          Role
          <select
            value={form.role}
            onChange={(event) =>
              setForm((current) => ({ ...current, role: event.target.value }))
            }
          >
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </label>

        <label>
          Username or email
          <input
            value={form.identifier}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                identifier: event.target.value
              }))
            }
            required
          />
        </label>

        <label>
          Password
          <input
            type="password"
            value={form.password}
            onChange={(event) =>
              setForm((current) => ({ ...current, password: event.target.value }))
            }
            required
          />
        </label>

        {error ? <p className="error-text">{error}</p> : null}

        <button type="submit" disabled={submitting}>
          {submitting ? 'Signing in...' : 'Sign in'}
        </button>
      </form>
    </section>
  );
}

function RoleDashboard({ role }) {
  const { user } = useAuth();

  return (
    <section className="panel compact">
      <span className="eyebrow">{role}</span>
      <h1>{role} dashboard shell</h1>
      <p>
        This route is reserved for the {role.toLowerCase()} portal. Dashboard
        data and protected routing will arrive after the auth and model phases.
      </p>
      <p className="muted">
        Signed in as {user?.firstName} ({user?.username}).
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
          element={
            <ProtectedRoute role="student">
              <RoleDashboard role="Student" />
            </ProtectedRoute>
          }
        />
        <Route
          path="/teacher/dashboard"
          element={
            <ProtectedRoute role="teacher">
              <RoleDashboard role="Teacher" />
            </ProtectedRoute>
          }
        />
        <Route
          path="/admin/dashboard"
          element={
            <ProtectedRoute role="admin">
              <RoleDashboard role="Admin" />
            </ProtectedRoute>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Shell>
  );
}

export default App;
