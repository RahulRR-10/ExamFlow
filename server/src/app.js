import cookieParser from 'cookie-parser';
import cors from 'cors';
import express from 'express';
import helmet from 'helmet';
import morgan from 'morgan';

import { env } from './config/env.js';
import { notFound } from './middleware/notFound.js';
import { errorHandler } from './middleware/errorHandler.js';
import healthRoutes from './routes/health.routes.js';

export function createApp() {
  const app = express();

  app.use(helmet());
  app.use(
    cors({
      origin: env.CLIENT_ORIGIN,
      credentials: true
    })
  );
  app.use(express.json({ limit: '1mb' }));
  app.use(express.urlencoded({ extended: true }));
  app.use(cookieParser());

  if (env.NODE_ENV === 'development') {
    app.use(morgan('dev'));
  }

  app.get('/api', (_req, res) => {
    res.json({
      name: 'ExamFlow API',
      version: '0.1.0',
      status: 'ready'
    });
  });

  app.use('/api/health', healthRoutes);

  app.use(notFound);
  app.use(errorHandler);

  return app;
}
