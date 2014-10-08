<?php

class util
{
    const PRFIX = 'SOME_PRIFIX';

    const USD = 'USD';
    const LKR = 'LKR';

    public static $CURRENCIES = array(self::USD, self::LKR);

    public static function getUniqId()
    {
        return strtoupper(self::PRFIX . '_' . dechex(rand()) . '_' . dechex(time()));
    }

    /**
     * Use to validate the checkout form
     * @todo - This function should implement
     * @param array $post
     * @return bool
     */
    public static function isFormValid(array $post)
    {
        return true;
    }



}