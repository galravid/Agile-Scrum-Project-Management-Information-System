<?php
session_start();
include 'db.php';

$message = '';
$newlyRegisteredUser = null;
$showUsersTable = false;

$teamMemberRoleID = 4;

$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_team_member'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $login = htmlspecialchars(trim($_POST['login']));
    $password = $_POST['password'];
    $email = htmlspecialchars(trim($_POST['email']));
    $role_text = htmlspecialchars(trim($_POST['role']));
    $skills = isset($_POST['skills']) ? $_POST['skills'] : [];

    if (empty($name) || empty($login) || empty($password) || empty($email) || empty($role_text)) {
        $message = "<p style='color: red;'>שגיאה: כל השדות (שם מלא, שם משתמש, סיסמה, אימייל, תפקיד) נדרשים.</p>";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_check_login = $pdo->prepare("SELECT COUNT(*) FROM user WHERE login = ?");
            $stmt_check_login->execute([$login]);
            if ($stmt_check_login->fetchColumn() > 0) {
                $message = "<p style='color: orange;'>שגיאה: שם המשתמש " . htmlspecialchars($login) . " כבר קיים. אנא בחר שם משתמש אחר.</p>";
                $pdo->rollBack();
            } else {
                $plain_password = $password;

                $stmt_max_uid = $pdo->query("SELECT MAX(uid) FROM user");
                $next_uid = $stmt_max_uid->fetchColumn() + 1;
                if ($next_uid < 1) $next_uid = 1;

                $stmt_insert_user = $pdo->prepare("INSERT INTO user (uid, login, name, role, password, lastused) VALUES (?, ?, ?, ?, ?, current_timestamp())");
                $stmt_insert_user->execute([$next_uid, $login, $name, $teamMemberRoleID, $plain_password]);

                if ($stmt_insert_user->rowCount() > 0) {
                    $member_data_array = [
                        'email' => $email,
                        'role_text' => $role_text,
                        'skills' => $skills,
                        'kanban' => [
                            'log' => [],
                            'ver' => '2025a',
                            'tasks' => [],
                            'status' => ["New", "To Do", "Doing", "Test", "Done"],
                            'process' => [],
                            'memberid' => $next_uid
                        ]
                    ];
                    $member_data = json_encode($member_data_array, JSON_UNESCAPED_UNICODE);

                    $stmt_insert_member = $pdo->prepare("INSERT INTO member (m_id, s_id, m_data) VALUES (?, ?, ?)");
                    $stmt_insert_member->execute([$next_uid, $next_uid, $member_data]);

                    if ($stmt_insert_member->rowCount() > 0) {
                        $message = "<p style='color: green;'>חבר צוות חדש (UID: " . htmlspecialchars($next_uid) . ") נרשם בהצלחה!</p>";

                        $stmt_new_user = $pdo->prepare("SELECT u.uid, u.login, u.name, u.role, u.password, u.lastused, m.m_data FROM user u LEFT JOIN member m ON u.uid = m.m_id WHERE u.uid = ?");
                        $stmt_new_user->execute([$next_uid]);
                        $newlyRegisteredUser = $stmt_new_user->fetch(PDO::FETCH_ASSOC);

                        if ($newlyRegisteredUser && isset($newlyRegisteredUser['m_data'])) {
                            $newlyRegisteredUser['m_data'] = json_decode($newlyRegisteredUser['m_data'], true);
                        }

                        $pdo->commit();
                        $showUsersTable = true;
                    } else {
                        $message = "<p style='color: red;'>שגיאה: המשתמש נרשם אך לא ניתן לרשום לטבלת חברים. אנא נסה שוב.</p>";
                        $pdo->rollBack();
                    }
                } else {
                    $message = "<p style='color: red;'>שגיאה: לא ניתן לרשום את חבר הצוות. אנא נסה שוב.</p>";
                    $pdo->rollBack();
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "<p style='color: red;'>שגיאה ברישום משתמש: " . htmlspecialchars($e->getMessage()) . "</p>";
            error_log("Error registering user: " . $e->getMessage());
        }
    }
}

