<?php
session_start();


if (!isset($_SESSION['parent_id'])){
    header("Location: index.php");
    exit();
}

require_once "db.php";

$parentId = (string) $_SESSION['parent_id'];
$parentName = (string) ($_SESSION['parent_name'] ?? 'Parent');

$nameParts = preg_split('/\s+/', $parentName);
$parentInitials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $parentInitials .= strtoupper(substr($part, 0, 1));
    }
}

$parentInitials = substr($parentInitials, 0, 2);

/* PARENT DASHBOARD STATISTICS */

// 1. TOTAL LINKED STUDENTS
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


// 2. TOTAL BALANCE OF LINKED STUDENTS
$balanceQuery = "
    SELECT COALESCE(SUM(s.balance), 0) AS total_balance
    FROM parent_student_link AS l
    INNER JOIN students AS s
        ON l.student_num = s.student_num
    WHERE l.parent_id = ?
";

$balanceStmt = $conn->prepare($balanceQuery);
$balanceStmt->bind_param("s", $parentId);
$balanceStmt->execute();

$balanceResult = $balanceStmt->get_result();
$balanceData = $balanceResult->fetch_assoc();

$totalBalance = (float) $balanceData['total_balance'];


// 3. TOTAL EXPENSES OF LINKED STUDENTS
$expensesQuery = "
    SELECT COALESCE(SUM(t.amount), 0) AS total_expenses
    FROM transactions AS t
    INNER JOIN parent_student_link AS l
        ON t.student_num = l.student_num
    WHERE l.parent_id = ?
    AND LOWER(t.transaction_type) = 'payment'
";

$expensesStmt = $conn->prepare($expensesQuery);
$expensesStmt->bind_param("s", $parentId);
$expensesStmt->execute();

$expensesResult = $expensesStmt->get_result();
$expensesData = $expensesResult->fetch_assoc();

$totalExpenses = (float) $expensesData['total_expenses'];



$studentStmt = $conn->prepare("
    SELECT
        s.student_num,
        s.Fname,
        s.balance,
        s.transaction_limit,
        s.transaction_limit_period,
        s.is_frozen
    FROM parent_student_link AS l
    INNER JOIN students AS s
        ON l.student_num = s.student_num
    WHERE l.parent_id = ?
    ORDER BY s.student_num ASC
");

if (!$studentStmt) {
    die("Student query preparation failed: " . $conn->error);
}

$studentStmt->bind_param("s", $parentId);

if (!$studentStmt->execute()) {
    die("Student query execution failed: " . $studentStmt->error);
}

$students = $studentStmt->get_result();


?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        Parent Dashboard
    </title>
    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

</head>


<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside
        class="sidebar"
        id="sidebar"
         >
        <!-- BRAND -->

        <div class="brand">

            <div class="logo">
                E
            </div>

            <div>

                <div class="brand-title">
                    EduPocket
                </div>

                <span class="brand-subtitle">
                    Parent Dashboard
                </span>

            </div>

        </div>

        <!-- MANAGEMENT -->

        <div class="nav-title">
            User Management
        </div>

        <nav class="nav">

            <button
                class="active"
                onclick="window.location.href='parent_dashboard.php'"
            >
                <span class="nav-icon">
                    ⌂
                </span>
                Dashboard
            </button>

            <button
            onclick="window.location.href='parent_student.php'"
            >
                <span class="nav-icon">    
                </span>
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

        <!--FINANCE AND SYSTEM-->

        <div class="nav-title finance-title">

            Transaction Records

        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='parent_topup.php'"
            >
                <span class="nav-icon">
                    ₱
                </span>
                Top-Ups
            </button>

            <button
            onclick="window.location.href='parent_transactions.php'"
            >
                <span class="nav-icon">
                    ▥
                </span>
                Transactions
            </button>
        </nav>

        <!--SUPERADMIN PROFILE-->

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

    <!--MAIN CONTENT-->

    <main>

        <!--TOP BAR-->

        <div class="topbar">
            <div class="topbar-left">

                <!-- MOBILE MENU -->

                <button
                    class="mobile-menu"
                    onclick="toggleSidebar()"
                >
                    ☰
                </button>

                <div>
                    <div class="eyebrow">
                        Welcome
                    </div>
                    <h1 id="pageTitle">
    <?php echo htmlspecialchars($parentName); ?>
</h1>
                </div>
            </div>

            <div class="top-actions">

            </div>

        </div>



        <!--STATISTICS-->

        <div class="total-cards">

        <!-- TOTAL STUDENTS -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Total Linked Students
                    </div>

                    <div class="stat-number">
                        <?php
                        echo number_format($totalStudents);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Connected student/child accounts
                    </div>

                </div>

            </div>

            <!-- TOTAL STUDENTS -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Total Balance
                    </div>

                    <div class="stat-number">
                        <?php
                        echo number_format($totalBalance,2);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Combined balance of student accounts
                    </div>

                </div>

            </div>

            <!-- TOTAL BALANCE -->

            <div class="total-card">

                <div class="total-info">

                    <div class="stat-label">
                        Total Expenses
                    </div>

                    <div class="stat-number">
                        ₱<?php
                        echo number_format($totalExpenses,2);
                        ?>
                    </div>

                    <div class="stat-trend">
                        Combined expenses from student/child accounts
                    </div>

                </div>

            </div>

        </div>

        <!-- =================================
             LINKED STUDENTS
        ================================== -->

        <section
            class="card"
            style="margin-top:20px;"
        >


            <div class="card-header">

                <h2>
                    Linked Students
                </h2>
                <span
                    class="view-link"
                    onclick="window.location.href='parent_student.php'"
                >
                    View All →
                </span>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student Number
                            </th>

                            <th>
                                Student Name
                            </th>

                            <th>
                                Balance
                            </th>

                            <th>
                                Transaction Limit
                            </th>

                            <th>
                                Account Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if ($students->num_rows > 0) {

                        while (
                            $student =
                            $students->fetch_assoc()
                        ) {

                            $studentName =
                                $student["Fname"];

                    ?>


                    <tr>


                        <!-- Student Number -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $student["student_num"]
                            );

                            ?>

                        </td>


                        <!-- Student Name -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $studentName
                            );

                            ?>

                        </td>


                        <!-- Balance -->

                        <td>

                            ₱<?php

                            echo number_format(
                                (float)$student["balance"],
                                2
                            );

                            ?>

                        </td>


                        <!-- Transaction Limit -->

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


                        <!-- Account Status -->

                        <td>

                            <?php

                            if (
                                $student["is_frozen"]
                            ) {

                                echo '
                                    <span
                                        style="
                                            color:#f59e0b;
                                            font-weight:600;
                                        "
                                    >
                                        Frozen
                                    </span>
                                ';

                            } else {

                                echo '
                                    <span
                                        style="
                                            color:green;
                                            font-weight:600;
                                        "
                                    >
                                        Active
                                    </span>
                                ';

                            }

                            ?>

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

                            No linked students found.

                        </td>

                    </tr>


                    <?php

                    }

                    ?>


                    </tbody>

                </table>

            </div>

        </section>

    </main>
</div>

<!--JAVASCRIPT CONNECTION-->

<!-- Connect external JavaScript -->
<script src="script.js"></script>

<!-- Admin Profile Dropdown -->
<script>
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

<?php
$conn->close();
?>