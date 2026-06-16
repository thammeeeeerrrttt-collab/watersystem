<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID']) || $_SESSION['Role'] != 'Admin') {
    die("❌ غير مصرح لك.");
}

$emp_id = intval($_GET['emp_id'] ?? 0);

// جلب بيانات الموظف
$employee = $conn->query("SELECT Name, Salary FROM Employee WHERE EmployeeID = $emp_id")->fetch_assoc();

if(!$employee) {
    die("❌ الموظف غير موجود.");
}

// جلب سجل السحبيات
$advances = $conn->query("SELECT * FROM employee_advances WHERE EmployeeID = $emp_id ORDER BY AdvanceDate DESC");

// حذف سحبية إذا لزم الأمر (لتصحيح الأخطاء)
if(isset($_GET['delete_adv'])) {
    $adv_id = intval($_GET['delete_adv']);
    $conn->query("DELETE FROM employee_advances WHERE AdvanceID = $adv_id");
    header("Location: advance_history.php?emp_id=$emp_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>سجل سحبيات: <?= htmlspecialchars($employee['Name']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; padding: 20px; }
    .container { max-width: 800px; margin: auto; background: #1e293b; padding: 20px; border-radius: 12px; }
    h2 { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 12px; text-align: right; border-bottom: 1px solid #334155; }
    th { background: #0f172a; color: #f59e0b; }
    .btn-back { background: #64748b; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; display: inline-block; margin-bottom: 15px; }
    .notes { color: #cbd5e1; font-style: italic; font-size: 13px; }
    .amount { color: #f87171; font-weight: bold; }
    .btn-del { color: #ef4444; text-decoration: none; font-size: 18px; }
</style>
</head>
<body>

<div class="container">
    <a href="employees.php" class="btn-back"><i class="fa fa-arrow-right"></i> رجوع لقائمة الموظفين</a>
    
    <h2>سجل السحبيات: <?= htmlspecialchars($employee['Name']) ?></h2>
    <p>الراتب الأساسي: <span style="color:#4ade80"><?= number_format($employee['Salary'], 2) ?></span></p>

    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>المبلغ</th>
                <th>الملاحظات / البيان</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = 0;
            if($advances->num_rows > 0):
                while($row = $advances->fetch_assoc()): 
                    $total += $row['Amount'];
            ?>
            <tr>
                <td><?= $row['AdvanceDate'] ?></td>
                <td class="amount"><?= number_format($row['Amount'], 2) ?></td>
                <td class="notes"><?= htmlspecialchars($row['Notes'] ?: 'بدون ملاحظات') ?></td>
                <td>
                    <a href="?emp_id=<?= $emp_id ?>&delete_adv=<?= $row['AdvanceID'] ?>" 
                       class="btn-del" onclick="return confirm('حذف هذه السحبية؟')" title="حذف السحبية">
                        <i class="fa fa-times-circle"></i>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
            <tr style="background:#0f172a; font-weight:bold;">
                <td>إجمالي المسحوبات التاريخي</td>
                <td class="amount"><?= number_format($total, 2) ?></td>
                <td colspan="2"></td>
            </tr>
            <?php else: ?>
            <tr><td colspan="4" style="text-align:center;">لا يوجد سجل سحبيات لهذا الموظف.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>