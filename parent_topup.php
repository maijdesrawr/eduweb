<?php
session_start();

if (!isset($_SESSION['parent_id'])) {
    header("Location: index.php");
    exit();
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPocket | Parent Top-Up</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
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
            class="active"
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

    <main class="page">

    <div>
                    <div class="eyebrow">
                        Payment
                    </div>
                    <h1>Wallet Top-Up</h1>
                </div>
                <label>
                <p>Select your child and choose the amount you want to add to their EduPocket wallet.</p>
            </label>

        <section class="topup-card">
<label class="field-label">Select a child</label>
            <div class="form-group">
                
                <select class="child-select" id="childSelect" disabled>
                    <option value="">Loading linked students...</option>
                </select>
            </div>

            <div class="amount-section">
                <label class="field-label">Select an amount</label>
                <div class="amount-grid" id="amountGrid">
                    <button type="button" class="amount-option" data-amount="10">₱10</button>
                    <button type="button" class="amount-option" data-amount="50">₱50</button>
                    <button type="button" class="amount-option" data-amount="100">₱100</button>
                    <button type="button" class="amount-option" data-amount="200">₱200</button>
                    <button type="button" class="amount-option" data-amount="300">₱300</button>
                    <button type="button" class="amount-option" data-amount="500">₱500</button>
                    <button type="button" class="amount-option" data-amount="1000">₱1,000</button>
                    <button type="button" class="amount-option" data-amount="2000">₱2,000</button>
                    <button type="button" class="amount-option" data-amount="5000">₱5,000</button>
                    <button type="button" class="amount-option" data-amount="10000">₱10,000</button>
                    <button type="button" class="amount-option" data-amount="20000">₱20,000</button>
                    <button type="button" class="amount-option" data-amount="50000">₱50,000</button>
                </div>

                <label class="field-label" for="customAmount">Input amount</label>
                <div class="input-wrap">
                    <span class="currency">₱</span>
                    <input
                        class="amount-input"
                        type="number"
                        id="customAmount"
                        min="1"
                        max="100000"
                        step="0.01"
                        placeholder="Enter an amount from 1 to 100,000"
                    >
                </div>

                <button type="button" class="confirm-button" id="confirmButton" disabled>Confirm Top-Up Amount</button>
                <div class="message" id="message"></div>
                <p class="payment-note">Web top-ups are available through QR Ph only.</p>
            </div>
        </section>
    </main>

    <script>
        const parentId = <?php echo json_encode($parentId); ?>;
        const childSelect = document.getElementById('childSelect');
        const customAmount = document.getElementById('customAmount');
        const confirmButton = document.getElementById('confirmButton');
        const message = document.getElementById('message');
        const amountButtons = document.querySelectorAll('.amount-option');

        let children = [];
        let selectedAmount = null;

        function showMessage(text, type = 'error') {
            message.textContent = text;
            message.className = 'message show' + (type === 'info' ? ' info' : '');
        }

        function clearMessage() {
            message.textContent = '';
            message.className = 'message';
        }

        function updateConfirmButton() {
            confirmButton.disabled = children.length === 0 || !childSelect.value;
        }

        async function loadChildren() {
            try {
                const response = await fetch(
                    'get_linked_children.php?parent_id=' + encodeURIComponent(parentId),
                    { credentials: 'same-origin' }
                );

                const responseText = await response.text();

console.log('PHP Response Status:', response.status);
console.log('PHP Response:', responseText);

let data;

try {
    data = JSON.parse(responseText);
} catch (error) {
    throw new Error(
        'The PHP checkout file did not return valid JSON. Check the browser console for details.'
    );
}

                if (!response.ok || data.status !== 'success' || !Array.isArray(data.children)) {
                    throw new Error(data.message || 'Unable to load linked students.');
                }

                const uniqueChildren = new Map();

                data.children.forEach(child => {
                    const id = String(child.student_num ?? '');
                    if (id && !uniqueChildren.has(id)) {
                        uniqueChildren.set(id, {
                            id: id,
                            name: String(child.Fname ?? 'Student'),
                            is_locked: Number(child.is_locked ?? 0),
                            is_frozen: Number(child.is_frozen ?? 0)
                        });
                    }
                });

                children = Array.from(uniqueChildren.values());
                childSelect.innerHTML = '';

                if (children.length === 0) {
                    childSelect.add(new Option('No linked students found', ''));
                    showMessage('There are no student accounts linked to this parent account.');
                    updateConfirmButton();
                    return;
                }

                childSelect.add(new Option('Select a child', ''));
                children.forEach(child => {
                    childSelect.add(new Option(child.name, child.id));
                });

                childSelect.disabled = false;
                updateConfirmButton();
            } catch (error) {
                childSelect.innerHTML = '';
                childSelect.add(new Option('Unable to load linked students', ''));
                showMessage(error.message || 'Unable to load linked students. Please refresh the page.');
            }
        }

        amountButtons.forEach(button => {
            button.addEventListener('click', () => {
                amountButtons.forEach(item => item.classList.remove('selected'));
                button.classList.add('selected');
                selectedAmount = Number(button.dataset.amount);
                customAmount.value = '';
                clearMessage();
            });
        });

        customAmount.addEventListener('input', () => {
            selectedAmount = null;
            amountButtons.forEach(item => item.classList.remove('selected'));
            clearMessage();
        });

        childSelect.addEventListener('change', () => {
            clearMessage();
            updateConfirmButton();
        });

        
confirmButton.addEventListener('click', async () => {
    clearMessage();

    const child = children.find(item => item.id === childSelect.value);

    if (!child) {
        showMessage('Please select a child.');
        return;
    }

    if (child.is_locked === 1 || child.is_frozen === 1) {
        showMessage('This account is frozen or locked. Top-up is unavailable.');
        return;
    }

    let amount = selectedAmount;

    if (customAmount.value.trim() !== '') {
        amount = Number(customAmount.value);
    }

    if (!Number.isFinite(amount) || amount <= 0 || amount > 100000) {
        showMessage('Please select an amount or enter an amount between ₱1 and ₱100,000.');
        return;
    }

    confirmButton.disabled = true;
    confirmButton.textContent = 'Connecting to PayMongo...';

    try {
        const response = await fetch('create_paymongo_checkout.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                child_id: child.id,
                amount: amount.toFixed(2),
                method: 'QR Ph'
            })
        });

        const responseText = await response.text();

console.log('PHP Response Status:', response.status);
console.log('PHP Response:', responseText);

let data;

try {
    data = JSON.parse(responseText);
} catch (error) {
    throw new Error(
        'The PHP checkout file did not return valid JSON. Check the browser console for details.'
    );
}

        if (!response.ok || data.status !== 'success' || !data.checkout_url) {
            throw new Error(data.message || 'Unable to create PayMongo checkout.');
        }

        window.location.href = data.checkout_url;

    } catch (error) {
        showMessage(error.message || 'Something went wrong. Please try again.');
        confirmButton.disabled = false;
        confirmButton.textContent = 'Confirm Top-Up Amount';
    }
});


        loadChildren();

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
