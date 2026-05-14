import { z } from 'zod';

import { USER_ROLES } from '../models/constants.js';

const objectId = z
  .string()
  .regex(/^[a-f\d]{24}$/i, 'Expected a valid MongoDB ObjectId');

const username = z
  .string()
  .trim()
  .min(3)
  .max(60)
  .transform((value) => value.toLowerCase());

const email = z
  .string()
  .trim()
  .email()
  .max(255)
  .transform((value) => value.toLowerCase());

const password = z.string().min(8).max(128);

export const registerStudentSchema = z.object({
  body: z.object({
    firstName: z.string().trim().min(1).max(120),
    email,
    username,
    password,
    schoolId: objectId.optional(),
    dateOfBirth: z.coerce.date().optional(),
    gender: z.string().trim().max(40).optional()
  })
});

export const registerTeacherSchema = z.object({
  body: z.object({
    firstName: z.string().trim().min(1).max(120),
    email,
    username,
    password,
    subject: z.string().trim().min(1).max(120),
    primarySchoolId: objectId.optional()
  })
});

export const loginSchema = z.object({
  body: z.object({
    role: z.enum(USER_ROLES),
    identifier: z.string().trim().min(1).max(255),
    password: z.string().min(1).max(128)
  })
});

export const refreshSchema = z.object({
  body: z
    .object({
      refreshToken: z.string().optional()
    })
    .optional()
    .default({})
});
