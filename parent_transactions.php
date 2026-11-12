
<?php
session_start();

if (!isset($_SESSION['parent_id'])) {
    header("Location: index.php");
    exit();
}

require_once "db.php";

$parentId = (string) $_SESSION['parent_id'];
$parentName = (string) ($_SESSION['parent_name'] ?? 'Parent');

// Generate parent initials
$nameParts = preg_split('/\s+/', $parentName);
$parentInitials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $parentInitials .= strtoupper(substr($part, 0, 1));
    }
}

$parentInitials = substr($parentInitials, 0, 2);


// =====================================
// TOTAL PAYMENT EXPENSES
// Payment transactions only
// =====================================

$expensesQuery = "
    SELECT COALESCE(SUM(t.amount), 0) AS total_expenses
    FROM transactions AS t
    WHERE LOWER(t.transaction_type) = 'payment'
      AND EXISTS (
          SELECT 1
          FROM parent_student_link AS l
          WHERE l.student_num = t.student_num
            AND l.parent_id = ?
      )
";

$expensesStmt = $conn->prepare($expensesQuery);

if (!$expensesStmt) {
    die("Expenses query preparation failed: " . $conn->error);
}

$expensesStmt->bind_param("s", $parentId);
$expensesStmt->execute();

$expensesResult = $expensesStmt->get_result();
$expensesData = $expensesResult->fetch_assoc();

$totalExpenses = (float) $expensesData['total_expenses'];


// =====================================
// TRANSACTION FILTERS
// Default: Show both Payment and Top-Up
// =====================================

$studentName = trim($_GET['Fname'] ?? '');
$paymentType = strtolower(trim($_GET['payment_type'] ?? 'all'));

// Allowed transaction types
$allowedTypes = ['all', 'payment', 'top-up'];

if (!in_array($paymentType, $allowedTypes, true)) {
    $paymentType = 'all';
}


// =====================================
// TRANSACTION HISTORY QUERY
// =====================================

$transactionQuery = "
    SELECT
        t.student_num,
        s.Fname,
        t.amount,
        t.transaction_type,
        t.description,
        t.date_time
    FROM transactions AS t
    INNER JOIN students AS s
        ON t.student_num = s.student_num
    WHERE EXISTS (
        SELECT 1
        FROM parent_student_link AS l
        WHERE l.student_num = t.student_num
          AND l.parent_id = ?
    )
";

$params = [$parentId];
$types = "s";

// Filter by transaction type
if ($paymentType !== 'all') {
    $transactionQuery .= " AND LOWER(t.transaction_type) = ?";
    $params[] = $paymentType;
    $types .= "s";
}

// Filter by student name
if ($studentName !== '') {
    $transactionQuery .= " AND s.Fname LIKE ?";
    $params[] = "%" . $studentName . "%";
    $types .= "s";
}

// Latest transactions first
$transactionQuery .= " ORDER BY t.date_time DESC";

$transactionStmt = $conn->prepare($transactionQuery);

if (!$transactionStmt) {
    die("Transaction query preparation failed: " . $conn->error);
}

$transactionStmt->bind_param($types, ...$params);

if (!$transactionStmt->execute()) {
    die("Transaction query execution failed: " . $transactionStmt->error);
}