$users = [];
$roleNames = [
    1 => 'מנהל חברה',
    2 => 'רכז סקראם',
    3 => 'בעל מוצר',
    4 => 'חבר צוות'
];

if ($showUsersTable) {
    try {
        $stmt = $pdo->query("SELECT uid, login, name, role, password, lastused FROM user ORDER BY uid ASC");
        $fetched_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fetched_users as $u) {
            $u['role_name'] = $roleNames[$u['role']] ?? 'תפקיד לא ידוע';
            $users[] = $u;
        }
    } catch (PDOException $e) {
        $message .= "<p style='color: red;'>שגיאה בטעינת רשימת משתמשים: " . htmlspecialchars($e->getMessage()) . "</p>";
        error_log("Error fetching users for display: " . $e->getMessage());
    }
}
?><!DOCTYPE html>
<html>

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>הרשמה לאיש צוות</title>
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
        @media screen and (max-width: 500px) {
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
    .users-table {
    width: 80%;
    margin: 20px auto;
    border-collapse: collapse;
    background-color: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    direction: rtl;
}

.users-table th, .users-table td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: center;
}

.users-table th {
    background-color: #266C99;
    color: white;
    font-weight: bold;
}

.users-table tbody tr:nth-child(even) {
    background-color: #f2f2f2;
}

