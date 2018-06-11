# PayPal IPN

## Usage

```html
require 'paypal.php';
$paypal_sandbox = true;
$paypal = new paypal($paypal_sandbox);
```

## PayPal Form

```html
$columns = array(
 'business' => '#Business Email#',
 'item_name' => '#Item Name#',
 'item_number' => '#Item Number#',
 'amount' => '#Item Amount#',
 'currency_code' => '#Currency Code#',
 'return' => '#Return URL#',
 'cancel_return' => '#Cancel URL#',
 'notify_url' => '#Notify URL#',
 'custom' => '#Custom Data#'
);
foreach ($columns as $k => $v) {
 $paypal->add_field($k, $v);
}
echo $paypal->submit_form();
```

## IPN Validate

```html
$paypal->validate_ipn();
```
