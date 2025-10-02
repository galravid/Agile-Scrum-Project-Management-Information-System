<?php
session_start();
include 'db.php'; 

$loggedInUserName = $_SESSION['user_name'] ?? '';
$company_id = $_POST['company_id'] ?? '';
$success_message = '';
$error_message = '';

$stmt = $pdo->query("SELECT c_id, c_data FROM company");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id_to_delete = $_POST['product_id_to_delete'] ?? null;
    $company_id_for_delete = $_POST['company_id_for_delete'] ?? null; // To keep the company selected after deletion

    if ($product_id_to_delete && $company_id_for_delete) {
        try {
            $stmt_fetch_product = $pdo->prepare("SELECT p_data FROM product WHERE p_id = ? AND c_id = ?");
            $stmt_fetch_product->execute([$product_id_to_delete, $company_id_for_delete]);
            $deleted_product_data = $stmt_fetch_product->fetchColumn();
            $deleted_product_name = 'מוצר'; // Default name if not found
            if ($deleted_product_data) {
                $decoded_data = json_decode($deleted_product_data, true);
                $deleted_product_name = $decoded_data['name'] ?? $deleted_product_name;
            }

            $stmt_delete = $pdo->prepare("DELETE FROM product WHERE p_id = ? AND c_id = ?");
            $stmt_delete->execute([$product_id_to_delete, $company_id_for_delete]);

            if ($stmt_delete->rowCount() > 0) {
                $success_message = 'המוצר "' . htmlspecialchars($deleted_product_name) . '" נמחק בהצלחה!';
                $company_id = $company_id_for_delete;
            } else {
                $error_message = 'שגיאה: המוצר לא נמצא או לא שייך לחברה הנבחרת.';
            }
        } catch (PDOException $e) {
            $error_message = 'שגיאה במחיקת המוצר: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = 'שגיאה: חסר מזהה מוצר או חברה למחיקה.';
    }
}