$transactions = $transactionStmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Parent Transactions</title>

    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

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

        <!-- USER MANAGEMENT -->
        <div class="nav-title">
            User Management
        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='parent_dashboard.php'"
            >
                <span class="nav-icon">⌂</span>
                Dashboard
            </button>

            <button
                onclick="window.location.href='parent_student.php'"
            >
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

        <!-- TRANSACTION RECORDS -->
        <div class="nav-title finance-title">
            Transaction Records
        </div>

        <nav class="nav">

            <button
                onclick="window.location.href='parent_topup.php'"
            >
                <span class="nav-icon">₱</span>
                Top-Ups
            </button>

            <button
                class="active"
                onclick="window.location.href='parent_transactions.php'"
            >
                <span class="nav-icon">▥</span>
                Transactions
            </button>

        </nav>

        <!-- PARENT PROFILE -->
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

                <span class="admin-profile-chevron">
                    ⌃
                </span>

            </button>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main>

        <!-- TOP BAR -->
        <div class="topbar">

            <div class="topbar-left">

                <button
                    class="mobile-menu"
                    onclick="toggleSidebar()"
                >
                    ☰
                </button>

                <div>

                    <div class="eyebrow">
                        Transaction Records
                    </div>

                    <h1 id="pageTitle">
                        Student Transaction History
                    </h1>

                </div>

            </div>

            <div class="top-actions"></div>

        </div>


        <!-- TOTAL EXPENSES -->
        <div class="single-total-card">

            <div class="single-total-info">

                <div class="stat-label">
                    Total Expenses
                </div>

                <div class="stat-number">
                    ₱<?php echo number_format($totalExpenses, 2); ?>
                </div>

                <div class="stat-trend">
                    Combined payment expenses from linked student accounts
                </div>

            </div>

        </div>


        <!-- FILTERS -->
        <div style="padding: 15px 0 0 0;">

            <form
                method="GET"
                action="parent_transactions.php"
                style="
                    display:flex;
                    gap:10px;
                    align-items:flex-end;
                    flex-wrap:wrap;
                "
            >

                <!-- STUDENT NAME FILTER -->
                <div class="form-group" style="margin:0;">

                    <label for="Fname">
                        Student Name
                    </label>

                    <input
                        type="text"
                        id="Fname"
                        name="Fname"
                        placeholder="Enter Student Name"
                        value="<?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?>"
                        style="width:200px;"
                    >

                </div>


                <!-- PAYMENT TYPE FILTER -->
                <div class="form-group" style="margin:0;">

                    <label for="payment_type">
                        Payment Type
                    </label>

                    <select
                        id="payment_type"
                        name="payment_type"
                        style="width:180px;"
                    >

                        <option
                            value="all"
                            <?php echo ($paymentType === 'all') ? 'selected' : ''; ?>
                        >
                            All Transactions
                        </option>

                        <option
                            value="payment"
                            <?php echo ($paymentType === 'payment') ? 'selected' : ''; ?>
                        >
                            Payment
                        </option>

                        <option
                            value="top-up"
                            <?php echo ($paymentType === 'top-up') ? 'selected' : ''; ?>
                        >
                            Top-Up
                        </option>

                    </select>

                </div>


                <!-- FILTER BUTTON -->
                <button
                    type="submit"
                    class="add-student-btn"
                >
                    Filter
                </button>


                <!-- CLEAR BUTTON -->
                <a
                    href="parent_transactions.php"
                    class="cancel-btn"
                    style="
                        text-decoration:none;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        height:48px;
                        box-sizing:border-box;
                    "
                >
                    Clear
                </a>

            </form>

        </div>


        <!-- TRANSACTION HISTORY -->
        <section
            class="card"
            style="margin-top:20px;"
        >

            <div class="card-header">

                <h2>
                    Transaction History
                </h2>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Date & Time</th>

                            <th>Student Name</th>

                            <th>Student Number</th>

                            <th>Amount</th>

                            <th>Description</th>

                            <th>Payment Type</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($transactions->num_rows > 0): ?>

                        <?php while ($transaction = $transactions->fetch_assoc()): ?>

                            <?php
                                $type = strtolower(trim($transaction['transaction_type'] ?? ''));

                                if ($type === 'top-up') {
                                    $amountSign = '+';
                                    $amountColor = '#16834a';
                                    } elseif ($type === 'payment') {
                                        $amountSign = '-';
                                        $amountColor = '#cf302e';
                                        } else {
                                            $amountSign = '';
                                            $amountColor = '#333';
                                            }
                            ?>

                            <tr>

                                <!-- DATE AND TIME -->
                                <td>

                                    <?php
                                    if (!empty($transaction['date_time'])) {

                                        echo htmlspecialchars(
                                            date(
                                                'M d, Y h:i A',
                                                strtotime($transaction['date_time'])
                                            )
                                        );

                                    } else {

                                        echo 'N/A';

                                    }
                                    ?>

                                </td>


                                <!-- STUDENT NAME -->
                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $transaction['Fname'] ?? 'N/A'
                                    );
                                    ?>
                                </td>


                                <!-- STUDENT NUMBER -->
                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $transaction['student_num'] ?? 'N/A'
                                    );
                                    ?>
                                </td>


                                <!-- AMOUNT -->
                                <td>

                                    <span style="color:<?php echo $amountColor; ?>; font-weight:600;">

                                        <?php echo $amountSign; ?>₱<?php
                                            echo number_format(
                                                (float) $transaction['amount'],
                                                2
                                            );
                                        ?>

                                    </span>

                                </td>


                                <!-- DESCRIPTION -->
                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $transaction['description'] ?? 'N/A'
                                    );
                                    ?>
                                </td>


                                <!-- PAYMENT TYPE -->
                                <td>
                                    <?php
                                    echo ($type === 'top-up') ? 'Top-Up' : 'Payment';
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                style="text-align:center;"
                            >

                                No transactions found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>


<!-- JAVASCRIPT -->
<script src="script.js"></script>

<script>

// PARENT PROFILE DROPDOWN
function toggleAdminMenu(event) {

    event.stopPropagation();

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    menu.classList.toggle("show");

    const isOpen = menu.classList.contains("show");

    profile.setAttribute("aria-expanded", isOpen);

}


// CLOSE DROPDOWN WHEN CLICKING OUTSIDE
document.addEventListener("click", function(event) {

    const menu = document.getElementById("adminLogoutMenu");
    const profile = document.getElementById("adminProfileButton");

    if (
        !menu.contains(event.target) &&
        !profile.contains(event.target)
    ) {

        menu.classList.remove("show");

        profile.setAttribute("aria-expanded", "false");

    }

});

</script>

</body>
</html>

<?php
$expensesStmt->close();
$transactionStmt->close();
$conn->close();
?>
