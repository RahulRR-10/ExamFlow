import { Router } from 'express';

import { getDatabaseStatus } from '../config/db.js';

const router = Router();

router.get('/', (_req, res) => {
  res.json({
    status: 'ok',
    service: 'examflow-api',
    timestamp: new Date().toISOString(),
    database: getDatabaseStatus()
  });
});

export default router;
