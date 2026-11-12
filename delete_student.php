<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once "db.php";

if (!isset($_GET["student_num"]) || empty($_GET["student_num"])) {
    header("Location: students.php");
    exit;
}

$studentNum = $_GET["student_num"];

try {

    $conn->begin_transaction();

    // Get the student's parent ID
    $stmt = $conn->prepare("
        SELECT parent_id
        FROM students
        WHERE student_num = ?
    ");
    $stmt->bind_param("s", $studentNum);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();

        header("Location: students.php");
        exit;
    }

    $student = $result->fetch_assoc();
    $parentID = $student["parent_id"];

    $stmt->close();


    // Delete parent-student link
    $stmt = $conn->prepare("
        DELETE FROM parent_student_link
        WHERE student_num = ?
    ");
    $stmt->bind_param("s", $studentNum);
    $stmt->execute();
    $stmt->close();


    // Delete student
    $stmt = $conn->prepare("
        DELETE FROM students
        WHERE student_num = ?
    ");
    $stmt->bind_param("s", $studentNum);
    $stmt->execute();
    $stmt->close();


    // If the parent has no remaining students, delete the parent account
    if (!empty($parentID)) {

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM students
            WHERE parent_id = ?
        ");

        $stmt->bind_param("s", $parentID);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $remainingStudents = $row["total"];

        $stmt->close();


        if ($remainingStudents == 0) {

            $stmt = $conn->prepare("
                DELETE FROM parents
                WHERE parent_id = ?
            ");

            $stmt->bind_param("s", $parentID);
            $stmt->execute();

            $stmt->close();
        }
    }


    $conn->commit();

    // Simply return to the students page
    header("Location: students.php");
    exit;


} catch (Throwable $error) {

    $conn->rollback();

    die("Unable to delete student: " . htmlspecialchars($error->getMessage()));
}

?>