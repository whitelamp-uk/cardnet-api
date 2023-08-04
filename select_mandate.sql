-- Must be a single select query
SELECT
  '{{CARDNET_CODE}}'
 ,null
 ,`refno`
 ,`cref`
 ,`created`
 ,DATE(`callback_at`)
 ,DATE_ADD(DATE(`callback_at`),INTERVAL 1 DAY)
 ,IF(LENGTH(`failure_code`)>0,'FAILED','LIVE')
 ,'Single'
 ,`amount`
 ,`quantity`
 ,CONCAT_WS(' ',`title`,`name_first`,`name_last`)
 ,''
 ,''
 ,''
 ,`id`
 ,1
 ,`created`
 ,`created`
FROM `cardnet_payment`
WHERE `created`>='{{CARDNET_FROM}}'
  AND `callback_at` IS NOT NULL
ORDER BY `id`
;

