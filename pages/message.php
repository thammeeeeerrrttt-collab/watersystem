<?php
session_start();
include "../db.php";

// 1. تحديد الدورة (الـ Period) المختار من القائمة المنسدلة - الافتراضي هو أول دورة متوفرة
$selected_period = isset($_GET['period_filter']) ? intval($_GET['period_filter']) : 0;

// جلب جميع الدورات من جدول billing_period لتعبئة قائمة الاختيار
$periods_options = [];
$periods_query = "SELECT PeriodID, PeriodName FROM billing_period ORDER BY PeriodID DESC"; 
$periods_result = $conn->query($periods_query);
if ($periods_result && $periods_result->num_rows > 0) {
    while ($p_row = $periods_result->fetch_assoc()) {
        $periods_options[] = $p_row;
    }
    // إذا لم يحدد المستخدم دورة، نختار أحدث دورة تلقائياً كافتراضي
    if ($selected_period == 0 && !empty($periods_options)) {
        $selected_period = intval($periods_options[0]['PeriodID']);
    }
}

// 2. معالجة توليد الرسائل للدورة المحددة فقط
if (isset($_POST['send_broadcast'])) {
    $template_text = $_POST['initial_message'];
    $selected_customers = isset($_POST['selected_customers']) ? $_POST['selected_customers'] : [];
    
    if (!empty($selected_customers) && !empty($template_text) && $selected_period > 0) {
        foreach ($selected_customers as $customer_id) {
            $customer_id = intval($customer_id);
            
            // جلب قراءة المشترك وفاتورته المرتبطة بالـ PeriodID المحدد حالياً
            $bill_query = "SELECT c.Name, c.phone, r.CurrentReading, r.PreviousReading, b.PreviousArrears, b.Amount 
                           FROM customer c 
                           INNER JOIN reading r ON c.CustomerID = r.CustomerID AND r.PeriodID = '$selected_period'
                           LEFT JOIN bill b ON c.CustomerID = b.CustomerID AND b.PeriodID = '$selected_period'
                           WHERE c.CustomerID = '$customer_id' 
                           LIMIT 1";
                           
            $bill_result = $conn->query($bill_query);
            
            if ($bill_result && $bill_result->num_rows > 0) {
                $bill_data = $bill_result->fetch_assoc();
                $current_reading   = $bill_data['CurrentReading'] ?? '0';
                $previous_reading  = $bill_data['PreviousReading'] ?? '0';
                $arrears           = $bill_data['PreviousArrears'] ?? '0';
                $amount            = $bill_data['Amount'] ?? '0';
                $total             = floatval($amount) + floatval($arrears);
                
                // استبدال الرموز بالبيانات الفعلية
                $personalized_message = $template_text;
                $personalized_message = str_replace("{الحالية}", $current_reading, $personalized_message);
                $personalized_message = str_replace("{السابقة}", $previous_reading, $personalized_message);
                $personalized_message = str_replace("{المتأخرات}", $arrears, $personalized_message);
                $personalized_message = str_replace("{المبلغ}", $amount, $personalized_message);
                $personalized_message = str_replace("{الإجمالي}", $total, $personalized_message);
                
                $final_message = mysqli_real_escape_string($conn, $personalized_message);
                
                // حفظ الرسالة وربطها بالدورة الحالية في جدول الـ messages
                $insert_query = "INSERT INTO messages (CustomerID, PeriodID, message, MessageType, Status) 
                                 VALUES ('$customer_id', '$selected_period', '$final_message', 'Reminder', 'Unread')";
                $conn->query($insert_query);
            }
        }
        $success_msg = "تم توليد وتخصيص الرسائل بنجاح للدورة المحددة.";
    }
}

// 3. معالجة تعديل رسالة مخصصة لمشترك
if (isset($_POST['update_message'])) {
    $message_id = intval($_POST['message_id']);
    $new_text = mysqli_real_escape_string($conn, $_POST['new_message_text']);
    $conn->query("UPDATE messages SET message = '$new_text' WHERE MessageID = '$message_id'");
    $success_msg = "تم تحديث نص الرسالة بنجاح.";
}

