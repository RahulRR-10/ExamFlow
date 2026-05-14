import { Navigate, useLocation } from 'react-router-dom';

import { useAuth } from './AuthContext.jsx';

export function ProtectedRoute({ role, children }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return (
      <section className="panel compact">
        <span className="eyebrow">Auth</span>
        <h1>Checking session</h1>
      </section>
    );
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (role && user.role !== role) {
    return <Navigate to={`/${user.role}/dashboard`} replace />;
  }

  return children;
}
