import jwt from 'jsonwebtoken';

import { env } from '../../config/env.js';

const ACCESS_TOKEN_TTL = '15m';
const REFRESH_TOKEN_TTL = '7d';

export function createAccessToken(user) {
  return jwt.sign(
    {
      sub: user._id.toString(),
      role: user.role,
      type: 'access'
    },
    env.JWT_ACCESS_SECRET,
    { expiresIn: ACCESS_TOKEN_TTL }
  );
}

export function createRefreshToken(user) {
  return jwt.sign(
    {
      sub: user._id.toString(),
      role: user.role,
      type: 'refresh'
    },
    env.JWT_REFRESH_SECRET,
    { expiresIn: REFRESH_TOKEN_TTL }
  );
}

export function verifyAccessToken(token) {
  return jwt.verify(token, env.JWT_ACCESS_SECRET);
}

export function verifyRefreshToken(token) {
  return jwt.verify(token, env.JWT_REFRESH_SECRET);
}

export function getAuthCookieOptions() {
  return {
    httpOnly: true,
    secure: env.NODE_ENV === 'production',
    sameSite: env.NODE_ENV === 'production' ? 'none' : 'lax',
    path: '/'
  };
}

export function setAuthCookies(res, { accessToken, refreshToken }) {
  const options = getAuthCookieOptions();

  res.cookie('accessToken', accessToken, {
    ...options,
    maxAge: 15 * 60 * 1000
  });

  res.cookie('refreshToken', refreshToken, {
    ...options,
    maxAge: 7 * 24 * 60 * 60 * 1000
  });
}

export function clearAuthCookies(res) {
  const options = getAuthCookieOptions();
  res.clearCookie('accessToken', options);
  res.clearCookie('refreshToken', options);
}
