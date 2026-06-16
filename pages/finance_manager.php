<?php
// Include database connection
// تأكد من مسار ملف الاتصال بقاعدة البيانات الخاص بك
include "../db.php";

// 1. Handle Form Submissions (Add Expense or Transfer)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense'])) {
        $amount = $_POST['amount'];
        $category = $_POST['category'];
        $notes = $_POST['notes'];
        $date = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO Expenses (Amount, Category, Notes, CreatedAt) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $amount, $category, $notes, $date);
        $stmt->execute();
    }

    if (isset($_POST['add_transfer'])) {
        $amount = $_POST['amount'];
        $receiver = $_POST['receiver'];
        $date = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO OwnerTransfers (Amount, ReceiverName, TransferDate) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $amount, $receiver, $date);
        $stmt->execute();
    }
}

// 2. Handle Deletion
if (isset($_GET['delete_expense'])) {
    $id = $_GET['delete_expense'];
    $conn->query("DELETE FROM Expenses WHERE ExpenseID = $id");
    header("Location: finance_manager.php");
}

if (isset($_GET['delete_transfer'])) {
    $id = $_GET['delete_transfer'];
    $conn->query("DELETE FROM OwnerTransfers WHERE TransferID = $id");
    header("Location: finance_manager.php");
}

// 3. Fetch Data
$expenses = $conn->query("SELECT * FROM Expenses ORDER BY CreatedAt DESC LIMIT 10");
$transfers = $conn->query("SELECT * FROM OwnerTransfers ORDER BY TransferDate DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإدارة المالية</title>
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #64748b;
            --danger: #ef4444;
            --success: #22c55e;
            --bg: #f8fafc;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            margin: 0;
            padding: 20px;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        h2 {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            transition: 0.3s;
        }

        button:hover {
            opacity: 0.9;
        }

        .table-container {
            margin-top: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #f1f5f9;
        }

        th {
            background-color: #f8fafc;
        }

        .btn-delete {
            color: var(--danger);
            text-decoration: none;
            font-weight: bold;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            background: #e2e8f0;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>نظام الإدارة المالية المحاسبي</h1>
        <p>إدارة المصروفات اليومية والتحويلات المالية للمالك</p>
    </div>

    <div class="grid">
        <!-- قسم المصروفات -->
        <div class="card">
            <h2>إضافة مصروف جديد</h2>
            <form method="POST">
                <div class="form-group">
                    <label>المبلغ</label>
                    <input type="number" name="amount" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>الفئة</label>
                    <select name="category">
                        <option value="صيانة">صيانة</option>
                        <option value="أدوات مكتبية">أدوات مكتبية</option>
                        <option value="كهرباء/ماء">كهرباء/ماء</option>
                        <option value="أخرى">نثريات أخرى</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ملاحظات</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <button type="submit" name="add_expense">حفظ المصروف</button>
            </form>

            <div class="table-container">
                <h3>آخر المصروفات</h3>
                <table>
                    <thead>
                        <tr>
                            <th>المبلغ</th>
                            <th>الفئة</th>
                            <th>التاريخ</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $expenses->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['Amount']; ?></td>
                            <td><span class="badge"><?php echo $row['Category']; ?></span></td>
                            <td><?php echo date('m/d', strtotime($row['CreatedAt'])); ?></td>
                            <td><a href="?delete_expense=<?php echo $row['ExpenseID']; ?>" class="btn-delete" onclick="return confirm('هل أنت متأكد من الحذف؟')">حذف</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- قسم تحويلات المالك -->
        <div class="card">
            <h2>تسليم مبالغ للمالك</h2>
            <form method="POST">
                <div class="form-group">
                    <label>المبلغ المسلم</label>
                    <input type="number" name="amount" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>اسم المستلم</label>
                    <input type="text" name="receiver" required placeholder="مثلاً: المدير العام">
                </div>
                <button type="submit" name="add_transfer" style="background-color: var(--success);">تأكيد عملية التسليم</button>
            </form>

            <div class="table-container">
                <h3>آخر التحويلات</h3>
                <table>
                    <thead>
                        <tr>
                            <th>المبلغ</th>
                            <th>المستلم</th>
                            <th>التاريخ</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $transfers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['Amount']; ?></td>
                            <td><?php echo $row['ReceiverName']; ?></td>
                            <td><?php echo date('m/d', strtotime($row['TransferDate'])); ?></td>
                            <td><a href="?delete_transfer=<?php echo $row['TransferID']; ?>" class="btn-delete" onclick="return confirm('هل أنت متأكد؟')">حذف</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>