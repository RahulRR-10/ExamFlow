import { School, TeacherSchoolMembership, User } from '../models/index.js';
import { hashPassword, verifyPassword } from '../services/auth/password.service.js';
import {
  clearAuthCookies,
  createAccessToken,
  createRefreshToken,
  setAuthCookies,
  verifyRefreshToken
} from '../services/auth/token.service.js';
import { presentUser } from '../services/auth/userPresenter.js';
import { ApiError } from '../utils/apiError.js';

function issueAuthResponse(res, user, statusCode = 200) {
  const accessToken = createAccessToken(user);
  const refreshToken = createRefreshToken(user);

  setAuthCookies(res, { accessToken, refreshToken });

  res.status(statusCode).json({
    user: presentUser(user),
    accessToken
  });
}

async function assertActiveSchool(schoolId) {
  if (!schoolId) {
    return null;
  }

  const school = await School.findById(schoolId);

  if (!school) {
    throw new ApiError(400, 'Selected school does not exist');
  }

  if (school.status !== 'active') {
    throw new ApiError(400, 'Selected school is not active');
  }

  return school;
}

async function assertUniqueUser({ role, username, email }) {
  const existing = await User.findOne({
    role,
    $or: [{ username }, { email }]
  });

  if (existing) {
    throw new ApiError(409, 'A user with this username or email already exists');
  }
}

export async function registerStudent(req, res) {
  const payload = req.validated.body;
  await assertActiveSchool(payload.schoolId);
  await assertUniqueUser({
    role: 'student',
    username: payload.username,
    email: payload.email
  });

  const user = await User.create({
    role: 'student',
    firstName: payload.firstName,
    email: payload.email,
    username: payload.username,
    passwordHash: await hashPassword(payload.password),
    studentProfile: {
      schoolId: payload.schoolId,
      dateOfBirth: payload.dateOfBirth,
      gender: payload.gender
    }
  });

  issueAuthResponse(res, user, 201);
}

export async function registerTeacher(req, res) {
  const payload = req.validated.body;
  await assertActiveSchool(payload.primarySchoolId);
  await assertUniqueUser({
    role: 'teacher',
    username: payload.username,
    email: payload.email
  });

  const user = await User.create({
    role: 'teacher',
    firstName: payload.firstName,
    email: payload.email,
    username: payload.username,
    passwordHash: await hashPassword(payload.password),
    teacherProfile: {
      subject: payload.subject,
      primarySchoolId: payload.primarySchoolId
    }
  });

  if (payload.primarySchoolId) {
    await TeacherSchoolMembership.create({
      teacherId: user._id,
      schoolId: payload.primarySchoolId,
      isPrimary: true,
      status: 'active'
    });
  }

  issueAuthResponse(res, user, 201);
}

export async function login(req, res) {
  const { role, identifier, password } = req.validated.body;
  const normalizedIdentifier = identifier.toLowerCase();

  const user = await User.findOne({
    role,
    $or: [{ username: normalizedIdentifier }, { email: normalizedIdentifier }]
  }).select('+passwordHash');

  if (!user) {
    throw new ApiError(401, 'Invalid login credentials');
  }

  if (user.status !== 'active') {
    throw new ApiError(403, 'This account is not active');
  }

  const passwordOk = await verifyPassword(password, user.passwordHash);

  if (!passwordOk) {
    throw new ApiError(401, 'Invalid login credentials');
  }

  if (role === 'student' && user.studentProfile?.schoolId) {
    await assertActiveSchool(user.studentProfile.schoolId);
  }

  user.lastLoginAt = new Date();
  await user.save();

  issueAuthResponse(res, user);
}

export async function logout(_req, res) {
  clearAuthCookies(res);
  res.status(204).send();
}

export async function me(req, res) {
  res.json({
    user: presentUser(req.user)
  });
}

export async function refresh(req, res) {
  const token = req.cookies?.refreshToken || req.validated.body?.refreshToken;

  if (!token) {
    throw new ApiError(401, 'Refresh token required');
  }

  let payload;
  try {
    payload = verifyRefreshToken(token);
  } catch (_error) {
    throw new ApiError(401, 'Invalid or expired refresh token');
  }

  if (payload.type !== 'refresh') {
    throw new ApiError(401, 'Invalid token type');
  }

  const user = await User.findById(payload.sub);

  if (!user || user.status !== 'active') {
    throw new ApiError(401, 'User is not active or no longer exists');
  }

  issueAuthResponse(res, user);
}
