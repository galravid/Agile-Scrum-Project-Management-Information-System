<?php
session_start();
include 'db.php'; // Make sure db.php contains your PDO connection setup

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';

$name_and_id = $_SESSION['name_and_id'] ?? ''; // Get from session, default to empty string if not set

if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] != '2') {
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

$companies_map = []; // c_id => company_name
try {
    $stmt = $pdo->query("SELECT c_id, c_data FROM company");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $c_data = json_decode($row['c_data'] ?? '{}', true);
        $companies_map[$row['c_id']] = $c_data['name'] ?? 'חברה לא ידועה';
    }
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת רשימת החברות: ' . htmlspecialchars($e->getMessage());
}

$all_products = []; // p_id => ['name' => '...', 'c_id' => '...']
$products_dropdown_options = []; // For the select dropdowns
try {
    $stmt = $pdo->query("SELECT p_id, c_id, p_data FROM product ORDER BY c_id, p_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $p_data = json_decode($row['p_data'] ?? '{}', true);
        $product_name = $p_data['name'] ?? 'מוצר לא ידוע';
        $company_name = $companies_map[$row['c_id']] ?? 'חברה לא ידועה';
        $all_products[$row['p_id']] = ['name' => $product_name, 'c_id' => $row['c_id']];
        $products_dropdown_options[$row['p_id']] = htmlspecialchars($product_name) . " (" . htmlspecialchars($company_name) . ")";
    }
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת רשימת המוצרים: ' . htmlspecialchars($e->getMessage());
}

$team_members = []; // uid => name
try {
    $stmt = $pdo->prepare("SELECT uid, name FROM user WHERE role = 4 ORDER BY name ASC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $team_members[$row['uid']] = $row['name'];
    }
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת רשימת אנשי הצוות (תפקיד 4): ' . htmlspecialchars($e->getMessage());
}

