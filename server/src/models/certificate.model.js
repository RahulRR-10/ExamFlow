import mongoose from 'mongoose';

import {
  CERTIFICATE_MINT_STATUSES,
  CERTIFICATE_TYPES
} from './constants.js';
import { fileSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const blockchainSchema = new Schema(
  {
    status: {
      type: String,
      enum: CERTIFICATE_MINT_STATUSES,
      default: 'not_minted',
      index: true
    },
    transactionHash: {
      type: String,
      trim: true
    },
    tokenId: {
      type: String,
      trim: true
    },
    contractAddress: {
      type: String,
      trim: true
    },
    metadataUrl: {
      type: String,
      trim: true
    },
    imageUrl: {
      type: String,
      trim: true
    },
    mintedAt: Date,
    errorMessage: {
      type: String,
      trim: true
    }
  },
  { _id: false }
);

const certificateSchema = new Schema(
  {
    ...legacyFields,
    type: {
      type: String,
      enum: CERTIFICATE_TYPES,
      required: true,
      index: true
    },
    recipientId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    schoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School',
      index: true
    },
    examAttemptId: {
      type: Schema.Types.ObjectId,
      ref: 'ExamAttempt'
    },
    teachingSessionIds: [
      {
        type: Schema.Types.ObjectId,
        ref: 'TeachingSession'
      }
    ],
    title: {
      type: String,
      required: true,
      trim: true
    },
    subject: {
      type: String,
      trim: true
    },
    activityPoints: {
      type: Number,
      min: 0
    },
    hoursTaught: {
      type: Number,
      min: 0
    },
    certificateFile: fileSchema,
    blockchain: {
      type: blockchainSchema,
      default: () => ({})
    },
    issuedAt: {
      type: Date,
      default: Date.now,
      index: true
    }
  },
  { timestamps: true }
);

certificateSchema.index({ recipientId: 1, type: 1, issuedAt: -1 });
certificateSchema.index({ 'blockchain.transactionHash': 1 }, { sparse: true });

export const Certificate =
  mongoose.models.Certificate ||
  mongoose.model('Certificate', certificateSchema);
