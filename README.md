# Bill-Payment-Page
With this plugin, you can add a Persian-language bill payment system to your WordPress website.
Installation Guide:
1. Save the code file in this path: "wp-content/plugins/bill-payment-system/bill-payment-system.php"
2. Create the following three pages in WordPress:
    2.1. "Bill Payment" page with shortcode: [bill_payment_form]
    2.2. "Bill Payment Confirmation" page with shortcode: [bill_confirmation]
    2.3. "Bill Payment Result" page with shortcode: [bill_payment_result]
3. Activate the plugin from the WordPress admin panel

# Important Notices: 
1. To connect to the actual API, you need to edit the validate_bill and get_bill_details functions to suit your provider.
2. To connect to the actual payment gateway, you need to edit the redirect_to_payment_gateway function.

# To test the plugin's functionality, you can use these sample IDs:
Bill ID: 1023456789
Payment ID: 8765432
