<?php
session_start();
include 'db.php'; // Make sure db.php contains your PDO connection setup
$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';
$name_and_id = $_SESSION['name_and_id'] ?? ''; // Get from session, default to empty string if not set

if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] != '2') { // Changed role check to '2' for Scrum Master
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
$products_dropdown_options = []; // For the select dropdown
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

$members_to_display = []; // uid => name
try {
    $stmt = $pdo->prepare("SELECT uid, name FROM user WHERE role = 4 ORDER BY name ASC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Here, uid from 'user' table acts as the 'member_id' for 'p_m' table
        $members_to_display[$row['uid']] = [
            'uid' => $row['uid'],
            'name' => $row['name']
        ];
    }
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת רשימת אנשי הצוות (תפקיד 4): ' . htmlspecialchars($e->getMessage());
}

$member_product_assignments = []; // uid (from user) => array of p_id's
try {
    $stmt = $pdo->query("SELECT p_id, m_id FROM p_m");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $member_product_assignments[$row['m_id']][] = $row['p_id'];
    }
} catch (PDOException $e) {
    $error_message .= '<br>שגיאה בטעינת שיוכים קיימים: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_member'])) {
    $member_uid = $_POST['member_id'] ?? ''; // This is now the user's UID
    $product_id = $_POST['product_id'] ?? '';

    if (empty($member_uid) || empty($product_id)) {
        $_SESSION['error_message'] = 'יש לבחור עובד ומוצר לשיוך.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM p_m WHERE p_id = ? AND m_id = ?");
            $stmt->execute([$product_id, $member_uid]); // Use member_uid as m_id
            $exists = $stmt->fetchColumn();

            if ($exists > 0) {
                $_SESSION['error_message'] = 'העובד כבר משויך למוצר זה.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO p_m (p_id, m_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $member_uid]); // Use member_uid as m_id
                $_SESSION['success_message'] = 'העובד ' . htmlspecialchars($members_to_display[$member_uid]['name'] ?? 'לא ידוע') . ' שויך בהצלחה למוצר ' . htmlspecialchars($all_products[$product_id]['name'] ?? 'לא ידוע') . '.';
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'שגיאה בשיוך העובד: ' . htmlspecialchars($e->getMessage());
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
<title>רכז סקראם - שיוך עובד למוצר</title>
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

    .members-listing-table {
        width: 95%;
        margin: 30px auto;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        direction: rtl;
    }
    .members-listing-table th, .members-listing-table td {
        border: 1px solid #ccc;
        padding: 12px;
        text-align: center;
    }
    .members-listing-table th {
        background-color: #266C99;
        color: white;
    }
    .members-listing-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }
    .members-listing-table button {
        padding: 8px 15px;
        font-size: 0.9em;
        margin-top: 0;
        background-color: #28a745; /* Green for assign */
        color: white;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .members-listing-table button:hover {
        background-color: #218838;
    }
    .members-listing-table select {
        padding: 8px;
        border-radius: 5px;
        border: 1px solid #ccc;
        text-align: right;
        direction: rtl;
        width: auto; /* Allow select to size naturally */
        max-width: 150px; /* Max width for smaller screens */
        margin-left: 10px; /* Space between select and button */
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
        .members-listing-table {
            border: 0;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
        }
        .members-listing-table thead {
            display: none;
        }
        .members-listing-table tr {
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
        .members-listing-table td {
            display: block;
            text-align: right !important;
            border-bottom: 1px solid #eee;
            padding: 8px 5px;
            position: relative;
            padding-right: 120px; /* Space for the data-label */
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .members-listing-table td:last-child {
            border-bottom: 0;
        }
        .members-listing-table td::before {
            content: attr(data-label);
            position: absolute;
            right: 5px;
            width: 110px; /* Width for the label */
            font-weight: bold;
            color: #266C99;
            text-align: right;
            box-sizing: border-box;
            white-space: normal;
        }
        .members-listing-table select {
            width: 100%; /* Full width for dropdown on small screens */
            max-width: unset;
            margin-left: 0; /* Remove margin-left for full width */
            margin-top: 5px; /* Add some space above it */
        }
        .members-listing-table form button {
            width: 100%; /* Full width for button on small screens */
            margin-top: 10px; /* Space between select and button */
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

<h1>רכז סקראם - שיוך איש צוות למוצר</h1>

<?php if ($error_message): ?>
    <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
<?php endif; ?>
<?php if ($success_message): ?>
    <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
<?php endif; ?>

<?php if (!empty($members_to_display)): ?>
    <h2>רשימת אנשי צוות ושיוכים למוצרים:</h2>
    <table class="members-listing-table">
        <thead>
            <tr>
                <th>שם עובד</th>
                <th>שיוך למוצר</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members_to_display as $member_uid => $member): // Use member_uid as the key ?>
                <?php
                $assigned_product_names = [];
                if (isset($member_product_assignments[$member_uid])) {
                    foreach ($member_product_assignments[$member_uid] as $p_id) {
                        if (isset($all_products[$p_id])) {
                            $assigned_product_names[] = htmlspecialchars($all_products[$p_id]['name']);
                        }
                    }
                }
                $assigned_products_display = empty($assigned_product_names) ? 'לא משויך' : implode(', ', $assigned_product_names);
                ?>
                <tr>
                    <td data-label="שם עובד"><?= htmlspecialchars($member['name']) ?></td>
                    <td data-label="שיוך למוצר">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 5px;">
                            <span><?= $assigned_products_display ?></span>
                            <form method="post" style="display:flex; align-items: center; margin: 0; padding: 0; flex-wrap: wrap; justify-content: center;">
                                <input type="hidden" name="member_id" value="<?= htmlspecialchars($member_uid) ?>">
                                <select name="product_id" required>
                                    <option value="">-- בחר מוצר נוסף --</option>
                                    <?php foreach ($products_dropdown_options as $p_id => $p_name_display): ?>
                                        <option value="<?= htmlspecialchars($p_id) ?>"><?= $p_name_display ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="assign_member">שייך</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>אין אנשי צוות (תפקיד 4) קיימים במערכת.</p>
<?php endif; ?>

<br><br>
  <footer>
    © 2025 Taskly Team D2
  </footer>

</body>

</html>