$existing_products = [];
$selected_company_name = '';
if ($company_id) {
    $stmt = $pdo->prepare("SELECT c_data FROM company WHERE c_id = ?");
    $stmt->execute([$company_id]);
    $selected_company_data = json_decode($stmt->fetchColumn() ?? '[]', true); // Added ?? '[]' for null safety
    $selected_company_name = $selected_company_data['name'] ?? '';

    $stmt = $pdo->prepare("SELECT p_id, p_data FROM product WHERE c_id = ?"); // Fetch p_id for deletion
    $stmt->execute([$company_id]);
    $existing_products = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch as assoc to get p_id and p_data
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>מנהל חברה - מחיקת מוצר</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
body {
    font-family: Arial, sans-serif;
    padding-top: 70px; /* Space for fixed toolbar */
    margin: 0;
    text-align: center;
    background-image: url('background.jpg');
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
}

h1 {
    color: #266C99;
    margin-top: 30px;
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

select {
    padding: 12px;
    font-size: 16pt;
    border-radius: 10px;
    border: 1px solid #ccc;
    width: 100%; /* Make it full width in its container */
    max-width: 400px; /* Limit max width for desktop */
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    background-color: white;
    text-align: right; /* ליישור לימין */
    direction: rtl; /* ליישור לימין */
}

select option {
    direction: rtl;
    text-align: right;
}

.button-group {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 40px;
    flex-wrap: wrap;
}

.button-group button {
    width: 200px;
    font-size: 20px;
    padding: 18px 32px;
    background-color: #266C99;
    color: white;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    text-align: center;
}

.button-group button:hover {
    background-color: #1f5a80;
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
    border: 1px solid #FFFFFF; /* Ensure borders are present */
    color: white;
    font-size: x-large;
    text-align: center;
}

.products-table { /* Specific class for the product listing table */
    width: 95%;
    margin: 30px auto;
    border-collapse: collapse;
    background: white; /* Ensure background is white for readability */
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.products-table th, .products-table td {
    border: 1px solid #ccc;
    padding: 12px;
    text-align: center;
}

.products-table th {
    background-color: #266C99;
    color: white;
}

.products-table th:hover {
    background-color: #1f5a80;
}

.products-table tr:nth-child(even) {
    background-color: #f2f2f2;
}

.delete-btn {
    background-color: #d9534f; /* Red for delete */
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s ease;
    white-space: nowrap; /* Prevents line break on button text */
}

.delete-btn:hover {
    background-color: #c9302c;
}

.success-message, .error-message {
    font-size: 18pt;
    border: 2px solid;
    border-radius: 30px;
    padding: 30px;
    margin: 40px auto;
    width: 70%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background-color: transparent; /* Keep background transparent for consistency */
}
.success-message {
    color: green;
    border-color: green;
}

.error-message {
    color: red;
    border-color: red;
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
    .products-table {
        border: 0;
    }

    .products-table thead {
        display: none;
    }

    .products-table tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        background-color: white;
        padding: 10px;
    }

    .products-table td {
        display: block;
        text-align: right !important;
        border-bottom: 1px solid #eee;
        padding: 8px 5px;
        position: relative;
        padding-right: 120px; /* Adjusted padding-right */
    }

    .products-table td:last-child {
        border-bottom: 0;
    }

    .products-table td::before {
        content: attr(data-label);
        position: absolute;
        right: 5px;
        width: 110px; /* Adjusted width */
        font-weight: bold;
        color: #266C99;
        text-align: right;
    }

    .button-group {
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }

    .button-group button {
        width: 80%;
        max-width: 300px;
    }
}
</style>
</head>

<body>

<div class="fixed-toolbar" dir="rtl">
  <table style="height: 70px; background-color: #266C99; width: 100%; border-collapse: collapse;">
    <tr>
	<td style="border: 1px solid #FFFFFF; width: 80px; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="index.php">
          <img src="logo.png" alt="תמונת לוגo" style="width: 50px; height: 50px;" align="middle">
        </a>
      </td>
      <td style="border: 1px solid #FFFFFF; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php" style="color: #FFFFFF; text-decoration: none;">תפריט ראשי</a>
      </td>
      <td style="border: 1px solid #FFFFFF; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="LocalMenuCompanyManager.php" style="color: #FFFFFF; text-decoration: none;">תפריט מקומי</a>
      </td>
<td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;">
	    הנך מחובר <?php echo htmlspecialchars($loggedInUserName); ?></td>
	       <td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id ?? ''); 
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>
<br><br><br><br><br><br><br>

<h1>מנהל חברה - מחיקת מוצר</h1>

<form method="post">
    <label><strong><span style="font-size: x-large">בחר חברה:</span></strong></label>
    <select name="company_id" required onchange="this.form.submit()">
        <option value="">--בחר--</option>
        <?php foreach ($companies as $company):
            $c = json_decode($company['c_data'] ?? '[]', true); // Added ?? '[]' for null safety
            $name = $c['name'] ?? 'לא ידוע';
            ?>
            <option value="<?= htmlspecialchars($company['c_id']) ?>" <?= ($company['c_id'] == $company_id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($name) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($company_id): ?>
    <?php if ($success_message): ?>
        <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <h2>מוצרים קיימים עבור חברת <?= htmlspecialchars($selected_company_name) ?>:</h2>
    <?php if ($existing_products): ?>
        <table class="products-table"> 
            <thead>
                <tr>
                    <th>פעולות</th>
                    <th>שם</th>
                    <th>תיאור</th>
                    <th>גרסה</th>
                    <th>תאריך שחרור</th>
                    <th>סטטוס</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existing_products as $product):
                    $p_data = json_decode($product['p_data'] ?? '[]', true); // Added ?? '[]' for null safety
                    ?>
                    <tr>
                        <td data-label="פעולות">
                            <form method="post" onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את המוצר: <?= htmlspecialchars($p_data['name'] ?? 'זה') ?>?');">
                                <input type="hidden" name="product_id_to_delete" value="<?= htmlspecialchars($product['p_id']) ?>">
                                <input type="hidden" name="company_id_for_delete" value="<?= htmlspecialchars($company_id) ?>">
                                <button type="submit" name="delete_product" class="delete-btn">
                                    מחק <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                        <td data-label="שם"><?= htmlspecialchars($p_data['name'] ?? '') ?></td>
                        <td data-label="תיאור"><?= htmlspecialchars($p_data['description'] ?? '') ?></td>
                        <td data-label="גרסה"><?= htmlspecialchars($p_data['version'] ?? '') ?></td>
                        <td data-label="תאריך שחרור"><?= htmlspecialchars($p_data['release_date'] ?? '') ?></td>
                        <td data-label="סטטוס"><?= htmlspecialchars($p_data['status'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>אין מוצרים לחברה זו עדיין.</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
