<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "admin_session.php";

require_once "db.php";

$adminName = trim($_SESSION['admin_name'] ?? 'Super Administrator');

$nameParts = preg_split('/\s+/', $adminName);
$adminInitials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $adminInitials .= strtoupper(substr($part, 0, 1));
    }
}

$adminInitials = substr($adminInitials, 0, 2);

$totalStudentsQuery = "
    SELECT COUNT(*) AS total_students
    FROM students
";

$totalStudentsResult = $conn->query($totalStudentsQuery);

if (!$totalStudentsResult) {
    die("Error loading total students: " . $conn->error);
}

$totalStudentsData = $totalStudentsResult->fetch_assoc();
$totalStudents = $totalStudentsData["total_students"];


$studentsQuery = "
    SELECT
        student_num,
        Fname,
        first_name,
        middle_name,
        last_name,
        suffix,
        balance,
        parent_id,
        is_frozen,
        is_locked
    FROM students
    ORDER BY student_num DESC
";

$studentsResult = $conn->query($studentsQuery);

if (!$studentsResult) {
    die("Error loading students: " . $conn->error);
}

$parentSearchOptions = [];

$parentSearchQuery = "
    SELECT
        parent_id,
        Fname,
        first_name,
        middle_name,
        last_name,
        suffix
    FROM parents
    ORDER BY parent_id ASC
";

$parentSearchResult = $conn->query($parentSearchQuery);

if ($parentSearchResult) {

    while ($parent = $parentSearchResult->fetch_assoc()) {

        $parentName = trim(
            $parent["Fname"] ??
            (
                $parent["first_name"] . " " .
                $parent["middle_name"] . " " .
                $parent["last_name"] . " " .
                $parent["suffix"]
            )
        );

        $parentSearchOptions[] = [
            "parent_id" => $parent["parent_id"],
            "name" => $parentName
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Student Accounts</title>

    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

    <style>

        .parent-search-wrapper {
            position: relative;
            width: 100%;
        }

        .parent-search-input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: var(--text);
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }

        .parent-search-input:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(207, 48, 46, 0.08);
        }

        .parent-search-results {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;

            max-height: 220px;
            overflow-y: auto;

            background: var(--white);

            border: 1px solid var(--border);
            border-radius: 9px;

            box-shadow: 0 8px 20px rgba(30, 20, 20, 0.12);

            z-index: 2000;
        }

        .parent-search-result {
            width: 100%;

            display: block;

            text-align: left;

            border: none;
            border-bottom: 1px solid var(--border);

            background: var(--white);
            color: var(--text);

            padding: 11px 12px;

            font-size: 14px;

            cursor: pointer;
        }

        .parent-search-result:last-child {
            border-bottom: none;
        }

        .parent-search-result:hover {
            background: var(--pastel-red);
        }

        .parent-search-result strong {
            color: var(--red);
        }

        .parent-search-result-name {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 13px;
        }

        .no-parent-results {
            padding: 12px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .selected-parent {
            margin-top: 8px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 10px;

            padding: 10px 12px;

            background: var(--pastel-red);

            border-radius: 9px;

            font-size: 14px;
        }

        .selected-parent-info {
            flex: 1;
            min-width: 0;
        }

        .selected-parent-info strong {
            color: var(--red);
        }

        .selected-parent-info small {
            display: block;
            margin-top: 2px;
            color: var(--muted);
        }

        .remove-parent-btn {
            border: none;
            background: transparent;

            color: var(--red);

            font-size: 20px;
            font-weight: bold;

            cursor: pointer;

            line-height: 1;

            padding: 0 4px;
        }

        .remove-parent-btn:hover {
            opacity: 0.7;
        }

        .parent-selection-message {
            margin-top: 7px;
            font-size: 12px;
            color: var(--muted);
        }

        .parent-selection-message.selected {
            color: var(--green);
        }

        .required {
            color: var(--red);
        }

    

        .student-id-link {
            color: var(--red);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .student-id-link:hover {
            text-decoration: underline;
        }


/* Student credentials success modal - hidden until student is created */
.credentials-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.55);
    padding: 20px;
}

.credentials-modal.show {
    display: flex;
}

.credentials-box {
    position: relative;
    width: min(480px, 100%);
    background: #fff;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    text-align: center;
}

.credentials-close {
    position: absolute;
    top: 10px;
    right: 14px;
    border: 0;
    background: transparent;
    font-size: 28px;
    cursor: pointer;
}

.credentials-icon {
    width: 55px;
    height: 55px;
    margin: 0 auto 15px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #22c55e;
    color: white;
    font-size: 30px;
    font-weight: 700;
}

.credentials-box h2 {
    margin: 0 0 8px;
}

.credentials-subtitle {
    margin-bottom: 22px;
    color: #666;
}

.credential-row {
    margin-bottom: 14px;
    text-align: left;
}

.credential-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}

