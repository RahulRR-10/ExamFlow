import { Router } from 'express';

import {
  login,
  logout,
  me,
  refresh,
  registerStudent,
  registerTeacher
} from '../controllers/auth.controller.js';
import { requireAuth } from '../middleware/auth.js';
import { authRateLimiter } from '../middleware/rateLimiters.js';
import { validateRequest } from '../middleware/validateRequest.js';
import { asyncHandler } from '../utils/asyncHandler.js';
import {
  loginSchema,
  refreshSchema,
  registerStudentSchema,
  registerTeacherSchema
} from '../validators/auth.validators.js';

const router = Router();

router.post(
  '/register/student',
  authRateLimiter,
  validateRequest(registerStudentSchema),
  asyncHandler(registerStudent)
);

router.post(
  '/register/teacher',
  authRateLimiter,
  validateRequest(registerTeacherSchema),
  asyncHandler(registerTeacher)
);

router.post(
  '/login',
  authRateLimiter,
  validateRequest(loginSchema),
  asyncHandler(login)
);

router.post(
  '/refresh',
  validateRequest(refreshSchema),
  asyncHandler(refresh)
);

router.post('/logout', asyncHandler(logout));
router.get('/me', requireAuth, asyncHandler(me));

export default router;
