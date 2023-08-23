<?php

namespace Blotto\Cardnet;

class PayApi {

    private  $connection;
    public   $constants = [
                 'CARDNET_CODE',
                 'CARDNET_DESCRIPTION',
                 'CARDNET_ERROR_LOG',
                 'CARDNET_REFNO_OFFSET',
                 'CARDNET_DEV_MODE',
                 'CARDNET_TABLE_MANDATE',
                 'CARDNET_TABLE_COLLECTION',
                 'CARDNET_URL',
                 'CARDNET_STORE_ID',
                 'CARDNET_SECRET',
             ];
    public   $database;
    public   $diagnostic;
    public   $error;
    public   $errorCode = 0;
    private  $from;
    private  $org;
    public   $supporter = [];
    public   $today;

    private  $txn_ref;

    public function __construct ($connection,$org=null) {
        // TODO: what about cut-offs and BST?
        $this->today        = date ('Y-m-d');
        $this->connection   = $connection;
        $this->org          = $org;
        $this->setup ();
    }

    public function __destruct ( ) {
    }

    public function callback (&$responded) {
        $responded          = false;
        $error              = null;
        $txn_ref            = null;
        error_log(__FILE__.' '.print_r($_POST, true));
        try {
            $step           = 0;

            $hashvals = [
                $_POST['chargetotal'],
                $_POST['currency'],
                $_POST['txndatetime'],
                CARDNET_STORE_ID,
                $_POST['approval_code'],
            ];
            $checkhash = $this->makeHash($hashvals, $_POST['hash_algorithm']);

            //error_log('Cardnet posted hash '.$_POST['notification_hash']);
            //error_log('check hash '.$checkhash);
            if ($_POST['notification_hash'] != $checkhash) {
                return false;
            }

            $step           = 1;
            $payment_id     = $this->complete ();
            // The payment (or lack thereof) is now recorded at this end
            http_response_code (200);
            $responded      = true;
            echo "Transaction completed id={$payment_id}, type={$_POST['status']}\n";
            if (!$payment_id) {
                return false;
            }
            echo "Payment received\n";
            $step           = 2;
            echo "    Adding supporter for payment_id=$payment_id\n";
            $this->supporter = $this->supporter_add ($payment_id);
            echo "    Supporter added = ";
            print_r ($this->supporter);
            if ($this->org['signup_paid_email']>0) {
                $step   = 3;
                $result = campaign_monitor (
                    $this->org['signup_cm_key'],
                    $this->org['signup_cm_id'],
                    $this->supporter['To'],
                    $this->supporter
                );
                $ok     = in_array ($result->http_status_code,[200,201,202]);
                if (!$ok) {
                    throw new \Exception (print_r($result,true));
                }
            }
            if ($this->org['signup_paid_sms']>0) {
                $step   = 4;
                $sms_msg = $this->org['signup_sms_message'];
                foreach ($this->supporter as $k=>$v) {
                    $sms_msg = str_replace ("{{".$k."}}",$v,$sms_msg);
                }
                sms ($this->org,$this->supporter['Mobile'],$sms_msg,$diagnostic);
            }
            return true;
        }
        catch (\Exception $e) {
            error_log ($e->getMessage());
            throw new \Exception ("cardnet payment_id=$payment_id, step=$step: {$e->getMessage()}");
            return false;
        }
    }

