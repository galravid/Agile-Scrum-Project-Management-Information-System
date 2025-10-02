<?php
session_start();
$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

if (!isset($_SESSION['user_name'])) {
    header("Location: Login.php");
    exit();
}

$localMenuPage = '';
switch ($userRole) {
    case '1': // Company Manager
        $localMenuPage = 'LocalMenuCompanyManager.php'; 
        break;
    case '2': // Scrum Master
        $localMenuPage = 'LocalMenuScrumMaster.php';
        break;
    case '3': // Product Owner
        $localMenuPage = 'LocalMenuProductOwner.php'; 
        break;
    case '4': // Team Member
        $localMenuPage = 'LocalMenuTeamMember.php'; 
        break;
    default:
        $localMenuPage = '#';
        break;
}

?>
<!DOCTYPE html>
<html>
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>עלינו</title>
  <style>
      body {
	  font-family: Arial, sans-serif;
	  margin: 0;
	  padding: 0;
	  text-align: center;
	  background-image: url('background.jpg');   background-size: cover;
	  background-repeat: no-repeat;
	  background-position: center;
	}
    table.top-bar {
      width: 100%;
      height: 70px;
      background-color: #266C99;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    p {
      font-size: 20pt;
      border: 2px solid #266C99;
      border-radius: 30px;
      padding: 30px;
      margin: 40px auto;
      width: 70%;
      background-color: #ffffff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    h1 { 	 
      font-size: 50px;
      margin-top: 20px;
    }
    h2 {
      font-size: 30pt;
    }
    input[type=button] {
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
    input[type=button]:hover {
      background-color: #1f5a80;
    }
       main {
      max-width: 800px;
      margin: 2rem auto;
      padding: 2rem;
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    .my-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 2rem;
      direction: rtl;
      background-color: #ffffff;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .my-table th, .my-table td {
      border: 1px solid #ccc;
      padding: 15px;
      text-align: right;
      font-size: 20px;
    }
    .my-table th {
      background-color: #266C99;
      color: white;
      font-weight: bold;
    }
    .my-table tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    .my-table tbody tr:nth-child(odd) {
      background-color: #ffffff;
    }
     main {
      padding: 1rem;
    }
    .my-table {
      font-size: 0.9rem;
    }
	.self_img {
	  width: 200px;
	  height: 200px;
	  object-fit: cover;
	  object-position: center 0%;
	  border-radius: 20px;
	  border: 2px solid #266C99;
	  box-sizing: border-box;
	}
	.my_table_p {
	  border: 1px solid #266C99;
	  padding: 5px;
	  text-align: right;
	  font-size: 20px;
	  text-align: center;
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
  .team-table {
    display: block;
    width: 100%;
  }

  .team-table tr {
    display: block;
  }

  .team-table td {
    display: block;
    width: 100%;
    text-align: center;
    margin-bottom: 30px;
  }

  .self_img {
    width: 60%;
    max-width: 250px;
    height: auto;
  }

  .my_table_p {
    width: 80%;
    margin: 0 auto;
    font-size: 16px;
  }
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

    </style>
</head>

<body class="has-toolbar" dir="rtl">

<div class="fixed-toolbar">
  <table style="height: 70px; background-color: #266C99; width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 80px; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php">
          <img src="logo.png" alt="תמונת לוגו" style="width: 50px; height: 50px;" align="middle">
        </a>
      </td>
      <td style="font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php" style="color: #FFFFFF; text-decoration: none;">תפריט ראשי</a>
      </td>
      <td style="font-size: x-large; cursor: pointer; text-align: center; color: #FFFFFF;">
            <a href="<?php echo $localMenuPage; ?>" style="color: #FFFFFF; text-decoration: none;">
	            תפריט מקומי
            </a></td>
	<td style="text-align: center; font-size: x-large; color: white; padding-right: 0;">
	   הנך מחובר <?php echo $loggedInUserName; ?></td> 
	       <td style="text-align: center; font-size: x-large; color: white; padding-right: 0;"><?php
            $file_path = 'NameAndID.txt';
            $name_and_id = @file_get_contents($file_path);
            echo htmlspecialchars($name_and_id);
            ?>
	   &nbsp;</td>
    </tr>
  </table>
</div>

  <header>
  <br><br><br><br><br><br><br>
    <h1>עלינו</h1>
  </header>
<table class="team-table" style="width: 100%">
	<tr>
			<td>
				<img src="Roy.png" class="self_img">
				<p class="my_table_p" style="width: 45%">רוי פלס<br>סטודנט להנדסת תעשייה וניהול שנה ג'<br>בן 25</p>
			</td> 
			<td>
				<img src="ido.png" class="self_img">
				<p class="my_table_p" style="width: 45%">עידו לייר<br>סטודנט להנדסת תעשייה וניהול שנה ג'<br>בן 25</p>
			</td>
			<td>
				<img src="gal.png" class="self_img">
				<p class="my_table_p" style="width: 45%">גל רביד<br>סטודנט להנדסת תעשייה וניהול שנה ג'<br>בן 27</p>
			</td>
			<td>
				<img src="dania.png" class="self_img">
				<p class="my_table_p" style="width: 45%">דניה קמחי<br>סטודנטית להנדסת תעשייה וניהול שנה ג'<br>בת 25</p>
			</td> 			
		</tr>
</table>
  <footer>
    &copy; 2025 Taskly Team D2
  </footer>

</body>
</html>
