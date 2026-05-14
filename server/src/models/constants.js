export const USER_ROLES = ['student', 'teacher', 'admin'];

export const USER_STATUSES = ['active', 'inactive', 'suspended'];

export const SCHOOL_STATUSES = ['active', 'inactive'];

export const SCHOOL_TYPES = [
  'primary',
  'secondary',
  'higher_secondary',
  'college',
  'other'
];

export const MEMBERSHIP_STATUSES = ['active', 'pending', 'rejected', 'removed'];

export const EXAM_STATUSES = ['draft', 'active', 'closed', 'graded', 'archived'];

export const EXAM_TYPES = ['mcq'];

export const PASS_STATUSES = ['pass', 'fail', 'pending'];

export const SUBMISSION_STATUSES = [
  'pending',
  'ocr_processing',
  'ocr_complete',
  'grading',
  'graded',
  'error'
];

export const OCR_STATUSES = ['pending', 'processing', 'completed', 'failed'];

export const GRADING_MODES = ['ai', 'manual'];

export const GRADE_SOURCES = ['ai', 'teacher', 'pending'];

export const MESSAGE_AUDIENCES = ['all', 'students', 'teachers', 'admins'];

export const TEACHING_SLOT_STATUSES = [
  'open',
  'partially_filled',
  'full',
  'completed',
  'cancelled'
];

export const SLOT_ENROLLMENT_STATUSES = [
  'booked',
  'cancelled',
  'completed',
  'no_show'
];

export const TEACHING_SESSION_STATUSES = [
  'pending',
  'start_submitted',
  'start_approved',
  'end_submitted',
  'approved',
  'rejected',
  'partial',
  'resubmission_requested'
];

export const CERTIFICATE_TYPES = ['student_exam', 'teacher_activity'];

export const CERTIFICATE_MINT_STATUSES = [
  'not_minted',
  'pending',
  'minted',
  'failed',
  'demo'
];
