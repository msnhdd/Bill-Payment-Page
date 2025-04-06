<?php
/**
 * Plugin Name: سیستم پرداخت قبض
 * Plugin URI: https://yourwebsite.com
 * Description: افزونه ای برای پرداخت قبض از طریق وب سایت وردپرس
 * Version: 1.0.0
 * Author: نام شما
 * Author URI: https://yourwebsite.com
 * Text Domain: bill-payment-system
 */

// جلوگیری از دسترسی مستقیم به فایل
if (!defined('ABSPATH')) {
    exit;
}

// تعریف کلاس اصلی افزونه
class Bill_Payment_System {
    
    // متغیر برای نگهداری نمونه کلاس (الگوی Singleton)
    private static $instance = null;
    
    // تابع برای دریافت نمونه کلاس
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // سازنده کلاس
    public function __construct() {
        // افزودن منو در پنل مدیریت
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // ثبت شورت‌کد برای نمایش فرم پرداخت قبض
        add_shortcode('bill_payment_form', array($this, 'bill_payment_form_shortcode'));
        
        // اضافه کردن اکشن برای پردازش فرم
        add_action('init', array($this, 'process_bill_payment_form'));
        
        // ثبت شورت‌کد برای نمایش صفحه تایید قبض
        add_shortcode('bill_confirmation', array($this, 'bill_confirmation_shortcode'));
        
        // ثبت شورت‌کد برای صفحه نتیجه پرداخت
        add_shortcode('bill_payment_result', array($this, 'bill_payment_result_shortcode'));
        
        // ثبت استایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'register_styles'));
    }
    
    // تابع برای ثبت استایل‌ها
    public function register_styles() {
        wp_enqueue_style('bill-payment-system-styles', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
    }
    
    // تابع برای افزودن منو در پنل مدیریت
    public function register_admin_menu() {
        add_menu_page(
            'سیستم پرداخت قبض',
            'پرداخت قبض',
            'manage_options',
            'bill-payment-system',
            array($this, 'admin_page_content'),
            'dashicons-money-alt',
            30
        );
    }
    
    // تابع برای نمایش محتوای صفحه مدیریت
    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1>تنظیمات سیستم پرداخت قبض</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bill_payment_system_options');
                do_settings_sections('bill-payment-system');
                submit_button('ذخیره تنظیمات');
                ?>
            </form>
            <div class="shortcode-info">
                <h2>کد کوتاه‌ها</h2>
                <p>برای نمایش فرم پرداخت قبض در صفحات خود از کد کوتاه زیر استفاده کنید:</p>
                <code>[bill_payment_form]</code>
                
                <p>برای صفحه تایید قبض از کد کوتاه زیر استفاده کنید:</p>
                <code>[bill_confirmation]</code>
                
                <p>برای صفحه نتیجه پرداخت از کد کوتاه زیر استفاده کنید:</p>
                <code>[bill_payment_result]</code>
            </div>
        </div>
        <?php
    }
    
