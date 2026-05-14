import { createApp } from './app.js';
import { connectDatabase } from './config/db.js';
import { env } from './config/env.js';

const app = createApp();

connectDatabase()
  .catch((error) => {
    console.error('Database startup check failed:', error.message);
  })
  .finally(() => {
    app.listen(env.PORT, () => {
      console.log(`ExamFlow API listening on http://localhost:${env.PORT}`);
    });
  });
