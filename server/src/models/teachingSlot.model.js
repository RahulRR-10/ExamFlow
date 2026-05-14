import mongoose from 'mongoose';

import {
  SLOT_ENROLLMENT_STATUSES,
  TEACHING_SLOT_STATUSES
} from './constants.js';
import { legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const slotEnrollmentSchema = new Schema(
  {
    teacherId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true
    },
    status: {
      type: String,
      enum: SLOT_ENROLLMENT_STATUSES,
      default: 'booked'
    },
    bookedAt: {
      type: Date,
      default: Date.now
    },
    cancelledAt: Date,
    cancellationReason: {
      type: String,
      trim: true
    }
  },
  { _id: true }
);

const teachingSlotSchema = new Schema(
  {
    ...legacyFields,
    schoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School',
      required: true,
      index: true
    },
    slotDate: {
      type: Date,
      required: true,
      index: true
    },
    startTime: {
      type: String,
      required: true,
      trim: true
    },
    endTime: {
      type: String,
      required: true,
      trim: true
    },
    teachersRequired: {
      type: Number,
      default: 1,
      min: 1
    },
    teachersEnrolled: {
      type: Number,
      default: 0,
      min: 0
    },
    status: {
      type: String,
      enum: TEACHING_SLOT_STATUSES,
      default: 'open',
      index: true
    },
    description: {
      type: String,
      trim: true
    },
    createdBy: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true
    },
    enrollments: {
      type: [slotEnrollmentSchema],
      default: []
    }
  },
  { timestamps: true }
);

teachingSlotSchema.index({ schoolId: 1, slotDate: 1, status: 1 });
teachingSlotSchema.index({ 'enrollments.teacherId': 1, slotDate: 1 });

export const TeachingSlot =
  mongoose.models.TeachingSlot ||
  mongoose.model('TeachingSlot', teachingSlotSchema);
