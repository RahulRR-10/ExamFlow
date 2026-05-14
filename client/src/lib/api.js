const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:5000/api';

async function apiRequest(path, options = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      ...options.headers
    },
    credentials: 'include',
    ...options
  });

  if (!response.ok) {
    let message = `Request failed with status ${response.status}`;

    try {
      const data = await response.json();
      message = data.error?.message || message;
    } catch (_error) {
      // Keep the generic message if the response is not JSON.
    }

    throw new Error(message);
  }

  if (response.status === 204) {
    return null;
  }

  return response.json();
}

export function getHealth() {
  return apiRequest('/health');
}

export function getCurrentUser() {
  return apiRequest('/auth/me');
}

export function loginUser(payload) {
  return apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify(payload)
  });
}

export function logoutUser() {
  return apiRequest('/auth/logout', {
    method: 'POST'
  });
}
