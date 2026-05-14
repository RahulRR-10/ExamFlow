import mongoose from 'mongoose';

import { MESSAGE_AUDIENCES } from './constants.js';
import { fileSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const readReceiptSchema = new Schema(
  {
    userId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true
    },
    readAt: {
      type: Date,
      default: Date.now
    }
  },
  { _id: false }
);

const messageSchema = new Schema(
  {
    ...legacyFields,
    schoolId: {
      type: Schema.Types.ObjectId,
      ref: 'School',
      index: true
    },
    senderId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    audience: {
      type: String,
      enum: MESSAGE_AUDIENCES,
      default: 'students',
      index: true
    },
    title: {
      type: String,
      required: true,
      trim: true
    },
    body: {
      type: String,
      required: true,
      trim: true
    },
    attachments: {
      type: [fileSchema],
      default: []
    },
    readBy: {
      type: [readReceiptSchema],
      default: []
    },
    expiresAt: Date
  },
  { timestamps: true }
);

messageSchema.index({ schoolId: 1, audience: 1, createdAt: -1 });

export const Message =
  mongoose.models.Message || mongoose.model('Message', messageSchema);
