<?php

class paypal {

 /**
  * @var bool $use_sandbox     Indicates if the sandbox endpoint is used.
  */
 private $use_sandbox = false;

 /**
  * @var bool $use_local_certs Indicates if the local certificates are used.
  */
 private $use_local_certs = false;
 private $fields = array();

 /** Production Postback URL */
 const uri = 'https://www.paypal.com/cgi-bin/webscr';
 const verify_uri = 'https://ipnpb.paypal.com/cgi-bin/webscr';

 /** Sandbox Postback URL */
 const sandbox_uri = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
 const sandbox_verify_uri = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';

 /** Response from PayPal indicating validation was successful */
 const valid = 'VERIFIED';

 /** Response from PayPal indicating validation failed */
 const invalid = 'INVALID';

 public function __construct($sandbox = false) {
  $this->use_sandbox = $sandbox;
 }

 public function paypal_url() {
  if ($this->use_sandbox) {
   return self::sandbox_uri;
  }
  return self::uri;
 }

 public function ipn_url() {
  if ($this->use_sandbox) {
   return self::verify_uri;
  }
  return self::sandbox_verify_uri;
 }

 public function validate_ipn() {
  if (!count($_POST)) {
   throw new Exception('Missing POST Data.');
  }

  $raw_post_data = file_get_contents('php://input');
  $raw_post_array = explode('&', $raw_post_data);

  $myPost = array();
  foreach ($raw_post_array as $keyval) {
   $keyval = explode('=', $keyval);
   if (count($keyval) == 2) {
    // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
    if ($keyval[0] === 'payment_date') {
     if (substr_count($keyval[1], '+') === 1) {
      $keyval[1] = str_replace('+', '%2B', $keyval[1]);
     }
    }
    $myPost[$keyval[0]] = urldecode($keyval[1]);
   }
  }

  // Build the body of the verification post request, adding the _notify-validate command.
  $req = 'cmd=_notify-validate';
  $get_magic_quotes_exists = false;
  if (function_exists('get_magic_quotes_gpc')) {
   $get_magic_quotes_exists = true;
  }
  foreach ($myPost as $key => $value) {
   if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
    $value = urlencode(stripslashes($value));
   } else {
    $value = urlencode($value);
   }
   $req .= "&$key=$value";
  }
  // Post the data back to PayPal, using curl. Throw exceptions if errors occur.
  $ch = curl_init($this->ipn_url());
  curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
  curl_setopt($ch, CURLOPT_SSLVERSION, 6);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  // This is often required if the server is missing a global cert bundle, or is using an outdated one.
  if ($this->use_local_certs) {
   curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cert/cacert.pem");
  }
  curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
  $res = curl_exec($ch);
  if (!($res)) {
   $errno = curl_errno($ch);
   $errstr = curl_error($ch);
   curl_close($ch);
   throw new Exception("cURL error: [$errno] $errstr");
  }
  $info = curl_getinfo($ch);
  $http_code = $info['http_code'];
  if ($http_code != 200) {
   throw new Exception("PayPal responded with http code $http_code");
  }
  curl_close($ch);
  // Check if PayPal verifies the IPN data, and if so, return true.
  if ($res == self::valid) {
   return true;
  } else {
   return false;
  }
 }

 public function test_ipn($email = 'example@gmail.com') {
  $this->pp_data = array('testing' => 'true');
  if (isset($_POST) && count($_POST) > 0) {
   foreach ($_POST as $k => $v) {
    $this->pp_data[$k] = (is_array($v) ? json_encode($v) : $v);
   }
  }
  if (isset($_SERVER) && count($_SERVER) > 0) {
   foreach ($_SERVER as $k => $v) {
    $this->pp_data[$k] = (is_array($v) ? json_encode($v) : $v);
   }
  }

  $headers = "MIME-Version: 1.0" . "\r\n";
  $headers .= "Content-type:text/html;charset=utf-8" . "\r\n";
  $headers .= 'From: ' . company_name . '<' . company_email . '>' . "\r\n";
  $subject = "Payment Transaction";
  $message = "Payment Details : <br /><br />";
  foreach ($this->pp_data as $k => $v) {
   $message .= $k . ": " . $v . "<br />";
  }
  $message .= "<br /><br />Kind Regards,<br />The Team";
  mail($email, $subject, $message, $headers);
 }

 public function add_field($field, $value) {
  $this->fields[$field] = $value;
 }

 public function submit_form() {
  $this->add_field('cmd', '_xclick');

  $str = '<form method="post" name="paypal-frm" action="' . $this->paypal_url() . '">' . "\n";
  foreach ($this->fields as $name => $value) {
   $str .= '<input type="hidden" name="' . $name . '" value="' . $value . '"/>' . "\n";
  }
  $str .= '<button type="submit" class="btn btn-primary hide">Submit</button>' . "\n";
  $str .= '</form>' . "\n";
  return $str;
 }

}
