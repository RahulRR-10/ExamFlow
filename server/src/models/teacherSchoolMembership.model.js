import mongoose from 'mongoose';

import { MEMBERSHIP_STATUSES } from './constants.js';
import { legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const teacherSchoolMembershipSchema = new Schema(
  {
    ...legacyFields,
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
    status: {
      type: String,
      enum: MEMBERSHIP_STATUSES,
      default: 'active',
      index: true
    },
    isPrimary: {
      type: Boolean,
      default: false
    },
    enrolledAt: {
      type: Date,
      default: Date.now
    },
    removedAt: Date,
    remarks: {
      type: String,
      trim: true
    }
  },
  { timestamps: true }
);

teacherSchoolMembershipSchema.index(
  { teacherId: 1, schoolId: 1 },
  { unique: true }
);
teacherSchoolMembershipSchema.index({ schoolId: 1, status: 1 });

export const TeacherSchoolMembership =
  mongoose.models.TeacherSchoolMembership ||
  mongoose.model('TeacherSchoolMembership', teacherSchoolMembershipSchema);
