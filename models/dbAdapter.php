<?php
 class dbAdapter
 {
     private $dbh;

     private $table = 'payment';

     private function getDb()
     {
         if (!isset(self::$dbh)) {
             require_once 'config/db.php';
             $this->dbh = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['db'] . ';charset=utf8', $db['user'], $db['password']);
         }

         return$this->dbh;
     }

    /**
    *
    * @todo
    * @param $refId
    * @param $currency
    * @param $amount
    * @param $orderInfo
    * @param $url
    */
    public function saveRequest($refId, $currency, $amount, $orderInfo, $url)
    {
        $dbh = $this->getDb();

        $stmt = $dbh->prepare('INSERT INTO '
            . $this->table
            . ' (ref_id, currency, requested_amount, order_info)'
            . 'VALUES (:ref_id, :currency, :requested_amount, :order_info)'
        );
        $stmt->bindParam(':ref_id', $refId);
        $stmt->bindParam(':currency', $currency);
        $stmt->bindParam(':requested_amount', $amount);
        $stmt->bindParam(':order_info', $orderInfo);

        $stmt->execute();
    }

    public function getPaymentByRefId($refId)
    {

    }

    public function saveRespond($status, array $result, array $response)
    {

    }
 }