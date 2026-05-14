import mongoose from 'mongoose';

import { PASS_STATUSES } from './constants.js';
import { ipAddressSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const answerSchema = new Schema(
  {
    questionId: {
      type: Schema.Types.ObjectId,
      required: true
    },
    selectedOptionId: Schema.Types.ObjectId,
    isCorrect: {
      type: Boolean,
      default: false
    },
    marksAwarded: {
      type: Number,
      default: 0,
      min: 0
    }
  },
  { _id: false }
);

const violationSchema = new Schema(
  {
    type: {
      type: String,
      required: true,
      trim: true
    },
    occurrence: {
      type: Number,
      default: 1,
      min: 1
    },
    penalty: {
      type: Number,
      default: 0,
      min: 0
    },
    recordedAt: {
      type: Date,
      default: Date.now
    }
  },
  { _id: true }
);

const examAttemptSchema = new Schema(
  {
    ...legacyFields,
    examId: {
      type: Schema.Types.ObjectId,
      ref: 'Exam',
      required: true,
      index: true
    },
    studentId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    answers: {
      type: [answerSchema],
      default: []
    },
    totalQuestions: {
      type: Number,
      default: 0,
      min: 0
    },
    correctAnswers: {
      type: Number,
      default: 0,
      min: 0
    },
    scoredMarks: {
      type: Number,
      default: 0,
      min: 0
    },
    totalMarks: {
      type: Number,
      default: 0,
      min: 0
    },
    percentage: {
      type: Number,
      default: 0,
      min: 0,
      max: 100
    },
    passStatus: {
      type: String,
      enum: PASS_STATUSES,
      default: 'pending'
    },
    integrityScore: {
      type: Number,
      default: 100,
      min: 0,
      max: 100
    },
    integrityCategory: {
      type: String,
      default: 'Good',
      trim: true
    },
    violations: {
      type: [violationSchema],
      default: []
    },
    startedAt: Date,
    submittedAt: Date,
    requestContext: ipAddressSchema
  },
  { timestamps: true }
);

examAttemptSchema.index({ examId: 1, studentId: 1 }, { unique: true });
examAttemptSchema.index({ studentId: 1, submittedAt: -1 });

export const ExamAttempt =
  mongoose.models.ExamAttempt ||
  mongoose.model('ExamAttempt', examAttemptSchema);
