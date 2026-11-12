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


/* =========================================================
   TOTAL CASHIERS
========================================================= */

$totalCashiersQuery = "
    SELECT COUNT(*) AS total_cashiers
    FROM cashiers
";

$totalCashiersResult = $conn->query($totalCashiersQuery);

if (!$totalCashiersResult) {
    die("Error loading total cashiers: " . $conn->error);
}

$totalCashiersData = $totalCashiersResult->fetch_assoc();
$totalCashiers = $totalCashiersData["total_cashiers"];


/* =========================================================
   CANTEENS
========================================================= */

$canteensQuery = "
    SELECT COUNT(*) AS total_canteens
    FROM canteens
";

$canteensResult = $conn->query($canteensQuery);
$canteenData = $canteensResult->fetch_assoc();

$totalCanteens = $canteenData["total_canteens"];

$canteensQuery = "
    SELECT
        canteen_id,
        Fname,
        location,
        balance
    FROM canteens
    ORDER BY canteen_id DESC
";

$canteensResult = $conn->query($canteensQuery);

if (!$canteensResult) {
    die("Error loading canteens: " . $conn->error);
}


/* =========================================================
   CASHIERS
========================================================= */

$cashiersQuery = "
    SELECT
        cashier_id,
        Fname,
        username,
        canteen_id,
        balance,
        profile_photo
    FROM cashiers
    ORDER BY cashier_id DESC
";

$cashiersResult = $conn->query($cashiersQuery);

if (!$cashiersResult) {
    die("Error loading cashiers: " . $conn->error);
}


/* =========================================================
   CANTEEN OPTIONS
========================================================= */

$canteenOptions = [];

$canteenOptionsQuery = "
    SELECT
        canteen_id,
        Fname
    FROM canteens
    ORDER BY canteen_id ASC
";

$canteenOptionsResult = $conn->query($canteenOptionsQuery);

