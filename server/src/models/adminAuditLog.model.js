import mongoose from 'mongoose';

import { ipAddressSchema, legacyFields } from './shared.schema.js';

const { Schema } = mongoose;

const adminAuditLogSchema = new Schema(
  {
    ...legacyFields,
    adminId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    actionType: {
      type: String,
      required: true,
      trim: true,
      index: true
    },
    targetCollection: {
      type: String,
      trim: true
    },
    targetId: {
      type: Schema.Types.ObjectId
    },
    details: Schema.Types.Mixed,
    requestContext: ipAddressSchema
  },
  {
    timestamps: {
      createdAt: true,
      updatedAt: false
    }
  }
);

adminAuditLogSchema.index({ adminId: 1, createdAt: -1 });
adminAuditLogSchema.index({ actionType: 1, createdAt: -1 });

export const AdminAuditLog =
  mongoose.models.AdminAuditLog ||
  mongoose.model('AdminAuditLog', adminAuditLogSchema);
