<?php
session_start();
include 'db.php';

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';
$delete_message = '';
$deleted_records_report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_to_delete'])) {
    $user_id_to_delete = filter_var($_POST['user_to_delete'], FILTER_SANITIZE_NUMBER_INT);

    if (empty($user_id_to_delete)) {
        $delete_message = "<p style='color: red;'>שגיאה: לא נבחר משתמש למחיקה.</p>";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT uid, login, name, role FROM user WHERE uid = ?");
            $stmt->execute([$user_id_to_delete]);
            $user_to_delete_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_to_delete_details) {
                if ($user_to_delete_details['role'] != 4) {
                    $delete_message = "<p style='color: red;'>שגיאה: ניתן למחוק רק אנשי צוות (Team Member).</p>";
                    $pdo->rollBack(); // Rollback if the user is not a Team Member
                } else {
                    $deleted_records_report[] = [
                        'table' => 'משתמש (user)',
                        'action' => 'נמחק',
                        'details' => "UID: " . htmlspecialchars($user_to_delete_details['uid']) . ", שם: " . htmlspecialchars($user_to_delete_details['name']) . ", תפקיד (ID): " . htmlspecialchars($user_to_delete_details['role'])
                    ];

                    if ($user_to_delete_details['role'] == 3) {
                        $stmt_products_to_update = $pdo->prepare("SELECT p_id, p_data FROM product");
                        $stmt_products_to_update->execute();
                        $products_updated_count = 0;

                        while ($product = $stmt_products_to_update->fetch(PDO::FETCH_ASSOC)) {
                            $p_data = json_decode($product['p_data'], true);
                            if ($p_data) { // Ensure JSON was decoded successfully
                                $data_changed = false;

                                if (isset($p_data['uid']) && $p_data['uid'] == $user_id_to_delete) {
                                    $p_data['uid'] = ""; // Set top-level uid to empty string
                                    $data_changed = true;
                                }

                                if (isset($p_data['kanban']['log']) && is_array($p_data['kanban']['log'])) {
                                    foreach ($p_data['kanban']['log'] as $key => $log_entry) {
                                        if (isset($log_entry['uid']) && $log_entry['uid'] == $user_id_to_delete) {
                                            $p_data['kanban']['log'][$key]['uid'] = ""; // Set log entry uid to empty string
                                            $data_changed = true;
                                        }
                                    }
                                }

                                $updated_p_data_json = json_encode($p_data);
                                if ($data_changed && $updated_p_data_json !== $product['p_data']) {
                                    $stmt_update_product = $pdo->prepare("UPDATE product SET p_data = ? WHERE p_id = ?");
                                    $stmt_update_product->execute([$updated_p_data_json, $product['p_id']]);
                                    if ($stmt_update_product->rowCount() > 0) {
                                        $products_updated_count++;
                                    }
                                }
                            }
                        }
                        if ($products_updated_count > 0) {
                            $deleted_records_report[] = ['table' => 'מוצר (product)', 'action' => 'עודכן UID', 'details' => "עודכן UID של בעל מוצר ו/או ביומנים ב- " . $products_updated_count . " מוצרים"];
                        }
                    }

                    if ($user_to_delete_details['role'] == 2) {
                        $stmt_scrum_teams_to_update = $pdo->prepare("SELECT s_id, c_data FROM scrum_team");
                        $stmt_scrum_teams_to_update->execute();
                        $scrum_teams_updated_count = 0;

                        while ($scrum_team = $stmt_scrum_teams_to_update->fetch(PDO::FETCH_ASSOC)) {
                            $c_data = json_decode($scrum_team['c_data'], true);
                            if ($c_data) { // Ensure JSON was decoded successfully
                                $data_changed = false;

                                if (isset($c_data['uid']) && $c_data['uid'] == $user_id_to_delete) {
                                    $c_data['uid'] = ""; // Clear the uid
                                    $fields_to_clear_personal_info = ['name', 'email', 'phone', 'experience_years'];
                                    foreach ($fields_to_clear_personal_info as $field) {
                                        if (isset($c_data[$field])) {
                                            $c_data[$field] = "";
                                        }
                                    }
                                    $data_changed = true;
                                }

                                if (isset($c_data['kanban']['log']) && is_array($c_data['kanban']['log'])) {
                                    foreach ($c_data['kanban']['log'] as $key => $log_entry) {
                                        if (isset($log_entry['uid']) && $log_entry['uid'] == $user_id_to_delete) {
                                            $c_data['kanban']['log'][$key]['uid'] = "";
                                            $data_changed = true;
                                        }
                                    }
                                }

                                $updated_c_data_json = json_encode($c_data);
                                if ($data_changed && $updated_c_data_json !== $scrum_team['c_data']) {
                                    $stmt_update_scrum_team = $pdo->prepare("UPDATE scrum_team SET c_data = ? WHERE s_id = ?");
                                    $stmt_update_scrum_team->execute([$updated_c_data_json, $scrum_team['s_id']]);
                                    if ($stmt_update_scrum_team->rowCount() > 0) {
                                        $scrum_teams_updated_count++;
                                    }
                                }
                            }
                        }
                        if ($scrum_teams_updated_count > 0) {
                            $deleted_records_report[] = ['table' => 'צוות סקראם (scrum_team)', 'action' => 'עודכן c_data', 'details' => "עודכן פרטי רכז סקראם ב- " . $scrum_teams_updated_count . " צוותי סקראם"];
                        }

                        $stmt_scrum = $pdo->prepare("DELETE FROM scrum_team WHERE s_id = ?");
                        $stmt_scrum->execute([$user_id_to_delete]);
                        $scrum_rows_deleted = $stmt_scrum->rowCount();
                        if ($scrum_rows_deleted > 0) {
                            $deleted_records_report[] = ['table' => 'צוות סקראם (scrum_team)', 'action' => 'נמחקו', 'details' => "נמחקו " . $scrum_rows_deleted . " רשומות (s_id תואם UID)"];
                        }
                    }

                    $stmt_member = $pdo->prepare("DELETE FROM member WHERE m_id = ?");
                    $stmt_member->execute([$user_id_to_delete]);
                    $member_rows_deleted = $stmt_member->rowCount();
                    if ($member_rows_deleted > 0) {
                        $deleted_records_report[] = ['table' => 'חבר צוות (member)', 'action' => 'נמחקו', 'details' => "נמחקו " . $member_rows_deleted . " רשומות (m_id תואם UID)"];

                        $stmt_pm = $pdo->prepare("DELETE FROM p_m WHERE m_id = ?");
                        $stmt_pm->execute([$user_id_to_delete]);
                        $pm_rows_deleted = $stmt_pm->rowCount();
                        if ($pm_rows_deleted > 0) {
                            $deleted_records_report[] = ['table' => 'קשר מוצר-חבר (p_m)', 'action' => 'נמחקו', 'details' => "נמחקו " . $pm_rows_deleted . " רשומות עבור m_id " . htmlspecialchars($user_id_to_delete)];
                        }
                    }

                    $stmt_user = $pdo->prepare("DELETE FROM user WHERE uid = ?");
                    $stmt_user->execute([$user_id_to_delete]);

                    if ($stmt_user->rowCount() > 0) {
                        $delete_message = "<p style='color: green;'>המשתמש " . htmlspecialchars($user_to_delete_details['name']) . " נמחק בהצלחה!</p>";
                    } else {
                        $delete_message = "<p style='color: orange;'>שגיאה: המשתמש לא נמצא או כבר נמחק.</p>";
                    }

                    $pdo->commit(); // Commit the transaction if all operations are successful
                } // End of role check else block
            } else {
                $delete_message = "<p style='color: orange;'>שגיאה: המשתמש עם UID " . htmlspecialchars($user_id_to_delete) . " לא נמצא.</p>";
                $pdo->rollBack(); // Rollback if user details not found
            }

        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback on any error
            $delete_message = "<p style='color: red;'>שגיאה במחיקת משתמש: " . htmlspecialchars($e->getMessage()) . "</p>";
            error_log("Error deleting user: " . $e->getMessage()); // Log the error for debugging
        }
    }
}