// 4. جلب المشتركين الذين لديهم قراءات في الدورة المحددة مع رسائلهم المخصصة
$customers_result = null;
if ($selected_period > 0) {
    $customers_query = "SELECT c.CustomerID, c.Name, c.phone,
                        m.MessageID, m.message
                        FROM customer c
                        INNER JOIN reading r ON c.CustomerID = r.CustomerID AND r.PeriodID = '$selected_period'
                        LEFT JOIN messages m ON m.CustomerID = c.CustomerID AND m.PeriodID = '$selected_period'
                        ORDER BY c.CustomerID DESC";
    $customers_result = $conn->query($customers_query);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة وإرسال رسائل الفواتير بالدورات</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --bg-dark: #0f172a; 
            --card-bg: #1e293b; 
            --text-main: #f8fafc; 
            --text-dim: #94a3b8; 
            --accent: #38bdf8; 
            --success: #10b981; 
            --sms-color: #f59e0b; 
            --border: rgba(255,255,255,0.08); 
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: var(--bg-dark); 
            color: var(--text-main); 
            padding: 20px; 
            direction: rtl; 
            line-height: 1.6;
        }
        
        .container { max-width: 1500px; margin: 0 auto; }
        
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--card-bg);
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }
        
        .top-navbar h2 { font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        .top-navbar h2 i { color: var(--accent); }
        
        .period-select-container { display: flex; align-items: center; gap: 10px; }
        .period-dropdown {
            background: #0f172a;
            color: #fff;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 0.95rem;
            cursor: pointer;
            outline: none;
        }
        .period-dropdown:focus { border-color: var(--accent); }

        .grid-layout { display: grid; grid-template-columns: 1fr 2.5fr; gap: 25px; align-items: start; }
        
        @media (max-width: 1200px) {
            .grid-layout { grid-template-columns: 1fr; }
        }
        
        .card { 
            background: var(--card-bg); 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); 
            border: 1px solid var(--border);
        }
        
        .card h3 { font-size: 1.1rem; margin-bottom: 20px; color: var(--text-dim); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .card h3 i { color: var(--accent); }
        
        .form-control { 
            width: 100%; 
            background: #0f172a; 
            border: 1px solid var(--border); 
            color: #fff; 
            padding: 12px; 
            border-radius: 8px; 
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
        }
        
        .btn { 
            background: var(--accent); 
            color: var(--bg-dark); 
            border: none; 
            padding: 14px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            width: 100%; 
            margin-top: 15px; 
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: rgba(15, 23, 42, 0.6); padding: 14px; text-align: right; color: var(--text-dim); font-size: 0.9rem; border-bottom: 2px solid var(--border); }
        .data-table td { padding: 14px; text-align: right; border-bottom: 1px solid var(--border); vertical-align: middle; }
        
        .inline-edit-container { display: flex; gap: 8px; align-items: center; width: 100%; }
        .inline-edit-input { 
            background: #0f172a; 
            border: 1px solid var(--border); 
            color: #fff; 
            padding: 8px 12px; 
            border-radius: 6px; 
            width: 100%;
            font-family: inherit;
        }
        
        .btn-inline-save { 
            background: var(--success); 
            border: none; 
            color: #fff; 
            padding: 10px 12px; 
            border-radius: 6px; 
            cursor: pointer;
        }
        
        .actions-cell { display: flex; gap: 8px; flex-wrap: nowrap; }
        
        .btn-action { 
            padding: 8px 12px; 
            border-radius: 6px; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            font-size: 0.85rem; 
            font-weight: bold; 
            color: #fff;
            white-space: nowrap;
        }
        
        .btn-whatsapp { background: #25D366; }
        .btn-sms { background: var(--sms-color); }
        
        .alert { 
            background: rgba(16, 185, 129, 0.15); 
            border: 1px solid rgba(16, 185, 129, 0.3); 
            color: #34d399; 
            padding: 12px 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }

        input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); }
    </style>
</head>
<body>

