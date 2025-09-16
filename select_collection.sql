-- Must be a single select query
SELECT
  `created`
 ,'{{CARDNET_CODE}}'
 ,null
 ,`refno`
 ,`cref`
 ,`amount`
 ,'Paid'
FROM `cardnet_payment`
WHERE `created`>='{{CARDNET_FROM}}'
  AND `callback_at` IS NOT NULL
  AND (`failure_code` IS NULL OR `failure_code`='')
  AND (`collection_date` IS NULL OR `collection_date`<=CURDATE())
ORDER BY `id`
;
