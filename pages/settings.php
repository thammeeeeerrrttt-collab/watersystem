<?php
session_start();
include "../db.php"; 

// التحقق من صلاحية الوصول (Admin فقط)
if(!isset($_SESSION['EmployeeID']) || $_SESSION['Role'] != 'Admin') {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>❌ عذراً، هذه الصفحة مخصصة لمدير النظام فقط.</h2><a href='../index.php'>العودة للرئيسية</a></div>");
}

$message = "";

// التأكد من وجود الجداول اللازمة عند التشغيل
$conn->query("CREATE TABLE IF NOT EXISTS Role (RoleID INT AUTO_INCREMENT PRIMARY KEY, RoleName VARCHAR(50) UNIQUE NOT NULL)");
$conn->query("
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    page_name VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_permission (role_name, page_name)
)");

// 1. معالجة إضافة دور جديد
if(isset($_POST['add_role'])){
    $new_role_name = trim($conn->real_escape_string($_POST['new_role_name']));
    if(!empty($new_role_name)){
        $check = $conn->query("SELECT RoleID FROM Role WHERE RoleName = '$new_role_name'");
        if($check->num_rows == 0){
            $conn->query("INSERT INTO Role (RoleName) VALUES ('$new_role_name')");
            $message = "<div class='msg success'>✅ تم إضافة الدور الجديد: $new_role_name</div>";
        } else {
            $message = "<div class='msg error'>❌ هذا الدور موجود مسبقاً!</div>";
        }
    }
}

