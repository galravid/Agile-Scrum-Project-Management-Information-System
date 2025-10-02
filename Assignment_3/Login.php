<?php
session_start();
$servername = "localhost"; 
$db_username = "root"; 
$db_password = ""; 
$dbname = "aspm";
$errorMessage = null; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars($_POST["username"]);
    $password = htmlspecialchars($_POST["password"]);
    $role = htmlspecialchars($_POST["role"]);
    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        $errorMessage = "שגיאה בחיבור למסד הנתונים. אנא נסה שוב מאוחר יותר.";
        $conn->close();
    } else { // Only proceed with DB operations if connection is successful
        $stmt = $conn->prepare("SELECT uid, name, role FROM user WHERE login = ? AND password = ? AND role = ?");
        $stmt->bind_param("ssi", $username, $password, $role); // 'ssi' means string, string, integer
        $stmt->execute();
        $stmt->bind_result($userId, $userName, $userRoleFromDB);
        $stmt->fetch();
        $stmt->close();
        if ($userId !== null) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $userName;
            $_SESSION['user_role'] = $userRoleFromDB;

            $update_stmt = $conn->prepare("UPDATE user SET lastused = NOW() WHERE uid = ?");
            $update_stmt->bind_param("i", $userId);
            $update_stmt->execute();
            $update_stmt->close();

            $conn->close();

            switch ($role) { // Use the role from the form, or $userRoleFromDB if you prefer strict matching
                case '1':
                    header("Location: LocalMenuCompanyManager.php");
                    break;
                case '2':
                    header("Location: LocalMenuScrumMaster.php");
                    break;
                case '3':
                    header("Location: LocalMenuProductOwner.php");
                    break;
                case '4':
                    header("Location: LocalMenuTeamMember.php");
                    break;
                default:
                    $errorMessage = "תפקיד לא חוקי נבחר.";
                    break;
            }
            if ($errorMessage === null) { // Only exit if a redirection actually happened
                exit();
            }

        } else {
            $errorMessage = "שם משתמש, סיסמה או תפקיד שגויים. אנא נסה שוב.";
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>התחברות</title>
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
	h2 {
	font-size: 30pt;
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
    
    </style>
</head>

<body class="has-toolbar" dir="rtl">

<div class="fixed-toolbar">
  <table style="height: 70px; background-color: #266C99; width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 80px; font-size: x-large; color: #FFFFFF; text-align: center;">
      <a href="index.php">
          <img src="logo.png" alt="תמונת לוגו" style="width: 50px; height: 50px;" align="middle">
      </td>
      
       <td style="text-align: center; font-size: x-large; color: white; padding-right: 0;">
	   &nbsp;</td>
      
       <td style="text-align: center; font-size: x-large; color: white; padding-right: 0; width: 230px;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>

<h1>התחברות למערכת</h1>

<?php if ($errorMessage):  ?>
    <p class="error-message"><?php echo $errorMessage; ?></p>
<?php endif; ?>

<form method="post" style="max-width: 400px; margin: 0 auto;">
  <div style="margin-bottom: 20px; text-align: right;">
    <label for="username" style="font-size: 18pt;">שם משתמש:</label><br>
    <input type="text" id="username" name="username" required
	 style="width: 80%; padding: 12px; font-size: 16pt; border-radius: 10px; border: 1px solid #ccc;">
  </div>
  <div style="margin-bottom: 30px; text-align: right;">
    <label for="password" style="font-size: 18pt;">סיסמה:</label><br>
    <input type="password" id="password" name="password" required
	 style="width: 80%; padding: 12px; font-size: 16pt; border-radius: 10px; border: 1px solid #ccc;">
  </div>
  <div style="margin-bottom: 30px; text-align: right;">
  <label for="role" style="font-size: 18pt;">תפקיד:</label><br>
  <select id="role" name="role" required
	 style="width: 90%; padding: 12px; font-size: 16pt; border-radius: 10px; border: 1px solid #ccc;">
    <option value="" disabled selected>בחר תפקיד</option>
    <option value="1">1- מנהל חברה</option>
    <option value="2">2- רכז סקראם</option>
    <option value="3">3- בעל מוצר</option>
    <option value="4">4- איש צוות</option>
  </select>
</div>
<div class="button-group">
  <button type="submit">התחבר</button>
</div>
</form>
<br><br>
  <footer>
    &copy; 2025 Taskly Team D2
  </footer>

</body>

</html>