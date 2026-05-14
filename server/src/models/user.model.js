import mongoose from 'mongoose';

import { SCHOOL_TYPES, USER_ROLES, USER_STATUSES } from './constants.js';
import { fileSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const studentProfileSchema = new Schema(
  {
    schoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School'
    },
    dateOfBirth: Date,
    gender: {
      type: String,
      trim: true
    }
  },
  { _id: false }
);

const teacherProfileSchema = new Schema(
  {
    subject: {
      type: String,
      trim: true
    },
    primarySchoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School'
    },
    activityPoints: {
      type: Number,
      default: 0,
      min: 0
    }
  },
  { _id: false }
);

const adminProfileSchema = new Schema(
  {
    phone: {
      type: String,
      trim: true
    },
    allowedSchoolTypes: [
      {
        type: String,
        enum: SCHOOL_TYPES
      }
    ]
  },
  { _id: false }
);

const userSchema = new Schema(
  {
    ...legacyFields,
    role: {
      type: String,
      enum: USER_ROLES,
      required: true,
      index: true
    },
    firstName: {
      type: String,
      required: true,
      trim: true
    },
    email: {
      type: String,
      required: true,
      trim: true,
      lowercase: true,
      index: true
    },
    username: {
      type: String,
      required: true,
      trim: true,
      lowercase: true,
      index: true
    },
    passwordHash: {
      type: String,
      required: true,
      select: false
    },
    status: {
      type: String,
      enum: USER_STATUSES,
      default: 'active',
      index: true
    },
    profileImage: fileSchema,
    studentProfile: studentProfileSchema,
    teacherProfile: teacherProfileSchema,
    adminProfile: adminProfileSchema,
    lastLoginAt: Date,
    passwordMigratedAt: Date
  },
  {
    timestamps: true
  }
);

userSchema.index({ role: 1, status: 1 });
userSchema.index({ username: 1, role: 1 }, { unique: true });
userSchema.index({ email: 1, role: 1 }, { unique: true });

export const User = mongoose.models.User || mongoose.model('User', userSchema);
