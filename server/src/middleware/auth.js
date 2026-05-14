import { User } from '../models/index.js';
import { verifyAccessToken } from '../services/auth/token.service.js';
import { ApiError } from '../utils/apiError.js';
import { asyncHandler } from '../utils/asyncHandler.js';

function getBearerToken(req) {
  const header = req.get('authorization');

  if (!header?.startsWith('Bearer ')) {
    return null;
  }

  return header.slice('Bearer '.length).trim();
}

export const requireAuth = asyncHandler(async (req, _res, next) => {
  const token = req.cookies?.accessToken || getBearerToken(req);

  if (!token) {
    throw new ApiError(401, 'Authentication required');
  }

  let payload;
  try {
    payload = verifyAccessToken(token);
  } catch (_error) {
    throw new ApiError(401, 'Invalid or expired access token');
  }

  if (payload.type !== 'access') {
    throw new ApiError(401, 'Invalid token type');
  }

  const user = await User.findById(payload.sub);

  if (!user || user.status !== 'active') {
    throw new ApiError(401, 'User is not active or no longer exists');
  }

  req.user = user;
  next();
});

export function requireRole(...roles) {
  return (req, _res, next) => {
    if (!req.user) {
      next(new ApiError(401, 'Authentication required'));
      return;
    }

    if (!roles.includes(req.user.role)) {
      next(new ApiError(403, 'You do not have permission to access this resource'));
      return;
    }

    next();
  };
}
