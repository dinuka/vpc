<?php
class paymentService
{
    private static $responseDescriptions = array(
        '0' => 'Transaction Successful',
        '?' => 'Transaction status is unknown',
        '1' => 'Unknown Error',
        '2' => 'Bank Declined Transaction',
        '3' => 'No Reply from Bank',
        '4' => 'Expired Card',
        '5' => 'Insufficient funds',
        '6' => 'Error Communicating with Bank',
        '7' => 'Payment Server System Error',
        '8' => 'Transaction Type Not Supported',
        '9' => 'Bank declined transaction (Do not contact Bank)',
        'A' => 'Transaction Aborted',
        'C' => 'Transaction Cancelled',
        'D' => 'Deferred transaction has been received and is awaiting processing',
        'F' => '3D Secure Authentication failed',
        'I' => 'Card Security Code verification failed',
        'L' => 'Shopping Transaction Locked (Please try the transaction again later)',
        'N' => 'Cardholder is not enrolled in Authentication scheme',
        'P' => 'Transaction has been received by the Payment Adaptor and is being processed',
        'R' => 'Transaction was not processed - Reached limit of retry attempts allowed',
        'S' => 'Duplicate SessionID (OrderInfo)',
        'T' => 'Address Verification Failed',
        'U' => 'Card Security Code Failed',
        'V' => 'Address Verification and Card Security Code Failed',
    );

    public static $bankProbs = array('3', '6', '7');

    private static $statusDescriptions = array(
        'Y' => 'The cardholder was successfully authenticated.',
        'E' => 'The cardholder is not enrolled.',
        'N' => 'The cardholder was not verified.',
        'U' => "The cardholder's Issuer was unable to authenticate due to some system error at the Issuer.",
        'F' => 'There was an error in the format of the request from the merchant.',
        'A' => 'Authentication of your Merchant ID and Password to the ACS Directory Failed.',
        'D' => 'Error communicating with the Directory Server.',
        'C' => 'The card type is not supported for authentication.',
        'S' => 'The signature on the response received from the Issuer could not be validated.',
        'P' => 'Error parsing input from Issuer.',
        'I' => 'Internal Payment Server system error.',
    );

    public static $cardTypes = array(
        'AE' => 'American Express',
        'DC' => 'Diners Club',
        'JC' => 'JCB Card',
        'MC' => 'MasterCard',
        'VC' => 'Visa Card',
    );

    /**
     * @param $refId
     * @param $currency
     * @param $amount
     * @param $orderInfo
     * @param $returnUrl
     * @return string
     * @throws Exception
     */
    public static function getRequestUrl($refId, $currency, $amount, $orderInfo, $returnUrl)
    {
        if (!(is_float($amount) || is_int($amount))) {
            throw new Exception('Invalid amount');
        }

        if (filter_var($returnUrl, FILTER_VALIDATE_URL) === FALSE) {
            throw new Exception('Invalid return url');
        }

        require_once 'config/vpc.php';

        $params = $vpc[$currency];

        $vpcSecureHash = $params['vpc_secure_secret'];

        $vpcParams = $params['vpc'];
        $vpcParams['vpc_Amount'] = $amount * 100;
        $vpcParams['vpc_MerchTxnRef'] = $refId;
        $vpcParams['vpc_OrderInfo'] = $orderInfo;
        $vpcParams['vpc_ReturnURL'] = $returnUrl;

        ksort($vpcParams);

        $md5HashData = $vpcSecureHash . implode('', array_values($vpcParams));

        if (!empty($vpcSecureHash)) {
            $vpcParams['vpc_SecureHash'] = strtoupper(md5($md5HashData));
        }

        $url = $vpc['url'] .'?'. http_build_query($vpcParams);

        return $url;
    }

    public static function processResponse($currency, array $response)
    {
        if (self::isValidHash($currency, $response)) {
            $result = array(
                'status' => isset($response['vpc_TxnResponseCode']) ? $response['vpc_TxnResponseCode'] : '',
                'message' => isset(self::$responseDescriptions[$response['vpc_TxnResponseCode']]) ? self::$responseDescriptions[$response['vpc_TxnResponseCode']] : '',
                'amount' => isset($response['vpc_Amount']) ? (int) $response['vpc_Amount'] : 0,
                'card' => isset($response['vpc_Card']) ? $response['vpc_Card'] : '',
                'order_info' => isset($response['vpc_OrderInfo']) ? $response['vpc_OrderInfo'] : '',
                'ref_id' => isset($response['vpc_MerchTxnRef']) ? $response['vpc_MerchTxnRef'] : '',
            );

            if ($response['vpc_TxnResponseCode'] != 0) {
                $result['error_message'] = 'Transaction rejected, please contact your bank';
            }

            if (in_array($response['vpc_TxnResponseCode'], self::$bankProbs)) {
                $result['error_message'] = 'Transaction unsuccessful, please try again';
            }

            if (!isset($response['vpc_TxnResponseCode']) || $response['vpc_TxnResponseCode'] != '0') {
                if (isset(self::$responseDescriptions[$response['vpc_TxnResponseCode']])) {
                    throw new Exception(self::$responseDescriptions[$response['vpc_TxnResponseCode']]);
                }

                throw new Exception('Unknown vpc_TxnResponseCode');
            }

            /* Enable this section in production */
            /*
            if (!isset($response['vpc_VerStatus']) || $response['vpc_VerStatus'] != 'Y') {
                if (isset(self::$statusDescriptions[$response['vpc_VerStatus']])) {
                    throw new Exception(self::$statusDescriptions[$response['vpc_VerStatus']]);
                }

                throw new Exception('Unknown vpc_VerStatus');
            }
            */

            return $result;
        }
    }

    public static function processResult(array $result, array $response)
    {
        if (empty($result['ref_id'])) {
            return false;
        }

        require_once 'dbAdapter.php';
        require_once 'mailService.php';

        $payment = dbAdapter::getPaymentByRefId($result['ref_id']);

        if ($result['status'] === self::$responseDescriptions['0']) {
            if ((int)$payment->amount !== $result['amount']) {
                $status = app_model::PAYMENT_FAILED;
                $result['message'] = 'Paid amount is invalid';
            } else {
                $status = app_model::PAID;
            }

            mailService::sendSuccessfulMailToClient($payment);
            mailService::sendSuccessfulMailToMerchant($payment);

        } elseif (in_array($result['status'], self::$bankProbs)) {
            $status = app_model::PAYMENT_PENDING;

            mailService::sendFailMailToClient($payment);
            mailService::sendFailMailToMerchant($payment);

        } else {
            $status = app_model::PAYMENT_FAILED;

            mailService::sendFailMailToClient($payment);
            mailService::sendFailMailToMerchant($payment);
        }

        dbAdapter::saveRespond($status, $result, $response);

        return $payment['ref_id'];
    }

    private static function isValidHash($currency, array $response)
    {
        require_once 'config/vpc.php';

        $vpcSecureHash = $vpc[$currency]['vpc_secure_secret'];

        if (isset($response["vpc_SecureHash"])) {
            $vpc_Txn_Secure_Hash = $response["vpc_SecureHash"];
            unset($response["vpc_SecureHash"]);
        } else {
            throw new Exception('vpc_SecureHash not found');
        }

        $md5HashData = $vpcSecureHash . implode('', array_values($response));

        if (strtoupper($vpc_Txn_Secure_Hash) === strtoupper(md5($md5HashData))) {
            return true;
        } else {
            throw new Exception('Invalid Hash');
        }
    }
}