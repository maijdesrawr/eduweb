<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "parent_session.php";

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


$student_num = trim(
    $_GET["student_num"] ?? ""
);


if (empty($student_num)) {

    die("Student number is required.");

}

$stmt = $conn->prepare("
    SELECT
        student_num,
        Fname,
        first_name,
        middle_name,
        last_name,
        suffix,
        balance,
        transaction_limit,
        transaction_limit_period,
        parent_id,
        qr_code,
        is_frozen,
        is_locked
    FROM students
    WHERE student_num = ?
      AND EXISTS (
          SELECT 1
          FROM parent_student_link AS l
          WHERE l.student_num = students.student_num
            AND l.parent_id = ?
      )
");

$stmt->bind_param("ss", $student_num, $parentId);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found or you are not authorized to view this student.");
}

// =====================================
// TRANSACTION FILTERS
// Default: Show both Payment and Top-Up
// =====================================

$paymentType = strtolower(trim($_GET['payment_type'] ?? 'all'));

$allowedTypes = ['all', 'payment', 'top-up'];

if (!in_array($paymentType, $allowedTypes, true)) {
    $paymentType = 'all';
}


// =====================================
// TRANSACTION HISTORY QUERY
// Show transactions of the selected student only
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
    WHERE t.student_num = ?
      AND EXISTS (
          SELECT 1
          FROM parent_student_link AS l
          WHERE l.student_num = t.student_num
            AND l.parent_id = ?
      )
";

$params = [$student_num, $parentId];
$types = "ss";

// Filter by transaction type
if ($paymentType !== 'all') {
    $transactionQuery .= " AND LOWER(t.transaction_type) = ?";
    $params[] = $paymentType;
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

// =====================================
// TOP-UP AND EXPENSE CHART
// =====================================

$chartPeriod = strtolower(trim($_GET['chart_period'] ?? 'monthly'));
$allowedChartPeriods = ['daily', 'weekly', 'monthly', 'yearly'];

if (!in_array($chartPeriod, $allowedChartPeriods, true)) {
    $chartPeriod = 'monthly';
}

// Prepare the chart labels and empty totals.
$chartLabels = [];
$topUpTotals = [];
$expenseTotals = [];

if ($chartPeriod === 'daily') {
    // One bar group for each hour of the selected day.
    for ($hour = 0; $hour < 24; $hour++) {
        $chartLabels[] = date('g A', mktime($hour, 0, 0));
        $topUpTotals[$hour] = 0;
        $expenseTotals[$hour] = 0;
    }
} elseif ($chartPeriod === 'weekly') {
    // Monday through Sunday.
    $chartLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    for ($day = 1; $day <= 7; $day++) {
        $topUpTotals[$day] = 0;
        $expenseTotals[$day] = 0;
    }
} elseif ($chartPeriod === 'monthly') {
    // Every day in the current month.
    $daysInMonth = (int) date('t');
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $chartLabels[] = (string) $day;
        $topUpTotals[$day] = 0;
        $expenseTotals[$day] = 0;
    }
} else {
    // January through December.
    $chartLabels = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    for ($month = 1; $month <= 12; $month++) {
        $topUpTotals[$month] = 0;
        $expenseTotals[$month] = 0;
    }
}

// Retrieve this student's transactions for the selected chart period.
$chartQuery = "
    SELECT amount, transaction_type, date_time
    FROM transactions
    WHERE student_num = ?
      AND EXISTS (
          SELECT 1
          FROM parent_student_link AS l
          WHERE l.student_num = transactions.student_num
            AND l.parent_id = ?
      )
";
$chartStmt = $conn->prepare($chartQuery);

if (!$chartStmt) {
    die("Chart query preparation failed: " . $conn->error);
}

$chartStmt->bind_param("ss", $student_num, $parentId);

if (!$chartStmt->execute()) {
    die("Chart query execution failed: " . $chartStmt->error);
}

$chartResult = $chartStmt->get_result();