// 2. معالجة حفظ الصلاحيات
if(isset($_POST['save_permissions'])){
    // ملاحظة: لا نحذف صلاحيات Admin لأننا جعلناها ثابتة برمجياً في العرض
    $conn->query("DELETE FROM role_permissions WHERE role_name != 'Admin'");
    
    if(isset($_POST['permissions']) && is_array($_POST['permissions'])){
        $stmt = $conn->prepare("INSERT INTO role_permissions (role_name, page_name) VALUES (?, ?)");
        foreach($_POST['permissions'] as $role => $allowed_pages){
            if($role == 'Admin') continue; // تخطي الآدمن لأننا نحميه
            foreach($allowed_pages as $page){
                $stmt->bind_param("ss", $role, $page);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
    $message = "<div class='msg success'>✅ تم حفظ وتحديث الصلاحيات بنجاح!</div>";
}

// جلب الأدوار
$roles_res = $conn->query("SELECT RoleName FROM Role ORDER BY RoleID ASC");
$roles_list = [];
while($r = $roles_res->fetch_assoc()){
    $roles_list[] = $r['RoleName'];
}

// القائمة المحدثة للصفحات
$pages = [
    'dashboard'   => 'الرئيسية (Dashboard)',
    'employees'   => 'إدارة الموظفين',
    'customers'   => 'المشتركين',
    'meters'      => 'العدادات',
    'periods'     => 'الدورات المالية',
    'bills'       => 'الفواتير والتحصيل',
    'analytics'   => 'التقارير واعتماد التحاليل',
    'maintenance' => 'الصيانة',
    'messages'    => 'الرسائل النصية',
    'logs'        => 'سجل النظام (Logs)',
    'settings'    => 'إعدادات النظام'
];

// جلب الصلاحيات الحالية من القاعدة
$current_permissions = [];
$perm_res = $conn->query("SELECT role_name, page_name FROM role_permissions");
if($perm_res){
    while($p = $perm_res->fetch_assoc()){
        $current_permissions[$p['role_name']][] = $p['page_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الصلاحيات والأدوار</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #0f172a; color: white; padding-bottom: 50px; }
        .header { background: #1e293b; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #0ea5e9; position: sticky; top: 0; z-index: 100; }
        .container { padding: 30px; max-width: 1200px; margin: auto; }
        
        .btn-back { background: #334155; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; border: 1px solid #475569; }
        .btn-back:hover { background: #475569; }

        .msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; animation: fadeIn 0.5s; }
        .success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid #22c55e; }
        .error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid #ef4444; }

        .card { background: #1e293b; border-radius: 12px; padding: 25px; border: 1px solid #334155; margin-bottom: 30px; }
        .card-title { margin-top: 0; margin-bottom: 20px; color: #38bdf8; display: flex; align-items: center; gap: 10px; font-size: 1.2rem; }

        .add-role-form { display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: end; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        input[type="text"] { padding: 12px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: white; outline: none; transition: 0.3s; }
        input[type="text"]:focus { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2); }
        
        .btn-add { background: #10b981; color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .btn-add:hover { background: #059669; }

        .table-responsive { overflow-x: auto; background: #1e293b; border-radius: 12px; border: 1px solid #334155; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: #111827; color: #94a3b8; font-weight: 600; padding: 15px; text-align: center; border-bottom: 2px solid #334155; }
        td { padding: 12px; border-bottom: 1px solid #334155; border-left: 1px solid #334155; text-align: center; }
        
        .page-col { text-align: right; background: #1e293b; position: sticky; right: 0; border-left: 3px solid #0ea5e9; font-weight: bold; color: #f1f5f9; min-width: 220px; }
        
        input[type="checkbox"] { width: 22px; height: 22px; cursor: pointer; accent-color: #0ea5e9; }
        input[type="checkbox"]:disabled { cursor: not-allowed; opacity: 0.5; }

        .btn-save { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 15px; margin-top: 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-save:hover { background: #0284c7; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="header">
    <h2 style="margin:0;"><i class="fa fa-user-shield"></i> مصفوفة الصلاحيات والأدوار</h2>
    <a href="../index.php" class="btn-back">
        <i class="fa fa-arrow-right"></i> العودة للرئيسية
    </a>
</div>

<div class="container">
    
    <?= $message ?>

    <!-- إضافة دور جديد -->
    <div class="card">
        <h3 class="card-title"><i class="fa fa-plus-circle"></i> إضافة مسمى وظيفي جديد</h3>
        <form method="POST" class="add-role-form">
            <div class="input-group">
                <label for="new_role_name" style="color:#94a3b8; font-size: 0.9rem;">اسم الدور الجديد (مثل: مشرف، محاسب، مدخل بيانات)</label>
                <input type="text" name="new_role_name" id="new_role_name" placeholder="أدخل المسمى الوظيفي هنا..." required>
            </div>
            <button type="submit" name="add_role" class="btn-add">
                <i class="fa fa-user-plus"></i> إضافة للدور
            </button>
        </form>
    </div>

    <!-- مصفوفة الصلاحيات -->
    <form method="POST">
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px;">
                <h3 class="card-title" style="margin:0;"><i class="fa fa-th-list"></i> تخصيص صلاحيات الوصول للشاشات</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="page-col" style="background: #111827;">الشاشة / القائمة</th>
                            <?php foreach($roles_list as $role_name): ?>
                                <th><?= htmlspecialchars($role_name) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pages as $page_key => $page_title): ?>
                            <tr>
                                <td class="page-col"><?= $page_title ?></td>
                                <?php foreach($roles_list as $role_name): ?>
                                    <?php 
                                        // فحص هل الصفحة مفعلة لهذا الدور
                                        $isChecked = (isset($current_permissions[$role_name]) && in_array($page_key, $current_permissions[$role_name])) ? 'checked' : '';
                                        
                                        // إذا كان الدور هو Admin، نجعل كل شيء مفعل وغير قابل للتعديل
                                        if($role_name == 'Admin') {
                                            $isChecked = 'checked';
                                            $isDisabled = 'onclick="return false;" style="opacity:0.6;"';
                                        } else {
                                            $isDisabled = '';
                                        }
                                    ?>
                                    <td>
                                        <input type="checkbox" 
                                               name="permissions[<?= $role_name ?>][]" 
                                               value="<?= $page_key ?>" 
                                               <?= $isChecked ?> 
                                               <?= $isDisabled ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" name="save_permissions" class="btn-save">
            <i class="fa fa-check-double"></i> حفظ كافة التغييرات على الصلاحيات
        </button>
    </form>

</div>

</body>
</html>