if ($canteenOptionsResult) {

    while ($canteen = $canteenOptionsResult->fetch_assoc()) {

        $canteenOptions[] = [
            "canteen_id" => $canteen["canteen_id"],
            "name" => $canteen["Fname"]
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

    <title>POS / Cashiers</title>

    <link
        rel="stylesheet"
        href="style.css?v=<?php echo time(); ?>"
    >

</head>


<body>


<div class="app">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

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


        <!-- USER MANAGEMENT -->

        <div class="nav-title">
            User Management
        </div>


        <nav class="nav">


            <button
                onclick="window.location.href='dashboard.php'"
            >

                <span class="nav-icon">
                    ⌂
                </span>

                Dashboard

            </button>


            <button
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
                class="active"
                onclick="window.location.href='pos_cashiers.php'"
            >

                <span class="nav-icon"></span>

                POS / Cashiers

            </button>



        </nav>


        <!-- TRANSACTION RECORDS -->

        <div class="nav-title finance-title">

            Transaction Records

        </div>


        <nav class="nav">


            <button
                onclick="window.location.href='topups.php'"
            >

                <span class="nav-icon">
                    ₱
                </span>

                Top-Ups

            </button>


            <button
                onclick="window.location.href='transactions.php'"
            >

                <span class="nav-icon">
                    ▥
                </span>

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


    <!-- =====================================================
         MAIN
    ====================================================== -->
    <main>


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="topbar">


            <div>

                <div class="eyebrow">
                    User Management
                </div>


                <h1>
                    POS / Cashiers
                </h1>

            </div>


            <div class="top-actions">



            </div>


        </div>


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <div class="student-summary-row">

    <div class="single-total-card">
        <div class="single-total-info">
            <div class="stat-label">Total Canteens</div>
            <div class="stat-number"><?php echo $totalCanteens; ?></div>
            <div class="stat-trend">Registered canteen accounts</div>
        </div>
    </div>

    <div class="single-total-card">
        <div class="single-total-info">
            <div class="stat-label">Total Cashiers</div>
            <div class="stat-number"><?php echo $totalCashiers; ?></div>
            <div class="stat-trend">Registered cashier accounts</div>
        </div>
    </div>

    <div style="display: flex; gap: 10px; margin-left: auto;">

        <button class="add-student-btn" onclick="openPosModal()">
            <span>+</span>New POS / Canteen
        </button>

        <button class="add-student-btn" onclick="openCashierModal()">
            <span>+</span>New Cashier Account
        </button>

    </div>

</div>


        <!-- =================================================
             REGISTERED CANTEENS
        ================================================== -->

        <section class="card table-card">


            <div class="card-header">

                <h2>
                    Registered Canteens
                </h2>

            </div>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>


                            <th>
                                Canteen
                            </th>


                            <th>
                                Canteen ID
                            </th>


                            <th>
                                Location
                            </th>


                            <th>
                                Balance
                            </th>


                            <th class="actions-header">
                                Actions
                            </th>


                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if ($canteensResult->num_rows > 0) {

                        while (
                            $canteen =
                            $canteensResult->fetch_assoc()
                        ) {


                            $canteenName =
                                $canteen["Fname"];

                            $canteenID =
                                $canteen["canteen_id"];

                            $location =
                                $canteen["location"];

                            $balance =
                                $canteen["balance"];


                            /* GET INITIALS */

                            $words = explode(
                                " ",
                                trim($canteenName)
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


                            <!-- CANTEEN -->

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
                                                $canteenName
                                            );

                                            ?>

                                        </strong>

                                    </div>


                                </div>


                            </td>


                            <!-- CANTEEN ID -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $canteenID
                                );

                                ?>

                            </td>


                            <!-- LOCATION -->

                            <td>

                                <?php

                                echo !empty($location)
                                    ? htmlspecialchars($location)
                                    : "—";

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
                                        onclick="viewCanteen('<?php echo htmlspecialchars($canteenID, ENT_QUOTES); ?>')"
                                    >

                                        View

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-btn"
                                        onclick="deleteCanteen('<?php echo htmlspecialchars($canteenID, ENT_QUOTES); ?>')"
                                    >

                                        Delete

                                    </button>


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

                                No registered canteens found.

                            </td>

                        </tr>


                    <?php

                    }

                    ?>


                    </tbody>


                </table>


            </div>


        </section>



        <!-- =================================================
             REGISTERED CASHIERS
        ================================================== -->

        <section class="card table-card">


            <div class="card-header">

                <h2>
                    Registered Cashier Accounts
                </h2>

            </div>


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>


                            <th>
                                Cashier
                            </th>


                            <th>
                                Cashier Number
                            </th>


                            <th>
                                Username
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

                    if ($cashiersResult->num_rows > 0) {

                        while (
                            $cashier =
                            $cashiersResult->fetch_assoc()
                        ) {


                            $name =
                                $cashier["Fname"];

                            $cashierID =
                                $cashier["cashier_id"];

                            $username =
                                $cashier["username"];

                            $balance =
                                $cashier["balance"];

                            $profilePhoto =
                                $cashier["profile_photo"];


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


                            <!-- CASHIER -->

                            <td>


                                <div class="user-cell">


                                    <?php

                                    if (
                                        !empty($profilePhoto)
                                    ):

                                    ?>


                                        <div class="avatar">

                                            <img
                                                src="<?php echo htmlspecialchars($profilePhoto); ?>"
                                                alt="Profile"
                                                style="
                                                    width:100%;
                                                    height:100%;
                                                    object-fit:cover;
                                                    border-radius:50%;
                                                "
                                            >

                                        </div>


                                    <?php

                                    else:

                                    ?>


                                        <div class="avatar">

                                            <?php

                                            echo htmlspecialchars(
                                                $initials
                                            );

                                            ?>

                                        </div>


                                    <?php

                                    endif;

                                    ?>


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


                            <!-- CASHIER NUMBER -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $cashierID
                                );

                                ?>

                            </td>


                            <!-- CANTEEN ID -->

                            <td>

                                <?php

                                echo !empty($username)
                                    ? htmlspecialchars($username)
                                    : "—";

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
                                        onclick="viewCashier('<?php echo htmlspecialchars($cashierID, ENT_QUOTES); ?>')"
                                    >

                                        View

                                    </button>


                                    <button
                                        type="button"
                                        class="delete-btn"
                                        onclick="deleteCashier('<?php echo htmlspecialchars($cashierID, ENT_QUOTES); ?>')"
                                    >

                                        Delete

                                    </button>


                                </div>


                            </td>


                        </tr>


                    <?php

                        }

                    } else {

                    ?>


                        <tr>

                            <td
                                colspan="6"
                                style="text-align:center;"
                            >

                                No cashier accounts found.

                            </td>

                        </tr>


                    <?php

                    }

                    ?>


                    </tbody>


                </table>


            </div>


        </section>



        <!-- =================================================
             NEW CASHIER MODAL
        ================================================== -->

        <div
            id="cashierModal"
            class="modal"
        >


            <div class="modal-content">


                <div class="modal-header">


                    <h2>
                        New Cashier Account
                    </h2>


                    <button
                        type="button"
                        class="close-modal"
                        onclick="closeCashierModal()"
                    >

                        &times;

                    </button>


                </div>


                <form
                    id="cashierForm"
                    action="add_pos_cashier.php"
                    method="POST"
                >


                    <input
                        type="hidden"
                        name="category"
                        value="cashier"
                    >


                    <!-- CASHIER INFORMATION -->

                    <div class="form-section">


                        <h3>
                            Cashier Information
                        </h3>


                        <div class="form-row">


                            <div class="form-group">

                                <label>
                                    Cashier ID
                                </label>

                                <input
                                    type="text"
                                    name="cashier_id"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Name
                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    required
                                >

                            </div>


                        </div>


                        <div class="form-row">


                            <div class="form-group">

                                <label>
                                    Username
                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    PIN
                                </label>

                                <input
                                    type="password"
                                    name="pin"
                                    maxlength="6"
                                    inputmode="numeric"
                                >

                            </div>


                        </div>


                        <div class="form-group">

                            <label>
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                required
                            >

                        </div>

                    </div>


                    <!-- MODAL ACTIONS -->

                    <div class="modal-actions">


                        <button
                            type="button"
                            class="cancel-btn"
                            onclick="closeCashierModal()"
                        >

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="save-btn"
                        >

                            Create Cashier

                        </button>


                    </div>


                </form>


            </div>


        </div>



        <!-- =================================================
             NEW POS / CANTEEN MODAL
        ================================================== -->

        <div
            id="posModal"
            class="modal"
        >


            <div class="modal-content">


                <div class="modal-header">


                    <h2>
                        New POS / Canteen
                    </h2>


                    <button
                        type="button"
                        class="close-modal"
                        onclick="closePosModal()"
                    >

                        &times;

                    </button>


                </div>


                <form
                    id="posForm"
                    action="add_pos_cashier.php"
                    method="POST"
                >


                    <input
                        type="hidden"
                        name="category"
                        value="pos"
                    >


                    <!-- CANTEEN INFORMATION -->

                    <div class="form-section">


                        <h3>
                            Canteen Information
                        </h3>


                        <div class="form-group">

                            <label>
                                Canteen ID
                            </label>

                            <input
                                type="text"
                                name="canteen_id"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Canteen Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Location
                            </label>

                            <input
                                type="text"
                                name="location"
                            >

                        </div>


                        <div class="form-row">


                            <div class="form-group">

                                <label>
                                    Password
                                </label>

                                <input
                                    type="password"
                                    name="password"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    PIN
                                </label>

                                <input
                                    type="password"
                                    name="pin"
                                    maxlength="6"
                                    inputmode="numeric"
                                >

                            </div>


                        </div>


                    </div>


                    <!-- MODAL ACTIONS -->

                    <div class="modal-actions">


                        <button
                            type="button"
                            class="cancel-btn"
                            onclick="closePosModal()"
                        >

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="save-btn"
                        >

                            Create Canteen

                        </button>


                    </div>


                </form>


            </div>


        </div>



        <!-- SUCCESS MESSAGE MODAL -->
        <div id="creationSuccessModal" class="modal" style="display:none; z-index:9999;">
            <div class="modal-content" style="max-width:480px; text-align:center;">
                <div style="font-size:48px; margin-bottom:10px;">&#10003;</div>
                <h2 id="creationSuccessTitle" style="margin-bottom:8px;">Account Created Successfully!</h2>
                <p id="creationSuccessMessage" style="margin-bottom:18px;">
                    The account has been created successfully.
                </p>
                <div id="crtails"
                     style="text-align:left; background:#f7f8fa; border-radius:10px; padding:14px; margin-bottom:20px;">
                </div>
                <button type="button" class="save-btn" onclick="closeCreationSuccessModal()">
                    Done
                </button>
            </div>
        </div>

    </main>


</div>



<script>


/* =========================================================
   CASHIER MODAL
========================================================= */

function openCashierModal() {

    const modal =
        document.getElementById(
            "cashierModal"
        );


    if (!modal) {
        return;
    }


    modal.style.display = "flex";

}


function closeCashierModal() {

    const modal =
        document.getElementById(
            "cashierModal"
        );


    if (!modal) {
        return;
    }


    modal.style.display = "none";

}


/* =========================================================
   POS MODAL
========================================================= */

function openPosModal() {

    const modal =
        document.getElementById(
            "posModal"
        );


    if (!modal) {
        return;
    }


    modal.style.display = "flex";

}


function closePosModal() {

    const modal =
        document.getElementById(
            "posModal"
        );


    if (!modal) {
        return;
    }


    modal.style.display = "none";

}


/* =========================================================
   CLOSE MODALS WHEN CLICKING OUTSIDE
========================================================= */

window.addEventListener(
    "click",
    function(event) {


        const cashierModal =
            document.getElementById(
                "cashierModal"
            );


        const posModal =
            document.getElementById(
                "posModal"
            );


        if (
            event.target ===
            cashierModal
        ) {

            closeCashierModal();

        }


        if (
            event.target ===
            posModal
        ) {

            closePosModal();

        }


    }
);


/* =========================================================
   VIEW CANTEEN
========================================================= */

function viewCanteen(
    canteenID
) {

    window.location.href =
        "view_canteen.php?canteen_id=" +
        encodeURIComponent(
            canteenID
        );

}


/* =========================================================
   DELETE CANTEEN
========================================================= */

function deleteCanteen(
    canteenID
) {


    if (
        confirm(
            "Are you sure you want to permanently delete this canteen?"
        )
    ) {


        window.location.href =
            "delete_canteen.php?canteen_id=" +
            encodeURIComponent(
                canteenID
            );


    }

}


/* =========================================================
   VIEW CASHIER
========================================================= */

function viewCashier(
    cashierID
) {

    window.location.href =
        "view_cashier.php?cashier_id=" +
        encodeURIComponent(
            cashierID
        );

}


/* =========================================================
   DELETE CASHIER
========================================================= */

function deleteCashier(
    cashierID
) {


    if (
        confirm(
            "Are you sure you want to permanently delete this cashier account?"
        )
    ) {


        window.location.href =
            "delete_cashier.php?cashier_id=" +
            encodeURIComponent(
                cashierID
            );


    }

}


/* =========================================================
   SEARCH CASHIERS
========================================================= */

function searchCashiers(
    searchValue
) {


    searchValue =
        searchValue
            .toLowerCase()
            .trim();


    const rows =
        document.querySelectorAll(
            "#usersTable tr"
        );


    rows.forEach(
        function(row) {


            const text =
                row.textContent
                    .toLowerCase();


            row.style.display =
                text.includes(
                    searchValue
                )
                    ? ""
                    : "none";


        }
    );

}



/* =========================================================
   CREATE POS / CANTEEN AND CASHIER WITH SUCCESS MESSAGE
========================================================= */

function showCreationSuccess(data) {
    const modal = document.getElementById("creationSuccessModal");
    const title = document.getElementById("creationSuccessTitle");
    const message = document.getElementById("creationSuccessMessage");
    const details = document.getElementById("creationSuccessDetails");

    if (!modal || !title || !message || !details) {
        alert(data.message || "Account created successfully.");
        window.location.reload();
        return;
    }

    title.textContent = data.account_type === "cashier"
        ? "Cashier Account Created Successfully!"
        : "POS / Canteen Created Successfully!";

    message.textContent = data.message || "The account has been created successfully.";

    const rows = [
        ["Account ID", data.account_id],
        ["Name", data.account_name]
    ];

    if (data.account_type === "cashier") {
        rows.push(["Username", data.username]);
    } else {
        rows.push(["Location", data.location || "Not specified"]);
    }

    details.innerHTML = rows
        .filter(function (item) {
            return item[1] !== undefined && item[1] !== null && item[1] !== "";
        })
        .map(function (item) {
            const label = escapeHTML(item[0]);
            const value = escapeHTML(String(item[1]));
            return '<div style="display:flex; justify-content:space-between; gap:16px; padding:7px 0; border-bottom:1px solid #e5e7eb;">' +
                '<strong>' + label + '</strong><span style="text-align:right; overflow-wrap:anywhere;">' + value + '</span></div>';
        })
        .join("");

    modal.style.display = "flex";
    modal.setAttribute("aria-hidden", "false");
}

function escapeHTML(value) {
    return value.replace(/[&<>"']/g, function (character) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[character];
    });
}

function closeCreationSuccessModal() {
    const modal = document.getElementById("creationSuccessModal");
    if (modal) {
        modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");
    }

    window.location.reload();
}

function handleAccountCreation(form, modalCloser) {
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton ? submitButton.textContent : "";

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = "Creating...";
        }

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form)
            });

            const data = await response.json();

            if (!response.ok || data.success !== true) {
                alert(data.message || "Unable to create the account.");
                return;
            }

            modalCloser();
            form.reset();
            showCreationSuccess(data);

        } catch (error) {
            console.error("Account creation error:", error);
            alert("Something went wrong while creating the account. Please try again.");
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    const cashierForm = document.getElementById("cashierForm");
    const posForm = document.getElementById("posForm");

    if (cashierForm) {
        handleAccountCreation(cashierForm, closeCashierModal);
    }

    if (posForm) {
        handleAccountCreation(posForm, closePosModal);
    }
});

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