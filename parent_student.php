
<?php
session_start();

if (!isset($_SESSION['parent_id'])) {
    header("Location: index.php");
    exit();
}

require_once "db.php";

$parentId = (string) $_SESSION['parent_id'];
$parentName = (string) ($_SESSION['parent_name'] ?? 'Parent');

// CSRF token for the Set Limit request
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Parent initials
$nameParts = preg_split('/\s+/', $parentName);
$parentInitials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $parentInitials .= strtoupper(substr($part, 0, 1));
    }
}

$parentInitials = substr($parentInitials, 0, 2);

// TOTAL LINKED STUDENTS
$studentsQuery = "
    SELECT COUNT(DISTINCT student_num) AS total_students
    FROM parent_student_link
    WHERE parent_id = ?
";

$studentsStmt = $conn->prepare($studentsQuery);
$studentsStmt->bind_param("s", $parentId);
$studentsStmt->execute();

$studentsResult = $studentsStmt->get_result();
$studentData = $studentsResult->fetch_assoc();
$totalStudents = (int) $studentData['total_students'];

// GET LINKED STUDENTS
$studentQuery = "
    SELECT
        s.student_num,
        s.Fname,
        s.balance,
        s.transaction_limit,
        s.transaction_limit_period,
        s.is_locked,
        s.is_frozen,
        (
            SELECT COALESCE(SUM(t.amount), 0)
            FROM transactions AS t
            WHERE t.student_num = s.student_num
              AND LOWER(t.transaction_type) = 'payment'
        ) AS expenses
    FROM parent_student_link AS l
    INNER JOIN students AS s
        ON l.student_num = s.student_num
    WHERE l.parent_id = ?
    ORDER BY s.student_num ASC
";

$studentStmt = $conn->prepare($studentQuery);

if (!$studentStmt) {
    die("Student query preparation failed: " . $conn->error);
}

$studentStmt->bind_param("s", $parentId);
$studentStmt->execute();

