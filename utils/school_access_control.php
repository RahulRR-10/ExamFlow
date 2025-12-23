<?php

/**
 * School Access Control Helper Functions
 * 
 * Provides validation functions to ensure students and teachers
 * can only access resources from their enrolled schools.
 */

/**
 * Validates if a student has access to a specific exam
 * 
 * @param mysqli $conn Database connection
 * @param string $uname Student username
 * @param int $exid Exam ID
 * @return bool True if access is allowed, false otherwise
 */
function validateStudentExamAccess($conn, $uname, $exid)
{
    if (!isset($_SESSION['school_id'])) {
        return false;
    }

    $school_id = intval($_SESSION['school_id']);
    $exid = intval($exid);

    $sql = "SELECT exid FROM exm_list WHERE exid = ? AND school_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $exid, $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0;
}

/**
 * Validates if a student has access to a specific mock exam
 * 
 * @param mysqli $conn Database connection
 * @param string $uname Student username
 * @param int $mock_exid Mock Exam ID
 * @return bool True if access is allowed, false otherwise
 */
function validateStudentMockExamAccess($conn, $uname, $mock_exid)
{
    if (!isset($_SESSION['school_id'])) {
        return false;
    }

    $school_id = intval($_SESSION['school_id']);
    $mock_exid = intval($mock_exid);

    $sql = "SELECT mock_exid FROM mock_exm_list WHERE mock_exid = ? AND school_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $mock_exid, $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0;
}

/**
 * Validates if a teacher has access to a specific school
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $school_id School ID
 * @return bool True if access is allowed, false otherwise
 */
function validateTeacherSchoolAccess($conn, $teacher_id, $school_id)
{
    $teacher_id = intval($teacher_id);
    $school_id = intval($school_id);

    $sql = "SELECT id FROM teacher_schools 
            WHERE teacher_id = ? AND school_id = ? AND enrollment_status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $teacher_id, $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0;
}

/**
 * Validates if a teacher has access to a specific exam
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @param int $exid Exam ID
 * @return bool True if access is allowed, false otherwise
 */
function validateTeacherExamAccess($conn, $teacher_id, $exid)
{
    $teacher_id = intval($teacher_id);
    $exid = intval($exid);

    $sql = "SELECT e.exid FROM exm_list e 
            INNER JOIN teacher_schools ts ON e.school_id = ts.school_id 
            WHERE e.exid = ? AND ts.teacher_id = ? AND ts.enrollment_status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $exid, $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_num_rows($result) > 0;
}

/**
 * Gets the school ID for a student
 * 
 * @param mysqli $conn Database connection
 * @param int $sid Student ID
 * @return int|null School ID or null if not found
 */
function getStudentSchoolId($conn, $sid)
{
    $sid = intval($sid);

    $sql = "SELECT school_id FROM student WHERE sid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $sid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        return intval($row['school_id']);
    }

    return null;
}

/**
 * Gets all schools a teacher is enrolled in
 * 
 * @param mysqli $conn Database connection
 * @param int $teacher_id Teacher ID
 * @return array Array of school IDs
 */
function getTeacherSchoolIds($conn, $teacher_id)
{
    $teacher_id = intval($teacher_id);
    $schools = [];

    $sql = "SELECT school_id FROM teacher_schools 
            WHERE teacher_id = ? AND enrollment_status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $schools[] = intval($row['school_id']);
    }

    return $schools;
}

/**
 * Denies access with an error message and redirect
 * 
 * @param string $message Error message to display
 * @param string $redirect URL to redirect to
 */
function denyAccess($message = "Access denied", $redirect = "dash.php")
{
    echo "<script>alert('$message'); window.location.href='$redirect';</script>";
    exit;
}
