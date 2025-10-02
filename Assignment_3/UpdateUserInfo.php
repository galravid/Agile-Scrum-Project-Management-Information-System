<?php
session_start();
include 'db.php'; 

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';
$message = '';
$updated_records_report = []; 
$selected_user_id = null;
$user_data = null;


$roleNames = [
    1 => 'Company Manager',
    2 => 'Scrum Master',
    3 => 'Product Owner',
    4 => 'Team Member'
];
$roleIDs = array_flip($roleNames); // For easy lookup from name to ID

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_user_to_edit'])) {
    $selected_user_id = filter_var($_POST['user_to_edit_id'], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($selected_user_id)) {
        try {
            $stmt = $pdo->prepare("SELECT uid, login, name, role FROM user WHERE uid = ?");
            $stmt->execute([$selected_user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_data) {
                $message = "<p style='color: orange;'>שגיאה: המשתמש עם UID " . htmlspecialchars($selected_user_id) . " לא נמצא.</p>";
                $selected_user_id = null; // Clear selection if user not found
            }
        } catch (PDOException $e) {
            $message = "<p style='color: red;'>שגיאה בטעינת פרטי משתמש: " . htmlspecialchars($e->getMessage()) . "</p>";
            error_log("Error fetching user for update: " . $e->getMessage());
        }
    } else {
        $message = "<p style='color: red;'>שגיאה: לא נבחר משתמש לעריכה.</p>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_details'])) {
    $uid = filter_var($_POST['uid'], FILTER_SANITIZE_NUMBER_INT);
    $login = htmlspecialchars(trim($_POST['login']));
    $name = htmlspecialchars(trim($_POST['name']));
    $new_role = (int)filter_var($_POST['role'], FILTER_SANITIZE_NUMBER_INT);
    $password = $_POST['password']; // Get password, will hash if provided

    if (empty($uid) || empty($login) || empty($name) || !isset($_POST['role'])) { // Check if role is set
        $message = "<p style='color: red;'>שגיאה: כל השדות (למעט סיסמה) נדרשים.</p>";
    } elseif (!array_key_exists($new_role, $roleNames)) {
        $message = "<p style='color: red;'>שגיאה: תפקיד לא חוקי נבחר.</p>";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_old_data = $pdo->prepare("SELECT login, name, role FROM user WHERE uid = ?");
            $stmt_old_data->execute([$uid]);
            $old_user_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

            if (!$old_user_data) {
                $message = "<p style='color: orange;'>שגיאה: המשתמש עם UID " . htmlspecialchars($uid) . " לא נמצא לעדכון.</p>";
                $pdo->rollBack();
                $userNotFound = true;
            } else {
                $update_fields = [];
                $update_values = [];
                $user_report_details = []; // Details specifically for the 'user' table update
                $old_role = (int)$old_user_data['role'];

                if ($old_user_data['login'] !== $login) {
                    $update_fields[] = 'login = ?';
                    $update_values[] = $login;
                    $user_report_details[] = "שם משתמש (Login): מ" . htmlspecialchars($old_user_data['login']) . " ל" . htmlspecialchars($login);
                }

                if ($old_user_data['name'] !== $name) {
                    $update_fields[] = 'name = ?';
                    $update_values[] = $name;
                    $user_report_details[] = "שם: מ" . htmlspecialchars($old_user_data['name']) . " ל" . htmlspecialchars($name);
                }

                if ($old_role !== $new_role) {
                    $update_fields[] = 'role = ?';
                    $update_values[] = $new_role;
                    $user_report_details[] = "תפקיד: מ" . htmlspecialchars($roleNames[$old_role]) . " ל" . htmlspecialchars($roleNames[$new_role]);

                    $was_team_member = ($old_role === 4);
                    $is_now_team_member = ($new_role === 4);

                    $was_scrum_master = ($old_role === 2);
                    $is_now_scrum_master = ($new_role === 2);

                    $was_product_owner = ($old_role === 3);
                    $is_now_product_owner = ($new_role === 3);

                    $member_email = '';
                    $scrum_master_email = '';
                    $product_owner_email = '';

                    if ($was_team_member) {
                        $stmt_get_member_data = $pdo->prepare("SELECT m_data FROM member WHERE m_id = ?");
                        $stmt_get_member_data->execute([$uid]);
                        $member_row = $stmt_get_member_data->fetch(PDO::FETCH_ASSOC);
                        if ($member_row) {
                            $member_json = json_decode($member_row['m_data'], true);
                            if (isset($member_json['email'])) {
                                $member_email = $member_json['email'];
                            }
                        }
                        $stmt_delete_member = $pdo->prepare("DELETE FROM member WHERE m_id = ?");
                        $stmt_delete_member->execute([$uid]);
                        if ($stmt_delete_member->rowCount() > 0) {
                            $updated_records_report[] = ['table' => 'member', 'details' => "המשתמש (UID: " . htmlspecialchars($uid) . ") הוסר מטבלת 'member' עקב שינוי תפקיד מ'חבר צוות'."];
                        } else {
                            $updated_records_report[] = ['table' => 'member', 'details' => "הערה: המשתמש (UID: " . htmlspecialchars($uid) . ") לא נמצא בטבלת 'member' למחיקה (ייתכן שחוסר עקביות בנתונים)."];
                        }
                        $stmt_delete_pm = $pdo->prepare("DELETE FROM p_m WHERE m_id = ?");
                        $stmt_delete_pm->execute([$uid]);
                        if ($stmt_delete_pm->rowCount() > 0) {
                            $updated_records_report[] = ['table' => 'p_m', 'details' => "נמחקו " . $stmt_delete_pm->rowCount() . " רשומות מטבלת 'p_m' עבור משתמש (UID: " . htmlspecialchars($uid) . ") עקב שינוי תפקיד מ'חבר צוות'."];
                        }
                    }

                    if ($was_scrum_master) {
                        $stmt_find_scrum_master = $pdo->query("SELECT s_id, c_data FROM scrum_team");
                        $scrum_master_s_id_to_delete = null;
                        while ($row = $stmt_find_scrum_master->fetch(PDO::FETCH_ASSOC)) {
                            $c_data_json = json_decode($row['c_data'], true);
                            if (isset($c_data_json['uid']) && $c_data_json['uid'] == $uid) {
                                $scrum_master_s_id_to_delete = $row['s_id'];
                                if (isset($c_data_json['email'])) {
                                    $scrum_master_email = $c_data_json['email'];
                                }
                                break;
                            }
                        }
                        if ($scrum_master_s_id_to_delete !== null) {
                            $stmt_delete_scrum_master = $pdo->prepare("DELETE FROM scrum_team WHERE s_id = ?");
                            $stmt_delete_scrum_master->execute([$scrum_master_s_id_to_delete]);
                            if ($stmt_delete_scrum_master->rowCount() > 0) {
                                $updated_records_report[] = ['table' => 'scrum_team', 'details' => "המשתמש (UID: " . htmlspecialchars($uid) . ") הוסר מטבלת 'scrum_team' עקב שינוי תפקיד מ'רכז סקראם'."];
                            } else {
                                $updated_records_report[] = ['table' => 'scrum_team', 'details' => "הערה: המשתמש (UID: " . htmlspecialchars($uid) . ") לא נמצא בטבלת 'scrum_team' למחיקה."];
                            }
                        } else {
                             $updated_records_report[] = ['table' => 'scrum_team', 'details' => "הערה: לא נמצא רכז סקראם עם UID " . htmlspecialchars($uid) . " בטבלת 'scrum_team' למחיקה."];
                        }
                    }

                    if ($was_product_owner) {
                        $stmt_get_products = $pdo->query("SELECT p_id, p_data FROM product");
                        $products_updated = 0; // Counter for reporting

                        while ($product_row = $stmt_get_products->fetch(PDO::FETCH_ASSOC)) {
                            $p_data_json = json_decode($product_row['p_data'], true);

                            if (isset($p_data_json['uid']) && $p_data_json['uid'] == $uid) {
                                if (isset($p_data_json['email'])) {
                                    $product_owner_email = $p_data_json['email'];
                                }

                                $p_data_json['uid'] = ""; // Set to empty string as requested

                                if (isset($p_data_json['product_owner'])) {
                                    unset($p_data_json['product_owner']); // Remove the nested object if it's not needed anymore
                                }


                                $updated_p_data_json = json_encode($p_data_json);

                                $stmt_update_product_data = $pdo->prepare("UPDATE product SET p_data = ? WHERE p_id = ?");
                                $stmt_update_product_data->execute([$updated_p_data_json, $product_row['p_id']]);
                                if ($stmt_update_product_data->rowCount() > 0) {
                                    $products_updated++;
                                }
                            }
                        }
                        if ($products_updated > 0) {
                            $updated_records_report[] = [
                                'table' => 'product',
                                'details' => "עמודת 'uid' של בעל המוצר ב-'" . $products_updated . "' רשומות בטבלת 'product' אופסה לערך ריק עקב שינוי תפקיד מ'בעל מוצר' (UID: " . htmlspecialchars($uid) . ")."
                            ];
                        } else {
                            $updated_records_report[] = [
                                'table' => 'product',
                                'details' => "הערה: המשתמש (UID: " . htmlspecialchars($uid) . ") לא נמצא כ'בעל מוצר' באף רשומה בטבלת 'product'." // This message should now be accurate
                            ];
                        }
                    }

                    if (!$was_team_member && $is_now_team_member) { // User is changing TO be a Team Member
                        $stmt_check_member = $pdo->prepare("SELECT COUNT(*) FROM member WHERE m_id = ?");
                        $stmt_check_member->execute([$uid]);
                        if ($stmt_check_member->fetchColumn() == 0) {
                            $initial_member_email = '';
                            if (!empty($scrum_master_email)) {
                                $initial_member_email = $scrum_master_email;
                            } elseif (!empty($product_owner_email)) { // If they were a PO and had an email
                                $initial_member_email = $product_owner_email;
                            } else {
                                $initial_member_email = $login . '@example.com';
                            }

                            $initial_m_data = json_encode([
                                'email' => $initial_member_email,
                                'role' => $roleNames[$new_role],
                                'skills' => [],
                                'kanban' => [ // Initialize kanban structure
                                    'log' => [],
                                    'ver' => '2025a',
                                    'tasks' => [],
                                    'status' => ["New", "To Do", "Doing", "Test", "Done"],
                                    'process' => [],
                                    'memberid' => (string)$uid // Use current UID for memberid
                                ]
                            ]);
                            $stmt_insert_member = $pdo->prepare("INSERT INTO member (m_id, s_id, m_data) VALUES (?, ?, ?)");
                            $stmt_insert_member->execute([$uid, $uid, $initial_m_data]);
                            $updated_records_report[] = [
                                'table' => 'member',
                                'details' => "המשתמש (UID: " . htmlspecialchars($uid) . ") נוסף לטבלת 'member' עקב שינוי תפקיד ל'חבר צוות' עם מייל: '" . htmlspecialchars($initial_member_email) . "'."
                            ];
                        } else {
                            if (!empty($scrum_master_email) || !empty($product_owner_email) || true) { // Always update if already member
                                $email_to_update = '';
                                if (!empty($scrum_master_email)) {
                                    $email_to_update = $scrum_master_email;
                                } elseif (!empty($product_owner_email)) {
                                    $email_to_update = $product_owner_email;
                                } else {
                                    $email_to_update = $login . '@example.com';
                                }

                                $stmt_get_current_m_data = $pdo->prepare("SELECT m_data FROM member WHERE m_id = ?");
                                $stmt_get_current_m_data->execute([$uid]);
                                $current_m_data_row = $stmt_get_current_m_data->fetch(PDO::FETCH_ASSOC);
                                if ($current_m_data_row) {
                                    $current_m_data = json_decode($current_m_data_row['m_data'], true);
                                    $current_m_data['email'] = $email_to_update;
                                    if (!isset($current_m_data['kanban'])) {
                                        $current_m_data['kanban'] = [
                                            'log' => [], 'ver' => '2025a', 'tasks' => [],
                                            'status' => ["New", "To Do", "Doing", "Test", "Done"],
                                            'process' => [], 'memberid' => (string)$uid
                                        ];
                                    } else {
                                        $current_m_data['kanban']['memberid'] = (string)$uid;
                                    }

                                    $updated_m_data_json = json_encode($current_m_data);

                                    $stmt_update_member_data = $pdo->prepare("UPDATE member SET s_id = ?, m_data = ? WHERE m_id = ?");
                                    $stmt_update_member_data->execute([$uid, $updated_m_data_json, $uid]);
                                    $updated_records_report[] = [
                                        'table' => 'member',
                                        'details' => "המשתמש (UID: " . htmlspecialchars($uid) . ") כבר קיים בטבלת 'member' ומייל ו-s_id עודכנו ל: '" . htmlspecialchars($email_to_update) . "'."
                                    ];
                                }
                            } else {
                                $updated_records_report[] = [
                                    'table' => 'member',
                                    'details' => "הערה: המשתמש (UID: " . htmlspecialchars($uid) . ") כבר קיים בטבלת 'member' ולא בוצעה הוספה כפולה."
                                ];
                            }
                        }
                    } elseif (!$was_scrum_master && $is_now_scrum_master) { // User is changing TO be a Scrum Master
                        $scrum_master_exists = false;
                        $stmt_check_scrum_master = $pdo->query("SELECT s_id, c_data FROM scrum_team");
                        while ($row = $stmt_check_scrum_master->fetch(PDO::FETCH_ASSOC)) {
                            $c_data_json = json_decode($row['c_data'], true);
                            if (isset($c_data_json['uid']) && $c_data_json['uid'] == $uid) {
                                $scrum_master_exists = true;
                                break;
                            }
                        }

                        if (!$scrum_master_exists) {
                            $stmt_max_sid = $pdo->query("SELECT MAX(s_id) FROM scrum_team");
                            $next_s_id = $stmt_max_sid->fetchColumn() + 1;
                            if ($next_s_id < 3) $next_s_id = 3; // Ensure s_id starts from 3 if 1 and 2 are pre-filled

                            $associated_c_id = 0; // Placeholder: Adjust as needed

                            $final_scrum_master_email = '';
                            if (!empty($member_email)) {
                                $final_scrum_master_email = $member_email;
                            } elseif (!empty($product_owner_email)) {
                                $final_scrum_master_email = $product_owner_email;
                            } else {
                                $final_scrum_master_email = $login . '@example.com';
                            }

                            $scrum_master_data = [
                                'uid' => $uid,
                                'name' => $name,
                                'email' => $final_scrum_master_email, // Use the retrieved email or default
                                'phone' => '', // Placeholder for phone
                                'experience_years' => 0, // Placeholder for experience years
                                'kanban' => [
                                    'log' => [],
                                    'ver' => '2025a',
                                    'tasks' => [],
                                    'status' => ["New", "To Do", "Doing", "Test", "Done"],
                                    'process' => []
                                ]
                            ];
                            $c_data_json_string = json_encode($scrum_master_data);

                            $stmt_insert_scrum_master = $pdo->prepare("INSERT INTO scrum_team (s_id, c_id, c_data) VALUES (?, ?, ?)");
                            $stmt_insert_scrum_master->execute([$next_s_id, $associated_c_id, $c_data_json_string]);
                            $updated_records_report[] = [
                                'table' => 'scrum_team',
                                'details' => "המשתמש (UID: " . htmlspecialchars($uid) . ", שם: " . htmlspecialchars($name) . ") נוסף לטבלת 'scrum_team' כרכז סקראם (S_ID: " . htmlspecialchars($next_s_id) . ") עם נתוני מייל ('" . htmlspecialchars($final_scrum_master_email) . "'), טלפון ושנות ניסיון כברירת מחדל."
                            ];
                        } else {
                            $updated_records_report[] = [
                                'table' => 'scrum_team',
                                'details' => "הערה: המשתמש (UID: " . htmlspecialchars($uid) . ") כבר קיים בטבלת 'scrum_team' ולא בוצעה הוספה כפולה."
                            ];
                        }
                    } elseif (!$was_product_owner && $is_now_product_owner) { // User is changing TO be a Product Owner
                        $updated_records_report[] = [
                            'table' => 'product',
                            'details' => "המשתמש (UID: " . htmlspecialchars($uid) . ") הפך ל'בעל מוצר'. ייתכן שיידרש שיבוץ ידני למוצרים."
                        ];
                    }
                }

                if (!empty($password)) {
                    $update_fields[] = 'password = ?';
                    $update_values[] = $password; // Storing the password as plaintext
                    $user_report_details[] = "סיסמה: עודכנה (לא מוצגת).";
                }

                if (!empty($update_fields)) {
                    $update_values[] = $uid; // Add UID for WHERE clause
                    $sql = "UPDATE user SET " . implode(', ', $update_fields) . " WHERE uid = ?";
                    $stmt_update = $pdo->prepare($sql);
                    $stmt_update->execute($update_values);

                    if ($stmt_update->rowCount() > 0 || !empty($user_report_details)) {
                        $updated_records_report[] = [
                            'table' => 'user',
                            'details' => "פרטי משתמש (UID: " . htmlspecialchars($uid) . ") עודכנו. שינויים: " . implode(", ", $user_report_details)
                        ];
                    }
                }

                if (!empty($updated_records_report)) {
                    $message = "<p style='color: green;'>פעולות העדכון הסתיימו. ראה דו\"ח למטה.</p>";
                    $pdo->commit();
                } else {
                    $message = "<p style='color: orange;'>לא בוצעו שינויים בפועל (אולי הנתונים זהים).</p>";
                    $pdo->rollBack(); // Rollback if no changes were actually made to any table
                }
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "<p style='color: red;'>שגיאה בעדכון משתמש: " . htmlspecialchars($e->getMessage()) . "</p>";
            error_log("Error updating user: " . $e->getMessage());
        }
    }
    if (strpos($message, 'בהצלחה') !== false || (!empty($userNotFound) && $userNotFound === true)) {
        $selected_user_id = null;
        $user_data = null;
    } else {
        $stmt = $pdo->prepare("SELECT uid, login, name, role FROM user WHERE uid = ?");
        $stmt->execute([$uid]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $selected_user_id = $uid; // Keep the user selected
    }
}


$users = [];
try {
    $stmt = $pdo->query("SELECT uid as id, name, role as role_id FROM user ORDER BY name");
    $fetched_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fetched_users as $u) {
        $u['role_name'] = $roleNames[$u['role_id']] ?? 'Unknown Role';
        $users[] = $u;
    }

} catch (PDOException $e) {
    $message = "<p style='color: red;'>שגיאה בטעינת רשימת משתמשים: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Error fetching users for selection: " . $e->getMessage());
}
?><!DOCTYPE html>
<html>

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>מנהל המערכת - עדכון פרטי משתמש</title>
  <style>
  .button-group {
  display: flex;
  justify-content: center;
  gap: 10px;
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
    direction: rtl; /* For Hebrew text */
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
    box-sizing: border-box; /* Include padding in element's total width and height */
    text-align: right; /* For Hebrew text alignment */
    direction: rtl; /* For Hebrew text */
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
    background-color: #e0ffe0; /* Light green for success */
    border: 1px solid #a0d9a0;
}

