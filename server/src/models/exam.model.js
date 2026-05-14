import mongoose from 'mongoose';

import { EXAM_STATUSES, EXAM_TYPES } from './constants.js';
import { legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const questionOptionSchema = new Schema(
  {
    text: {
      type: String,
      required: true,
      trim: true
    },
    isCorrect: {
      type: Boolean,
      default: false
    }
  },
  { _id: true }
);

const examQuestionSchema = new Schema(
  {
    ...legacyFields,
    text: {
      type: String,
      required: true,
      trim: true
    },
    options: {
      type: [questionOptionSchema],
      default: []
    },
    marks: {
      type: Number,
      default: 1,
      min: 0
    },
    order: {
      type: Number,
      default: 0
    }
  },
  { _id: true }
);

const examSchema = new Schema(
  {
    ...legacyFields,
    type: {
      type: String,
      enum: EXAM_TYPES,
      default: 'mcq',
      index: true
    },
    name: {
      type: String,
      required: true,
      trim: true
    },
    subject: {
      type: String,
      trim: true,
      index: true
    },
    description: {
      type: String,
      trim: true
    },
    schoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School',
      required: true,
      index: true
    },
    teacherId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    durationMinutes: {
      type: Number,
      default: 60,
      min: 1
    },
    totalMarks: {
      type: Number,
      default: 0,
      min: 0
    },
    status: {
      type: String,
      enum: EXAM_STATUSES,
      default: 'draft',
      index: true
    },
    startsAt: Date,
    closesAt: Date,
    questions: {
      type: [examQuestionSchema],
      default: []
    }
  },
  { timestamps: true }
);

examSchema.index({ schoolId: 1, status: 1, startsAt: -1 });
examSchema.index({ teacherId: 1, createdAt: -1 });

export const Exam = mongoose.models.Exam || mongoose.model('Exam', examSchema);
