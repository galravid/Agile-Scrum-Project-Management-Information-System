<?php
session_start();

require_once 'db.php';

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';
$loggedInUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null; // Assume user_id is stored in session

$currentLogin = '';
$currentName = '';
$currentEmail = '';
$currentRole = '';
$currentSkills = ''; // Will be a comma-separated string for the input field
$existingKanbanData = null; // Variable to store existing kanban data

if ($loggedInUserId) {
    try {
        $stmtUser = $pdo->prepare("SELECT `login`, `name` FROM `user` WHERE `uid` = ?");
        $stmtUser->execute([$loggedInUserId]);
        $currentUserData = $stmtUser->fetch();

        if ($currentUserData) {
            $currentLogin = htmlspecialchars($currentUserData['login']);
            $currentName = htmlspecialchars($currentUserData['name']);
        }

        $stmtMember = $pdo->prepare("SELECT `m_data` FROM `member` WHERE `m_id` = ?");
        $stmtMember->execute([$loggedInUserId]);
        $currentMemberData = $stmtMember->fetch();

        if ($currentMemberData && !empty($currentMemberData['m_data'])) {
            $m_data_json = json_decode($currentMemberData['m_data'], true);
            if ($m_data_json) {
                $currentEmail = htmlspecialchars($m_data_json['email'] ?? '');
                $currentRole = htmlspecialchars($m_data_json['role'] ?? '');
                // Convert skills array to a comma-separated string for the input field
                $currentSkills = htmlspecialchars(implode(', ', $m_data_json['skills'] ?? []));
                
                $existingKanbanData = $m_data_json['kanban'] ?? null; // Store kanban if it exists
            }
        }

    } catch (PDOException $e) {
        error_log("Error fetching current user/member details: " . $e->getMessage());
        $message = "אירעה שגיאה בטעינת הפרטים הקיימים. (פרטים טכניים נשמרו בלוג)";
    }
}