    private function complete () {
        if (!in_array($_POST['status'],['APPROVED','DECLINED','FAILED','WAITING'])) {
            throw new \Exception ('Unrecognised Cardnet response status');
            return false;
        }
        $payment_id             = $_POST['oid'];
        $failure_code           = '';
        $failure_message        = '';
        $status                 = $_POST['status'];
        if ($status != 'APPROVED') {
            $failure_code       = $_POST['fail_rc'];
            $failure_message    = $_POST['fail_reason'];
            error_log ("Cardnet charge status $status $failure_code $failure_message");
            if ($failure_code == '') {
                $failure_code = strtolower($status);
            }
        }

        try {
            $failure_code    = $this->connection->real_escape_string ($failure_code);
            $failure_message = $this->connection->real_escape_string ($failure_message);
            $this->connection->query (
              "
                UPDATE `cardnet_payment`
                SET
                  `callback_at`=NOW()
                 ,`refno`={$this->refno($payment_id)}
                 ,`cref`='{$this->cref($payment_id)}'
                 ,`failure_code`='{$failure_code}'
                 ,`failure_message`='{$failure_message}'
                WHERE `id`='$payment_id'
              "
            );
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (127,'SQL update failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
        if ($_POST['status']!='APPROVED') {
            return false;
        }
        return $payment_id;
    }

    private function createExtendedHash($values) {
      ksort($values);
      return $this->makeHash($values, $values['hash_algorithm']);
    }

    private function cref ($id) {
        return CARDNET_CODE.'_'.$this->refno($id);
    }

    private function error_log ($code,$message) {
        $this->errorCode    = $code;
        $this->error        = $message;
        if (!defined('CARDNET_ERROR_LOG') || !CARDNET_ERROR_LOG) {
            return;
        }
        error_log ($code.' '.$message);
    }

    public function errorMessage ( ) {
        if (array_key_exists('fail_reason',$_POST) && $_POST['fail_reason']) {
            return $_POST['fail_reason'];
        }
        return '[no message]';
    }

    private function execute ($sql_file) {
        $sql = $this->sql_instantiate (file_get_contents($sql_file));
        try {
            $result = $this->connection->query ($sql);
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (126,'SQL execute failed: '.$e->getMessage());
            throw new \Exception ('SQL execution error');
            return false;
        }
        return $result;
    }

    public function import ($from) {
        $from               = new \DateTime ($from);
        $this->from         = $from->format ('Y-m-d');
        $this->output_mandates ();
        $this->output_collections ();
    }

    private function makeHash($values, $longalg) {
      $alg = $this->shortAlgName($longalg);
      $stringToHash = implode('|', $values);
      $hash = base64_encode(hash_hmac($alg, $stringToHash, CARDNET_SECRET, true));
      return $hash;
    }
 
    private function output_collections ( ) {
        $sql                = "INSERT INTO `".CARDNET_TABLE_COLLECTION."`\n";
        $sql               .= file_get_contents (__DIR__.'/select_collection.sql');
        $sql                = $this->sql_instantiate ($sql);
        echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} collections\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (125,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
    }

    private function output_mandates ( ) {
        $sql                = "INSERT INTO `".CARDNET_TABLE_MANDATE."`\n";
        $sql               .= file_get_contents (__DIR__.'/select_mandate.sql');
        $sql                = $this->sql_instantiate ($sql);
        echo $sql;
        try {
            $this->connection->query ($sql);
            tee ("Output {$this->connection->affected_rows} mandates\n");
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (124,'SQL insert failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
    }

    private function prinput($name, $value, $label=false) {  //tidy up?
      ?>
      <p
      <?php 
      if (!$label) echo " hidden";
      ?>
      >
      <?php if ($label) { ?>
      <label for="<?php echo $name; ?>"><?php echo $label ?>:</label>
      <?php } ?>
      <input type="text" name="<?php echo $name ?>" value="<?php echo $value; ?>" <?php if (!$label) echo 'readonly="readonly"'; ?>/>
      </p>
      <?php
    }

    public function reference ( ) {
        if (array_key_exists('oid',$_POST) && $_POST['oid']) {
            return CARDNET_REFNO_OFFSET + $_POST['oid'];
        }
        return false;
    }

    private function refno ($id) {
        return CARDNET_REFNO_OFFSET + $id;
    }

    private function responseHash() {
      // approval_code|chargetotal|currency|txndatetime|storename
      $values = [
        $_POST['approval_code'],
        $_POST['chargetotal'],
        $_POST['currency'],
        $_POST['txndatetime'],
        CARDNET_STORE_ID,
      ];
      return $this->makeHash($values, $_POST['hash_algorithm']);
    }

    private function setup ( ) {
        foreach ($this->constants as $c) {
            if (!defined($c)) {
                $this->error_log (123,"Configuration error $c not defined");
                throw new \Exception ("Configuration error $c not defined");
                return false;
            }
        }
        $sql                = "SELECT DATABASE() AS `db`";
        try {
            $db             = $this->connection->query ($sql);
            $db             = $db->fetch_assoc ();
            $this->database = $db['db'];
            // Create the table if not exists
            $this->execute (__DIR__.'/create_payment.sql');
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (122,'SQL select failed: '.$e->getMessage());
            throw new \Exception ('SQL database error');
            return false;
        }
    }

    private function shortAlgName($longalg) {
      if ($longalg == 'HMACSHA256') {
        return 'sha256';
      }
      else if ($longalg == 'HMACSHA384') {
        return 'sha384';
      }
      else if ($longalg == 'HMACSHA512') {
        return 'sha512';
      }
      exit ('unknown hash_algorithm');
    }

    private function sql_instantiate ($sql) {
        $sql                = str_replace ('{{CARDNET_FROM}}',$this->from,$sql);
        $sql                = str_replace ('{{CARDNET_CODE}}',CARDNET_CODE,$sql);
        return $sql;
    }

    public function start (&$err) {
        $v = www_signup_vars ();
        $today = date ('Y-m-d');
        if ($v['collection_date']) {
            $dt = new \DateTime ($v['collection_date']);
            if (defined('BLOTTO_INSURE_DAYS') && BLOTTO_INSURE_DAYS>0) {
                $dt->sub (new \DateInterval('P'.BLOTTO_INSURE_DAYS.'D'));
            }
            $dt = $dt->format ('Y-m-d');
            if ($dt<$today) {
                $v['collection_date'] = $today;
            }
            else {
                $v['collection_date'] = $dt;
            }
        }
        else {
            $v['collection_date'] = $today;
        }
        foreach ($v as $key => $val) {
            if (preg_match('<^pref_>',$key)) {
                $v[$key] = yes_or_no ($val,'Y','N');
                continue;
            }
            $v[$key] = $this->connection->real_escape_string ($val);
        }
        $amount = intval($v['quantity']) * intval($v['draws']) * BLOTTO_TICKET_PRICE;
        $pounds_amount = number_format ($amount/100,2,'.','');
        $sql = "
          INSERT INTO `cardnet_payment`
          SET
            `collection_date`='{$v['collection_date']}'
           ,`quantity`='{$v['quantity']}'
           ,`draws`='{$v['draws']}'
           ,`amount`='{$pounds_amount}'
           ,`title`='{$v['title']}'
           ,`name_first`='{$v['name_first']}'
           ,`name_last`='{$v['name_last']}'
           ,`dob`='{$v['dob']}'
           ,`email`='{$v['email']}'
           ,`mobile`='{$v['mobile']}'
           ,`telephone`='{$v['telephone']}'
           ,`postcode`='{$v['postcode']}'
           ,`address_1`='{$v['address_1']}'
           ,`address_2`='{$v['address_2']}'
           ,`address_3`='{$v['address_3']}'
           ,`town`='{$v['town']}'
           ,`county`='{$v['county']}'
           ,`gdpr`='{$v['gdpr']}'
           ,`terms`='{$v['terms']}'
           ,`pref_email`='{$v['pref_email']}'
           ,`pref_sms`='{$v['pref_sms']}'
           ,`pref_post`='{$v['pref_post']}'
           ,`pref_phone`='{$v['pref_phone']}'
          ;
        ";
        try {
            $this->connection->query ($sql);
            $newid = $this->connection->insert_id;
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (121,'SQL insert failed: '.$e->getMessage());
            $err[] = 'Sorry something went wrong - please try later';
            return;
        }
        require __DIR__.'/form.php';
    }

    public function success ( ) {
        return array_key_exists('status',$_POST) && $_POST['status']=='APPROVED';
    }

    private function supporter_add ($payment_id) {
        try {
            $s = $this->connection->query (
              "
                SELECT
                  *
                FROM `cardnet_payment`
                WHERE `id`='$payment_id'
                LIMIT 0,1
              "
            );
            $s = $s->fetch_assoc ();
            if (!$s) {
                $this->error_log (120,"cardnet_payment id '$payment_id' was not found");
                throw new \Exception ("cardnet_payment id '$payment_id' was not found");
                return false;
            }
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (119,'SQL select failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
        // Get first draw dates
        if ($s['collection_date']) {
            $draw_first     = new \DateTime (draw_first_asap($s['collection_date']));
        }
        else {
            $draw_first     = new \DateTime (draw_first_asap($this->today));
        }
        $draw_closed        = $draw_first->format ('Y-m-d');
        // Insert a supporter, a player and a contact
        echo "    Running signup() for '{$s['cref']}'\n";
        signup ($this->org,$s,CARDNET_CODE,$s['cref'],$draw_closed);
        // Add tickets
        echo "    Adding tickets for '{$s['cref']}'\n";
        $tickets            = tickets (CARDNET_CODE,$s['refno'],$s['cref'],$s['quantity']);
        // Return "rich text" data
        try {
            $d = $this->connection->query (
              "SELECT drawOnOrAfter('$draw_closed') AS `draw_date`;"
            );
            $d = $d->fetch_assoc ();
            if (!$d) {
                $this->error_log (118,'SQL failed: '.$e->getMessage());
                throw new \Exception ("SQL function could not be run");
                return false;
            }
        }
        catch (\mysqli_sql_exception $e) {
            $this->error_log (117,'SQL select failed: '.$e->getMessage());
            throw new \Exception ('SQL error');
            return false;
        }
        $draw_date          = new \Datetime ($d['draw_date']);
        return [
            'To'                => $s['name_first'].' '.$s['name_last'].' <'.$s['email'].'>',
            'Title'             => $s['title'],
            'Name'              => $s['name_first'].' '.$s['name_last'],
            'Email'             => $s['email'],
            'Mobile'            => $s['mobile'],
            'First_Name'        => $s['name_first'],
            'Last_Name'         => $s['name_last'],
            'Reference'         => $s['cref'],
            'Chances'           => $s['quantity'],
            'Tickets'           => implode (',',$tickets),
            'Draws'             => $s['draws'],
            'First_Draw_Closed' => $draw_first->format ('l jS F Y'),
            'First_Draw_Day'    => $draw_date->format ('l jS F Y'),
            'First_Draw'        => $draw_date->format ('l jS F Y')
        ];
    }

}
