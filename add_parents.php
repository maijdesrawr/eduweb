<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: parents.php");
    exit;
}

$firstName = trim($_POST["first_name"] ?? "");
$middleName = trim($_POST["middle_name"] ?? "");
$lastName = trim($_POST["last_name"] ?? "");
$suffix = trim($_POST["suffix"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

$linkedStudents = $_POST["linked_students"] ?? [];

if (!is_array($linkedStudents)) {
    $linkedStudents = [];
}

$linkedStudents = array_map(
    "trim",
    $linkedStudents
);

$linkedStudents = array_filter(
    $linkedStudents,
    function ($studentNum) {
        return $studentNum !== "";
    }
);

$linkedStudents = array_unique(
    $linkedStudents
);

if (
    $firstName === "" ||
    $lastName === "" ||
    $password === ""
) {
    die(
        "First name, last name, and password are required."
    );
}

if ($email !== "") {

    $checkEmail = $conn->prepare("
        SELECT parent_id
        FROM parents
        WHERE email = ?
        LIMIT 1
    ");

    $checkEmail->bind_param(
        "s",
        $email
    );

    $checkEmail->execute();

    $emailResult =
        $checkEmail->get_result();

    if ($emailResult->num_rows > 0) {

        $checkEmail->close();

        die(
            "This email is already registered."
        );
    }

    $checkEmail->close();
}

$fullName = trim(
    $firstName . " " .
    ($middleName !== ""
        ? $middleName . " "
        : "") .
    $lastName .
    ($suffix !== ""
        ? " " . $suffix
        : "")
);

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

try {

    $conn->begin_transaction();

    $parentID = "10000000001";

$result = $conn->query("
    SELECT parent_id
    FROM parents
    WHERE parent_id REGEXP '^[0-9]{11}$'
    ORDER BY CAST(parent_id AS UNSIGNED) DESC
    LIMIT 1
");

if ($result && $result->num_rows > 0) {

    $lastParent = $result->fetch_assoc();

    $lastNumber = (int) $lastParent["parent_id"];

    $nextNumber = $lastNumber + 1;

    $parentID = str_pad(
        (string) $nextNumber,
        11,
        "0",
        STR_PAD_LEFT
    );
}

    $stmt = $conn->prepare("
        INSERT INTO parents
        (
            parent_id,
            Fname,
            first_name,
            middle_name,
            last_name,
            suffix,
            Password,
            email,
            linked_student_num
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
    ");

    $stmt->bind_param(
        "ssssssss",
        $parentID,
        $fullName,
        $firstName,
        $middleName,
        $lastName,
        $suffix,
        $hashedPassword,
        $email
    );

    $stmt->execute();

    $stmt->close();

    foreach ($linkedStudents as $studentNum) {

        $checkStudent = $conn->prepare("
            SELECT student_num
            FROM students
            WHERE student_num = ?
            LIMIT 1
        ");

        $checkStudent->bind_param(
            "s",
            $studentNum
        );

        $checkStudent->execute();

        $studentResult =
            $checkStudent->get_result();

        if ($studentResult->num_rows === 0) {

            $checkStudent->close();

            throw new Exception(
                "Student " .
                $studentNum .
                " does not exist."
            );
        }

        $checkStudent->close();

        $checkParent = $conn->prepare("
            SELECT parent_id
            FROM students
            WHERE student_num = ?
            LIMIT 1
        ");

        $checkParent->bind_param(
            "s",
            $studentNum
        );

        $checkParent->execute();

        $parentResult =
            $checkParent->get_result();

        $studentData =
            $parentResult->fetch_assoc();

        $checkParent->close();

        if (
            !empty($studentData["parent_id"]) &&
            $studentData["parent_id"] !== $parentID
        ) {

            throw new Exception(
                "Student " .
                $studentNum .
                " is already linked to another parent."
            );
        }

        $checkLink = $conn->prepare("
            SELECT parent_id
            FROM parent_student_link
            WHERE student_num = ?
            LIMIT 1
        ");

        $checkLink->bind_param("s", $studentNum);
        $checkLink->execute();

        $linkResult = $checkLink->get_result();
        $linkExists = $linkResult->num_rows > 0;

        if ($linkExists) {
            $existingLink = $linkResult->fetch_assoc();

            $checkLink->close();

            if ($existingLink["parent_id"] !== $parentID) {
                throw new Exception(
                    "Student " .
                    $studentNum .
                    " is already linked to another parent."
                );
            }
        } else {
            $checkLink->close();
        }

        $linkStudent = $conn->prepare("
            INSERT INTO parent_student_link
            (
                parent_id,
                student_num
            )
            VALUES (?, ?)
        ");

        $linkStudent->bind_param(
            "ss",
            $parentID,
            $studentNum
        );

        if (!$linkExists) {
            $linkStudent->execute();
        }

        $linkStudent->close();

        $updateStudent = $conn->prepare("
            UPDATE students
            SET parent_id = ?
            WHERE student_num = ?
        ");

        $updateStudent->bind_param(
            "ss",
            $parentID,
            $studentNum
        );

        $updateStudent->execute();

        $updateStudent->close();

    }

    if (!empty($linkedStudents)) {

        $firstStudent =
            reset($linkedStudents);

        $updateParent = $conn->prepare("
            UPDATE parents
            SET linked_student_num = ?
            WHERE parent_id = ?
        ");

        $updateParent->bind_param(
            "ss",
            $firstStudent,
            $parentID
        );

        $updateParent->execute();

        $updateParent->close();
    }

    $conn->commit();

    $conn->close();

    header(
        "Location: parents.php?success=parent_added"
    );

    exit;

} catch (Throwable $error) {

    $conn->rollback();

    die(
        "Unable to create parent account: " .
        htmlspecialchars(
            $error->getMessage()
        )
    );
}

?>