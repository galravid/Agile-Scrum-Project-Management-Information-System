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
    $stmt = $pdo->query("SELECT uid as id, name, role as role_id FROM user ORDER BY name");
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
<title>מנהל המערכת - מחיקת משתמש</title>
  <style>
  .button-group {
  display: flex;
  justify-content: center;
  gap: 40px;
  margin-top: 40px;
  flex-wrap: wrap;
}

@media screen and (max-width: 500px) {
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

.button-group button:not(.delete-button) {

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

.delete-button {
  width: 200px;
  font-size: 20px;
  padding: 18px 32px;
  background-color: #cc3333;
  color: white;
  border: none;
  border-radius: 15px;
  cursor: pointer;
  transition: background-color 0.3s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
  text-align: center;
}

.delete-button:hover {
  background-color: #a82828;
}

  body {
  font-family: Arial, sans-serif;
  padding-top:70px;
  margin: 0;
  padding: 0;
  text-align: center;
  background-image: url('background.jpg');
  background-size: cover;
  background-repeat: no-repeat;
  background-position: center;
}
    table {
      background-color:transparent;
      width: 100%;
      height: 70px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
    h1 {
      color: #333;
      margin-top: 20px;
    }
	
	.image-wrapper {
  	width: 100%;
  	height: 500px;
  	overflow: hidden;
	}

    img.background
    {
    width:100%;
    height: auto;
    display: block;
    }

    input[type=button] {
      padding: 25px 50px;
      font-size: 24pt;
      margin: 50px;
      border: none;
      border-radius: 15px;
      background-color:  #266C99;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    input[type=button]:hover {
      background-color: #1f5a80;
    }
    
    .fixed-toolbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 999;
  }
  
  body.has-toolbar {
  padding-top: 70px;
}
    @media screen and (max-width: 350px) {
        form {
            max-width: 90% !important;
        }
        input[type=text], input[type=password], select {
            padding: 8px !important;
            font-size: 14pt !important;
        }
        label {
            font-size: 16pt !important;
        }
        body.has-toolbar {
            padding-top: auto;
        }
    }
    .data-table, .report-table {
    width: 80%;
    margin: 20px auto;
    border-collapse: collapse;
    background-color: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    direction: rtl;
}

.data-table th, .data-table td, .report-table th, .report-table td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: center;
}

.data-table th, .report-table th {
    background-color: #266C99;
    color: white;
    font-weight: bold;
}

.data-table tbody tr:nth-child(even), .report-table tbody tr:nth-child(even) {
    background-color: #f2f2f2;
}

.data-table tbody tr:hover, .report-table tbody tr:hover {
    background-color: #ddd;
}

.selection-form {
    max-width: 600px;
    margin: 30px auto;
    padding: 20px;
    background-color: #ffffff;
    border-radius: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.selection-form label {
    font-size: 1.2em;
    display: block;
    margin-bottom: 15px;
    color: #333;
}

.selection-form select {
    width: calc(100% - 20px);
    padding: 10px;
    margin-bottom: 20px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box; 
    text-align: right;
    direction: rtl; 
}

.selection-form button {
    width: 100%;
    padding: 15px;
    font-size: 1.2em;
}

.message {
    font-size: 1.2em;
    margin-top: 20px;
    padding: 15px;
    border-radius: 10px;
    color: #333;
    background-color: #e0ffe0; 
    border: 1px solid #a0d9a0;
}

.message.error {
    background-color: #ffe0e0; 
    border: 1px solid #d9a0a0;
}
    </style>
</head>

<body class="has-toolbar" dir="rtl">

<div class="fixed-toolbar">
  <table style="height: 70px; background-color: #266C99; width: 100%; border-collapse: collapse;">
    <tr>
	<td style="width: 40px; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: right;">
        <a href="index.php">
          <img src="logo.png" alt="תמונת לוגו" style="width: 50px; height: 50px;" align="middle">
        </a>
       <td style="text-align: left; font-size: x-large; color: white; padding-right: 0; width: 230px;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>

<h1>מנהל המערכת - מחיקת משתמש</h1>

<?php if (!empty($delete_message)): ?>
    <div class="message <?php echo strpos($delete_message, 'שגיאה') !== false ? 'error' : ''; ?>">
        <?php echo $delete_message; ?>
    </div>
<?php endif; ?>

<form method="post" class="selection-form">
    <label for="user_to_delete">בחר משתמש למחיקה:</label>
    <select name="user_to_delete" id="user_to_delete" required>
        <option value="">-- בחר משתמש --</option>
        <?php foreach ($users as $user): ?>
            <option value="<?php echo htmlspecialchars($user['id']); ?>">
                <?php echo htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['role_name']) . ")"; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="button-group">
        <button class="delete-button" type="submit">מחק משתמש</button>
    </div>
</form>

<?php if (!empty($deleted_records_report)): ?>
    <div style="margin-top: 50px;">
        <h2>דו"ח מחיקה</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>טבלה</th>
                    <th>פעולה</th>
                    <th>פרטים</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deleted_records_report as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['table']); ?></td>
                        <td><?php echo htmlspecialchars($record['action']); ?></td>
                        <td><?php echo htmlspecialchars($record['details']); ?></td>
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