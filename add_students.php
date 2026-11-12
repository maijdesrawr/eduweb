<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once "db.php";

header('Content-Type: application/json');
function generateTempPassword($length = 10)
{
    $characters =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[
            random_int(0, strlen($characters) - 1)
        ];
    }

    return $password;
}
function generateTempPIN($length = 6)
{
    $pin = '';

    for ($i = 0; $i < $length; $i++) {
        $pin .= random_int(0, 9);
    }

    return $pin;
}

function generateParentID($conn)
{
    $stmt = $conn->prepare("
        SELECT parent_id
        FROM parents
        WHERE parent_id REGEXP '^26[0-9]{9}$'
        ORDER BY parent_id DESC
        LIMIT 1
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $lastNumber = (int) substr($row['parent_id'], 2);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    $stmt->close();

    return "26" . str_pad($nextNumber, 9, "0", STR_PAD_LEFT);
}

$response = [
    "status" => "error",
    "message" => ""
];

try {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        throw new Exception(
            "Invalid request method."
        );
    }

    $parentMode =
        trim($_POST["parent_mode"] ?? "existing");

    if (
        $parentMode !== "new" &&
        $parentMode !== "existing"
    ) {

        throw new Exception(
            "Invalid parent option."
        );
    }

    $studentFirstName =
        trim($_POST["student_first_name"] ?? "");

    $studentMiddleName =
        trim($_POST["student_middle_name"] ?? "");

    $studentLastName =
        trim($_POST["student_last_name"] ?? "");

    $studentSuffix =
        trim($_POST["student_suffix"] ?? "");

    $studentNum =
        trim($_POST["student_num"] ?? "");

    if (
        empty($studentFirstName) ||
        empty($studentLastName) ||
        empty($studentNum)
    ) {

        throw new Exception(
            "Student first name, last name, and student number are required."
        );
    }

    if (
        !ctype_digit($studentNum) ||
        strlen($studentNum) !== 11
    ) {

        throw new Exception(
            "Student Number must be exactly 11 digits."
        );
    }

    $stmt = $conn->prepare("
        SELECT student_num
        FROM students
        WHERE student_num = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $studentNum
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $stmt->close();

        throw new Exception(
            "Student Number already exists."
        );
    }

    $stmt->close();

    $studentName =
        trim(
            $studentFirstName . " " .
            $studentMiddleName . " " .
            $studentLastName . " " .
            $studentSuffix
        );

    $studentTempPassword =
        generateTempPassword();

    $studentPassword =
        password_hash(
            $studentTempPassword,
            PASSWORD_DEFAULT
        );

    $studentTempPIN =
        generateTempPIN();

    $studentPIN =
        password_hash(
            $studentTempPIN,
            PASSWORD_DEFAULT
        );

    $qrCode =
        "QR_" . $studentNum;

    $balance = 0.00;

    $passwordChanged = 0;

    $conn->begin_transaction();

    try {

        $parentID = null;

        $parentTempPassword = null;

        if ($parentMode === "new") {

            $parentFirstName =
                trim(
                    $_POST["parent_first_name"] ?? ""
                );

            $parentMiddleName =
                trim(
                    $_POST["parent_middle_name"] ?? ""
                );

            $parentLastName =
                trim(
                    $_POST["parent_last_name"] ?? ""
                );

            $parentSuffix =
                trim(
                    $_POST["parent_suffix"] ?? ""
                );

            if (
                empty($parentFirstName) ||
                empty($parentLastName)
            ) {

                throw new Exception(
                    "Parent first name and last name are required."
                );
            }

            $parentName =
                trim(
                    $parentFirstName . " " .
                    $parentMiddleName . " " .
                    $parentLastName . " " .
                    $parentSuffix
                );

            $parentID =
                generateParentID($conn);

            $parentTempPassword =
                generateTempPassword();

            $parentPassword =
                password_hash(
                    $parentTempPassword,
                    PASSWORD_DEFAULT
                );

            $parentStmt = $conn->prepare("
                INSERT INTO parents (
                    parent_id,
                    Fname,
                    first_name,
                    middle_name,
                    last_name,
                    suffix,
                    Password,
                    linked_student_num,
                    password_changed
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    ?
                )
            ");

            $parentStmt->bind_param(
                "sssssssi",
                $parentID,
                $parentName,
                $parentFirstName,
                $parentMiddleName,
                $parentLastName,
                $parentSuffix,
                $parentPassword,
                $passwordChanged
            );

            $parentStmt->execute();

            $parentStmt->close();
        }

        else {

            $parentID =
                trim(
                    $_POST["parent_id"] ?? ""
                );

            if (!empty($parentID)) {

                $parentStmt = $conn->prepare("
                    SELECT parent_id
                    FROM parents
                    WHERE parent_id = ?
                    LIMIT 1
                ");

                $parentStmt->bind_param(
                    "s",
                    $parentID
                );

                $parentStmt->execute();

                $parentResult =
                    $parentStmt->get_result();


                if ($parentResult->num_rows === 0) {

                    $parentStmt->close();

                    throw new Exception(
                        "The Parent ID you entered does not exist."
                    );
                }

                $parentStmt->close();
            }

            else {

                $parentID = null;
            }

            $parentTempPassword = null;
        }

        $studentStmt = $conn->prepare("
            INSERT INTO students (
                student_num,
                Fname,
                first_name,
                middle_name,
                last_name,
                suffix,
                Password,
                pin,
                balance,
                parent_id,
                qr_code,
                password_changed
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $studentStmt->bind_param(
            "ssssssssdssi",
            $studentNum,
            $studentName,
            $studentFirstName,
            $studentMiddleName,
            $studentLastName,
            $studentSuffix,
            $studentPassword,
            $studentPIN,
            $balance,
            $parentID,
            $qrCode,
            $passwordChanged
        );

        $studentStmt->execute();

        $studentStmt->close();

        if (
            $parentMode === "new" &&
            !empty($parentID)
        ) {

            $updateParentStmt = $conn->prepare("
                UPDATE parents
                SET linked_student_num = ?
                WHERE parent_id = ?
            ");

            $updateParentStmt->bind_param(
                "ss",
                $studentNum,
                $parentID
            );

            $updateParentStmt->execute();

            $updateParentStmt->close();
        }

        if (!empty($parentID)) {

            $linkStmt = $conn->prepare("
                INSERT INTO parent_student_link (
                    parent_id,
                    student_num
                )
                VALUES (
                    ?,
                    ?
                )
            ");

            $linkStmt->bind_param(
                "ss",
                $parentID,
                $studentNum
            );

            $linkStmt->execute();

            $linkStmt->close();
        }

        $conn->commit();

        $response["status"] =
            "success";

        $response["message"] =
            "Student account created successfully.";

        $response["student_num"] =
            $studentNum;

        $response["student_temp_password"] =
            $studentTempPassword;

        $response["student_temp_pin"] =
            $studentTempPIN;

        $response["parent_id"] =
            $parentID;


        if ($parentMode === "new") {

            $response["parent_temp_password"] =
                $parentTempPassword;
        }

    } catch (Throwable $error) {

        $conn->rollback();

        throw $error;
    }

} catch (mysqli_sql_exception $error) {

    if ($error->getCode() == 1062) {

        $response["status"] =
            "error";

        $response["message"] =
            "A duplicate account or relationship already exists.";

    } elseif ($error->getCode() == 1452) {

        $response["status"] =
            "error";

        $response["message"] =
            "Unable to create the account because a required relationship does not exist.";

    } elseif ($error->getCode() == 1451) {

        $response["status"] =
            "error";

        $response["message"] =
            "This account cannot be changed because it is still linked to another record.";

    } else {

        $response["status"] =
            "error";

        $response["message"] =
            "Database Error: " .
            $error->getMessage();
    }

} catch (Throwable $error) {

    $response["status"] =
        "error";

    $response["message"] =
        $error->getMessage();
}

echo json_encode(
    $response,
    JSON_UNESCAPED_UNICODE
);

exit;

?>