if (isset($_POST['update_details'])) {
    // Sanitize and validate input for 'user' table
    $newLogin = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_SPECIAL_CHARS);
    $newName = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $newPassword = $_POST['password']; // Get the password, handle empty string for no change

    // Sanitize input for 'member' m_data JSON
    $newEmail = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $newRole = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
    $newSkillsString = filter_input(INPUT_POST, 'skills', FILTER_SANITIZE_SPECIAL_CHARS);
    $newSkills = array_map('trim', explode(',', $newSkillsString ?? ''));

    // Basic validation for 'user' table fields
    if (empty($newLogin) || empty($newName)) {
        $message = "שם משתמש ושם מלא לא יכולים להיות ריקים.";
    } else {
        try {
            // Start a transaction for atomicity
            $pdo->beginTransaction();

            // Prepare the UPDATE statement for 'user' table
            if (!empty($newPassword)) {
                $stmtUserUpdate = $pdo->prepare("UPDATE `user` SET `login` = ?, `password` = ?, `name` = ?, `lastused` = NOW() WHERE `uid` = ?");
                $stmtUserUpdate->execute([$newLogin, $newPassword, $newName, $loggedInUserId]);
            } else {
                $stmtUserUpdate = $pdo->prepare("UPDATE `user` SET `login` = ?, `name` = ?, `lastused` = NOW() WHERE `uid` = ?");
                $stmtUserUpdate->execute([$newLogin, $newName, $loggedInUserId]);
            }

            $stmtReFetchMember = $pdo->prepare("SELECT `m_data` FROM `member` WHERE `m_id` = ?");
            $stmtReFetchMember->execute([$loggedInUserId]);
            $reFetchedMemberData = $stmtReFetchMember->fetch();
            $existing_m_data = [];
            if ($reFetchedMemberData && !empty($reFetchedMemberData['m_data'])) {
                $existing_m_data = json_decode($reFetchedMemberData['m_data'], true);
                if (!is_array($existing_m_data)) { // Ensure it's an array if json_decode returns non-array
                    $existing_m_data = [];
                }
            }

            // 2. Update only the relevant fields, keeping others (like kanban)
            $existing_m_data['email'] = $newEmail;
            $existing_m_data['role'] = $newRole;
            $existing_m_data['skills'] = $newSkills;
            $updated_m_data_json = json_encode($existing_m_data, JSON_UNESCAPED_UNICODE);
            $stmtMemberUpdate = $pdo->prepare("UPDATE `member` SET `m_data` = ? WHERE `m_id` = ?");
            $stmtMemberUpdate->execute([$updated_m_data_json, $loggedInUserId]);
            $pdo->commit();

            $_SESSION['user_name'] = $newName;
            $loggedInUserName = $newName; // Update the displayed name immediately

            $message = "פרטיך עודכנו בהצלחה!";

            if ($loggedInUserId) {
                $stmtUser = $pdo->prepare("SELECT `login`, `name` FROM `user` WHERE `uid` = ?");
                $stmtUser->execute([$loggedInUserId]);
                $currentUserData = $stmtUser->fetch();
                if ($currentUserData) {
                    $currentLogin = htmlspecialchars($currentUserData['login']);
                    $currentName = htmlspecialchars($currentUserData['name']);
                }

                $stmtMember = $pdo->prepare("SELECT `m_data` FROM `member` WHERE `m_id` = ?");
                $stmtMember->execute([$loggedInUserId]);
                $currentMemberData = $stmtMember->fetch();
                if ($currentMemberData && !empty($currentMemberData['m_data'])) {
                    $m_data_json = json_decode($currentMemberData['m_data'], true);
                    if ($m_data_json) {
                        $currentEmail = htmlspecialchars($m_data_json['email'] ?? '');
                        $currentRole = htmlspecialchars($m_data_json['role'] ?? '');
                        $currentSkills = htmlspecialchars(implode(', ', $m_data_json['skills'] ?? []));
                        // No need to re-assign existingKanbanData here as it's not displayed
                    }
                }
            }


        } catch (PDOException $e) {
            // Rollback the transaction on error
            $pdo->rollBack();
            error_log("Error updating user details: " . $e->getMessage());
            $message = "אירעה שגיאה בעת עדכון הפרטים. אנא נסה שוב מאוחר יותר. (פרטים טכניים נשמרו בלוג)";
        }
    }
}
?><!DOCTYPE html>
<html>

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>איש צוות - תפריט מקומי</title>
<style>
    /* ... (Existing CSS styles remain the same) ... */
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

    body {
        font-family: Arial, sans-serif;
        padding-top:70px;
        margin: 0;
        padding: 0;
        text-align: center;
        background-image: url('background.jpg'); /* Ensure this path is correct */
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

    img.background {
        width:100%;
        height: auto;
        display: block;
    }

    input[type=button], button[type=submit] { /* Added button[type=submit] */
        padding: 25px 50px;
        font-size: 24pt;
        margin: 50px;
        border: none;
        border-radius: 15px;
        background-color: #266C99;
        color: white;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    input[type=button]:hover, button[type=submit]:hover { /* Added button[type=submit] */
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
        .fixed-toolbar table tr {
            display: flex; 
            flex-direction: column;
            height: auto;          }

        .fixed-toolbar table td {
            width: 100% !important; 
            text-align: center;
            padding: 5px 0; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);          }
        
        .fixed-toolbar table td:last-child {
            border-bottom: none;    
        }
        body.has-toolbar {
            padding-top: auto;
        }
    }

    /* Styles for the form elements */
    form {
        width: 80%;
        margin: 20px auto;
        padding: 30px;
        background-color: rgba(255, 255, 255, 0.9); /* Slightly transparent background */
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        text-align: right; /* Align text to the right for RTL */
    }

    form h2 {
        color: #266C99;
        margin-bottom: 25px;
        text-align: center;
    }

    form label {
        display: block; /* Each label on its own line */
        margin-bottom: 8px;
        color: #333;
        font-weight: bold;
        font-size: 1.1em;
    }

    form input[type=text], form input[type=email], form input[type=password] {
        width: calc(100% - 20px); /* Adjust width to account for padding */
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 1em;
        box-sizing: border-box; /* Include padding in the width calculation */
        direction: rtl; /* Ensure input text is RTL */
        text-align: right; /* Ensure input text is RTL */
    }

    form small {
        font-size: 0.9em;
        color: #666;
        margin-top: -15px; /* Adjust spacing */
        margin-bottom: 15px;
        display: block;
        text-align: right;
    }

    form button[type=submit] {
        width: auto; /* Allow button to size naturally based on padding */
        padding: 15px 30px;
        font-size: 1.2em;
        background-color: #266C99;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        margin-top: 20px;
        display: block; /* Make button take full width if desired, or inline-block for specific placement */
        margin-left: auto; /* Align button to the left in RTL if not full width */
        margin-right: auto; /* Center button */
    }

    form button[type=submit]:hover {
        background-color: #1f5a80;
    }

    form > div { /* The div wrapping labels and inputs */
        display: flex;
        flex-direction: column;
        gap: 15px; /* Space between form elements */
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
        <a href="LocalMenuTeamMember.php" style="color: #FFFFFF; text-decoration: none;">תפריט מקומי</a>
      </td>
<td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;">
    הנך מחובר <?php echo $loggedInUserName; ?></td>
        <td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;"><?php
            $file_path = 'NameAndID.txt'; // Make sure this file exists and contains the correct data
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
    &nbsp;</td>
    </tr>
  </table>
</div>
<br><br><br><br><br><br><br>

<h1>איש צוות - עדכון פרטים אישיים</h1>

<?php if (isset($message)): ?>
    <p style="color: green; font-weight: bold;"><?php echo $message; ?></p>
<?php endif; ?>

<form method="post">
    <h2>עדכן פרטים:</h2>
    <div style="text-align: right; width: 100%;">
        <label for="login">שם משתמש חדש:</label>
        <input type="text" id="login" name="login" value="<?php echo $currentLogin; ?>" required>
        
        <label for="name">שם מלא חדש:</label>
        <input type="text" id="name" name="name" value="<?php echo $currentName; ?>" required>

        <label for="email">אימייל חדש:</label>
        <input type="email" id="email" name="email" value="<?php echo $currentEmail; ?>">

        <label for="role">תפקיד חדש:</label>
        <input type="text" id="role" name="role" value="<?php echo $currentRole; ?>">

        <label for="skills">כישורים חדשים (מופרדים בפסיקים):</label>
        <input type="text" id="skills" name="skills" value="<?php echo $currentSkills; ?>" placeholder="לדוגמה: HTML, CSS, JavaScript">

        <label for="password">סיסמה חדשה:</label>
        <input type="password" id="password" name="password" value="" placeholder="השאר ריק אם אינך רוצה לשנות" autocomplete="new-password">
        <small style="display: block; text-align: right; color: #555; margin-bottom: 10px;">הכנס סיסמה חדשה אם ברצונך לשנותה, אחרת השאר ריק.</small>

        <button type="submit" name="update_details">עדכן פרטים</button>
    </div>
</form>

<br><br>
<footer>
    © 2025 Taskly Team D2
</footer>

</body>

</html>