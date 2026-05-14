import mongoose from 'mongoose';

import { ipAddressSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const mockViolationSchema = new Schema(
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

const mockAnswerSchema = new Schema(
  {
    questionId: {
      type: Schema.Types.ObjectId,
      required: true
    },
    selectedAnswer: {
      type: String,
      trim: true
    },
    isCorrect: {
      type: Boolean,
      default: false
    }
  },
  { _id: false }
);

const mockAttemptSchema = new Schema(
  {
    ...legacyFields,
    mockExamId: {
      type: Schema.Types.ObjectId,
      ref: 'MockExam',
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
      type: [mockAnswerSchema],
      default: []
    },
    correctAnswers: {
      type: Number,
      default: 0,
      min: 0
    },
    totalQuestions: {
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
    integrityScore: {
      type: Number,
      default: 100,
      min: 0,
      max: 100
    },
    violations: {
      type: [mockViolationSchema],
      default: []
    },
    startedAt: Date,
    submittedAt: Date,
    requestContext: ipAddressSchema
  },
  { timestamps: true }
);

mockAttemptSchema.index({ mockExamId: 1, studentId: 1 });
mockAttemptSchema.index({ studentId: 1, submittedAt: -1 });

export const MockAttempt =
  mongoose.models.MockAttempt ||
  mongoose.model('MockAttempt', mockAttemptSchema);