while ($chartTransaction = $chartResult->fetch_assoc()) {
    if (empty($chartTransaction['date_time'])) {
        continue;
    }

    $timestamp = strtotime($chartTransaction['date_time']);
    if ($timestamp === false) {
        continue;
    }

    $type = strtolower(trim($chartTransaction['transaction_type'] ?? ''));
    $amount = (float) $chartTransaction['amount'];

    // Identify the matching bucket for each transaction.
    if ($chartPeriod === 'daily') {
        // Only include transactions from today, grouped by hour.
        if (date('Y-m-d', $timestamp) !== date('Y-m-d')) {
            continue;
        }
        $bucket = (int) date('G', $timestamp);
    } elseif ($chartPeriod === 'weekly') {
        // Only include transactions from the current Monday-Sunday week.
        $today = new DateTime('today');
        $weekStart = (clone $today)->modify('monday this week');
        $weekEnd = (clone $weekStart)->modify('+7 days');
        $transactionDate = new DateTime(date('Y-m-d', $timestamp));

        if ($transactionDate < $weekStart || $transactionDate >= $weekEnd) {
            continue;
        }
        $bucket = (int) date('N', $timestamp);
    } elseif ($chartPeriod === 'monthly') {
        // Only include transactions from the current month.
        if (date('Y-m', $timestamp) !== date('Y-m')) {
            continue;
        }
        $bucket = (int) date('j', $timestamp);
    } else {
        // Only include transactions from the current year.
        if (date('Y', $timestamp) !== date('Y')) {
            continue;
        }
        $bucket = (int) date('n', $timestamp);
    }

    if (in_array($type, ['top-up', 'topup', 'top_up'], true)) {
        $topUpTotals[$bucket] += $amount;
    } elseif ($type === 'payment') {
        $expenseTotals[$bucket] += $amount;
    }
}

$chartStmt->close();

$topUpChartData = array_values($topUpTotals);
$expenseChartData = array_values($expenseTotals);


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
        View Student
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
                
                onclick="window.location.href='parent_dashboard.php'"
            >
                <span class="nav-icon">
                    ⌂
                </span>
                Dashboard
            </button>

            <button
            class="active"
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

    <main style="width:100%;">

        <div class="topbar">

            <div>

                <div class="eyebrow">
                    Student Account
                </div>

                <h1>
                    Student Details
                </h1>

            </div>

        </div>


        <section class="card">


            <div style="padding:25px;">

            <?php
$name = $student["Fname"];

$words = explode(" ", trim($name));
$initials = "";

foreach ($words as $word) {
    if (!empty($word)) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
}

$initials = substr($initials, 0, 2);
?>

            <div class="user-cell">
    <div class="avatar">
        <?php echo htmlspecialchars($initials); ?>
    </div>

    <h2>
        <?php echo htmlspecialchars($student["Fname"]); ?>
    </h2>
