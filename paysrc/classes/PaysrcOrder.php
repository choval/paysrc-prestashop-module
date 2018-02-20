<?php
class PaysrcOrder extends ObjectModel
{
    public $id_paysrc_order;
    public $id_order;
    public $payment;
    public $checked;
    public $expires;
    public $status;
    public $updated;
    public $amount;
    public $balance;
    public $address;
	public $order_canceled;

    public static $definition = [
        'table' => 'paysrc_order',
        'primary' => 'id_paysrc_order',
        'multilang' => false,
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'payment' => ['type' => self::TYPE_INT],
            'checked' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'expires' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'status' => ['type'=> self::TYPE_STRING, 'validate' => 'isString'],
            'updated' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'amount' => ['type' => self::TYPE_FLOAT],
            'balance' => ['type' => self::TYPE_FLOAT],
            'address' => ['type' => self::TYPE_STRING],
            'order_canceled' => ['type' => self::TYPE_BOOL],
        ]
    ];

    static public function loadByPayment($payment) {
        $sql = new DbQuery() ;
        $sql->select('id_paysrc_order');
        $sql->from('paysrc_order');
        $sql->where('payment = '.(int)$payment);
        $id_paysrc_order = Db::getInstance()->getValue($sql);
        return new self($id_paysrc_order);
    }

    static public function loadByOrderId($id_order) {
        $sql = new DbQuery() ;
        $sql->select('id_paysrc_order');
        $sql->from('paysrc_order');
        $sql->where('id_order = '.(int)$id_order);
        $id_paysrc_order = Db::getInstance()->getValue($sql);
        return new self($id_paysrc_order);
    }



}

