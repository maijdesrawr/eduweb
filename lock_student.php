<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php";


if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    die("Invalid request.");

}


$student_num = trim(
    $_GET["student_num"] ?? ""
);


if (empty($student_num)) {

    die("Student number is required.");

}


/* =========================================
   GET CURRENT FREEZE STATUS
========================================= */

$stmt = $conn->prepare("
    SELECT is_locked
    FROM students
    WHERE student_num = ?
");

$stmt->bind_param(
    "s",
    $student_num
);

$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();


if (!$student) {

    die("Student not found.");

}


/* =========================================
   TOGGLE FREEZE
========================================= */

$newStatus =
    $student["is_locked"] ? 0 : 1;


$update = $conn->prepare("
    UPDATE students
    SET is_locked = ?
    WHERE student_num = ?
");

$update->bind_param(
    "is",
    $newStatus,
    $student_num
);


if ($update->execute()) {

    header(
        "Location: students.php?message=Account status updated"
    );

    exit;

} else {

    die(
        "Unable to update account: " .
        $update->error
    );

}

?>