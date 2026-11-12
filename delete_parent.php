<?php

require_once "db.php";

if (!isset($_GET["parent_id"])) {
    die("Parent ID is required.");
}

$parentID = $_GET["parent_id"];

$stmt = $conn->prepare(
    "DELETE FROM parents WHERE parent_id = ?"
);

$stmt->bind_param(
    "s",
    $parentID
);

if ($stmt->execute()) {

    header("Location: parents.php?deleted=1");
    exit;

} else {

    die(
        "Error deleting parent: " .
        $stmt->error
    );

}

?>