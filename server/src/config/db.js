import mongoose from 'mongoose';

import { env } from './env.js';

const dbState = {
  status: 'not_started',
  lastError: null,
  connectedAt: null
};

mongoose.connection.on('connected', () => {
  dbState.status = 'connected';
  dbState.connectedAt = new Date().toISOString();
  dbState.lastError = null;
});

mongoose.connection.on('disconnected', () => {
  if (dbState.status !== 'error') {
    dbState.status = 'disconnected';
  }
});

mongoose.connection.on('error', (error) => {
  dbState.status = 'error';
  dbState.lastError = error.message;
});

export async function connectDatabase() {
  dbState.status = 'connecting';

  try {
    await mongoose.connect(env.MONGODB_URI, {
      serverSelectionTimeoutMS: 3000
    });
    return true;
  } catch (error) {
    dbState.status = 'error';
    dbState.lastError = error.message;
    console.warn(`MongoDB unavailable: ${error.message}`);
    return false;
  }
}

export function getDatabaseStatus() {
  return {
    ...dbState,
    readyState: mongoose.connection.readyState
  };
}
