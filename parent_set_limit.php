
<?php
session_start();

header("Content-Type: application/json");

function sendResponse($status, $message, $httpCode = 200) {
    http_response_code($httpCode);

    echo json_encode([
        "status" => $status,
        "message" => $message
    ]);

    exit();
}

// Check parent login
if (!isset($_SESSION["parent_id"])) {
    sendResponse("error", "Your session has expired. Please log in again.", 401);
}

// Only accept POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse("error", "Invalid request method.", 405);
}

// Check CSRF token
$sessionToken = $_SESSION["csrf_token"] ?? "";
$submittedToken = $_POST["csrf_token"] ?? "";

if (
    empty($sessionToken) ||
    empty($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    sendResponse("error", "Invalid or expired request. Please refresh the page.", 403);
}

require_once "db.php";

$parentId = (string) $_SESSION["parent_id"];

$studentNumber = trim($_POST["student_num"] ?? "");
$amountInput = trim($_POST["transaction_limit"] ?? "");
$period = strtolower(trim($_POST["transaction_limit_period"] ?? ""));

// Validate student number
if ($studentNumber === "") {
    sendResponse("error", "Student number is required.", 400);
}

// Validate amount
if (
    $amountInput === "" ||
    !is_numeric($amountInput) ||
    !is_finite((float) $amountInput) ||
    (float) $amountInput <= 0
) {
    sendResponse("error", "Enter a valid amount greater than zero.", 400);
}

$amount = (float) $amountInput;

// Validate period
$allowedPeriods = ["daily", "weekly", "monthly"];

if (!in_array($period, $allowedPeriods, true)) {
    sendResponse("error", "Please select Daily, Weekly, or Monthly.", 400);
}

// Update only if the student is linked to this parent
$query = "
    UPDATE students AS s
    SET
        s.transaction_limit = ?,
        s.transaction_limit_period = ?
    WHERE s.student_num = ?
      AND EXISTS (
          SELECT 1
          FROM parent_student_link AS l
          WHERE l.student_num = s.student_num
            AND l.parent_id = ?
      )
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    sendResponse("error", "Database query preparation failed.", 500);
}

$stmt->bind_param(
    "dsss",
    $amount,
    $period,
    $studentNumber,
    $parentId
);

if (!$stmt->execute()) {
    sendResponse("error", "Unable to update the transaction limit.", 500);
}

// A zero affected-row count can mean the saved value was unchanged.
// Check whether the student is actually linked to the parent.
$checkQuery = "
    SELECT s.student_num
    FROM students AS s
    INNER JOIN parent_student_link AS l
        ON l.student_num = s.student_num
    WHERE s.student_num = ?
      AND l.parent_id = ?
    LIMIT 1
";

$checkStmt = $conn->prepare($checkQuery);

if (!$checkStmt) {
    sendResponse("error", "Unable to verify the student account.", 500);
}

$checkStmt->bind_param("ss", $studentNumber, $parentId);
$checkStmt->execute();

$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    sendResponse("error", "You are not authorized to update this student account.", 403);
}

$stmt->close();
$checkStmt->close();
$conn->close();

sendResponse(
    "success",
    "Transaction limit successfully saved."
);
?>
