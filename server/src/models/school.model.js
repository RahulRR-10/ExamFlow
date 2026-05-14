import mongoose from 'mongoose';

import { SCHOOL_STATUSES, SCHOOL_TYPES } from './constants.js';
import { gpsSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const schoolLocationSchema = new Schema(
  {
    ...gpsSchema.obj,
    address: {
      type: String,
      trim: true
    },
    validationRadiusMeters: {
      type: Number,
      default: 500,
      min: 0
    }
  },
  { _id: false }
);

const verificationSettingsSchema = new Schema(
  {
    requireGps: {
      type: Boolean,
      default: true
    },
    maxFileSizeMb: {
      type: Number,
      default: 10,
      min: 1
    },
    allowedFormats: {
      type: [String],
      default: ['jpg', 'jpeg', 'png', 'heic']
    },
    maxPhotoAgeDays: {
      type: Number,
      default: 7,
      min: 0
    },
    durationToleranceMinutes: {
      type: Number,
      default: 15,
      min: 0
    },
    minDurationPercent: {
      type: Number,
      default: 80,
      min: 0,
      max: 100
    }
  },
  { _id: false }
);

const schoolSchema = new Schema(
  {
    ...legacyFields,
    name: {
      type: String,
      required: true,
      trim: true
    },
    code: {
      type: String,
      trim: true,
      uppercase: true
    },
    status: {
      type: String,
      enum: SCHOOL_STATUSES,
      default: 'active',
      index: true
    },
    type: {
      type: String,
      enum: SCHOOL_TYPES,
      default: 'secondary'
    },
    address: {
      type: String,
      trim: true
    },
    contactPerson: {
      type: String,
      trim: true
    },
    contactEmail: {
      type: String,
      trim: true,
      lowercase: true
    },
    contactPhone: {
      type: String,
      trim: true
    },
    location: schoolLocationSchema,
    verificationSettings: {
      type: verificationSettingsSchema,
      default: () => ({})
    }
  },
  { timestamps: true }
);

schoolSchema.index({ code: 1 }, { unique: true, sparse: true });
schoolSchema.index({ name: 1 }, { unique: true });
schoolSchema.index({ status: 1, type: 1 });

export const School =
  mongoose.models.School || mongoose.model('School', schoolSchema);
