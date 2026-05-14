import { env } from '../config/env.js';

export function errorHandler(error, _req, res, _next) {
  const statusCode = error.statusCode || error.status || 500;

  res.status(statusCode).json({
    error: {
      message:
        statusCode === 500 && env.NODE_ENV === 'production'
          ? 'Internal server error'
          : error.message,
      statusCode,
      stack: env.NODE_ENV === 'development' ? error.stack : undefined
    }
  });
}