.credential-value {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f8f8f8;
}

.credential-value strong {
    flex: 1;
    word-break: break-all;
}

.credential-copy {
    border: 0;
    border-radius: 6px;
    padding: 7px 12px;
    cursor: pointer;
}

.credentials-done {
    width: 100%;
    margin-top: 12px;
    padding: 11px 16px;
    border: 0;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}


/* Lock / unlock confirmation modal */
.lock-confirm-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.55);
}
.lock-confirm-modal.show { display: flex; }
.lock-confirm-box {
    width: min(420px, 100%);
    padding: 28px;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
    text-align: center;
}
.lock-confirm-box h2 { margin: 0 0 10px; color: #222; }
.lock-confirm-box p { margin: 0 0 22px; color: #666; line-height: 1.5; }
.lock-confirm-actions { display: flex; justify-content: center; gap: 10px; }
.lock-confirm-actions button {
    min-width: 110px; padding: 10px 16px; border: 0;
    border-radius: 8px; cursor: pointer; font-weight: 600;
}
.lock-confirm-cancel { background: #eee; color: #333; }
.lock-confirm-submit { background: var(--red, #cf302e); color: #fff; }
.lock-confirm-submit:disabled { opacity: .65; cursor: wait; }

</style>

</head>

<body>

<div class="app">

    <aside class="sidebar">

        <div class="brand">

            <div class="logo">
                E
            </div>

            <div>

                <div class="brand-title">
                    EduPocket
                </div>

                <span class="brand-subtitle">
                    Admin Dashboard
                </span>

            </div>

        </div>

        <div class="nav-title">
            User Management
        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='dashboard.php'"
            >
                <span class="nav-icon">⌂</span>
                Dashboard
            </button>

            <button
                class="active"
                onclick="window.location.href='students.php'"
            >
                <span class="nav-icon"></span>
                Students
            </button>

            <button
                onclick="window.location.href='parents.php'"
            >
                <span class="nav-icon"></span>
                Parents / Guardians
            </button>

            <button
                onclick="window.location.href='pos_cashiers.php'"
            >
                <span class="nav-icon"></span>
                POS / Cashiers
            </button>

        </nav>

        <div class="nav-title finance-title">
            Transaction Records
        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='topups.php'"
            >
                <span class="nav-icon">₱</span>
                Top-Ups
            </button>


            <button
                onclick="window.location.href='transactions.php'"
            >
                <span class="nav-icon">▥</span>
                Transactions
            </button>

        </nav>

        <div class="sidebar-footer">

    <div class="admin-logout-menu" id="adminLogoutMenu">
        <a href="logout.php" class="admin-logout-link">
            ↪ Log Out
        </a>
    </div>

    <button
        type="button"
        class="admin-card"
        id="adminProfileButton"
        onclick="toggleAdminMenu(event)"
    >
        <span class="avatar">
            <?php echo htmlspecialchars($adminInitials); ?>
        </span>

        <span class="admin-profile-details">
            <strong>
                <?php echo htmlspecialchars($adminName); ?>
            </strong>

            <small>System Administrator</small>
        </span>

        <span class="admin-profile-chevron">⌃</span>
    </button>

</div>

    </aside>

    <main>

        <!-- PAGE HEADER -->

        <div class="topbar">

            <div>

                <div class="eyebrow">
                    User Management
                </div>

                <h1>
                    Student Accounts
                </h1>

            </div>

            <div class="top-actions">

                <input
                    type="search"
                    id="studentSearch"
                    class="search"
                    placeholder="Search student name or ID..."
                    autocomplete="off"
                >

                <button
                    class="notification"
                >
                    🔎︎
                </button>

            </div>

        </div>

        <!-- SUMMARY -->

        <div class="student-summary-row">

            <div class="single-total-card">

                <div class="single-total-info">

                    <div class="stat-label">
                        Total Students
                    </div>

                    <div class="stat-number">

                        <?php
                        echo number_format($totalStudents);
                        ?>

                    </div>

                    <div class="stat-trend">
                        Registered student accounts
                    </div>

                </div>

            </div>

            <button
                type="button"
                class="add-student-btn"
                onclick="openStudentModal()"
            >

                <span>+</span>

                New Student Account

            </button>

        </div>

        <!-- STUDENT TABLE -->

        <section class="card table-card">

            <div class="card-header">

                <h2>
                    Registered Student Accounts
                </h2>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Student Number
                            </th>

                            <th>
                                Account Status
                            </th>

                            <th>
                                Balance
                            </th>

                            <th class="actions-header">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody id="usersTable">

                    <?php

                    if ($studentsResult->num_rows > 0) {

                        while (
                            $student =
                            $studentsResult->fetch_assoc()
                        ) {

                            $name =
                                $student["Fname"];

                            $studentNumber =
                                $student["student_num"];

                            $balance =
                                $student["balance"];

                            $isFrozen =
                                (int)$student["is_frozen"];

                            $isLocked =
                                (int)$student["is_locked"];


                            /* GET INITIALS */

                            $words = explode(
                                " ",
                                trim($name)
                            );

                            $initials = "";

                            foreach (
                                $words
                                as $word
                            ) {

                                if (!empty($word)) {

                                    $initials .=
                                        strtoupper(
                                            substr(
                                                $word,
                                                0,
                                                1
                                            )
                                        );

                                }

                            }

                            $initials =
                                substr(
                                    $initials,
                                    0,
                                    2
                                );

                    ?>

                        <tr>

                            <!-- STUDENT -->

                            <td>

                                <div class="user-cell">

                                    <div class="avatar">

                                        <?php
                                        echo htmlspecialchars(
                                            $initials
                                        );
                                        ?>

                                    </div>


                                    <div>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $name
                                            );
                                            ?>

                                        </strong>

                                    </div>

                                </div>

                            </td>

                            <td>
                            
                                    <?php
                                    echo htmlspecialchars(
                                        $studentNumber
                                    );
                                    ?>
                                </td>

                            <!-- ACCOUNT STATUS -->

                            <td>

                                <?php
                                if ($isFrozen):
                                ?>

                                    <span class="badge student">
                                        Frozen
                                    </span>

                                <?php
                                elseif ($isLocked):
                                ?>

                                    <span class="badge student">
                                        Locked
                                    </span>

                                <?php
                                else:
                                ?>

                                    -

                                <?php
                                endif;
                                ?>

                            </td>


                            <!-- BALANCE -->

                            <td>

                                ₱<?php

                                echo number_format(
                                    (float)$balance,
                                    2
                                );

                                ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="action-buttons">

                                    <button
                                        type="button"
                                        class="view-btn"
                                        onclick="viewStudent('<?php echo htmlspecialchars($studentNumber, ENT_QUOTES); ?>')"
                                    >
                                        View
                                    </button>

                                    <button
                                        type="button"
                                        class="delete-btn"
                                        onclick="deleteStudent('<?php echo htmlspecialchars($studentNumber, ENT_QUOTES); ?>')"
                                    >
                                        Delete
                                    </button>

                                    <?php if ($isLocked): ?>
                                        <button
                                            type="button"
                                            class="freeze-btn"
                                            onclick="lockStudent('<?php echo htmlspecialchars($studentNumber, ENT_QUOTES); ?>', true)"
                                        >
                                            Unlock
                                        </button>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="freeze-btn"
                                            onclick="lockStudent('<?php echo htmlspecialchars($studentNumber, ENT_QUOTES); ?>', false)"
                                        >
                                            Lock
                                        </button>
                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>


                    <?php

                        }

                    } else {

                    ?>

                        <tr>

                            <td
                                colspan="5"
                                style="text-align:center;"
                            >
                                No student accounts found.
                            </td>

                        </tr>

                    <?php

                    }

                    ?>

                    </tbody>

                </table>

            </div>

        </section>

        <!-- NEW STUDENT MODAL -->

        <div
            id="studentModal"
            class="modal"
        >

            <div class="modal-content">

                <div class="modal-header">

                    <h2>
                        New Student Account
                    </h2>

                    <button
                        type="button"
                        class="close-modal"
                        onclick="closeStudentModal()"
                    >
                        &times;
                    </button>

                </div>

                <form
                    id="studentForm"
                    action="add_students.php"
                    method="POST"
                >

                    <input
                        type="hidden"
                        name="category"
                        id="studentCategory"
                        value="student"
                    >

                    <!-- PARENT / GUARDIAN -->

                    <div class="form-section">

                        <h3>
                            Parent / Guardian
                        </h3>


                        <div class="parent-option-container">

                            <label class="parent-option">

                                <input
                                    type="radio"
                                    name="parent_mode"
                                    value="new"
                                    checked
                                    onchange="toggleParentMode()"
                                >

                                <span>
                                    Create New Parent
                                </span>

                            </label>


                            <label class="parent-option">

                                <input
                                    type="radio"
                                    name="parent_mode"
                                    value="existing"
                                    onchange="toggleParentMode()"
                                >

                                <span>
                                    Link to Existing Parent
                                </span>

                            </label>

                        </div>

                    </div>

                    <!-- STUDENT INFORMATION -->

                    <div class="form-section">

                        <h3>
                            Student Information
                        </h3>

                        <div class="form-row">

                            <div class="form-group">

                                <label>
                                    First Name
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="student_first_name"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Middle Name
                                </label>

                                <input
                                    type="text"
                                    name="student_middle_name"
                                >

                            </div>

                        </div>

                        <div class="form-row">

                            <div class="form-group">

                                <label>
                                    Last Name
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="student_last_name"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Suffix
                                </label>

                                <input
                                    type="text"
                                    name="student_suffix"
                                    placeholder="Jr., Sr., III"
                                >

                            </div>

                        </div>

                        <div class="form-group">

                            <label>
                                Student Number
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="student_num"
                                maxlength="11"
                                minlength="11"
                                pattern="[0-9]{11}"
                                inputmode="numeric"
                                placeholder="11-digit student number"
                                required
                            >

                        </div>

                    </div>

                    <!-- NEW PARENT -->

                    <div
                        id="newParentSection"
                        class="form-section"
                    >

                        <h3>
                            New Parent Information
                        </h3>


                        <div class="form-row">

                            <div class="form-group">

                                <label>
                                    First Name
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="parent_first_name"
                                    id="parent_first_name"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Middle Name
                                </label>

                                <input
                                    type="text"
                                    name="parent_middle_name"
                                    id="parent_middle_name"
                                >

                            </div>

                        </div>

                        <div class="form-row">

                            <div class="form-group">

                                <label>
                                    Last Name
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    name="parent_last_name"
                                    id="parent_last_name"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Suffix
                                </label>

                                <input
                                    type="text"
                                    name="parent_suffix"
                                    id="parent_suffix"
                                    placeholder="Jr., Sr., III"
                                >

                            </div>

                        </div>

                    </div>

                    <!-- EXISTING PARENT -->

                    <div
                        id="existingParentSection"
                        class="form-section"
                        style="display:none;"
                    >

                        <h3>
                            Existing Parent
                        </h3>

                        <div class="form-group">

                            <label>
                                Parent ID
                                <span class="required">*</span>
                            </label>

                            <div class="parent-search-wrapper">

                                <input
                                    type="text"
                                    id="parentSearch"
                                    class="parent-search-input"
                                    placeholder="Search parent ID or name..."
                                    autocomplete="off"
                                    oninput="searchParents(this.value)"
                                    onfocus="searchParents(this.value)"
                                >

                                <div
                                    id="parentSearchResults"
                                    class="parent-search-results"
                                ></div>

                            </div>

                            <input
                                type="hidden"
                                name="parent_id"
                                id="parent_id"
                            >

                            <div
                                id="selectedParent"
                                style="display:none;"
                                class="selected-parent"
                            >

                                <div
                                    class="selected-parent-info"
                                >

                                    <strong
                                        id="selectedParentID"
                                    ></strong>

                                    <small
                                        id="selectedParentName"
                                    ></small>

                                </div>


                                <button
                                    type="button"
                                    class="remove-parent-btn"
                                    onclick="removeSelectedParent()"
                                    title="Remove parent"
                                >
                                    ×
                                </button>

                            </div>


                            <div
                                id="parentSelectionMessage"
                                class="parent-selection-message"
                            >
                                Type a Parent ID or parent name, then select a parent.
                            </div>

                        </div>

                    </div>


                    <!-- MODAL BUTTONS -->

                    <div class="modal-actions">

                        <button
                            type="button"
                            class="cancel-btn"
                            onclick="closeStudentModal()"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            Create Student
                        </button>

                    </div>

                </form>

            </div>

        </div>

        <div id="lockConfirmModal" class="lock-confirm-modal"
             aria-hidden="true" role="dialog" aria-modal="true"
             aria-labelledby="lockConfirmTitle">
            <div class="lock-confirm-box">
                <h2 id="lockConfirmTitle">Confirm Account Action</h2>
                <p id="lockConfirmMessage"></p>
                <div class="lock-confirm-actions">
                    <button type="button" class="lock-confirm-cancel"
                            onclick="closeLockConfirmModal()">Cancel</button>
                    <button type="button" id="confirmLockActionButton"
                            class="lock-confirm-submit"
                            onclick="confirmLockAction()">Confirm</button>
                </div>
            </div>
        </div>

        <div
            id="studentCredentialsModal"
            class="credentials-modal"
            aria-hidden="true"
        >
            <div class="credentials-box">
                <button
                    type="button"
                    class="credentials-close"
                    onclick="closeStudentCredentials()"
                    aria-label="Close"
                >
                    &times;
                </button>

                <div class="credentials-icon">✓</div>

                <h2>Student Created Successfully!</h2>

                <p class="credentials-subtitle">
                    Please save these temporary login credentials.
                </p>

                <div class="credential-row">
                    <span class="credential-label">
                        Student Number
                    </span>

                    <div class="credential-value">
                        <strong id="createdStudentNumber"></strong>

                        <button
                            type="button"
                            class="credential-copy"
                            onclick="copyStudentCredential('createdStudentNumber')"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="credential-row">
                    <span class="credential-label">
                        Temporary Password
                    </span>

                    <div class="credential-value">
                        <strong id="createdStudentPassword"></strong>

                        <button
                            type="button"
                            class="credential-copy"
                            onclick="copyStudentCredential('createdStudentPassword')"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="credential-row">
                    <span class="credential-label">
                        Temporary PIN
                    </span>

                    <div class="credential-value">
                        <strong id="createdStudentPIN"></strong>

                        <button
                            type="button"
                            class="credential-copy"
                            onclick="copyStudentCredential('createdStudentPIN')"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="credential-row">
                    <span class="credential-label">
                        Parent ID
                    </span>

                    <div class="credential-value">
                        <strong id="createdParentID"></strong>

                        <button
                            type="button"
                            class="credential-copy"
                            onclick="copyStudentCredential('createdParentID')"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="credential-row" id="parentPasswordRow">
                    <span class="credential-label">
                        Parent Temporary Password
                    </span>

                    <div class="credential-value">
                        <strong id="createdParentPassword"></strong>

                        <button
                            type="button"
                            class="credential-copy"
                            onclick="copyStudentCredential('createdParentPassword')"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="credentials-done"
                    onclick="closeStudentCredentials()"
                >
                    Done
                </button>
            </div>
        </div>

    </main>

</div>

<script>

const parents = <?php
    echo json_encode(
        $parentSearchOptions,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );
?>;

function openStudentModal() {

    const modal =
        document.getElementById("studentModal");

    if (!modal) {
        return;
    }

    modal.style.display = "flex";

    toggleParentMode();

}

function closeStudentModal() {

    const modal =
        document.getElementById("studentModal");

    if (!modal) {
        return;
    }

    modal.style.display = "none";

}

function toggleParentMode() {

    const selected =
        document.querySelector(
            'input[name="parent_mode"]:checked'
        );

    if (!selected) {
        return;
    }

    const newParentSection =
        document.getElementById(
            "newParentSection"
        );

    const existingParentSection =
        document.getElementById(
            "existingParentSection"
        );

    const parentFirstName =
        document.getElementById(
            "parent_first_name"
        );

    const parentLastName =
        document.getElementById(
            "parent_last_name"
        );

    const parentID =
        document.getElementById(
            "parent_id"
        );

    const category =
        document.getElementById(
            "studentCategory"
        );

    const parentSearch =
        document.getElementById(
            "parentSearch"
        );

    if (selected.value === "new") {

        newParentSection.style.display =
            "block";

        existingParentSection.style.display =
            "none";

        parentFirstName.required = true;

        parentLastName.required = true;

        parentID.required = false;

        parentID.value = "";

        category.value = "student";


        if (parentSearch) {
            parentSearch.value = "";
        }

        removeSelectedParent();

    } else {

        newParentSection.style.display =
            "none";

        existingParentSection.style.display =
            "block";

        parentFirstName.required = false;

        parentLastName.required = false;

        parentID.required = true;

        parentFirstName.value = "";

        parentLastName.value = "";

        category.value = "child";

    }

}

function searchParents(searchValue) {

    const results =
        document.getElementById(
            "parentSearchResults"
        );


    if (!results) {
        return;
    }

    const search =
        searchValue
            .trim()
            .toLowerCase();


    results.innerHTML = "";


    const filteredParents =
        parents.filter(function(parent) {

            return (

                parent.parent_id
                    .toLowerCase()
                    .includes(search)

                ||

                parent.name
                    .toLowerCase()
                    .includes(search)

            );

        });

    if (
        filteredParents.length === 0
    ) {

        if (search !== "") {

            results.innerHTML =
                '<div class="no-parent-results">' +
                'No parents found.' +
                '</div>';

        }

        return;

    }

    filteredParents.forEach(
        function(parent) {

            const result =
                document.createElement(
                    "button"
                );


            result.type = "button";


            result.className =
                "parent-search-result";


            const parentID =
                document.createElement(
                    "strong"
                );


            parentID.textContent =
                parent.parent_id;


            const parentName =
                document.createElement(
                    "span"
                );


            parentName.className =
                "parent-search-result-name";


            parentName.textContent =
                parent.name;


            result.appendChild(
                parentID
            );


            result.appendChild(
                parentName
            );


            result.onclick =
                function() {

                    selectParent(
                        parent.parent_id,
                        parent.name
                    );

                };


            results.appendChild(
                result
            );

        }
    );

}

function selectParent(
    parentID,
    parentName
) {

    const hiddenParentID =
        document.getElementById(
            "parent_id"
        );


    const searchInput =
        document.getElementById(
            "parentSearch"
        );


    const results =
        document.getElementById(
            "parentSearchResults"
        );


    const selectedParent =
        document.getElementById(
            "selectedParent"
        );


    const selectedParentID =
        document.getElementById(
            "selectedParentID"
        );


    const selectedParentName =
        document.getElementById(
            "selectedParentName"
        );


    const message =
        document.getElementById(
            "parentSelectionMessage"
        );


    hiddenParentID.value =
        parentID;


    selectedParentID.textContent =
        parentID;


    selectedParentName.textContent =
        parentName;


    selectedParent.style.display =
        "flex";


    searchInput.value = "";


    results.innerHTML = "";


    message.textContent =
        "Parent selected.";


    message.classList.add(
        "selected"
    );

}

function removeSelectedParent() {

    const hiddenParentID =
        document.getElementById(
            "parent_id"
        );


    const selectedParent =
        document.getElementById(
            "selectedParent"
        );


    const searchInput =
        document.getElementById(
            "parentSearch"
        );


    const message =
        document.getElementById(
            "parentSelectionMessage"
        );


    if (hiddenParentID) {

        hiddenParentID.value = "";

    }


    if (selectedParent) {

        selectedParent.style.display =
            "none";

    }


    if (searchInput) {

        searchInput.value = "";

    }


    if (message) {

        message.textContent =
            "Type a Parent ID or parent name, then select a parent.";

        message.classList.remove(
            "selected"
        );

    }

}

document.addEventListener(
    "click",
    function(event) {

        const wrapper =
            document.querySelector(
                ".parent-search-wrapper"
            );


        if (
            wrapper &&
            !wrapper.contains(
                event.target
            )
        ) {

            const results =
                document.getElementById(
                    "parentSearchResults"
                );


            if (results) {

                results.innerHTML = "";

            }

        }

    }
);

window.addEventListener(
    "click",
    function(event) {

        const modal =
            document.getElementById(
                "studentModal"
            );


        if (
            event.target === modal
        ) {

            closeStudentModal();

        }

    }
);

function viewStudent(
    studentNumber
) {

    window.location.href =
        "view_student.php?student_num=" +
        encodeURIComponent(
            studentNumber
        );

}

let pendingLockStudentNumber = "";
let pendingLockAction = "";

function lockStudent(studentNumber, isCurrentlyLocked) {
    const modal = document.getElementById("lockConfirmModal");
    const message = document.getElementById("lockConfirmMessage");
    const title = document.getElementById("lockConfirmTitle");
    const confirmButton = document.getElementById("confirmLockActionButton");

    if (!modal || !message || !title || !confirmButton) return;

    pendingLockStudentNumber = studentNumber;
    pendingLockAction = isCurrentlyLocked ? "unlock" : "lock";

    if (pendingLockAction === "lock") {
        title.textContent = "Lock Student Account?";
        message.textContent =
            "Are you sure you want to lock student account " +
            studentNumber +
            "? The student may no longer be able to use their account.";
        confirmButton.textContent = "Lock Account";
    } else {
        title.textContent = "Unlock Student Account?";
        message.textContent =
            "Are you sure you want to unlock student account " +
            studentNumber +
            "?";
        confirmButton.textContent = "Unlock Account";
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeLockConfirmModal() {
    const modal = document.getElementById("lockConfirmModal");
    if (!modal) return;

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
    pendingLockStudentNumber = "";
    pendingLockAction = "";

    const confirmButton = document.getElementById("confirmLockActionButton");
    if (confirmButton) {
        confirmButton.disabled = false;
        confirmButton.textContent = "Confirm";
    }
}

function confirmLockAction() {
    if (!pendingLockStudentNumber || !pendingLockAction) return;

    const confirmButton = document.getElementById("confirmLockActionButton");
    if (confirmButton) {
        confirmButton.disabled = true;
        confirmButton.textContent = "Please wait...";
    }

    // The existing endpoint performs the lock/unlock operation.
    window.location.href =
        "lock_student.php?student_num=" +
        encodeURIComponent(pendingLockStudentNumber);
}

document.addEventListener("click", function(event) {
    const modal = document.getElementById("lockConfirmModal");
    if (modal && event.target === modal) closeLockConfirmModal();
});

document.addEventListener("keydown", function(event) {
    const modal = document.getElementById("lockConfirmModal");
    if (event.key === "Escape" && modal && modal.classList.contains("show")) {
        closeLockConfirmModal();
    }
});

function deleteStudent(
    studentNumber
) {

    if (
        confirm(
            "Are you sure you want to permanently delete this student account?"
        )
    ) {

        window.location.href =
            "delete_student.php?student_num=" +
            encodeURIComponent(
                studentNumber
            );

    }

}

function searchStudents(searchValue) {

    searchValue =
        searchValue
            .toLowerCase()
            .trim();

    const tableBody =
        document.getElementById("usersTable");

    if (!tableBody) {
        return;
    }

    const rows =
        tableBody.querySelectorAll(
            "tr:not(#noSearchResults)"
        );

    let foundStudents = 0;

    rows.forEach(function(row) {

        const text =
            row.textContent
                .toLowerCase()
                .trim();

        if (text.includes(searchValue)) {

            row.style.display = "";

            foundStudents++;

        } else {

            row.style.display = "none";

        }

    });

    const existingMessage =
        document.getElementById("noSearchResults");

    if (existingMessage) {
        existingMessage.remove();
    }

    if (
        foundStudents === 0 &&
        searchValue !== ""
    ) {

        const messageRow =
            document.createElement("tr");

        messageRow.id =
            "noSearchResults";

        messageRow.innerHTML = `
            <td
                colspan="5"
                style="
                    text-align: center;
                    padding: 30px;
                    color: #777;
                "
            >
                No students found for
                "<strong>${escapeHtml(searchValue)}</strong>"
            </td>
        `;

        tableBody.appendChild(messageRow);
    }

}

function escapeHtml(value) {

    const div =
        document.createElement("div");

    div.textContent = value;

    return div.innerHTML;

}

const studentSearch =
    document.getElementById(
        "studentSearch"
    );

if (studentSearch) {

    studentSearch.addEventListener(
        "input",
        function() {

            searchStudents(
                this.value
            );

        }
    );

}

function showStudentCredentials(data) {
    const modal = document.getElementById("studentCredentialsModal");
    const studentNumber = document.getElementById("createdStudentNumber");
    const password = document.getElementById("createdStudentPassword");
    const pin = document.getElementById("createdStudentPIN");
    const parentID = document.getElementById("createdParentID");
    const parentPassword = document.getElementById("createdParentPassword");
    const parentPasswordRow = document.getElementById("parentPasswordRow");

    if (!modal || !studentNumber || !password || !pin || !parentID || !parentPassword || !parentPasswordRow) {
        return;
    }

    studentNumber.textContent = data.student_num || "";
    password.textContent = data.student_temp_password || "";
    pin.textContent = data.student_temp_pin || "";
    parentID.textContent = data.parent_id || "";

    if (data.parent_temp_password) {
        parentPassword.textContent = data.parent_temp_password;
        parentPasswordRow.style.display = "block";
    } else {
        parentPassword.textContent = "";
        parentPasswordRow.style.display = "none";
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeStudentCredentials() {
    const modal = document.getElementById("studentCredentialsModal");

    if (!modal) {
        return;
    }

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");

        window.location.reload();
}

function copyStudentCredential(elementId) {
    const element = document.getElementById(elementId);

    if (!element) {
        return;
    }

    const value = element.textContent.trim();

    if (!value) {
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value)
            .then(function () {
                alert("Copied to clipboard.");
            })
            .catch(function () {
                alert("Unable to copy. Please copy it manually.");
            });
    } else {
        const tempInput = document.createElement("input");
        tempInput.value = value;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        tempInput.remove();
        alert("Copied to clipboard.");
    }
}

document.addEventListener("click", function (event) {
    const modal = document.getElementById("studentCredentialsModal");

    if (modal && event.target === modal) {
        closeStudentCredentials();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeStudentCredentials();
    }
});

const studentForm =
    document.getElementById(
        "studentForm"
    );


if (studentForm) {

    studentForm.addEventListener(
        "submit",
        function(event) {

            event.preventDefault();


            const form =
                this;


            const formData =
                new FormData(
                    form
                );


            const parentMode =
                document.querySelector(
                    'input[name="parent_mode"]:checked'
                );

            if (
                parentMode &&
                parentMode.value ===
                    "existing"
            ) {

                const parentID =
                    document.getElementById(
                        "parent_id"
                    ).value.trim();


                if (!parentID) {

                    alert(
                        "Please search for and select an existing parent."
                    );

                    return;

                }

            }

            const submitButton =
                form.querySelector(
                    'button[type="submit"]'
                );


            if (submitButton) {

                submitButton.disabled =
                    true;

                submitButton.textContent =
                    "Creating...";

            }

            fetch(
                "add_students.php",
                {
                    method: "POST",
                    body: formData
                }
            )

            .then(
                function(response) {

                    return response.json();

                }
            )

            .then(
                function(data) {

                    if (
                        data.status ===
                        "success"
                    ) {

                        closeStudentModal();

                        showStudentCredentials(data);
                        
                    } else {

                        alert(
                            data.message ||
                            "Unable to create student account."
                        );

                    }

                }
            )

            .catch(
                function(error) {

                    console.error(
                        "Create student error:",
                        error
                    );


                    alert(
                        "An unexpected error occurred while creating the student account."
                    );

                }
            )

            .finally(
                function() {

                    if (submitButton) {

                        submitButton.disabled =
                            false;

                        submitButton.textContent =
                            "Create Student";

                    }

                }
            );

        }
    );

}

function toggleAdminMenu(event) {
    event.stopPropagation();

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    menu.classList.toggle("show");

    const isOpen = menu.classList.contains("show");
    profile.setAttribute("aria-expanded", isOpen);
}

// Close dropdown when clicking outside
document.addEventListener("click", function(event) {

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    if (!menu.contains(event.target) &&
        !profile.contains(event.target)) {

        menu.classList.remove("show");
        profile.setAttribute("aria-expanded", "false");
    }

});

</script>

</body>

</html>