<?php

class paymentController
{
    const PAYMENT_FAILED_MESSAGE = 'Transaction rejected, please contact your bank';

    public function __construct()
    {
        session_start();
    }

    public function checkoutAction()
    {
        require_once 'models/util.php';

        if (!empty($_POST)) {
            if (util::isFormValid($_POST)) {
                $refId = $_POST['ref_id'];
                $currency = $_POST['currency'];
                $amount = (float) $_POST['amount'];
                $orderInfo = $_POST['desc'];

                if ($currency === util::LKR) {
                    $returnUrl = BASE_URL . 'processLkr';
                } else {
                    $returnUrl = BASE_URL . 'processUsd';
                }

                require_once 'models/paymentService.php';
                require_once 'models/dbAdapter.php';
                require_once 'models/mailService.php';

                try {
                    $url = paymentService::getRequestUrl($refId, $currency, $amount, $orderInfo, $returnUrl);

                    $db = new dbAdapter();
                    $db->saveRequest($refId, $currency, $amount, $orderInfo, $url); die;

                    mailService::sendAcknowledgementToMerchant();

                    header('Location: ' . $url);
                    exit(0);
                } catch (Exception $e) {
                    $data['error'] = $e->getMessage();
                }
            }
        }

        $data['ref_id'] = util::getUniqId();
        $data['currencies'] = util::$CURRENCIES;

        include 'views/checkout.phtml';
    }

    public function processLkrAction()
    {
        require_once 'models/util.php';

        $this->process(util::LKR, $_GET);
    }

    public function processUsdAction()
    {
        require_once 'models/util.php';

        $this->process(util::USD, $_GET);
    }

    public function confirmAction()
    {
        if (isset($_SESSION['error'])) {
            $data['error'] = $_SESSION['error'];
            unset($_SESSION['error']);
        }

        include 'views/confirm.phtml';
    }

    public function termsAction()
    {
        include 'views/terms.phtml';
    }

    private function process($currency, $response)
    {
        require_once 'models/paymentService.php';

        try {
            $result = paymentService::processResponse($currency, $response);

            $refId = paymentService::processResult($result, $response);

            header('Location: ' . BASE_URL . 'confirm.phtml?refid=' . $refId);
            exit(0);
        } catch (Exception $e) {
            echo $e->getMessage(); die;
            $_SESSION['error'] = self::PAYMENT_FAILED_MESSAGE;
            header('Location: ' . BASE_URL . 'confirm');
            exit(0);
        }
    }


}