import mongoose from 'mongoose';

import { fileSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const studyMaterialSchema = new Schema(
  {
    ...legacyFields,
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
    title: {
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
    file: {
      type: fileSchema,
      required: true
    },
    publishedAt: {
      type: Date,
      default: Date.now
    }
  },
  { timestamps: true }
);

studyMaterialSchema.index({ schoolId: 1, subject: 1, publishedAt: -1 });

export const StudyMaterial =
  mongoose.models.StudyMaterial ||
  mongoose.model('StudyMaterial', studyMaterialSchema);