<div class="container">
    
    <!-- القائمة المنسدلة لاختيار الدورة المحددة -->
    <div class="top-navbar">
        <h2><i class="fa fa-clock"></i> إدارة رسائل الفواتير بحسب الدورة الحالية</h2>
        
        <div class="period-select-container">
            <label for="period_select"><i class="fa fa-filter" style="color: var(--accent);"></i> اختر الدورة:</label>
            <select id="period_select" class="period-dropdown" onchange="filterByPeriod(this.value)">
                <?php foreach($periods_options as $p): ?>
                    <option value="<?= $p['PeriodID'] ?>" <?= $selected_period == $p['PeriodID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['PeriodName'] ?? ("دورة رقم " . $p['PeriodID'])) ?>
                    </option>
                <?php endforeach; ?>
                <?php if(empty($periods_options)): ?>
                    <option value="0">لا توجد دورات مسجلة</option>
                <?php endif; ?>
            </select>
        </div>
    </div>
    
    <?php if(isset($success_msg)): ?>
        <div class="alert"><i class="fa fa-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>

    <div class="grid-layout">
        <!-- قسم القالب وتوليد الرسائل -->
        <div class="card">
            <h3><i class="fa fa-sliders"></i> قالب الرسالة المعتمد</h3>
            <form method="POST" id="broadcastForm" action="?period_filter=<?= $selected_period ?>">
                <textarea name="initial_message" class="form-control" rows="10"><?="حساب مشروع مياه ثامر\nالمبلغ الحالي = {المبلغ}\nالقراءه الحالية = {الحالية}\nالقراءة السابقة = {السابقة}\nالمتاخرة السابقة = {المتأخرات}\nالاجمالي كامل = {الإجمالي}"?></textarea>
                <button type="submit" name="send_broadcast" class="btn"><i class="fa fa-cogs"></i> توليد وتخصيص الرسائل للدورة</button>
            </form>
        </div>

        <!-- جدول المشتركين التابعين للدورة المحددة فقط -->
        <div class="card">
            <h3><i class="fa fa-users"></i> المشتركون المسجلون بقراءات في هذه الدورة</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="40"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                            <th>المشترك</th>
                            <th>رقم الهاتف</th>
                            <th>نص الرسالة المخصص لهذه الدورة</th>
                            <th>إجراء الإرسال</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($customers_result && $customers_result->num_rows > 0): ?>
                            <?php while($row = $customers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_customers[]" value="<?= $row['CustomerID'] ?>" class="customer-checkbox" form="broadcastForm"></td>
                                    <td><strong><?= htmlspecialchars($row['Name']) ?></strong></td>
                                    <td><span style="color:var(--accent); font-weight: 500;"><?= htmlspecialchars($row['phone'] ?? 'بدون رقم') ?></span></td>
                                    <td>
                                        <?php if ($row['MessageID']): ?>
                                            <form method="POST" action="?period_filter=<?= $selected_period ?>">
                                                <div class="inline-edit-container">
                                                    <input type="hidden" name="message_id" value="<?= $row['MessageID'] ?>">
                                                    <textarea name="new_message_text" class="inline-edit-input" rows="2"><?= htmlspecialchars($row['message']) ?></textarea>
                                                    <button type="submit" name="update_message" class="btn-inline-save" title="حفظ وتحديث"><i class="fa fa-save"></i></button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:var(--text-dim); font-style:italic; font-size: 0.9rem;">اضغط زر "توليد" لإنشاء رسائل هذه الدورة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <?php if ($row['MessageID'] && !empty($row['phone'])): ?>
                                                <a href="https://api.whatsapp.com/send?phone=<?= urlencode($row['phone']) ?>&text=<?= urlencode($row['message']) ?>" 
                                                   target="_blank" class="btn-action btn-whatsapp"><i class="fab fa-whatsapp"></i> واتساب</a>
                                                <a href="sms:<?= urlencode($row['phone']) ?>?&body=<?= urlencode($row['message']) ?>" 
                                                   class="btn-action btn-sms"><i class="fa fa-comment-sms"></i> SMS</a>
                                            <?php else: ?>
                                                <span style="color:var(--text-dim); font-size:0.85rem;">غير جاهز</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 30px;">لا توجد قراءات مسجلة للمشتركين في هذه الدورة حتى الآن.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// إعادة توجيه الصفحة عند تغيير الدورة لتحديث البيانات
function filterByPeriod(periodId) {
    window.location.href = '?period_filter=' + periodId;
}

// تحديد وإلغاء تحديد كل المشتركين
function toggleSelectAll(source) {
    const checkboxes = document.getElementsByClassName('customer-checkbox');
    for(let i=0; i < checkboxes.length; i++) { 
        checkboxes[i].checked = source.checked; 
    }
}
</script>
</body>
</html>