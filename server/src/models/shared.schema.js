import mongoose from 'mongoose';

const { Schema } = mongoose;

export const legacyFields = {
  legacyId: {
    type: Number,
    index: true
  },
  legacyTable: {
    type: String,
    trim: true
  }
};

export const fileSchema = new Schema(
  {
    originalName: {
      type: String,
      trim: true
    },
    storedName: {
      type: String,
      trim: true
    },
    path: {
      type: String,
      trim: true
    },
    mimeType: {
      type: String,
      trim: true
    },
    size: {
      type: Number,
      min: 0
    },
    uploadedAt: {
      type: Date,
      default: Date.now
    }
  },
  { _id: false }
);

export const gpsSchema = new Schema(
  {
    latitude: {
      type: Number,
      min: -90,
      max: 90
    },
    longitude: {
      type: Number,
      min: -180,
      max: 180
    },
    altitude: Number,
    accuracyMeters: Number
  },
  { _id: false }
);

export const validationIssueSchema = new Schema(
  {
    code: {
      type: String,
      trim: true
    },
    message: {
      type: String,
      trim: true
    },
    severity: {
      type: String,
      enum: ['info', 'warning', 'error'],
      default: 'info'
    }
  },
  { _id: false }
);

export const ipAddressSchema = new Schema(
  {
    ip: {
      type: String,
      trim: true
    },
    userAgent: {
      type: String,
      trim: true
    }
  },
  { _id: false }
);
