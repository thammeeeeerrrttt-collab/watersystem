<?php
session_start();
include "../db.php"; // تأكد من مسار الاتصال بقاعدة البيانات

if(!isset($_SESSION['EmployeeID']) || $_SESSION['Role'] != 'Admin') {
    die("❌ عذراً، هذه الصفحة مخصصة لمدير النظام فقط.");
}

$message = "";

// 1. إضافة حقل الراتب إلى جدول الموظفين إذا لم يكن موجوداً
$check_salary_col = $conn->query("SHOW COLUMNS FROM Employee LIKE 'Salary'");
if($check_salary_col->num_rows == 0) {
    $conn->query("ALTER TABLE Employee ADD Salary DECIMAL(10,2) DEFAULT 0 AFTER RoleID");
}

// إضافة حقل المنطقة/الموقع إلى جدول الموظفين إذا لم يكن موجوداً
$check_location_col = $conn->query("SHOW COLUMNS FROM Employee LIKE 'Location'");
if($check_location_col->num_rows == 0) {
    $conn->query("ALTER TABLE Employee ADD Location VARCHAR(255) NULL AFTER Salary");
}

// 2. إنشاء جدول السحبيات / السلف
$create_advances_table = "
CREATE TABLE IF NOT EXISTS employee_advances (
    AdvanceID INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID INT NOT NULL,
    Amount DECIMAL(10,2) NOT NULL,
    AdvanceDate DATE NOT NULL,
    Notes VARCHAR(255),
    FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID) ON DELETE CASCADE
)";
$conn->query($create_advances_table);

