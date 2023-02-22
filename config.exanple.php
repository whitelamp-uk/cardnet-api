<?php

// cardnet-api, a Cardnet payment class
define ( 'BLOTTO_PAY_API_CARDNET',       '/path/to/cardnet-api/PayApi.php'   );
define ( 'BLOTTO_PAY_API_CARDNET_CLASS', '\Blotto\Cardnet\PayApi'             );
define ( 'CARDNET_CMPLN_EML',        false       ); // Send completion message by email
define ( 'CARDNET_CMPLN_MOB',        false       ); // Send completion message by SMS
define ( 'CARDNET_CMPLN_EML_CM_ID',  ''          );
define ( 'CARDNET_ERROR_LOG',        true        );
define ( 'CARDNET_REFNO_OFFSET',     100000000   );
define ( 'CARDNET_DESCRIPTION',      'My Org Lottery'                );
define ( 'CARDNET_PRODUCT_NAME',     'ONE-OFF-LOTTERY-PAYMENT'               );
define ( 'CARDNET_URL',      'https://test.ipg-online.com/connect/gateway/processing' );
define ( 'CARDNET_STORE_ID', '' );
define ( 'CARDNET_SECRET',   '' );
define ( 'CARDNET_DEV_MODE', true );
define ( 'CARDNET_RESPONSE', 'https://foo.com/cardnet-finished.php');
define ( 'CARDNET_CALLBACK', 'https://foo.com/callback.php?provider='.CARDNET_CODE);


// Organisation - all payment providers
define ( 'BLOTTO_DEV_MODE',         true        );
define ( 'BLOTTO_MAX_PAYMENT',      50          );
define ( 'CAMPAIGN_MONITOR',        '/path/to/createsend-php/csrest_transactional_smartemail.php' );
define ( 'DATA8_USERNAME',          ''          );
define ( 'DATA8_PASSWORD',          ''          );
define ( 'DATA8_COUNTRY',           'GB'        );
define ( 'VOODOOSMS',               '/path/to/voodoosms/SMS.class.php' );


// Global - Cardnet
define ( 'CARDNET_CODE',             'CDNT'      ); // CCC and Provider
define ( 'CARDNET_DD',               false       ); // Does not offer direct debit
define ( 'CARDNET_BUY',              true        ); // Offers web integration
define ( 'CARDNET_TABLE_MANDATE',    'blotto_build_mandate'      );
define ( 'CARDNET_TABLE_COLLECTION', 'blotto_build_collection'   );


// Global - all payment providers
define ( 'DATA8_EMAIL_LEVEL',       'MX'        );
define ( 'VOODOOSMS_DEFAULT_COUNTRY_CODE', 44   );
define ( 'VOODOOSMS_FAIL_STRING',   'Sending SMS failed'        );
define ( 'VOODOOSMS_JSON',          __DIR__.'/voodoosms.cfg.json' );

