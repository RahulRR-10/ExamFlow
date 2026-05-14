export function presentUser(user) {
  if (!user) {
    return null;
  }

  const plain = typeof user.toObject === 'function' ? user.toObject() : user;

  return {
    id: plain._id?.toString?.() || plain.id,
    role: plain.role,
    firstName: plain.firstName,
    email: plain.email,
    username: plain.username,
    status: plain.status,
    studentProfile: plain.studentProfile,
    teacherProfile: plain.teacherProfile,
    adminProfile: plain.adminProfile,
    createdAt: plain.createdAt,
    updatedAt: plain.updatedAt
  };
}
