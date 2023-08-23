<?php
//error_log(print_r($v,true));
$values = [
  "bname" => $v['title'].' '.$v['name_first'].' '.$v['name_last'],
  "chargetotal" => $pounds_amount,
  "oid" => $newid,
  "storename" => CARDNET_STORE_ID,
  "timezone" => "Europe/London",
  "txntype" => "sale",
  "currency" => "826",
  "txndatetime" => date("Y:m:d-H:i:s"),
  "hash_algorithm" => "HMACSHA256",
  "checkoutoption" => "combinedpage",
  "responseSuccessURL" => CARDNET_RESPONSE,
  "responseFailURL" => CARDNET_RESPONSE,
  "transactionNotificationURL" => CARDNET_CALLBACK,
  "cardFunction" => "debit",
  "full_bypass" => "true",
];

?>


            <style>
<?php require __DIR__.'/client.css'; ?>
            </style>

            <form id="payment-form" method="post" action=<?php echo CARDNET_URL; ?>>

<?php if (defined('CARDNET_DEV_MODE') && CARDNET_DEV_MODE): ?>
              <table>
                <tr><td>A: Payment succeeds</td><td>4147463011110083</td></tr>
                <tr><td>B: Payment requires authentication</td><td>4099000000001960 ??</td></tr>
                <tr><td>C: Payment is declined</td><td>4265880000000056 ?</td></tr>
              </table>
<?php endif; ?>
              <div>
                <?php
                $tplural = $v['quantity'] > 1 ? 's': '';
                $wplural = $v['draws'] > 1 ? 's': '';
                echo "Pay &pound;{$pounds_amount} for {$v['quantity']} ticket{$tplural} for {$v['draws']} week{$wplural}."
                ?>
              </div>
<fieldset>
<legend>Card Details</legend>

              <div id="card-element">
   <?php

$this->prinput("cardnumber", "", "Card Number");
$this->prinput("expmonth", "", "Expiry Month");
$this->prinput("expyear", "", "Expiry Year");
$this->prinput("cvm", "", "Three digit code");

?>             


              <button id="submit">
                <div class="spinner hidden" id="spinner"></div>
                <span id="button-text">Pay now</span>
              </button>

<?php foreach ($values as $name => $value) {
  $this->prinput($name, $value);
}
$this->prinput("hashExtended", $this->createExtendedHash($values));
?>

              </div>
</fieldset>

            </form>


