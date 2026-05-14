import mongoose from 'mongoose';

import { TEACHING_SESSION_STATUSES } from './constants.js';
import {
  fileSchema,
  gpsSchema,
  legacyFields,
  validationIssueSchema
} from './shared.schema.js';

const { Schema } = mongoose;

const sessionPhotoSchema = new Schema(
  {
    file: fileSchema,
    gps: gpsSchema,
    photoTakenAt: Date,
    uploadedAt: {
      type: Date,
      default: Date.now
    },
    distanceFromSchoolMeters: {
      type: Number,
      min: 0
    },
    exif: Schema.Types.Mixed,
    device: {
      make: String,
      model: String,
      software: String
    }
  },
  { _id: false }
);

const sessionValidationSchema = new Schema(
  {
    overallStatus: {
      type: String,
      enum: ['valid', 'warning', 'reject', 'manual_review'],
      default: 'manual_review'
    },
    canAutoApprove: {
      type: Boolean,
      default: false
    },
    requiresManualReview: {
      type: Boolean,
      default: true
    },
    issues: {
      type: [validationIssueSchema],
      default: []
    },
    actualDurationMinutes: {
      type: Number,
      min: 0
    }
  },
  { _id: false }
);

const teachingSessionSchema = new Schema(
  {
    ...legacyFields,
    slotId: {
      type: Schema.Types.ObjectId,
      ref: 'TeachingSlot',
      required: true,
      index: true
    },
    teacherId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    schoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School',
      required: true,
      index: true
    },
    sessionDate: {
      type: Date,
      required: true,
      index: true
    },
    status: {
      type: String,
      enum: TEACHING_SESSION_STATUSES,
      default: 'pending',
      index: true
    },
    startPhoto: sessionPhotoSchema,
    endPhoto: sessionPhotoSchema,
    validation: {
      type: sessionValidationSchema,
      default: () => ({})
    },
    verifiedBy: {
      type: Schema.Types.ObjectId,
      ref: 'User'
    },
    verifiedAt: Date,
    adminRemarks: {
      type: String,
      trim: true
    }
  },
  { timestamps: true }
);

teachingSessionSchema.index({ teacherId: 1, sessionDate: -1 });
teachingSessionSchema.index({ schoolId: 1, status: 1 });
teachingSessionSchema.index({ status: 1, verifiedBy: 1 });

export const TeachingSession =
  mongoose.models.TeachingSession ||
  mongoose.model('TeachingSession', teachingSessionSchema);
