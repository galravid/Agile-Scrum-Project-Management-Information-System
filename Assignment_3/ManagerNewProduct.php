<?php
session_start();
include 'db.php';
$loggedInUserName = $_SESSION['user_name'] ?? '';
$company_id = $_POST['company_id'] ?? '';
$title_after_submit = false;
$error_message = '';
$success_message = '';


if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it after displaying
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear it after displaying
}

$status = '';

$stmt = $pdo->query("SELECT c_id, c_data FROM company");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = $_POST['product_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $version = $_POST['version'] ?? '';
    $release_date = $_POST['release_date'] ?? '';
    $status = $_POST['status'] ?? ''; // Update $status based on post, or keep empty if not posted
    $company_id_post = $_POST['company_id'] ?? ''; // Get company_id from POST for redirect

    if (empty($product_name) || empty($description) || empty($version) || empty($release_date) || empty($status)) {
        $_SESSION['error_message'] = 'יש למלא את כל השדות הנדרשים (שם מוצר, תיאור, גרסה, תאריך שחרור, וסטטוס).';
        $_SESSION['form_data'] = $_POST; // Store form data to re-populate
    } elseif (!strtotime($release_date)) { // Checks if the date is a valid date string
        $_SESSION['error_message'] = 'תאריך השחרור אינו תקין.';
        $_SESSION['form_data'] = $_POST; // Store form data to re-populate
    } else {
        $product_uid = ''; 

        $product_data = json_encode([
            'uid' => $product_uid, // Assign the generated unique ID
            'name' => $product_name,
            'description' => $description,
            'version' => $version,
            'release_date' => $release_date,
            'status' => $status,
            'kanban' => new stdClass() // Empty object for kanban as per schema
        ], JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $pdo->prepare("INSERT INTO product (c_id, p_data) VALUES (?, ?)");
            $stmt->execute([$company_id_post, $product_data]);
            $_SESSION['success_message'] = 'המוצר "' . htmlspecialchars($product_name) . '" נוסף בהצלחה!';
            // No need to clear form fields here, as we are redirecting.
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'שגיאה בהוספת המוצר: ' . htmlspecialchars($e->getMessage());
            $_SESSION['form_data'] = $_POST; // Store form data even on DB error
            error_log("Error adding product: " . $e->getMessage()); // Log for debugging
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?company_id=' . urlencode($company_id_post));
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    $status = ''; // Explicitly ensure status is empty for this case
    if (isset($_SESSION['form_data'])) {
        $product_name = $_SESSION['form_data']['product_name'] ?? '';
        $description = $_SESSION['form_data']['description'] ?? '';
        $version = $_SESSION['form_data']['version'] ?? '';
        $release_date = $_SESSION['form_data']['release_date'] ?? '';
        $status = $_SESSION['form_data']['status'] ?? '';
        unset($_SESSION['form_data']); // Clear after re-populating
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['company_id'])) {
    $company_id = $_GET['company_id'];
    if (isset($_SESSION['form_data'])) {
        $product_name = $_SESSION['form_data']['product_name'] ?? '';
        $description = $_SESSION['form_data']['description'] ?? '';
        $version = $_SESSION['form_data']['version'] ?? '';
        $release_date = $_SESSION['form_data']['release_date'] ?? '';
        $status = $_SESSION['form_data']['status'] ?? '';
        unset($_SESSION['form_data']); // Clear after re-populating
    }
}


$existing_products = [];
$selected_company_name = '';
if ($company_id) {
    $stmt = $pdo->prepare("SELECT c_data FROM company WHERE c_id = ?");
    $stmt->execute([$company_id]);
    $selected_company_data = json_decode($stmt->fetchColumn() ?? '[]', true);
    $selected_company_name = $selected_company_data['name'] ?? '';

    $stmt = $pdo->prepare("SELECT p_data FROM product WHERE c_id = ?");
    $stmt->execute([$company_id]);
    $existing_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>מנהל חברה - הוספת מוצר חדש</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding-top: 70px; /* Space for fixed toolbar */
        text-align: center;
        background-image: url('background.jpg');
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
    }
    h1 {
        color: #266C99;
        margin-top: 30px; /* Adjusted margin for H1 */
        margin-bottom: 30px;
    }

    p {
        font-size: 18pt;
        border: 2px solid #266C99;
        border-radius: 30px;
        padding: 30px;
        margin: 40px auto;
        width: 70%;
        background-color: transparent;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .fixed-toolbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 999;
    }
    .fixed-toolbar table {
        height: 70px;
        background-color: #266C99;
        width: 100%;
        border-collapse: collapse;
    }
    .fixed-toolbar td {
        color: white;
        font-size: x-large;
        text-align: center;
        border: 1px solid #FFFFFF; /* Ensure borders are present */
    }

    form {
        width: 80%; /* Adjusted for better mobile fit */
        max-width: 600px; /* Max width for larger screens */
        margin: auto;
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        display: flex; /* Use flexbox for better layout control */
        flex-direction: column; /* Stack elements vertically */
        align-items: center; /* Center items horizontally */
    }
    form label {
        font-size: large;
        margin-bottom: 5px;
        align-self: flex-end; /* Align labels to the right in RTL */
        margin-right: 5px; /* Small margin for spacing */
    }
    select, input[type=text], input[type=date], textarea {
        width: calc(100% - 20px); /* Account for padding */
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box; /* Include padding and border in element's total width/height */
        text-align: right; /* Align text right for RTL input */
        direction: rtl; /* Ensure RTL text direction */
    }
    textarea {
        resize: vertical; /* Allow vertical resizing */
        min-height: 80px; /* Minimum height for textarea */
    }
    button {
        padding: 10px 20px;
        background-color: #266C99;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 1.1em;
    }
    button:hover {
        background-color: #1f5a80;
    }

    .products-listing-table { /* Changed class from .products */
        width: 95%;
        margin: 30px auto;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        direction: rtl; /* Added for RTL table */
    }
    .products-listing-table th, .products-listing-table td {
        border: 1px solid #ccc;
        padding: 12px;
        text-align: center;
    }
    .products-listing-table th {
        background-color: #266C99;
        color: white;
    }
    .products-listing-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    .error-message {
        color: red;
        margin-bottom: 15px;
        border: 2px solid red;
        border-radius: 10px;
        padding: 10px;
        background-color: #ffe6e6;
        width: fit-content; /* Make message width fit content */
        margin-left: auto; /* Center the message */
        margin-right: auto; /* Center the message */
    }
    .success-message {
        color: #28a745; /* Green color */
        font-size: 1.5em; /* Larger font */
        font-weight: bold; /* Bold text */
        margin-bottom: 20px;
        border: 2px solid #28a745;
        border-radius: 10px;
        padding: 10px;
        background-color: #e6ffe6;
        width: fit-content; /* Make message width fit content */
        margin-left: auto; /* Center the message */
        margin-right: auto; /* Center the message */
    }

    @media screen and (max-width: 350px) {
        .fixed-toolbar table tr {
            display: flex;
            flex-direction: column;
            height: auto;
        }
        .fixed-toolbar table td {
            width: 100% !important;
            text-align: center;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .fixed-toolbar table td:last-child {
            border-bottom: none;
        }
    }

    @media screen and (max-width: 500px) {
        .products-listing-table {
            border: 0;
        }
        .products-listing-table thead {
            display: none;
        }
        .products-listing-table tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            background-color: white;
            padding: 10px;
        }
        .products-listing-table td {
            display: block;
            text-align: right !important;
            border-bottom: 1px solid #eee;
            padding: 8px 5px;
            position: relative;
            padding-right: 120px; /* Space for the data-label */
        }
        .products-listing-table td:last-child {
            border-bottom: 0;
        }
        .products-listing-table td::before {
            content: attr(data-label);
            position: absolute;
            right: 5px;
            width: 90px; /* Width for the label */
            font-weight: bold;
            color: #266C99;
            text-align: right;
        }
    }
</style>
</head>
<body>

<div class="fixed-toolbar" dir="rtl">
  <table>
    <tr>
    <td style="width: 80px;">
        <a href="index.php">
          <img src="logo.png" alt="תמונת לוגו" style="width: 50px; height: 50px;" align="middle">
        </a>
      </td>
      <td><a href="MainMenu.php" style="color: white; text-decoration: none;">תפריט ראשי</a></td>
      <td><a href="LocalMenuCompanyManager.php" style="color: white; text-decoration: none;">תפריט מקומי</a></td>
      <td>הנך מחובר <?php echo htmlspecialchars($loggedInUserName); ?></td>
      <td style="width: 230px;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id ?? '');
      ?>&nbsp;</td>
    </tr>
  </table>
</div>
<br><br><br><br><br><br><br>

<h1>מנהל חברה - הוספת מוצר חדש</h1>

<form method="post">
    <div style="text-align: right">
    <label><strong><span style="font-size: large">בחר חברה:</span></strong></label>
    <select name="company_id" required onchange="this.form.submit()">
        <option value="">--בחר--</option>
        <?php foreach ($companies as $company):
            $c = json_decode($company['c_data'] ?? '[]', true);
            $name = $c['name'] ?? 'לא ידוע';
            ?>
            <option value="<?= htmlspecialchars($company['c_id']) ?>" <?= ($company['c_id'] == $company_id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($company_id): ?>
        <?php
        // Display messages that came from the session
        if ($error_message): ?>
            <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
        <?php endif; ?>

        <label><strong><span style="font-size: large">שם מוצר:</span></strong></label>
        <input type="text" name="product_name" value="<?= htmlspecialchars($product_name ?? '') ?>" required>

        <label><strong><span style="font-size: large">תיאור:</span></strong></label>
        <textarea name="description" required><?= htmlspecialchars($description ?? '') ?></textarea>

        <label><strong><span style="font-size: large">גרסה:</span></strong></label>
        <input type="text" name="version" value="<?= htmlspecialchars($version ?? '') ?>" required>

        <label><strong><span style="font-size: large">תאריך שחרור:</span></strong></label>
        <input type="date" name="release_date" value="<?= htmlspecialchars($release_date ?? '') ?>" required>

        <label><strong><span style="font-size: large">סטטוס:</span></strong></label>
        <select name="status" required>
            <option value="" <?= ($status == '') ? 'selected' : '' ?>>-- בחר סטטוס --</option>
            <option value="In Development" <?= ($status == 'In Development') ? 'selected' : '' ?>>In Development</option>
            <option value="Released" <?= ($status == 'Released') ? 'selected' : '' ?>>Released</option>
        </select>

        <button type="submit" name="add_product">
        <div style="text-align: center">
            הוסף מוצר</div>
    </button>
    <?php endif; ?>
</form>

<?php if ($company_id && $existing_products): ?>
    <h2>מוצרים קיימים מעודכנים לחברה זו:</h2>
    <table class="products-listing-table">
        <thead>
            <tr>
                <th>שם</th>
                <th>תיאור</th>
                <th>גרסה</th>
                <th>תאריך שחרור</th>
                <th>סטטוס</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($existing_products as $p_data):
                $p = json_decode($p_data ?? '[]', true); ?>
                <tr>
                    <td data-label="שם"><?= htmlspecialchars($p['name'] ?? '') ?></td>
                    <td data-label="תיאור"><?= htmlspecialchars($p['description'] ?? '') ?></td>
                    <td data-label="גרסה"><?= htmlspecialchars($p['version'] ?? '') ?></td>
                    <td data-label="תאריך שחרור"><?= htmlspecialchars($p['release_date'] ?? '') ?></td>
                    <td data-label="סטטוס"><?= htmlspecialchars($p['status'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>