.message.error {
    background-color: #ffe0e0; /* Light red for error */
    border: 1px solid #d9a0a0;
}
.large-input-field {
    width: 100%; 
    max-width: 570px; 
    padding: 10px; 
        margin-bottom: 15px; 
    font-size: 1.1em; 
    border: 1px solid #ccc;
    border-radius: 5px; 
    box-sizing: border-box; 
}

.large-input-field:focus {
    border-color: #007bff; 
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); 
    outline: none; 
}

@media (max-width: 500px) {
    .large-input-field {
        font-size: 1em; 
        padding: 8px; 
    }
}

@media (max-width: 350px) {
    .large-input-field {
        font-size: 0.9em; 
        padding: 6px; 
        margin-bottom: 10px; 
    }
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
        </a></td>
       <td style="text-align: left; font-size: x-large; color: white; padding-right: 0; width: 230px;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>

<h1>מנהל המערכת - עדכון פרטי משתמש</h1>

<?php if (!empty($message)): ?>
    <div class="message <?php echo strpos($message, 'שגיאה') !== false ? 'error' : ''; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($user_data): // Show update form if a user is selected ?>
    <form method="post" class="selection-form">
        <input type="hidden" name="uid" value="<?php echo htmlspecialchars($user_data['uid']); ?>">

        <label for="login">שם משתמש (Login):</label>
		<input type="text" id="login" name="login" value="<?= htmlspecialchars($user_data['login'] ?? '') ?>" class="large-input-field"><br><br>

        <label for="name">שם מלא:</label>
		<input type="text" id="name" name="name" value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" class="large-input-field"><br><br>

        <label for="role">תפקיד:</label>
        <select id="role" name="role" required>
            <?php foreach ($roleNames as $id => $name): ?>
                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($user_data['role'] == $id) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="password">סיסמה חדשה (השאר ריק אם אין שינוי):</label>
		<input type="password" id="password" name="password" class="large-input-field"><br><br>

        <div class="button-group">
            <button type="submit" name="update_user_details">עדכן פרטים</button>
            <button type="button" onclick="window.location.href='UpdateUserInfo.php'">בטל ובחר משתמש אחר</button>
        </div>
    </form>
<?php else: // Show user selection form if no user is selected or after update ?>
    <form method="post" class="selection-form">
        <label for="user_to_edit_id">בחר משתמש לעריכה:</label>
        <select name="user_to_edit_id" id="user_to_edit_id" required>
            <option value="">-- בחר משתמש --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                    <?php echo htmlspecialchars($user['name']) . " (תפקיד: " . htmlspecialchars($user['role_name']) . ")"; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="button-group">
            <button type="submit" name="select_user_to_edit">בחר משתמש</button>
        </div>
    </form>
<?php endif; ?>

<?php if (!empty($updated_records_report)): ?>
    <div style="margin-top: 50px;">
        <h2>דו"ח עדכון</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>טבלה</th>
                    <th>פרטים</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($updated_records_report as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['table']); ?></td>
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