// لتسهيل العرض
$roles_map = [
    1 => ['name' => 'مدير النظام (Admin)', 'role_key' => 'Admin'],
    2 => ['name' => 'متحصل (Cashier)', 'role_key' => 'Cashier'],
    3 => ['name' => 'قارئ عدادات (Reader)', 'role_key' => 'Reader'],
    4 => ['name' => 'عامل صيانة (Maintenance)', 'role_key' => 'Maintenance']
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_employee'])) {
    $name     = $conn->real_escape_string($_POST['name']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    // استقبال اسم الصلاحية كنص بدلاً من رقم
    $role_name_input = trim($_POST['role_name']);
    $role_name_safe  = $conn->real_escape_string($role_name_input);

    // البحث عن الصلاحية في جدول Role، وإذا لم تكن موجودة نقوم بإضافتها تلقائياً
    $check_role = $conn->query("SELECT RoleID FROM Role WHERE RoleName = '$role_name_safe'");
    if ($check_role && $check_role->num_rows > 0) {
        $role_row = $check_role->fetch_assoc();
        $role_id = $role_row['RoleID'];
    } else {
        // إنشاء الصلاحية الجديدة في قاعدة البيانات
        $conn->query("INSERT INTO Role (RoleName) VALUES ('$role_name_safe')");
        $role_id = $conn->insert_id;
    }

    $salary   = floatval($_POST['salary']); // الحقل الجديد للراتب
    
    // التعديل هنا: إذا كانت المنطقة فارغة نخزن 'الكل'
    $location = !empty($_POST['location']) ? $conn->real_escape_string($_POST['location']) : 'الكل';
    $phone    = $conn->real_escape_string($_POST['phone']);
    $email    = $conn->real_escape_string($_POST['email']);
    
    $check_user = $conn->query("SELECT EmployeeID FROM Employee WHERE Username = '$username'");
    if ($check_user->num_rows > 0) {
        $message = "<div class='msg error'>❌ اسم المستخدم موجود مسبقاً، يرجى اختيار اسم آخر.</div>";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // أضفنا حقل Salary و Location للاستعلام
        $insert_query = "INSERT INTO Employee (Name, Username, Password, RoleID, Salary, Location, Phone, Email) 
                         VALUES ('$name', '$username', '$hashed_password', $role_id, $salary, '$location', '$phone', '$email')";
        
        if ($conn->query($insert_query)) {
            $message = "<div class='msg success'>✅ تم إضافة الموظف بنجاح.</div>";
        } else {
            $message = "<div class='msg error'>❌ حدث خطأ أثناء الإضافة: " . $conn->error . "</div>";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_advance'])) {
    $emp_id = intval($_POST['advance_emp_id']);
    $amount = floatval($_POST['advance_amount']);
    $notes  = $conn->real_escape_string($_POST['advance_notes']);
    $date   = date('Y-m-d'); // تاريخ اليوم

    if($emp_id > 0 && $amount > 0) {
        $insert_advance = "INSERT INTO employee_advances (EmployeeID, Amount, AdvanceDate, Notes) 
                           VALUES ($emp_id, $amount, '$date', '$notes')";
        if($conn->query($insert_advance)) {
            $message = "<div class='msg success'>✅ تم تسجيل السحبية بنجاح وتم خصمها من الراتب.</div>";
        } else {
            $message = "<div class='msg error'>❌ خطأ في تسجيل السحبية.</div>";
        }
    }
}

if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if ($del_id == $_SESSION['EmployeeID']) {
        $message = "<div class='msg error'>❌ لا يمكنك حذف حسابك الشخصي!</div>";
    } else {
        $conn->query("DELETE FROM Employee WHERE EmployeeID = $del_id");
        $message = "<div class='msg success'>✅ تم حذف الموظف بنجاح.</div>";
    }
}

if (isset($_GET['reset_device_id'])) {
    $reset_id = intval($_GET['reset_device_id']);
    $conn->query("UPDATE Employee SET DeviceToken = NULL WHERE EmployeeID = $reset_id");
    $message = "<div class='msg success'>✅ تم فك ارتباط جهاز الموظف.</div>";
}

//// نجلب الموظفين، ونجمع السحبيات (فقط للشهر الحالي والسنة الحالية) باستخدام LEFT JOIN
$current_month = date('m');
$current_year = date('Y');

$employees_query = "
    SELECT e.*, 
           COALESCE(SUM(a.Amount), 0) AS TotalAdvances,
           r.RoleName
    FROM Employee e
    LEFT JOIN employee_advances a 
           ON e.EmployeeID = a.EmployeeID 
           AND MONTH(a.AdvanceDate) = $current_month 
           AND YEAR(a.AdvanceDate) = $current_year
    LEFT JOIN Role r ON e.RoleID = r.RoleID
    GROUP BY e.EmployeeID
    ORDER BY e.EmployeeID DESC
";
$employees = $conn->query($employees_query);

// جلب الصلاحيات المسجلة مسبقاً لاقتراحها أثناء الكتابة
$existing_roles = $conn->query("SELECT RoleName FROM Role");

// جلب قائمة الموظفين لاستخدامها في قائمة السحبيات المنسدلة
$emps_list = $conn->query("SELECT EmployeeID, Name FROM Employee ORDER BY Name ASC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة الموظفين والرواتب</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    body {
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: white;
        padding-bottom: 50px;
    }

    .header {
        background: #111827;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    }

    .header h2 { margin: 0; color: #38bdf8; }

    .btn-back {
        background: #64748b;
        color: white;
        padding: 10px 15px;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
        transition: 0.3s;
    }
    .btn-back:hover { background: #475569; }

    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .msg {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: bold;
        text-align: center;
    }
    .success { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #22c55e; }
    .error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #ef4444; }

    .grid-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }
    @media(max-width: 768px) {
        .grid-layout { grid-template-columns: 1fr; }
        .table-responsive { overflow-x: auto; display: block; }
    }

    .form-card {
        background: rgba(255, 255, 255, 0.05);
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border: 1px solid #334155;
    }
    .form-card.advance-card {
        border-color: #f59e0b;
        background: rgba(245, 158, 11, 0.05);
    }
    
    .form-card h3 {
        margin-top: 0;
        border-bottom: 1px solid #334155;
        padding-bottom: 10px;
        margin-bottom: 20px;
        color: #e2e8f0;
    }
    .advance-card h3 { color: #fcd34d; border-color: rgba(245, 158, 11, 0.3); }

    .grid-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .form-group { display: flex; flex-direction: column; margin-bottom: 15px;}
    .form-group label { margin-bottom: 5px; color: #94a3b8; font-size: 14px; }
    .form-group input, .form-group select {
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #475569;
        background: #1e293b;
        color: white;
        font-family: inherit;
        outline: none;
        transition: 0.3s;
        box-sizing: border-box;
    }
    .form-group input:focus, .form-group select:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
    }
    .advance-card input:focus, .advance-card select:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }

    .btn-submit {
        background: #0ea5e9;
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        width: 100%;
        transition: 0.3s;
        margin-top: 10px;
    }
    .btn-submit:hover { background: #0284c7; }
    
    .btn-advance { background: #f59e0b; }
    .btn-advance:hover { background: #d97706; }

    /* جدول الموظفين */
    table {
        width: 100%;
        border-collapse: collapse;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        overflow: hidden;
        white-space: nowrap;
    }
    th, td {
        padding: 12px;
        text-align: right;
        border-bottom: 1px solid #334155;
        font-size: 14px;
    }
    th { background: #1e293b; color: #38bdf8; }
    tr:hover { background: rgba(255, 255, 255, 0.08); }

    .role-badge {
        background: #3b82f6;
        color: white;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
    }

    /* الوان الرواتب */
    .salary-text { color: #38bdf8; font-weight: bold; }
    .advance-text { color: #ef4444; font-weight: bold; }
    .remain-text { color: #4ade80; font-weight: bold; font-size: 15px; }

    .btn-action {
        padding: 6px 10px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
        transition: 0.3s;
        display: inline-block;
        color: white;
        margin-left: 3px;
    }
    .btn-reset { background: #64748b; }
    .btn-reset:hover { background: #475569; }
    .btn-delete { background: #ef4444; }
    .btn-delete:hover { background: #b91c1c; }
</style>
</head>
<body>

<div class="header">
    <h2><i class="fa fa-users-cog"></i> إدارة الموظفين والرواتب</h2>
    <a href="../index.php" class="btn-back"><i class="fa fa-arrow-right"></i> رجوع</a>
</div>

<div class="container">
    
    <?= $message ?>

    <div class="grid-layout">
        
        <!-- بطاقة إضافة موظف -->
        <div class="form-card">
            <h3><i class="fa fa-user-plus"></i> إضافة موظف جديد</h3>
            <form method="POST" action="">
                <div class="grid-form">
                    <div class="form-group">
                        <label>الاسم الكامل</label>
                        <input type="text" name="name" required placeholder="أدخل اسم الموظف">
                    </div>
                    
                    <div class="form-group">
                        <label>اسم المستخدم</label>
                        <input type="text" name="username" required placeholder="لتسجيل الدخول">
                    </div>

                    <div class="form-group">
                        <label>كلمة المرور</label>
                        <input type="password" name="password" required placeholder="******">
                    </div>

                    <!-- إدخال الصلاحية مع قائمة منسدلة مقترحة -->
                    <div class="form-group">
                        <label>الصلاحية (الدور)</label>
                        <input type="text" name="role_name" list="roles-list" required placeholder="اختر أو اكتب صلاحية جديدة" autocomplete="off">
                        <datalist id="roles-list">
                            <?php
                            if($existing_roles && $existing_roles->num_rows > 0) {
                                while($r = $existing_roles->fetch_assoc()) {
                                    echo "<option value='".htmlspecialchars($r['RoleName'])."'>";
                                }
                            }
                            ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label style="color:#4ade80;">الراتب الأساسي</label>
                        <input type="number" step="any" name="salary" required placeholder="مثال: 100000">
                    </div>

                    <div class="form-group">
                        <label>المنطقة / الموقع</label>
                        <input type="text" name="location" placeholder="المنطقة المسؤل عنها (اختياري)">
                    </div>

                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <input type="text" name="phone" placeholder="اختياري">
                    </div>

                    <div class="form-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" name="email" placeholder="اختياري">
                    </div>
                </div>
                <button type="submit" name="add_employee" class="btn-submit"><i class="fa fa-save"></i> حفظ بيانات الموظف</button>
            </form>
        </div>

        <!-- بطاقة تسجيل السحبيات / السلف -->
        <div class="form-card advance-card">
            <h3><i class="fa fa-hand-holding-usd"></i> تسجيل سحبية</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>الموظف</label>
                    <select name="advance_emp_id" required>
                        <option value="">-- اختر الموظف --</option>
                        <?php
                        if($emps_list && $emps_list->num_rows > 0) {
                            while($emp = $emps_list->fetch_assoc()) {
                                echo "<option value='{$emp['EmployeeID']}'>".htmlspecialchars($emp['Name'])."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label style="color:#f87171;">مبلغ السحبية</label>
                    <input type="number" step="any" name="advance_amount" required placeholder="المبلغ المخصوم">
                </div>

                <div class="form-group">
                    <label>البيان / الملاحظات</label>
                    <input type="text" name="advance_notes" placeholder="سبب السحبية (اختياري)">
                </div>

                <button type="submit" name="add_advance" class="btn-submit btn-advance"><i class="fa fa-minus-circle"></i> خصم من الراتب</button>
            </form>
        </div>

    </div>

    <!-- جدول عرض الموظفين -->
    <div class="form-card">
        <h3><i class="fa fa-list"></i> قائمة الموظفين (لشهر <?= date('m/Y') ?>)</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>م</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الصلاحية</th>
                        <th>المنطقة</th>
                        <th>الراتب الأساسي</th>
                        <th>مسحوبات الشهر</th>
                        <th>صافي الراتب</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($employees && $employees->num_rows > 0): 
                        $count = 1;
                        while($row = $employees->fetch_assoc()):
                            $salary = floatval($row['Salary']);
                            $advances = floatval($row['TotalAdvances']);
                            $net = $salary - $advances;
                    ?>
                    <tr>
                        <td><?= $count++ ?></td>
                        <td><?= htmlspecialchars($row['Name']) ?></td>
                        <td><?= htmlspecialchars($row['Username']) ?></td>
                        <td><span class="role-badge"><?= htmlspecialchars($row['RoleName'] ?? 'غير محدد') ?></span></td>
                        <td><?= htmlspecialchars($row['Location'] ?? 'الكل') ?></td>
                        <td class="salary-text"><?= number_format($salary, 2) ?></td>
                        <td class="advance-text"><?= number_format($advances, 2) ?></td>
                        <td class="remain-text"><?= number_format($net, 2) ?></td>
                        <td>
                            <a href="?reset_device_id=<?= $row['EmployeeID'] ?>" class="btn-action btn-reset" title="فك ارتباط الجهاز" onclick="return confirm('هل أنت متأكد من فك ارتباط جهاز هذا الموظف؟');"><i class="fa fa-mobile-alt"></i> فك</a>
                            <?php if ($row['EmployeeID'] != $_SESSION['EmployeeID']): ?>
                                <a href="?delete_id=<?= $row['EmployeeID'] ?>" class="btn-action btn-delete" title="حذف الموظف" onclick="return confirm('هل أنت متأكد من حذف الموظف نهائياً؟');"><i class="fa fa-trash"></i> حذف</a>
                            <?php endif; ?>
                        </td> 
                        <a href="advance_history.php?emp_id=<?= $row['EmployeeID'] ?>" class="btn-action btn-history">
                            <i class="fa fa-eye"></i> مراجعة السلف
                        </a>
                    </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 20px;">لا يوجد موظفين مسجلين.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>