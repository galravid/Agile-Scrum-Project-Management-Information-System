<?php
session_start();
include 'db.php';

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';
$name_and_id = $_SESSION['name_and_id'] ?? ''; // Get from session, default to empty string if not set

if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] != '3') {
    header("Location: Login.php"); // Redirect to login page if not authorized
    exit();
}

$company_id = $_REQUEST['company_id'] ?? ''; // Use $_REQUEST to get company_id from both POST and GET
$error_message = '';
$success_message = '';
$product_name = '';
$description = '';
$version = '';
$release_date = '';
$status = ''; // Initialize status for the form dropdown

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it immediately after displaying
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear it immediately after displaying
}

$companies = [];
try {
    $stmt = $pdo->query("SELECT c_id, c_data FROM company");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'שגיאה בטעינת רשימת החברות: ' . htmlspecialchars($e->getMessage());
    header("Location: " . $_SERVER['PHP_SELF'] . "?company_id=" . urlencode($company_id));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $company_id = $_POST['company_id'] ?? ''; // Ensure company_id is captured from the form
    $product_name = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $release_date = trim($_POST['release_date'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if (empty($company_id)) {
        $_SESSION['error_message'] = 'יש לבחור חברה.';
    } elseif (empty($product_name) || empty($description) || empty($version) || empty($release_date) || empty($status)) {
        $_SESSION['error_message'] = 'יש למלא את כל השדות הנדרשים (שם מוצר, תיאור, גרסה, תאריך שחרור, וסטטוס).';
        $_SESSION['form_data'] = [
            'product_name' => $product_name,
            'description' => $description,
            'version' => $version,
            'release_date' => $release_date,
            'status' => $status
        ];
    } elseif (!strtotime($release_date)) { // Checks if the date is a valid date string
        $_SESSION['error_message'] = 'תאריך השחרור אינו תקין.';
        $_SESSION['form_data'] = [
            'product_name' => $product_name,
            'description' => $description,
            'version' => $version,
            'release_date' => $release_date,
            'status' => $status
        ];
    } else {
        $product_uid = "";

        $product_data = json_encode([
            'uid' => $product_uid,
            'name' => $product_name,
            'description' => $description,
            'version' => $version,
            'release_date' => $release_date,
            'status' => $status,
            'kanban' => new stdClass() // Empty object for kanban as per schema
        ], JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $pdo->prepare("INSERT INTO product (c_id, p_data) VALUES (?, ?)");
            $stmt->execute([$company_id, $product_data]);
            $_SESSION['success_message'] = 'המוצר "' . htmlspecialchars($product_name) . '" נוסף בהצלחה!';
            // Clear form data from session after successful submission
            unset($_SESSION['form_data']);
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'שגיאה בהוספת המוצר: ' . htmlspecialchars($e->getMessage());
            // Preserve form data on DB error
            $_SESSION['form_data'] = [
                'product_name' => $product_name,
                'description' => $description,
                'version' => $version,
                'release_date' => $release_date,
                'status' => $status
            ];
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?company_id=" . urlencode($company_id));
    exit();
} 
elseif (isset($_SESSION['form_data'])) {
    $product_name = $_SESSION['form_data']['product_name'] ?? '';
    $description = $_SESSION['form_data']['description'] ?? '';
    $version = $_SESSION['form_data']['version'] ?? '';
    $release_date = $_SESSION['form_data']['release_date'] ?? '';
    $status = $_SESSION['form_data']['status'] ?? '';
    unset($_SESSION['form_data']); // Clear after retrieving
}


$existing_products = [];
$selected_company_name = '';
if ($company_id) {
    try {
        $stmt = $pdo->prepare("SELECT c_data FROM company WHERE c_id = ?");
        $stmt->execute([$company_id]);
        $selected_company_data = json_decode($stmt->fetchColumn() ?? '[]', true);
        $selected_company_name = $selected_company_data['name'] ?? '';

        $stmt = $pdo->prepare("SELECT p_data FROM product WHERE c_id = ?");
        $stmt->execute([$company_id]);
        $existing_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $error_message .= '<br>שגיאה בטעינת מוצרים קיימים: ' . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>בעל מוצר - הוספת מוצר</title>
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
            width: 100%; /* Ensure it spans full width of its parent */
            padding: 0 5px; /* Add some horizontal padding to the container */
            box-sizing: border-box;
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
            width: auto; /* Let it adjust to its parent with padding */
            max-width: 100%; /* Ensure it doesn't exceed its parent */
            margin: 0 auto 15px auto; /* Center the cards within the table container */
            box-sizing: border-box;
        }
        .products-listing-table td {
            display: block;
            text-align: right !important;
            border-bottom: 1px solid #eee;
            padding: 8px 5px;
            position: relative;
            padding-right: 110px; /* Adjusted padding-right */
            box-sizing: border-box;
            word-wrap: break-word; /* Allow long words to break */
            overflow-wrap: break-word; /* Modern equivalent */
        }
        .products-listing-table td:last-child {
            border-bottom: 0;
        }
        .products-listing-table td::before {
            content: attr(data-label);
            position: absolute;
            right: 5px;
            width: 100px; /* Adjusted width */
            font-weight: bold;
            color: #266C99;
            text-align: right;
            box-sizing: border-box;
            white-space: normal; /* Allow label text to wrap */
        }
    }
</style>
</head>
<body class="has-toolbar" dir="rtl">

<div class="fixed-toolbar">
  <table style="height: 70px; background-color: #266C99; width: 100%; border-collapse: collapse;">
    <tr>
	<td style="border: 1px solid #FFFFFF; width: 80px; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php">
          <img src="logo.png" alt="תמונת לוגו" style="width: 50px; height: 50px;" align="middle">
        </a>
      </td>
      <td style="border: 1px solid #FFFFFF; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php" style="color: #FFFFFF; text-decoration: none;">תפריט ראשי</a>
      </td>
      <td style="border: 1px solid #FFFFFF; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="LocalMenuProductOwner.php" style="color: #FFFFFF; text-decoration: none;">תפריט מקומי</a>
      </td>
<td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;">
	   הנך מחובר <?php echo $loggedInUserName; ?></td>
	       <td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>
<br><br><br><br><br><br><br>

<h1>בעל מוצר - הוספת מוצר חדש</h1>

<form method="post">
    <div style="text-align: center">
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
        <?php if ($error_message): ?>
            </div>
            <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
        <?php endif; ?>

        <label><strong><span style="font-size: large">שם מוצר:</span></strong></label>
        <input type="text" name="product_name" value="<?= htmlspecialchars($product_name) ?>" required>

        <label><strong><span style="font-size: large">תיאור:</span></strong></label>
        <textarea name="description" required><?= htmlspecialchars($description) ?></textarea>

        <label><strong><span style="font-size: large">גרסה:</span></strong></label>
        <input type="text" name="version" value="<?= htmlspecialchars($version) ?>" required>

        <label><strong><span style="font-size: large">תאריך שחרור:</span></strong></label>
        <input type="date" name="release_date" value="<?= htmlspecialchars($release_date) ?>" required>

        <label><strong><span style="font-size: large">סטטוס:</span></strong></label>
        <select name="status" required>
            <option value="" <?= ($status == '') ? 'selected' : '' ?>>-- בחר סטטוס --</option>
            <option value="In Development" <?= ($status == 'In Development') ? 'selected' : '' ?>>In Development</option>
            <option value="Released" <?= ($status == 'Released') ? 'selected' : '' ?>>Released</option>
        </select>

        <button type="submit" name="add_product">הוסף מוצר</button>
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
