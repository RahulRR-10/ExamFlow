import mongoose from 'mongoose';

import {
  GRADE_SOURCES,
  OCR_STATUSES,
  PASS_STATUSES,
  SUBMISSION_STATUSES
} from './constants.js';
import { fileSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const answerImageSchema = new Schema(
  {
    ...fileSchema.obj,
    imageOrder: {
      type: Number,
      default: 1,
      min: 1
    },
    ocrText: {
      type: String,
      trim: true
    },
    ocrStatus: {
      type: String,
      enum: OCR_STATUSES,
      default: 'pending',
      index: true
    },
    ocrConfidence: {
      type: Number,
      min: 0,
      max: 100
    },
    ocrErrorMessage: {
      type: String,
      trim: true
    },
    processedAt: Date
  },
  { _id: true }
);

const objectiveGradeSchema = new Schema(
  {
    questionId: {
      type: Schema.Types.ObjectId,
      required: true
    },
    questionNumber: {
      type: Number,
      required: true,
      min: 1
    },
    studentAnswerText: {
      type: String,
      trim: true
    },
    aiSuggestedMarks: {
      type: Number,
      min: 0
    },
    aiFeedback: {
      type: String,
      trim: true
    },
    finalMarks: {
      type: Number,
      min: 0
    },
    teacherFeedback: {
      type: String,
      trim: true
    },
    gradedBy: {
      type: String,
      enum: GRADE_SOURCES,
      default: 'pending'
    }
  },
  { _id: true, timestamps: true }
);

const objectiveSubmissionSchema = new Schema(
  {
    ...legacyFields,
    examId: {
      type: Schema.Types.ObjectId,
      ref: 'ObjectiveExam',
      required: true,
      index: true
    },
    studentId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    status: {
      type: String,
      enum: SUBMISSION_STATUSES,
      default: 'pending',
      index: true
    },
    answerImages: {
      type: [answerImageSchema],
      default: []
    },
    grades: {
      type: [objectiveGradeSchema],
      default: []
    },
    totalMarks: {
      type: Number,
      min: 0
    },
    scoredMarks: {
      type: Number,
      min: 0
    },
    percentage: {
      type: Number,
      min: 0,
      max: 100
    },
    passStatus: {
      type: String,
      enum: PASS_STATUSES,
      default: 'pending'
    },
    feedback: {
      type: String,
      trim: true
    },
    submittedAt: {
      type: Date,
      default: Date.now
    },
    ocrCompletedAt: Date,
    gradedAt: Date,
    gradedBy: {
      type: Schema.Types.ObjectId,
      ref: 'User'
    }
  },
  { timestamps: true }
);

objectiveSubmissionSchema.index({ examId: 1, studentId: 1 }, { unique: true });
objectiveSubmissionSchema.index({ status: 1, submittedAt: 1 });

export const ObjectiveSubmission =
  mongoose.models.ObjectiveSubmission ||
  mongoose.model('ObjectiveSubmission', objectiveSubmissionSchema);
