import { ZodError } from 'zod';

import { ApiError } from '../utils/apiError.js';

export function validateRequest(schema) {
  return (req, _res, next) => {
    try {
      const parsed = schema.parse({
        body: req.body,
        params: req.params,
        query: req.query
      });

      req.validated = parsed;
      next();
    } catch (error) {
      if (error instanceof ZodError) {
        next(new ApiError(400, 'Invalid request data', error.flatten()));
        return;
      }

      next(error);
    }
  };
}