$users = [];
$roleNames = [
    1 => 'Company Manager',
    2 => 'Scrum Master',
    3 => 'Product Owner',
    4 => 'Team Member'
];

try {
    $stmt = $pdo->query("SELECT uid as id, name, role as role_id FROM user WHERE role = 4 ORDER BY name");
    $fetched_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fetched_users as $u) {
        $u['role_name'] = $roleNames[$u['role_id']] ?? 'Unknown Role';
        $users[] = $u;
    }

} catch (PDOException $e) {
    $delete_message = "<p style='color: red;'>שגיאה בטעינת רשימת משתמשים: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Error fetching users: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>רכז סקראם - מחיקת איש צוות</title>
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

    .error-message {
        color: red;
        margin-bottom: 15px;
        border: 2px solid red;
        border-radius: 10px;
        padding: 10px;
        background-color: #ffe6e6;
        width: fit-content;
        margin-left: auto;
        margin-right: auto;
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
        width: fit-content;
        margin-left: auto;
        margin-right: auto;
    }

    .report-table {
        width: 80%;
        margin: 30px auto;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        direction: rtl;
    }
    .report-table th, .report-table td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: right;
    }
    .report-table th {
        background-color: #266C99;
        color: white;
    }
    .report-table tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    .selection-form {
        display: flex; /* Use flexbox for alignment */
        flex-direction: column; /* Stack label, select, and button vertically */
        align-items: center; /* Center items horizontally */
        gap: 20px; /* Space between elements */
        width: fit-content; /* Adjust width to content */
        margin: 50px auto; /* Center the form on the page */
        padding: 30px;
        border: 1px solid #ccc;
        border-radius: 10px;
        background-color: rgba(255, 255, 255, 0.9); /* Slightly transparent white background */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .selection-form label {
        font-size: 1.2em;
        color: #333;
        margin-bottom: 10px; /* Space below the label */
    }

    .selection-form select {
        padding: 12px 15px;
        border-radius: 8px;
        border: 2px solid #266C99; /* Blue border for focus */
        font-size: 1.1em;
        text-align: right;
        direction: rtl;
        width: 280px; /* Fixed width for consistency */
        max-width: 90%; /* Max width for smaller screens */
        box-sizing: border-box; /* Include padding and border in the element's total width and height */
        background-color: #f9f9f9;
        color: #333;
        appearance: none; /* Remove default arrow */
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23266C99%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13.2-6.5H18.6c-5%200-9.3%201.8-13.2%206.5-3.9%204.6-5.9%2010.5-5.9%2017.1%200%206.5%202%2012.4%205.9%2017.1l118.9%20118.9c3.9%204.6%209.1%207%2014.1%207s10.2-2.4%2014.1-7l118.9-118.9c3.9-4.7%205.9-10.5%205.9-17.1S290.9%2074.1%20287%2069.4z%22%2F%3E%3C%2Fsvg%3E');
        background-repeat: no-repeat, repeat;
        background-position: left 0.7em top 50%, 0 0;
        background-size: 0.6em auto, 100%;
        padding-left: 2.5em; /* Space for the custom arrow */
    }

    .selection-form select:focus {
        outline: none;
        border-color: #0056b3; /* Darker blue on focus */
        box-shadow: 0 0 0 3px rgba(38, 108, 153, 0.25); /* Focus glow */
    }

    .selection-form button.delete-button {
        padding: 12px 25px;
        font-size: 1.2em;
        background-color: #dc3545; /* Red for delete */
        color: white;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        width: 200px; /* Fixed width for consistency */
        max-width: 90%; /* Max width for smaller screens */
    }
    .selection-form button.delete-button:hover {
        background-color: #c82333; /* Darker red on hover */
        transform: translateY(-2px); /* Slight lift effect */
    }
    .selection-form button.delete-button:active {
        background-color: #bd2130; /* Even darker red on active */
        transform: translateY(0);
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
        .report-table {
            border: 0;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
        }
        .report-table thead {
            display: none;
        }
        .report-table tr {
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
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .report-table td {
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
        .report-table td:last-child {
            border-bottom: 0;
        }
        .report-table td::before {
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
        .selection-form {
            width: 95%; /* Make form wider on small screens */
            max-width: 95%;
            padding: 20px;
        }
        .selection-form select,
        .selection-form button.delete-button {
            width: 100%; /* Full width for dropdown and button on small screens */
            max-width: unset; /* Remove max-width constraint */
            margin-left: 0; /* Remove margin-left for full width */
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
<h1>רכז סקראם - מחיקת איש צוות</h1>

<?php if (!empty($delete_message)): ?>
    <div class="message <?php echo strpos($delete_message, 'שגיאה') !== false ? 'error-message' : 'success-message'; ?>">
        <?php echo $delete_message; ?>
    </div>
<?php endif; ?>

<form method="post" class="selection-form">
    <label for="user_to_delete">בחר משתמש למחיקה:</label>
    <select name="user_to_delete" id="user_to_delete" required style="margin-right: 0px">
        <option value="">-- בחר משתמש --</option>
        <?php foreach ($users as $user): ?>
            <option value="<?php echo htmlspecialchars($user['id']); ?>">
                <?php echo htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['role_name']) . ")"; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="delete-button" type="submit">מחק משתמש</button>
</form>

<?php if (!empty($deleted_records_report)): ?>
    <div style="margin-top: 50px;">
        <h2>דו"ח מחיקה</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>טבלאות</th>
                    <th>פעולה</th>
                    <th>פרטים</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deleted_records_report as $record): ?>
                    <tr>
                        <td data-label="טבלה"><?php echo htmlspecialchars($record['table']); ?></td>
                        <td data-label="פעולה"><?php echo htmlspecialchars($record['action']); ?></td>
                        <td data-label="פרטים"><?php echo htmlspecialchars($record['details']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<br><br>
  <footer>
    &copy; 2025 Taskly Team D2
  </footer>

</body>

</html>