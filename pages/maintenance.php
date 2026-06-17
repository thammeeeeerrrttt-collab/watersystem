<?php
session_start();
include "../db.php"; // تأكد من مسار ملف الاتصال

// حماية الصفحة
if(!isset($_SESSION['EmployeeID']) || $_SESSION['Role'] != 'Admin') {
    die("❌ غير مصرح لك.");
}

$message = "";

// 1. معالجة إدخال المواد للمخزن
if(isset($_POST['submit_inbound'])) {
    
    // التحقق هل اختار صنف موجود أم كتب صنف جديد؟
    if($_POST['itemname_select'] === 'new_item') {
        $itemname = mysqli_real_escape_string($conn, $_POST['itemname_new']);
    } else {
        $itemname = mysqli_real_escape_string($conn, $_POST['itemname_select']);
    }

    $quantity = intval($_POST['quantity']);
    $entrydate = mysqli_real_escape_string($conn, $_POST['entrydate']);
    $receivername = mysqli_real_escape_string($conn, $_POST['receivername']);
    $invoicenumber = mysqli_real_escape_string($conn, $_POST['invoicenumber']);
    $paidby = mysqli_real_escape_string($conn, $_POST['paidby']);
    $isdebt = intval($_POST['isdebt']);

    $sql_in = "INSERT INTO inboundmaterials (itemname, quantity, entrydate, receivername, invoicenumber, paidby, isdebt) 
               VALUES ('$itemname', $quantity, '$entrydate', '$receivername', '$invoicenumber', '$paidby', $isdebt)";
    
    if($conn->query($sql_in)) {
        $message = "<div class='alert success'><i class='fa fa-check-circle'></i> تم تسجيل إدخال المواد بنجاح!</div>";
    } else {
        $message = "<div class='alert error'><i class='fa fa-times-circle'></i> خطأ: " . $conn->error . "</div>";
    }
}

