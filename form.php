<?php
//error_log(print_r($v,true));

$cardnet_response = CARDNET_RESPONSE;
if ($_GET['d']) {
  if (strpos($cardnet_response, '?')) {
    $cardnet_response .= "&d=".$_GET['d'];
  } else {
    $cardnet_response .= "?d=".$_GET['d'];
  }
}

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
  "responseSuccessURL" => $cardnet_response,
  "responseFailURL" => $cardnet_response,
  "transactionNotificationURL" => CARDNET_CALLBACK,
  "cardFunction" => "debit",
  "full_bypass" => "true",
  "parentUri" => CARDNET_PARENT_URI,
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
                <tr><td>cardnet_response</td><td><?php echo $cardnet_response; ?></td></tr>

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

                <label for="cardnumber">Card number</label>
                <input type="text" name="cardnumber" value="" required pattern="[0-9]{16}" title="this should be the 16-digit number on the front of the card" />

                <label for="expmonth">Expires</label>
                <select name="expmonth" required title="expiry month is compulsory" >
                  <option value="">Month</option>
                  <option value="01">01</option>
                  <option value="02">02</option>
                  <option value="03">03</option>
                  <option value="04">04</option>
                  <option value="05">05</option>
                  <option value="06">06</option>
                  <option value="07">07</option>
                  <option value="08">08</option>
                  <option value="09">09</option>
                  <option value="10">10</option>
                  <option value="11">11</option>
                  <option value="12">12</option>
                </select>
                <select name="expyear" required title="expiry year is compulsory" >
                  <option value="">Year</option>
<?php $yr = date ('Y'); ?>
<?php for ($i=$yr;$i<($yr+6);$i++): ?>
                  <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
<?php endfor; ?>
                </select>

                <label for="cvm">Three-digit code</label>
                <input type="text" name="cvm" value="" required pattern="[0-9]{3}" title="this should be the 3-digit CVM number on the back of the card" />

                <button id="submit">
                  <div class="spinner hidden" id="spinner"></div>
                  <span id="button-text">Pay now</span>
                </button>

<?php
foreach ($values as $name => $value) {
    $this->prinput ($name,$value);
}
$this->prinput ("hashExtended",$this->createExtendedHash($values));
?>

              </div>
</fieldset>

            </form>


