import mongoose from 'mongoose';

import { EXAM_STATUSES } from './constants.js';
import { legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const mockQuestionSchema = new Schema(
  {
    ...legacyFields,
    text: {
      type: String,
      required: true,
      trim: true
    },
    options: {
      type: [String],
      default: []
    },
    correctAnswer: {
      type: String,
      trim: true
    },
    sourceQuestionId: Schema.Types.ObjectId,
    order: {
      type: Number,
      default: 0
    }
  },
  { _id: true }
);

const mockExamSchema = new Schema(
  {
    ...legacyFields,
    name: {
      type: String,
      required: true,
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
      index: true
    },
    sourceExamId: {
      type: Schema.Types.ObjectId,
      ref: 'Exam'
    },
    subject: {
      type: String,
      trim: true
    },
    durationMinutes: {
      type: Number,
      default: 60,
      min: 1
    },
    status: {
      type: String,
      enum: EXAM_STATUSES,
      default: 'active',
      index: true
    },
    questions: {
      type: [mockQuestionSchema],
      default: []
    }
  },
  { timestamps: true }
);

mockExamSchema.index({ schoolId: 1, status: 1, createdAt: -1 });

export const MockExam =
  mongoose.models.MockExam || mongoose.model('MockExam', mockExamSchema);