$students = $studentStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Parent Dashboard</title>

    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

    <style>
        /* FREEZE / UNFREEZE MODAL */
        .lock-confirm-modal,
        .limit-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.55);
        }

        .lock-confirm-modal.show,
        .limit-modal.show {
            display: flex;
        }

        .lock-confirm-box,
        .limit-modal-box {
            width: min(420px, 100%);
            padding: 28px;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
        }

        .lock-confirm-box {
            text-align: center;
        }

        .lock-confirm-box h2,
        .limit-modal-box h2 {
            margin: 0 0 10px;
            color: #222;
        }

        .lock-confirm-box p {
            margin: 0 0 22px;
            color: #666;
            line-height: 1.5;
        }

        .limit-modal-description {
            color: #666;
            margin-bottom: 20px;
        }

        .limit-form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .limit-form-group label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-weight: 600;
        }

        .limit-form-group input,
        .limit-form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            background: #fff;
        }

        .limit-form-group input:focus,
        .limit-form-group select:focus {
            outline: none;
            border-color: #cf302e;
        }

        .lock-confirm-actions,
        .limit-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        .lock-confirm-actions button,
        .limit-modal-actions button {
            min-width: 110px;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .lock-confirm-cancel,
        .limit-cancel-btn {
            background: #eee;
            color: #333;
        }

        .lock-confirm-submit,
        .limit-save-btn {
            background: var(--red, #cf302e);
            color: #fff;
        }

        .lock-confirm-submit:disabled,
        .limit-save-btn:disabled {
            opacity: .65;
            cursor: wait;
        }

        .limit-error {
            display: none;
            margin-top: 12px;
            color: #cf302e;
            font-size: 14px;
        }

        .limit-error.show {
            display: block;
        }
    </style>
</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

        <div class="brand">
            <div class="logo">E</div>

            <div>
                <div class="brand-title">EduPocket</div>
                <span class="brand-subtitle">Parent Dashboard</span>
            </div>
        </div>

        <div class="nav-title">User Management</div>

        <nav class="nav">
            <button onclick="window.location.href='parent_dashboard.php'">
                <span class="nav-icon">⌂</span>
                Dashboard
            </button>

            <button class="active" onclick="window.location.href='parent_student.php'">
                <span class="nav-icon"></span>
                Students
            </button>

            <button
            onclick="window.location.href='parent_graph.php'"
            >
                <span class="nav-icon">    
                </span>
                Graphs
            </button>
            
        </nav>

        <div class="nav-title finance-title">
            Transaction Records
        </div>

        <nav class="nav">
            <button onclick="window.location.href='parent_topup.php'">
                <span class="nav-icon">₱</span>
                Top-Ups
            </button>

            <button onclick="window.location.href='parent_transactions.php'">
                <span class="nav-icon">▥</span>
                Transactions
            </button>
        </nav>

        <!-- PROFILE -->
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
                    <?php echo htmlspecialchars($parentInitials); ?>
                </span>

                <span class="admin-profile-details">
                    <strong>
                        <?php echo htmlspecialchars($parentName); ?>
                    </strong>
                    <small>Parent</small>
                </span>

                <span class="admin-profile-chevron">⌃</span>
            </button>

        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main>

        <!-- TOP BAR -->
        <div class="topbar">

            <div class="topbar-left">

                <button class="mobile-menu" onclick="toggleSidebar()">
                    ☰
                </button>

                <div>
                    <div class="eyebrow">User Management</div>
                    <h1 id="pageTitle">Linked Student Accounts</h1>
                </div>

            </div>

            <div class="top-actions"></div>

        </div>

        <!-- STATISTICS -->
        <div class="single-total-card">

            <div class="single-total-info">

                <div class="stat-label">
                    Total Linked Students
                </div>

                <div class="stat-number">
                    <?php echo number_format($totalStudents); ?>
                </div>

                <div class="stat-trend">
                    Connected student/child accounts
                </div>

            </div>

        </div>

        <!-- STUDENT TABLE -->
        <section class="card table-card">

            <div class="card-header">
                <h2>Linked Student Accounts</h2>
            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student Number</th>
                            <th>Account Status</th>
                            <th>Transaction Limit</th>
                            <th>Balance</th>
                            <th>Expenses</th>
                            <th class="actions-header">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($students->num_rows > 0): ?>

                        <?php while ($student = $students->fetch_assoc()): ?>

                            <?php
                                $studentNumber = (string) $student["student_num"];
                                $isFrozen = (int) $student["is_frozen"];
                                $isLocked =(int)$student["is_locked"];
                                $expenses = (float) $student["expenses"];
                                $currentLimit = (float) ($student["transaction_limit"] ?? 0);
                                $currentPeriod = (string) ($student["transaction_limit_period"] ?? "daily");
                            ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($student["Fname"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($studentNumber); ?>
                                </td>

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

                                <td>

                            ₱<?php

                            echo number_format(
                                (float)$student["transaction_limit"],
                                2
                            );

                            ?>


                            /


                            <?php

                            echo htmlspecialchars(
                                $student[
                                    "transaction_limit_period"
                                ]
                            );

                            ?>

                        </td>

                                <td>
                                    ₱<?php echo number_format((float) $student["balance"], 2); ?>
                                </td>

                                <td>
                                    ₱<?php echo number_format($expenses, 2); ?>
                                </td>

                                <td>
                                    <div class="action-buttons">

                                        <button
                                            type="button"
                                            class="view-btn"
                                            onclick="viewStudent(<?php echo htmlspecialchars(json_encode($studentNumber), ENT_QUOTES, 'UTF-8'); ?>)"
                                        >
                                            View
                                        </button>

                                        <!-- SET LIMIT BUTTON -->
                                        <button
                                            type="button"
                                            class="delete-btn"
                                            onclick="openSetLimitModal(
                                                <?php echo htmlspecialchars(json_encode($studentNumber), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($currentLimit), ENT_QUOTES, 'UTF-8'); ?>,
                                                <?php echo htmlspecialchars(json_encode($currentPeriod), ENT_QUOTES, 'UTF-8'); ?>
                                            )"
                                        >
                                            Set Limit
                                        </button>

                                        <!-- FREEZE / UNFREEZE BUTTON -->
                                        <?php if ($isFrozen): ?>

                                            <button
                                                type="button"
                                                class="freeze-btn"
                                                onclick="freezeStudent(
                                                    <?php echo htmlspecialchars(json_encode($studentNumber), ENT_QUOTES, 'UTF-8'); ?>,
                                                    true
                                                )"
                                            >
                                                Unfreeze
                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="button"
                                                class="freeze-btn"
                                                onclick="freezeStudent(
                                                    <?php echo htmlspecialchars(json_encode($studentNumber), ENT_QUOTES, 'UTF-8'); ?>,
                                                    false
                                                )"
                                            >
                                                Freeze
                                            </button>

                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6">
                                No linked students found for your account.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </section>

    </main>
</div>

<!-- SET LIMIT MODAL -->
<div
    id="setLimitModal"
    class="limit-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="setLimitTitle"
>
    <div class="limit-modal-box">

        <h2 id="setLimitTitle">Set Student Transaction Limit</h2>

        <p class="limit-modal-description">
            Set the maximum amount your child can spend within the selected period.
        </p>

        <form id="setLimitForm">

            <input
                type="hidden"
                id="limitStudentNumber"
                name="student_num"
            >

            <div class="limit-form-group">

                <label for="limitAmount">
                    Spending Limit Amount (₱)
                </label>

                <input
                    type="number"
                    id="limitAmount"
                    name="transaction_limit"
                    min="0.01"
                    step="0.01"
                    placeholder="Enter amount"
                    required
                >

            </div>

            <div class="limit-form-group">

                <label for="limitPeriod">
                    Limit Period
                </label>

                <select
                    id="limitPeriod"
                    name="transaction_limit_period"
                    required
                >
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>

            </div>

            <div id="limitError" class="limit-error"></div>

            <div class="limit-modal-actions">

                <button
                    type="button"
                    class="limit-cancel-btn"
                    onclick="closeSetLimitModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    id="saveLimitButton"
                    class="limit-save-btn"
                >
                    Save Limit
                </button>

            </div>

        </form>

    </div>
</div>

<!-- FREEZE / UNFREEZE MODAL -->
<div
    id="freezeConfirmModal"
    class="lock-confirm-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="freezeConfirmTitle"
>
    <div class="lock-confirm-box">

        <h2 id="freezeConfirmTitle">Confirm Account Action</h2>

        <p id="freezeConfirmMessage"></p>

        <div class="lock-confirm-actions">

            <button
                type="button"
                class="lock-confirm-cancel"
                onclick="closeFreezeConfirmModal()"
            >
                Cancel
            </button>

            <button
                type="button"
                id="confirmFreezeActionButton"
                class="lock-confirm-submit"
                onclick="confirmFreezeAction()"
            >
                Confirm
            </button>

        </div>

    </div>
</div>

<!-- JAVASCRIPT -->
<script src="script.js"></script>

<script>
const csrfToken = <?php echo json_encode($csrfToken); ?>;

/* PROFILE DROPDOWN */
function toggleAdminMenu(event) {
    event.stopPropagation();

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    menu.classList.toggle("show");

    profile.setAttribute(
        "aria-expanded",
        menu.classList.contains("show")
    );
}

document.addEventListener("click", function(event) {

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    if (
        menu &&
        profile &&
        !menu.contains(event.target) &&
        !profile.contains(event.target)
    ) {
        menu.classList.remove("show");
        profile.setAttribute("aria-expanded", "false");
    }

});

/* SET LIMIT MODAL */
function openSetLimitModal(studentNumber, currentLimit, currentPeriod) {

    const modal = document.getElementById("setLimitModal");

    document.getElementById("limitStudentNumber").value = studentNumber;

    document.getElementById("limitAmount").value =
        Number(currentLimit) > 0 ? currentLimit : "";

    const allowedPeriods = ["daily", "weekly", "monthly"];

    document.getElementById("limitPeriod").value =
        allowedPeriods.includes(currentPeriod)
            ? currentPeriod
            : "daily";

    document.getElementById("limitError").textContent = "";
    document.getElementById("limitError").classList.remove("show");

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");

    document.getElementById("limitAmount").focus();
}

function closeSetLimitModal() {

    const modal = document.getElementById("setLimitModal");

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");

    document.getElementById("setLimitForm").reset();
    document.getElementById("limitError").classList.remove("show");

    const saveButton = document.getElementById("saveLimitButton");
    saveButton.disabled = false;
    saveButton.textContent = "Save Limit";
}

/* SAVE LIMIT */
document.getElementById("setLimitForm").addEventListener("submit", async function(event) {

    event.preventDefault();

    const studentNumber =
        document.getElementById("limitStudentNumber").value;

    const amount =
        document.getElementById("limitAmount").value;

    const period =
        document.getElementById("limitPeriod").value;

    const errorBox = document.getElementById("limitError");
    const saveButton = document.getElementById("saveLimitButton");

    errorBox.classList.remove("show");
    errorBox.textContent = "";

    if (!studentNumber) {
        errorBox.textContent = "Student account could not be identified.";
        errorBox.classList.add("show");
        return;
    }

    if (amount === "" || !Number.isFinite(Number(amount)) || Number(amount) <= 0) {
        errorBox.textContent = "Please enter a valid amount greater than zero.";
        errorBox.classList.add("show");
        return;
    }

    if (!["daily", "weekly", "monthly"].includes(period)) {
        errorBox.textContent = "Please select a valid limit period.";
        errorBox.classList.add("show");
        return;
    }

    const formData = new FormData();

    formData.append("student_num", studentNumber);
    formData.append("transaction_limit", amount);
    formData.append("transaction_limit_period", period);
    formData.append("csrf_token", csrfToken);

    saveButton.disabled = true;
    saveButton.textContent = "Saving...";

    try {

        const response = await fetch("parent_set_limit.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (!response.ok || result.status !== "success") {
            throw new Error(result.message || "Unable to save the transaction limit.");
        }

        alert(result.message);
        closeSetLimitModal();

        // Refresh the table so the updated limit is loaded next time.
        window.location.reload();

    } catch (error) {

        errorBox.textContent = error.message;
        errorBox.classList.add("show");

        saveButton.disabled = false;
        saveButton.textContent = "Save Limit";
    }

});

/* CLOSE SET LIMIT MODAL WHEN CLICKING OUTSIDE */
document.getElementById("setLimitModal").addEventListener("click", function(event) {

    if (event.target === this) {
        closeSetLimitModal();
    }

});

/* FREEZE / UNFREEZE */
let pendingFreezeStudentNumber = "";
let pendingFreezeAction = "";

function freezeStudent(studentNumber, isCurrentlyFrozen) {

    const modal = document.getElementById("freezeConfirmModal");
    const message = document.getElementById("freezeConfirmMessage");
    const title = document.getElementById("freezeConfirmTitle");
    const confirmButton = document.getElementById("confirmFreezeActionButton");

    if (!modal || !message || !title || !confirmButton) {
        console.error("Freeze modal elements were not found.");
        return;
    }

    pendingFreezeStudentNumber = studentNumber;
    pendingFreezeAction = isCurrentlyFrozen ? "unfreeze" : "freeze";

    if (pendingFreezeAction === "freeze") {

        title.textContent = "Freeze Student Account?";

        message.textContent =
            "Are you sure you want to freeze student account " +
            studentNumber +
            "? The student will no longer be able to perform transactions.";

        confirmButton.textContent = "Freeze Account";

    } else {

        title.textContent = "Unfreeze Student Account?";

        message.textContent =
            "Are you sure you want to unfreeze student account " +
            studentNumber +
            "?";

        confirmButton.textContent = "Unfreeze Account";
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeFreezeConfirmModal() {

    const modal = document.getElementById("freezeConfirmModal");

    if (!modal) return;

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");

    pendingFreezeStudentNumber = "";
    pendingFreezeAction = "";

    const confirmButton = document.getElementById("confirmFreezeActionButton");

    if (confirmButton) {
        confirmButton.disabled = false;
        confirmButton.textContent = "Confirm";
    }
}

function confirmFreezeAction() {

    if (!pendingFreezeStudentNumber || !pendingFreezeAction) {
        return;
    }

    const confirmButton = document.getElementById("confirmFreezeActionButton");

    if (confirmButton) {
        confirmButton.disabled = true;
        confirmButton.textContent = "Please wait...";
    }

    window.location.href =
        "freeze_student.php?student_num=" +
        encodeURIComponent(pendingFreezeStudentNumber) +
        "&action=" +
        encodeURIComponent(pendingFreezeAction);
}

/* CLOSE FREEZE MODAL */
document.getElementById("freezeConfirmModal").addEventListener("click", function(event) {

    if (event.target === this) {
        closeFreezeConfirmModal();
    }

});

/* ESCAPE KEY */
document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        const limitModal = document.getElementById("setLimitModal");
        const freezeModal = document.getElementById("freezeConfirmModal");

        if (limitModal.classList.contains("show")) {
            closeSetLimitModal();
        }

        if (freezeModal.classList.contains("show")) {
            closeFreezeConfirmModal();
        }
    }

});

/* VIEW STUDENT */
function viewStudent(studentNumber) {

    window.location.href =
        "view_parent_student.php?student_num=" +
        encodeURIComponent(studentNumber);

}
</script>

</body>
</html>

<?php
$conn->close();
?>