    // تابع برای شورت‌کد فرم پرداخت قبض
    public function bill_payment_form_shortcode() {
        ob_start();
        ?>
        <div class="bill-payment-form-container">
            <h2>پرداخت قبض</h2>
            <form method="post" action="" class="bill-payment-form">
                <?php wp_nonce_field('bill_payment_form_nonce', 'bill_payment_nonce'); ?>
                
                <div class="form-group">
                    <label for="bill_id">شناسه قبض:</label>
                    <input type="text" name="bill_id" id="bill_id" class="form-control" required placeholder="شناسه قبض را وارد کنید">
                </div>
                
                <div class="form-group">
                    <label for="payment_id">شناسه پرداخت:</label>
                    <input type="text" name="payment_id" id="payment_id" class="form-control" required placeholder="شناسه پرداخت را وارد کنید">
                </div>
                
                <div class="form-group">
                    <button type="submit" name="bill_payment_submit" class="button button-primary">استعلام قبض</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // تابع برای پردازش فرم پرداخت قبض
    public function process_bill_payment_form() {
        if (isset($_POST['bill_payment_submit']) && isset($_POST['bill_payment_nonce']) && wp_verify_nonce($_POST['bill_payment_nonce'], 'bill_payment_form_nonce')) {
            
            $bill_id = sanitize_text_field($_POST['bill_id']);
            $payment_id = sanitize_text_field($_POST['payment_id']);
            
            // بررسی صحت اطلاعات قبض (در اینجا میتوانید به API متصل شوید)
            if ($this->validate_bill($bill_id, $payment_id)) {
                // ذخیره اطلاعات در سشن
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['bill_info'] = $this->get_bill_details($bill_id, $payment_id);
                
                // هدایت به صفحه تایید
                $confirmation_page = get_page_by_title('تایید پرداخت قبض');
                if ($confirmation_page) {
                    wp_redirect(get_permalink($confirmation_page->ID));
                    exit;
                } else {
                    // اگر صفحه تایید وجود نداشت، خطا نمایش داده شود
                    wp_die('صفحه تایید پرداخت تنظیم نشده است. لطفا با مدیر سایت تماس بگیرید.');
                }
            } else {
                // نمایش خطا در صورت نامعتبر بودن اطلاعات قبض
                wp_die('اطلاعات قبض وارد شده معتبر نیست. لطفا مجددا تلاش کنید.');
            }
        }
    }
    
    // تابع برای اعتبارسنجی اطلاعات قبض
    private function validate_bill($bill_id, $payment_id) {
        // در اینجا می‌توانید به API سرویس دهنده قبض متصل شوید
        // برای نمونه، یک تابع ساده برای بررسی اعتبار قبض
        
        // بررسی طول شناسه قبض (معمولا بین 8 تا 13 رقم)
        if (strlen($bill_id) < 8 || strlen($bill_id) > 13) {
            return false;
        }
        
        // بررسی طول شناسه پرداخت (معمولا بین 6 تا 10 رقم)
        if (strlen($payment_id) < 6 || strlen($payment_id) > 10) {
            return false;
        }
        
        // بررسی عددی بودن شناسه‌ها
        if (!is_numeric($bill_id) || !is_numeric($payment_id)) {
            return false;
        }
        
        // اینجا می‌توانید اتصال به API را پیاده‌سازی کنید
        // برای مثال بررسی می‌کنیم که آیا شناسه قبض با عدد خاصی شروع می‌شود
        if (substr($bill_id, 0, 2) !== '10') {
            return false;
        }
        
        return true;
    }
    
    // تابع برای دریافت جزئیات قبض از API
    private function get_bill_details($bill_id, $payment_id) {
        // در اینجا باید به API سرویس‌دهنده متصل شوید
        // برای مثال، ما یک آرایه با اطلاعات نمونه برمی‌گردانیم
        
        return array(
            'bill_id' => $bill_id,
            'payment_id' => $payment_id,
            'amount' => rand(10000, 1000000), // مبلغ قبض به صورت تصادفی
            'bill_type' => 'قبض آب', // نوع قبض
            'deadline' => date('Y-m-d', strtotime('+10 days')), // مهلت پرداخت
            'name' => 'کاربر نمونه', // نام صاحب قبض
            'code' => rand(100000, 999999), // کد قبض
        );
    }
    
    // تابع برای شورت‌کد صفحه تایید قبض
    public function bill_confirmation_shortcode() {
        if (!session_id()) {
            session_start();
        }
        
        // بررسی وجود اطلاعات قبض در سشن
        if (!isset($_SESSION['bill_info'])) {
            return '<div class="bill-error">اطلاعات قبضی پیدا نشد. لطفا ابتدا <a href="' . home_url('/پرداخت-قبض') . '">فرم پرداخت قبض</a> را پر کنید.</div>';
        }
        
        $bill_info = $_SESSION['bill_info'];
        
        ob_start();
        ?>
        <div class="bill-confirmation-container">
            <h2>تایید پرداخت قبض</h2>
            
            <div class="bill-details">
                <div class="bill-row">
                    <div class="bill-label">نوع قبض:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['bill_type']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">شناسه قبض:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['bill_id']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">شناسه پرداخت:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['payment_id']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">نام مشترک:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['name']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">مهلت پرداخت:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['deadline']); ?></div>
                </div>
                
                <div class="bill-row bill-amount">
                    <div class="bill-label">مبلغ قابل پرداخت:</div>
                    <div class="bill-value"><?php echo number_format($bill_info['amount']) . ' ریال'; ?></div>
                </div>
            </div>
            
            <form method="post" action="" class="bill-payment-confirmation-form">
                <?php wp_nonce_field('bill_payment_confirmation_nonce', 'bill_confirmation_nonce'); ?>
                <div class="form-group">
                    <button type="submit" name="bill_confirmation_submit" class="button button-primary">تایید و پرداخت</button>
                    <a href="<?php echo home_url('/پرداخت-قبض'); ?>" class="button">انصراف</a>
                </div>
            </form>
        </div>
        <?php
        
        // پردازش فرم تایید پرداخت
        if (isset($_POST['bill_confirmation_submit']) && isset($_POST['bill_confirmation_nonce']) && wp_verify_nonce($_POST['bill_confirmation_nonce'], 'bill_payment_confirmation_nonce')) {
            // هدایت به درگاه پرداخت
            $this->redirect_to_payment_gateway($bill_info);
        }
        
        return ob_get_clean();
    }
    