</div>
            
                <p>
                    <strong>
                        Student Number:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $student["student_num"]
                    );
                    ?>

                </p>


                <p>
                    <strong>
                        First Name:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $student["first_name"]
                    );
                    ?>

                </p>


                <p>
                    <strong>
                        Middle Name:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $student["middle_name"] ?? ""
                    );
                    ?>

                </p>


                <p>
                    <strong>
                        Last Name:
                    </strong>

                    <?php
                    echo htmlspecialchars(
                        $student["last_name"]
                    );
                    ?>

                </p>


                <p>
                    <strong>
                        Balance:
                    </strong>

                    ₱<?php
                    echo number_format(
                        (float)$student["balance"],
                        2
                    );
                    ?>

                </p>


                <p>
                    <strong>
                        Parent ID:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $student["parent_id"] ?? "None"
                    );

                    ?>

                </p>


                <p>
                    <strong>
                        Transaction Limit:
                    </strong>

                    ₱<?php

                    echo number_format(
                        (float)$student["transaction_limit"],
                        2
                    );

                    ?>

                    /
                    <?php

                    echo htmlspecialchars(
                        $student["transaction_limit_period"]
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        Account Status:
                    </strong>

                    <?php

                    if ($student["is_frozen"]) {

                        echo '<span style="color:#f59e0b;">
                                Frozen
                              </span>';
                    }
                    elseif ($student["is_locked"]) {
                        echo '<span style="color:#ef4444;">
                                Locked
                            </span>';
                    }

                    else {

                        echo '<span style="color:green;">
                                Active
                              </span>';

                    }

                    ?>

                </p>

            </div>

        </section>

        <!-- FILTERS -->
<div style="padding: 15px 0 0 0;">

    <form
        method="GET"
        action="view_parent_student.php"
        style="
            display:flex;
            gap:10px;
            align-items:flex-end;
            flex-wrap:wrap;
        "
    >

        <!-- KEEP SELECTED STUDENT -->
        <input
            type="hidden"
            name="student_num"
            value="<?php echo htmlspecialchars($student_num, ENT_QUOTES, 'UTF-8'); ?>"
        >

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
            href="view_parent_student.php?student_num=<?php echo urlencode($student_num); ?>"
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



        <!-- TOP-UPS AND EXPENSES BAR CHART -->
        <section class="card" style="margin-top:20px; padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:15px; flex-wrap:wrap; margin-bottom:18px;">
                <div>
                    <h2 style="margin:0 0 5px;">Top-Ups and Expenses</h2>
                    <p style="margin:0; color:#777; font-size:13px;">
                        <?php
                        $periodDescriptions = [
                            'daily' => 'Today, grouped by hour',
                            'weekly' => 'This week, Monday to Sunday',
                            'monthly' => 'This month, grouped by day',
                            'yearly' => 'This year, grouped by month'
                        ];
                        echo htmlspecialchars($periodDescriptions[$chartPeriod]);
                        ?>
                    </p>
                </div>

                <form method="GET" action="view_parent_student.php"
                      style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;">
                    <input type="hidden" name="student_num"
                           value="<?php echo htmlspecialchars($student_num, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="payment_type"
                           value="<?php echo htmlspecialchars($paymentType, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group" style="margin:0;">
                        <label for="chart_period">Show chart by</label>
                        <select id="chart_period" name="chart_period" style="min-width:140px;"
                                onchange="this.form.submit()">
                            <option value="daily" <?php echo $chartPeriod === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $chartPeriod === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $chartPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="yearly" <?php echo $chartPeriod === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                </form>
            </div>

            <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:12px; font-size:13px;">
                <span style="display:flex; align-items:center; gap:7px;">
                    <span style="width:12px; height:12px; border-radius:3px; background:#22a06b; display:inline-block;"></span>
                    Top-Ups
                </span>
                <span style="display:flex; align-items:center; gap:7px;">
                    <span style="width:12px; height:12px; border-radius:3px; background:#f59e0b; display:inline-block;"></span>
                    Expenses
                </span>
            </div>

            <div style="position:relative; width:100%; height:340px;">
                <canvas id="studentFinanceChart"
                        aria-label="Bar chart comparing top-ups and expenses"
                        role="img"></canvas>
            </div>
        </section>

        <div style="margin-top:20px;">

            <button
                class="save-btn"
                onclick="window.location.href='parent_student.php'"
            >
                ← Back to Students
            </button>

        </div>

    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const chartCanvas = document.getElementById('studentFinanceChart');

    if (chartCanvas && typeof Chart !== 'undefined') {
        const chartLabels = <?php echo json_encode($chartLabels); ?>;
        const topUpData = <?php echo json_encode($topUpChartData); ?>;
        const expenseData = <?php echo json_encode($expenseChartData); ?>;

        new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Top-Ups',
                        data: topUpData,
                        backgroundColor: '#22a06b',
                        borderRadius: 4,
                        barPercentage: 0.75,
                        categoryPercentage: 0.75
                    },
                    {
                        label: 'Expenses',
                        data: expenseData,
                        backgroundColor: '#f59e0b',
                        borderRadius: 4,
                        barPercentage: 0.75,
                        categoryPercentage: 0.75
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₱' +
                                    Number(context.raw).toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: false,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + Number(value).toLocaleString('en-PH');
                            }
                        }
                    }
                }
            }
        });
    }
</script>

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