.users-table tbody tr:hover {
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
    border: 1px solid #d9a0d0;
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

@media screen and (max-width: 500px) {
    .users-table {
        border: 0;
    }

    .users-table thead {
        display: none;
    }

    .users-table tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        background-color: white;
        padding: 10px;
    }

    .users-table td {
        display: block;
        text-align: right !important;
        border-bottom: 1px solid #eee;
        padding: 8px 5px;
        position: relative;
        padding-right: 100px;
    }

    .users-table td:last-child {
        border-bottom: 0;
    }

    .users-table td::before {
        content: attr(data-label);
        position: absolute;
        right: 5px;
        width: 95px;
        font-weight: bold;
        color: #266C99;
        text-align: right;
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

<h1>הרשמה לאיש צוות</h1>

<?php if (!empty($message)): ?>
        <div class="message-box <?= (strpos($message, 'בהצלחה') !== false) ? 'success' : ((strpos($message, 'שגיאה') !== false) ? 'error' : 'warning'); ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div style="margin-bottom: 20px;">
            <label for="name" style="font-size: 18pt;">שם מלא:</label><br>
            <input type="text" id="name" name="name" required class="large-input-field">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="email" style="font-size: 18pt;">אימייל:</label><br>
            <input type="email" id="email" name="email" required class="large-input-field">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="role" style="font-size: 18pt;">תפקיד:</label><br>
            <input type="text" id="role" name="role" required class="large-input-field">
        </div>

        <div style="margin-bottom: 30px; ">
            <div style="text-align: center">
            <label style="font-size: 18pt;">יכולות:</label><br>
            </div>
            <div class="skills-checkbox-container">
                <label>
                    <input type="checkbox" name="skills[]" value="HTML">
                    HTML
                    <input type="checkbox" name="skills[]" value="CSS">
                    CSS
                    <input type="checkbox" name="skills[]" value="JavaScript">
                    JavaScript
                    <input type="checkbox" name="skills[]" value="PHP">
                    PHP
                    <input type="checkbox" name="skills[]" value="SQL">
                    SQL
                    <input type="checkbox" name="skills[]" value="REST APIs">
                    REST APIs
                    <input type="checkbox" name="skills[]" value="Figma">
                    Figma
                    <input type="checkbox" name="skills[]" value="AdobeXD">
                    AdobeXD
                    <input type="checkbox" name="skills[]" value="Test Automation">
                    Test Automation
                    <input type="checkbox" name="skills[]" value="Selenium">
                    Selenium
                    <input type="checkbox" name="skills[]" value="Bug Tracking">
                    Bug Tracking
                    <input type="checkbox" name="skills[]" value="CI/CD">
                    CI/CD
                    <input type="checkbox" name="skills[]" value="Docker">
                    Docker
                    <input type="checkbox" name="skills[]" value="GitHubActions">
                    GitHubActions
                    <input type="checkbox" name="skills[]" value="React">
                    React
                    <input type="checkbox" name="skills[]" value="NodeJS">
                    NodeJS
                    <input type="checkbox" name="skills[]" value="MongoDB">
                    MongoDB
                    <input type="checkbox" name="skills[]" value="Express">
                    Express
                    <input type="checkbox" name="skills[]" value="Agile">
                    Agile
                    <input type="checkbox" name="skills[]" value="Scrum">
                    Scrum
                    <input type="checkbox" name="skills[]" value="Leadership">
                    Leadership
                    <input type="checkbox" name="skills[]" value="Excel">
                    Excel
                    <input type="checkbox" name="skills[]" value="PowerBI">
                    PowerBI
                </label>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="login" style="font-size: 18pt;">שם משתמש:</label><br>
            <input type="text" id="login" name="login" required class="large-input-field">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="password" style="font-size: 18pt;">סיסמה:</label><br>
            <input type="password" id="password" name="password" required class="large-input-field">
        </div>

        <div class="button-group">
            <button type="submit" name="register_team_member">הירשם</button>
        </div>
    </form>

    <?php if ($newlyRegisteredUser): ?>
        <h2>פרטי המשתמש החדש שנרשם:</h2>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>שם משתמש</th>
                        <th>שם מלא</th>
                        <th>סיסמה</th>
                        <th>תפקיד כללי</th>
                        <th>אימייל</th>
                        <th>תפקיד ספציפי</th>
                        <th>יכולות</th>
                        <th>תאריך</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-label="UID"><?= htmlspecialchars($newlyRegisteredUser['uid']) ?></td>
                        <td data-label="שם משתמש"><?= htmlspecialchars($newlyRegisteredUser['login']) ?></td>
                        <td data-label="שם מלא"><?= htmlspecialchars($newlyRegisteredUser['name']) ?></td>
                        <td data-label="סיסמה"><?= htmlspecialchars($newlyRegisteredUser['password']) ?></td>
                        <td data-label="תפקיד כללי"><?= htmlspecialchars($roleNames[$newlyRegisteredUser['role']] ?? 'תפקיד לא ידוע') ?></td>
                        <td data-label="אימייל"><?= htmlspecialchars($newlyRegisteredUser['m_data']['email'] ?? 'N/A') ?></td>
                        <td data-label="תפקיד ספציפי"><?= htmlspecialchars($newlyRegisteredUser['m_data']['role_text'] ?? 'N/A') ?></td>
                        <td data-label="יכולות"><?= htmlspecialchars(implode(', ', $newlyRegisteredUser['m_data']['skills'] ?? [])) ?></td>
                        <td data-label="תאריך"><?= htmlspecialchars($newlyRegisteredUser['lastused'] ?? 'N/A') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($showUsersTable): ?>
        <h2>טבלת כלל המשתמשים המעודכנת (כולל חברי צוות):</h2>
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>שם משתמש</th>
                        <th>שם מלא</th>
                        <th>סיסמה</th>
                        <th>תפקיד כללי</th>
                        <th>תאריך</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="UID"><?= htmlspecialchars($user['uid']) ?></td>
                                <td data-label="שם משתמש"><?= htmlspecialchars($user['login']) ?></td>
                                <td data-label="שם מלא"><?= htmlspecialchars($user['name']) ?></td>
                                <td data-label="סיסמה"><?= htmlspecialchars($user['password']) ?></td>
                                <td data-label="תפקיד כללי"><?= htmlspecialchars($user['role_name']) ?></td>
                                <td data-label="תאריך"><?= htmlspecialchars($user['lastused']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">אין משתמשים להצגה.</td>
                        </tr>
                    <?php endif; ?>
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