// 2. معالجة إخراج المواد (صرف للصيانة)
if(isset($_POST['submit_outbound'])) {
    $employeeid = intval($_POST['employeeid']);
    $meterid = !empty($_POST['meterid']) ? intval($_POST['meterid']) : "NULL";
    $maintenancedate = mysqli_real_escape_string($conn, $_POST['maintenancedate']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $sql_out = "INSERT INTO maintenance (meterid, employeeid, description, status, maintenancedate) 
                VALUES ($meterid, $employeeid, '$description', '$status', '$maintenancedate')";
    
    if($conn->query($sql_out)) {
        $message = "<div class='alert success'><i class='fa fa-check-circle'></i> تم تسجيل مهمة الصيانة وإخراج المواد بنجاح!</div>";
    } else {
        $message = "<div class='alert error'><i class='fa fa-times-circle'></i> خطأ: " . $conn->error . "</div>";
    }
}

// جلب قائمة الموظفين
$employees = $conn->query("SELECT EmployeeID, Name FROM employee");

// جلب الأصناف الفريدة (التي تم إدخالها سابقاً) لتعبئة القائمة المنسدلة
$items_list = $conn->query("SELECT DISTINCT itemname FROM inboundmaterials WHERE itemname != '' ORDER BY itemname ASC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة الصيانة والمخزون</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; padding: 20px; }
    .container { max-width: 1000px; margin: auto; background: #1e293b; padding: 20px; border-radius: 12px; }
    h2 { color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 10px; text-align: center; margin-bottom: 20px; }
    
    .forms-wrapper { display: flex; gap: 20px; }
    @media (max-width: 768px) { .forms-wrapper { flex-direction: column; } }
    
    .form-box { background: #0f172a; padding: 20px; border-radius: 8px; flex: 1; border: 1px solid #334155; }
    .form-box h3 { border-bottom: 1px dashed #334155; padding-bottom: 10px; margin-top: 0; }
    .title-inbound { color: #4ade80; }
    .title-outbound { color: #f87171; }

    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; color: #cbd5e1; font-size: 14px; }
    input, select, textarea { 
        width: 100%; padding: 10px; background: #1e293b; border: 1px solid #334155; 
        color: white; border-radius: 5px; box-sizing: border-box; font-family: inherit;
    }
    input:focus, select:focus, textarea:focus { border-color: #38bdf8; outline: none; }
    textarea { resize: vertical; min-height: 80px; }

    .btn { width: 100%; padding: 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 15px; margin-top: 10px; }
    .btn-in { background: #4ade80; color: #0f172a; }
    .btn-out { background: #f87171; color: white; }
    .btn:hover { opacity: 0.8; }

    .radio-group { display: flex; gap: 15px; padding-top: 5px; }
    .radio-group label { display: inline-flex; align-items: center; gap: 5px; cursor: pointer; color: white; font-size: 14px; }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; }
    .alert.success { background: rgba(74, 222, 128, 0.1); color: #4ade80; border: 1px solid #4ade80; }
    .alert.error { background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid #f87171; }
</style>
</head>
<body>

<div class="container">
    <h2><i class="fa fa-tools"></i> إدارة الصيانة والمخزون</h2>
    
    <?= $message ?>

    <div class="forms-wrapper">
        
        <div class="form-box">
            <h3 class="title-inbound"><i class="fa fa-box-open"></i> إدخال مواد جديدة (تأمين المخزون)</h3>
            <form action="" method="POST">
                
                <div class="form-group">
                    <label>اسم الصنف / المواد</label>
                    <select name="itemname_select" id="itemname_select" onchange="checkNewItem(this)" required>
                        <option value="">-- اختر الصنف من المخزن --</option>
                        <?php 
                        if($items_list && $items_list->num_rows > 0) {
                            while($item = $items_list->fetch_assoc()) {
                                echo "<option value='{$item['itemname']}'>{$item['itemname']}</option>";
                            }
                        }
                        ?>
                        <option value="new_item" style="color: #4ade80; font-weight: bold;">➕ إضافة صنف جديد...</option>
                    </select>
                    <input type="text" name="itemname_new" id="itemname_new" style="display: none; margin-top: 10px; border-color: #4ade80;" placeholder="اكتب اسم الصنف الجديد هنا...">
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>الكمية</label>
                        <input type="number" name="quantity" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>التاريخ</label>
                        <input type="date" name="entrydate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>المستلم (من أدخل المواد)</label>
                    <input type="text" name="receivername" required>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>رقم الفاتورة</label>
                        <input type="text" name="invoicenumber">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>من دفع الزلط؟</label>
                        <input type="text" name="paidby">
                    </div>
                </div>
                <div class="form-group">
                    <label>طريقة الدفع</label>
                    <div class="radio-group">
                        <label><input type="radio" name="isdebt" value="0" checked> كاش</label>
                        <label><input type="radio" name="isdebt" value="1"> مديونية</label>
                    </div>
                </div>
                <button type="submit" name="submit_inbound" class="btn btn-in">حفظ في المخزن</button>
            </form>
        </div>

        <div class="form-box">
            <h3 class="title-outbound"><i class="fa fa-wrench"></i> إخراج مواد (صرف للصيانة)</h3>
            <form action="" method="POST">
                <div class="form-group">
                    <label>الموظف المستلم (فني الصيانة)</label>
                    <select name="employeeid" required>
                        <option value="">-- اختر الموظف --</option>
                        <?php 
                        if($employees && $employees->num_rows > 0) {
                            while($emp = $employees->fetch_assoc()) {
                                echo "<option value='{$emp['EmployeeID']}'>{$emp['Name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>رقم العداد (إن وجد)</label>
                        <input type="number" name="meterid" placeholder="اختياري">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>تاريخ الصيانة</label>
                        <input type="date" name="maintenancedate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>الأصناف المسحوبة والتفاصيل</label>
                    <textarea name="description" required placeholder="ماذا تم سحبه وما هي المشكلة؟..."></textarea>
                </div>
                <div class="form-group">
                    <label>حالة الصيانة</label>
                    <select name="status">
                        <option value="قيد العمل">قيد العمل (الآن)</option>
                        <option value="مكتملة">مكتملة</option>
                        <option value="معلقة">معلقة</option>
                    </select>
                </div>
                <button type="submit" name="submit_outbound" class="btn btn-out">تسجيل عملية الإخراج</button>
            </form>
        </div>

    </div>
</div>

<script>
function checkNewItem(selectObj) {
    var newField = document.getElementById("itemname_new");
    if(selectObj.value === "new_item") {
        newField.style.display = "block";
        newField.setAttribute("required", "required"); // يجعله إجبارياً
    } else {
        newField.style.display = "none";
        newField.removeAttribute("required");
        newField.value = ""; // تفريغ الحقل إذا تراجع واختار صنف موجود
    }
}
</script>

</body>
</html>