    // تابع برای هدایت به درگاه پرداخت
    private function redirect_to_payment_gateway($bill_info) {
        // در اینجا باید به درگاه پرداخت متصل شوید
        // برای مثال، به صفحه نتیجه هدایت می‌کنیم
        
        // ذخیره اطلاعات تراکنش
        $_SESSION['transaction_info'] = array(
            'bill_info' => $bill_info,
            'transaction_id' => uniqid('trans_'),
            'transaction_date' => date('Y-m-d H:i:s'),
        );
        
        // هدایت به صفحه نتیجه (معمولا به درگاه پرداخت هدایت می‌شود)
        $result_page = get_page_by_title('نتیجه پرداخت قبض');
        if ($result_page) {
            wp_redirect(get_permalink($result_page->ID));
            exit;
        } else {
            // اگر صفحه نتیجه وجود نداشت، خطا نمایش داده شود
            wp_die('صفحه نتیجه پرداخت تنظیم نشده است. لطفا با مدیر سایت تماس بگیرید.');
        }
    }
    
    // تابع برای شورت‌کد صفحه نتیجه پرداخت
    public function bill_payment_result_shortcode() {
        if (!session_id()) {
            session_start();
        }
        
        // بررسی وجود اطلاعات تراکنش در سشن
        if (!isset($_SESSION['transaction_info'])) {
            return '<div class="bill-error">اطلاعات تراکنشی پیدا نشد. لطفا ابتدا <a href="' . home_url('/پرداخت-قبض') . '">فرم پرداخت قبض</a> را پر کنید.</div>';
        }
        
        $transaction_info = $_SESSION['transaction_info'];
        $bill_info = $transaction_info['bill_info'];
        
        // در اینجا می‌توان وضعیت پرداخت را از بانک استعلام کرد
        // برای مثال، فرض می‌کنیم پرداخت موفق بوده است
        $payment_success = true;
        
        ob_start();
        ?>
        <div class="bill-result-container <?php echo $payment_success ? 'payment-success' : 'payment-failed'; ?>">
            <h2>نتیجه پرداخت</h2>
            
            <?php if ($payment_success) : ?>
                <div class="payment-status success">
                    <span class="dashicons dashicons-yes"></span>
                    <span class="status-text">پرداخت با موفقیت انجام شد.</span>
                </div>
            <?php else : ?>
                <div class="payment-status failed">
                    <span class="dashicons dashicons-no"></span>
                    <span class="status-text">پرداخت ناموفق بود.</span>
                </div>
            <?php endif; ?>
            
            <div class="transaction-details">
                <div class="bill-row">
                    <div class="bill-label">شماره تراکنش:</div>
                    <div class="bill-value"><?php echo esc_html($transaction_info['transaction_id']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">تاریخ تراکنش:</div>
                    <div class="bill-value"><?php echo esc_html($transaction_info['transaction_date']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">نوع قبض:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['bill_type']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">شناسه قبض:</div>
                    <div class="bill-value"><?php echo esc_html($bill_info['bill_id']); ?></div>
                </div>
                
                <div class="bill-row">
                    <div class="bill-label">مبلغ پرداختی:</div>
                    <div class="bill-value"><?php echo number_format($bill_info['amount']) . ' ریال'; ?></div>
                </div>
            </div>
            
            <div class="payment-actions">
                <a href="<?php echo home_url(); ?>" class="button">بازگشت به صفحه اصلی</a>
                <a href="<?php echo home_url('/پرداخت-قبض'); ?>" class="button">پرداخت قبض جدید</a>
                
                <?php if ($payment_success) : ?>
                <button class="button print-receipt" onclick="window.print();">چاپ رسید</button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        // پاک کردن اطلاعات سشن پس از نمایش نتیجه
        unset($_SESSION['bill_info']);
        unset($_SESSION['transaction_info']);
        
        return ob_get_clean();
    }
}

// راه‌اندازی نمونه کلاس
function run_bill_payment_system() {
    return Bill_Payment_System::get_instance();
}

run_bill_payment_system();

// افزودن استایل‌ها
function bill_payment_system_styles() {
    ?>
    <style>
        .bill-payment-form-container,
        .bill-confirmation-container,
        .bill-result-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            font-family: 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
        }
        
        .bill-payment-form-container h2,
        .bill-confirmation-container h2,
        .bill-result-container h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .button:hover {
            background-color: #005a87;
        }
        
        .bill-details {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .bill-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .bill-label {
            font-weight: bold;
            width: 40%;
        }
        
        .bill-value {
            width: 60%;
        }
        
        .bill-amount {
            font-size: 18px;
            color: #0073aa;
            border-top: 2px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-status {
            text-align: center;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 18px;
        }
        
        .payment-status.success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        
        .payment-status.failed {
            background-color: #f2dede;
            color: #a94442;
        }
        
        .payment-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        .bill-error {
            padding: 15px;
            background-color: #f2dede;
            color: #a94442;
            border-radius: 4px;
            text-align: center;
        }
        
        @media print {
            .payment-actions {
                display: none;
            }
        }
    </style>
    <?php
}

add_action('wp_head', 'bill_payment_system_styles');