<?php
session_start();
$loggedInUserName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : '';

if (!isset($_SESSION['user_name']) || $_SESSION['user_role'] != '3') {
    header("Location: Login.php");
    exit();
}

?>
<!DOCTYPE html>
<html>

<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>תפריט מקומי - בעל מוצר</title>
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
	<td style="border: 1px solid #FFFFFF; width: 80px; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php">
          <img src="logo.png" alt="תמונת לוגו" style="width: 50px; height: 50px;" align="middle">
        </a>
      </td>
      <td style="border: 1px solid #FFFFFF; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="MainMenu.php" style="color: #FFFFFF; text-decoration: none;">תפריט ראשי</a>
      </td>
      <td style="border: 1px solid #FFFFFF; font-size: x-large; cursor: pointer; color: #FFFFFF; text-align: center;">
        <a href="LocalMenuProductOwner.php" style="color: #FFFFFF; text-decoration: none;">תפריט מקומי</a>
      </td>
<td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;">
	    הנך מחובר <?php echo $loggedInUserName; ?></td>
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

<h1>בעל מוצר - תפריט מקומי</h1>
<br>
  <div class="button-group">
  <button onclick="location.href='ProductOwnerKanbanBoard.php'">עדכון לוח קנבן</button>
  <button onclick="location.href='ProductManagerNewProduct.php'">הוספת מוצר</button>
  <button onclick="location.href='ProductManagerUpdateProduct.php'">עדכון פרטי מוצר</button>
  <button onclick="location.href='ProductManagerDeleteProduct.php'">מחיקת מוצר</button>
  </div>
<br><br>
  <footer>
    © 2025 Taskly Team D2
  </footer>

</body>

</html>
