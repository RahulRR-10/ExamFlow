import mongoose from 'mongoose';

import { EXAM_STATUSES, GRADING_MODES } from './constants.js';
import { fileSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const objectiveQuestionSchema = new Schema(
  {
    ...legacyFields,
    questionNumber: {
      type: Number,
      required: true,
      min: 1
    },
    text: {
      type: String,
      required: true,
      trim: true
    },
    maxMarks: {
      type: Number,
      required: true,
      min: 0
    },
    answerKeyText: {
      type: String,
      trim: true
    }
  },
  { _id: true }
);

const objectiveExamSchema = new Schema(
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
      required: true,
      index: true
    },
    gradingMode: {
      type: String,
      enum: GRADING_MODES,
      default: 'manual',
      index: true
    },
    answerKeyText: {
      type: String,
      trim: true
    },
    answerKeyFile: fileSchema,
    totalMarks: {
      type: Number,
      default: 100,
      min: 0
    },
    passingMarks: {
      type: Number,
      default: 40,
      min: 0
    },
    instructions: {
      type: String,
      trim: true
    },
    examDate: {
      type: Date,
      required: true
    },
    submissionDeadline: {
      type: Date,
      required: true,
      index: true
    },
    durationMinutes: {
      type: Number,
      default: 60,
      min: 1
    },
    status: {
      type: String,
      enum: EXAM_STATUSES,
      default: 'draft',
      index: true
    },
    questions: {
      type: [objectiveQuestionSchema],
      default: []
    }
  },
  { timestamps: true }
);

objectiveExamSchema.index({ schoolId: 1, status: 1, submissionDeadline: 1 });
objectiveExamSchema.index({ teacherId: 1, createdAt: -1 });

export const ObjectiveExam =
  mongoose.models.ObjectiveExam ||
  mongoose.model('ObjectiveExam', objectiveExamSchema);
