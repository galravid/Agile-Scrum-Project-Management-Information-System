<?php
session_start();

include 'db.php'; // Make sure db.php contains your PDO connection setup

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';

$name_and_id = $_SESSION['name_and_id'] ?? ''; // Get from session, default to empty string if not set

if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] != '3') {
    header("Location: Login.php"); // Redirect to login page if not authorized
    exit();
}

$error_message = '';
$success_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it immediately after displaying
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear it immediately after displaying
}

$companies_map = [];
try {
    $stmt = $pdo->query("SELECT c_id, c_data FROM company");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $c_data = json_decode($row['c_data'] ?? '{}', true);
        $companies_map[$row['c_id']] = $c_data['name'] ?? 'חברה לא ידועה';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'שגיאה בטעינת רשימת החברות: ' . htmlspecialchars($e->getMessage());
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id_to_delete = $_POST['product_id'] ?? '';
    $company_id_of_product = $_POST['company_id'] ?? '';
    $product_name_to_delete = $_POST['product_name'] ?? 'המוצר'; // For message

    if (empty($product_id_to_delete) || empty($company_id_of_product)) {
        $_SESSION['error_message'] = 'חסרים פרטים לזיהוי המוצר למחיקה.';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM product WHERE p_id = ? AND c_id = ?");
            $stmt->execute([$product_id_to_delete, $company_id_of_product]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = 'המוצר "' . htmlspecialchars($product_name_to_delete) . '" נמחק בהצלחה!';
            } else {
                $_SESSION['error_message'] = 'המוצר לא נמצא או שכבר נמחק.';
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'שגיאה במחיקת המוצר: ' . htmlspecialchars($e->getMessage());
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$all_existing_products = [];
try {
    $stmt = $pdo->query("SELECT p_id, c_id, p_data FROM product ORDER BY c_id, p_id"); // Order by company and then product ID
    $all_existing_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת כל המוצרים: ' . htmlspecialchars($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>בעל מוצר - מחיקת מוצר</title>
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

    .products-listing-table {
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
    .products-listing-table button {
        padding: 8px 15px;
        font-size: 0.9em;
        margin-top: 0; /* Override default button margin */
        background-color: #dc3545; /* Red for delete */
    }
    .products-listing-table button:hover {
        background-color: #c82333; /* Darker red on hover */
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
            width: 100%;
            padding: 0 5px;
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
            width: auto;
            max-width: 100%;
            margin: 0 auto 15px auto;
            box-sizing: border-box;
        }
        .products-listing-table td {
            display: block;
            text-align: right !important;
            border-bottom: 1px solid #eee;
            padding: 8px 5px;
            position: relative;
            padding-right: 100px; /* Adjusted padding-right to be smaller */
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .products-listing-table td:last-child {
            border-bottom: 0;
        }
        .products-listing-table td::before {
            content: attr(data-label);
            position: absolute;
            right: 5px;
            width: 90px;
            font-weight: bold;
            color: #266C99;
            text-align: right;
            box-sizing: border-box;
            white-space: normal;
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

<h1>בעל מוצר - מחיקת מוצר</h1>

<?php if ($error_message): ?>
    <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
<?php endif; ?>
<?php if ($success_message): ?>
    <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
<?php endif; ?>

<?php if (!empty($all_existing_products)): ?>
    <h2>בחר מוצר למחיקה:</h2>
    <table class="products-listing-table">
        <thead>
            <tr>
                <th>שם מוצר</th>
                <th>חברה</th>
                <th>תיאור</th>
                <th>גרסה</th>
                <th>תאריך שחרור</th>
                <th>סטטוס</th>
                <th>פעולה</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_existing_products as $product):
                $p = json_decode($product['p_data'] ?? '[]', true);
                $company_name = $companies_map[$product['c_id']] ?? 'חברה לא ידועה';
                ?>
                <tr>
                    <td data-label="שם מוצר"><?= htmlspecialchars($p['name'] ?? '') ?></td>
                    <td data-label="חברה"><?= htmlspecialchars($company_name) ?></td>
                    <td data-label="תיאור"><?= htmlspecialchars($p['description'] ?? '') ?></td>
                    <td data-label="גרסה"><?= htmlspecialchars($p['version'] ?? '') ?></td>
                    <td data-label="תאריך שחרור"><?= htmlspecialchars($p['release_date'] ?? '') ?></td>
                    <td data-label="סטטוס"><?= htmlspecialchars($p['status'] ?? '') ?></td>
                    <td data-label="פעולה">
                        <form method="post" style="display:inline-block; margin: 0; padding: 0;" onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את המוצר \'<?= htmlspecialchars($p['name'] ?? '') ?>\'?');">
                            <input type="hidden" name="company_id" value="<?= htmlspecialchars($product['c_id']) ?>">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['p_id']) ?>">
                            <input type="hidden" name="product_name" value="<?= htmlspecialchars($p['name'] ?? '') ?>">
                            <button type="submit" name="delete_product">מחק</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>אין מוצרים קיימים במערכת.</p>
<?php endif; ?>

<br><br>
  <footer>
    © 2025 Taskly Team D2
  </footer>

</body>

</html>
