import mongoose from 'mongoose';

const { Schema } = mongoose;

const uploadRateLimitSchema = new Schema(
  {
    userId: {
      type: Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    scope: {
      type: String,
      required: true,
      trim: true,
      default: 'default'
    },
    dateKey: {
      type: String,
      required: true,
      trim: true,
      index: true
    },
    count: {
      type: Number,
      default: 0,
      min: 0
    },
    lastUploadAt: Date
  },
  { timestamps: true }
);

uploadRateLimitSchema.index({ userId: 1, scope: 1, dateKey: 1 }, { unique: true });

export const UploadRateLimit =
  mongoose.models.UploadRateLimit ||
  mongoose.model('UploadRateLimit', uploadRateLimitSchema);
