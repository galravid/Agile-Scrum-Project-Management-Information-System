<?php
session_start();
include 'db.php';

if (!isset($_GET['company_id'])) {
    die("No company selected.");
}

$company_id = intval($_GET['company_id']);
$sort = $_GET['sort'] ?? 'product';
$order = $_GET['order'] ?? 'asc';

$sort_column = ($sort === 'user') ? 'user_name' : 'product_name';
$order_direction = ($order === 'desc') ? 'DESC' : 'ASC';
$next_order = ($order === 'desc') ? 'asc' : 'desc';

$stmt = $pdo->prepare("SELECT c_data FROM company WHERE c_id = ?");
$stmt->execute([$company_id]);
$c_data = json_decode($stmt->fetchColumn(), true);
$company_name = $c_data['name'] ?? 'חברה ללא שם';

$query = "
    SELECT 
        u.name AS user_name,
        JSON_UNQUOTE(JSON_EXTRACT(m.m_data, '$.email')) AS email,
        JSON_UNQUOTE(JSON_EXTRACT(m.m_data, '$.role')) AS user_role,
        JSON_UNQUOTE(JSON_EXTRACT(m.m_data, '$.skills')) AS skills,
        JSON_UNQUOTE(JSON_EXTRACT(p.p_data, '$.name')) AS product_name,
        JSON_UNQUOTE(JSON_EXTRACT(p.p_data, '$.description')) AS description,
        JSON_UNQUOTE(JSON_EXTRACT(p.p_data, '$.version')) AS version,
        JSON_UNQUOTE(JSON_EXTRACT(p.p_data, '$.release_date')) AS release_date,
        JSON_UNQUOTE(JSON_EXTRACT(p.p_data, '$.status')) AS status
    FROM product p
    JOIN p_m ON p.p_id = p_m.p_id
    JOIN member m ON m.m_id = p_m.m_id
    JOIN user u ON u.uid = m.m_id
    WHERE p.c_id = ?
    ORDER BY $sort_column $order_direction, user_name ASC, product_name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$company_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$loggedInUserName = $_SESSION['user_name'] ?? '';
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>דוחות עבור <?php echo htmlspecialchars($company_name); ?></title>
    <style>
    /* General Styles */
    body {
        font-family: Arial, sans-serif;
        padding-top: 70px; /* Space for fixed toolbar */
        margin: 0;
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

    .button-group {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin-top: 40px;
        flex-wrap: wrap;
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
        border: 1px solid #FFFFFF; /* Ensure borders are present */
        color: white;
        font-size: x-large;
        text-align: center;
    }

    .report-data-table { /* Added specific class */
        width: 95%;
        margin: 30px auto;
        border-collapse: collapse;
        background: white; /* Ensure background is white for readability */
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .report-data-table th, .report-data-table td { /* Target specific class */
        border: 1px solid #ccc;
        padding: 12px;
        text-align: center;
    }

    .report-data-table th { /* Target specific class */
        background-color: #266C99;
        color: white;
    }

    .report-data-table th a { /* Target specific class */
        color: white;
        text-decoration: none;
    }

    .report-data-table th:hover { /* Target specific class */
        background-color: #1f5a80;
    }

    .report-data-table tr:nth-child(even) { /* Target specific class */
        background-color: #f2f2f2;
    }

    .back-btn {
        margin-top: 30px;
        margin-bottom: 50px; /* Add some space below the button */
    }

    .back-btn a {
        padding: 12px 25px;
        background-color: #266C99;
        color: white;
        border-radius: 10px;
        text-decoration: none;
    }

    .back-btn a:hover {
        background-color: #1f5a80;
    }

    @media screen and (max-width: 500px) {
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
        
        .report-data-table { /* This targets the main data table */
            border: 0; /* Remove table borders */
        }

        .report-data-table thead {
            display: none; /* Hide table headers */
        }

        .report-data-table tr {
            display: block; /* Make each row a block, acting as a card */
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            background-color: white;
            padding: 10px;
        }

        .report-data-table td {
            display: block; /* Make each cell a block */
            text-align: right !important; /* Align text to the right within the card */
            border-bottom: 1px solid #eee; /* Separator between "fields" in a card */
            padding: 8px 5px;
            position: relative;
            padding-right: 100px; /* Space for the data-label */
        }

        .report-data-table td:last-child {
            border-bottom: 0; /* No border for the last field in a card */
        }

        .report-data-table td::before {
            content: attr(data-label); /* Display the label from data-label attribute */
            position: absolute;
            right: 5px;
            width: 130px; /* Width for the label */
            font-weight: bold;
            color: #266C99;
            text-align: right;
        }

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
    </style>
</head>

<body>

<div class="fixed-toolbar" dir="rtl">
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
                <a href="LocalMenuCompanyManager.php" style="color: #FFFFFF; text-decoration: none;">תפריט מקומי</a>
            </td>
            <td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;">
                הנך מחובר <?php echo $loggedInUserName; ?>
            </td>
            <td style="border: 1px solid #FFFFFF; text-align: center; font-size: x-large; color: white; padding-right: 0;"><?php
                $file_path = 'NameAndID.txt';
                $name_and_id = @file_get_contents($file_path);
                echo htmlspecialchars($name_and_id);
            ?>
                &nbsp;
            </td>
        </tr>
    </table>
</div>
<br><br><br><br><br><br><br><br>
<h1>מנהל חברה - דוחות - עבור החברה: <?php echo htmlspecialchars($company_name); ?></h1>
<br>

<?php if (empty($results)): ?>
    <p>לא נמצאו רשומות עבור חברה זו.</p>
<?php else: ?>
    <table class="report-data-table"> 
        <thead>
            <tr>
                <th>
                    <a href="?company_id=<?= $company_id ?>&sort=user&order=<?= ($sort === 'user' ? $next_order : 'asc') ?>">
                        שם משתמש <?= $sort === 'user' ? ($order === 'asc' ? '&#9650;' : '&#9660;') : '&#9650;/&#9660;' ?>
                    </a>
                </th>
                <th>מייל</th>
                <th>תפקיד</th>
                <th>יכולות</th>
                <th>
                    <a href="?company_id=<?= $company_id ?>&sort=product&order=<?= ($sort === 'product' ? $next_order : 'asc') ?>">
                        שם מוצר <?= $sort === 'product' ? ($order === 'asc' ? '&#9650;' : '&#9660;') : '&#9650;/&#9660;' ?>
                    </a>
                </th>
                <th>תיאור</th>
                <th>גרסה</th>
                <th>תאריך שחרור</th>
                <th>סטטוס</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $row): ?>
                <tr>
                    <td data-label="שם משתמש"><?= htmlspecialchars($row['user_name'] ?? '') ?></td>
                    <td data-label="מייל"><?= htmlspecialchars($row['email'] ?? '') ?></td>
                    <td data-label="תפקיד"><?= htmlspecialchars($row['user_role'] ?? '') ?></td>
                    <td data-label="יכולות"><?= htmlspecialchars(implode(', ', json_decode($row['skills'] ?? '[]', true) ?? [])) ?></td>
                    <td data-label="שם מוצר"><?= htmlspecialchars($row['product_name'] ?? '') ?></td>
                    <td data-label="תיאור"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                    <td data-label="גרסה"><?= htmlspecialchars($row['version'] ?? '') ?></td>
                    <td data-label="תאריך שחרור"><?= htmlspecialchars($row['release_date'] ?? '') ?></td>
                    <td data-label="סטטוס"><?= htmlspecialchars($row['status'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="back-btn">
    <a href="ReportsCompanyManager.php">חזור לבחירת חברה</a>
</div>

</body>
</html>