$member_product_assignments = []; // p_id => array of m_id's
$product_member_assignments_display = []; // p_id => array of member_names for display
try {
    $stmt = $pdo->query("SELECT p_id, m_id FROM p_m");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $member_product_assignments[$row['p_id']][] = $row['m_id'];
        if (isset($team_members[$row['m_id']])) {
            $product_member_assignments_display[$row['p_id']][] = htmlspecialchars($team_members[$row['m_id']]);
        }
    }
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת שיוכים קיימים: ' . htmlspecialchars($e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_member'])) {
    $member_uid = $_POST['member_uid'] ?? '';
    $source_product_id = $_POST['source_product_id'] ?? '';
    $target_product_id = $_POST['target_product_id'] ?? '';

    if (empty($member_uid) || empty($source_product_id) || empty($target_product_id)) {
        $_SESSION['error_message'] = 'יש לבחור עובד, מוצר מקור ומוצר יעד.';
    } elseif ($source_product_id == $target_product_id) {
        $_SESSION['error_message'] = 'מוצר המקור ומוצר היעד חייבים להיות שונים.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM p_m WHERE p_id = ? AND m_id = ?");
            $stmt->execute([$source_product_id, $member_uid]);
            $is_assigned_to_source = $stmt->fetchColumn();

            if ($is_assigned_to_source == 0) {
                $_SESSION['error_message'] = 'העובד אינו משויך למוצר המקור שנבחר.';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM p_m WHERE p_id = ? AND m_id = ?");
                $stmt->execute([$target_product_id, $member_uid]);
                $is_assigned_to_target = $stmt->fetchColumn();

                if ($is_assigned_to_target > 0) {
                    $_SESSION['error_message'] = 'העובד כבר משויך למוצר היעד שנבחר.';
                } else {
                    $pdo->beginTransaction(); // Start transaction for atomicity
                    $stmt = $pdo->prepare("DELETE FROM p_m WHERE p_id = ? AND m_id = ?");
                    $stmt->execute([$source_product_id, $member_uid]);
                    $stmt = $pdo->prepare("INSERT INTO p_m (p_id, m_id) VALUES (?, ?)");
                    $stmt->execute([$target_product_id, $member_uid]);

                    $pdo->commit(); // Commit transaction

                    $_SESSION['success_message'] = 'העובד ' . htmlspecialchars($team_members[$member_uid] ?? 'לא ידוע') . 
                                                   ' הועבר בהצלחה ממוצר "' . htmlspecialchars($all_products[$source_product_id]['name'] ?? 'לא ידוע') . 
                                                   '" למוצר "' . htmlspecialchars($all_products[$target_product_id]['name'] ?? 'לא ידוע') . '".';
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback on error
            $_SESSION['error_message'] = 'שגיאה בהעברת העובד: ' . htmlspecialchars($e->getMessage());
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>רכז סקראם - העברת עובד ממוצר למוצר</title>
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
        width: 80%;
        max-width: 600px;
        margin: 20px auto;
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    form label {
        font-size: large;
        margin-bottom: 5px;
        align-self: flex-end;
        margin-right: 5px;
    }
    select, input[type=text], input[type=date], textarea {
        width: calc(100% - 20px);
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
        text-align: right;
        direction: rtl;
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
        margin-top: 10px;
    }
    button:hover {
        background-color: #1f5a80;
    }

    .product-member-table {
        width: 95%;
        margin: 30px auto;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        direction: rtl;
    }
    .product-member-table th, .product-member-table td {
        border: 1px solid #ccc;
        padding: 12px;
        text-align: center;
    }
    .product-member-table th {
        background-color: #266C99;
        color: white;
    }
    .product-member-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    .product-member-table h3 {
        color: #266C99;
        margin: 10px 0;
        font-size: 1.3em;
        text-align: right;
        padding-right: 10px;
    }
    .product-member-table ul {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: right;
    }
    .product-member-table li {
        padding: 5px 0;
        border-bottom: 1px dashed #eee;
    }
    .product-member-table li:last-child {
        border-bottom: none;
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
        color: #28a745;
        font-size: 1.5em;
        font-weight: bold;
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
            height: auto;         }

        .fixed-toolbar table td {
            width: 100% !important; 
            text-align: center;
            padding: 5px 0; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);         }
        
        .fixed-toolbar table td:last-child {
            border-bottom: none; 
        }
        body.has-toolbar {
            padding-top: auto;
        }
    }
    

    @media screen and (max-width: 500px) {
        form {
            width: 80%;
            padding: 15px;
        }
        select, input[type=text], input[type=date], textarea {
            width: 100%;
        }
        .product-member-table {
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
        }
        .product-member-table thead {
            display: none;
        }
        .product-member-table tbody, .product-member-table tr, .product-member-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .product-member-table tr {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            background-color: white;
            padding: 10px;
            box-sizing: border-box;
        }
        .product-member-table td {
            text-align: right !important;
            border-bottom: 1px solid #eee;
            padding: 8px 5px;
            position: relative;
            padding-right: 120px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .product-member-table td:last-child {
            border-bottom: 0;
        }
        .product-member-table td::before {
            content: attr(data-label);
            position: absolute;
            right: 5px;
            width: 110px;
            font-weight: bold;
            color: #266C99;
            text-align: right;
            white-space: normal;
        }
        .product-member-table h3 {
            text-align: center; /* Center product name on mobile */
            padding-right: 0;
        }
        .product-member-table ul {
            text-align: center; /* Center member list on mobile */
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
        <a href="LocalMenuScrumMaster.php" style="color: #FFFFFF; text-decoration: none;">תפריט מקומי</a>
      </td>
<td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;">
	   הנך מחובר <?php echo $loggedInUserName; ?></td>
	       <td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0; "><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>
<br><br><br><br><br><br><br>

<h1>רכז סקראם - העברת עובד ממוצר למוצר</h1>

<?php if ($error_message): ?>
    <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
<?php endif; ?>
<?php if ($success_message): ?>
    <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
<?php endif; ?>

<form method="post">
    <h2>העברת עובד:</h2>
    <div style="text-align: right">
    <label for="member_uid">בחר עובד:</label>
    <select name="member_uid" id="member_uid" required>
        <option value="">-- בחר עובד --</option>
        <?php foreach ($team_members as $uid => $name): ?>
            <option value="<?= htmlspecialchars($uid) ?>"><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="source_product_id">מוצר מקור (ממנו להעביר):</label>
    <select name="source_product_id" id="source_product_id" required>
        <option value="">-- בחר מוצר --</option>
        <?php foreach ($products_dropdown_options as $p_id => $p_name_display): ?>
            <option value="<?= htmlspecialchars($p_id) ?>"><?= $p_name_display ?></option>
        <?php endforeach; ?>
    </select>

    <label for="target_product_id">מוצר יעד (אליו להעביר):</label>
    <select name="target_product_id" id="target_product_id" required>
        <option value="">-- בחר מוצר --</option>
        <?php foreach ($products_dropdown_options as $p_id => $p_name_display): ?>
            <option value="<?= htmlspecialchars($p_id) ?>"><?= $p_name_display ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" name="move_member">העבר</button>
	</div>
</form>

<?php if ($error_message || $success_message): // Display table only after a form submission ?>
    <?php if (!empty($all_products)): ?>
        <h2>שיוכי עובדים למוצרים:</h2>
        <table class="product-member-table">
            <thead>
                <tr>
                    <th>שם מוצר</th>
                    <th>עובדים משויכים</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_products as $p_id => $product): ?>
                    <tr>
                        <td data-label="שם מוצר"><?= htmlspecialchars($product['name']) ?> (חברה: <?= htmlspecialchars($companies_map[$product['c_id']] ?? 'לא ידועה') ?>)</td>
                        <td data-label="עובדים משויכים">
                            <?php if (isset($product_member_assignments_display[$p_id]) && !empty($product_member_assignments_display[$p_id])): ?>
                                <ul>
                                    <?php foreach ($product_member_assignments_display[$p_id] as $member_name): ?>
                                        <li><?= $member_name ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                אין עובדים משויכים למוצר זה.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>אין מוצרים קיימים במערכת.</p>
    <?php endif; ?>
<?php endif; ?>

<br><br>
  <footer>
    © 2025 Taskly Team D2
  </footer>

</body>

</html>
