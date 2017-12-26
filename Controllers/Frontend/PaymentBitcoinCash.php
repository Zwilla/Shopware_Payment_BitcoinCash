<?php

/*
 * (c) LX <lxhost.com@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__ . '/../../Components/CSRFWhitelistAware.php';

class Shopware_Controllers_Frontend_PaymentBitcoinCash extends Shopware_Controllers_Frontend_Payment implements \Shopware\Components\CSRFWhitelistAware
{
    /**
     * @var Enlight_Components_Session_Namespace $session
     */
    private $session;

    /**
     * Whitelist notify- and webhook-action for bitcoincash
     */
    public function getWhitelistedCSRFActions()
    {
        return array(
            'notify'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->session = Shopware()->Session();
    }

    /**
     * {@inheritdoc}
     */
    public function preDispatch()
    {
        if (in_array($this->Request()->getActionName(), array('notify'))) {
            $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        }
    }

    /**
     * Index action method.
     *
     * Forwards to correct the action.
     */
    public function indexAction()
    {
        if ($this->getPaymentShortName() == 'zwillaweb_payment_bitcoincash') {
            $this->forward('gateway');
        } else {
            $this->redirect(array('controller' => 'checkout'));
        }
    }

    /**
     * Gateway payment action method.
     *
     * Collects the payment information and transmit it to the blockchain.info.
     */
    public function gatewayAction()
    {
        $user = $this->getUser();
        $amountdue = $this->getAmount();

        if ($user !== null && $amountdue > 0.15) {
            $limited_currencies = array('USD','ISK','HKD','TWD','CHF','EUR','DKK','CLP','CAD','CNY','THB','AUD','SGD','KRW','JPY','PLN','GBP','SEK','NZD','BRL','RUB');
            $currency_iso_code = $this->getCurrencyShortName();
            if (!in_array($currency_iso_code, $limited_currencies)) {
                $this->redirect(array('controller' => 'checkout'));
            } else {
                /** https://cex.io/api/last_price/BCH/EUR **/
                //$value_in_BCH = file_get_contents("https://blockchain.info/tobch?currency=".$currency_iso_code."&value=".$amountdue."");
                $json = file_get_contents('https://cex.io/api/last_price/BCH/EUR');
                $json = json_decode($json, true);

                $value_in_BCH = 1/($json['lprice'] / $amountdue);

                if ($value_in_BCH > 0) {
                    /** @var TYPE_NAME $zw_extended_public_key */
                    $zw_extended_public_key = Shopware()->Config()->getByNamespace('ZwWebPaymentBitcoinCash', 'zw_extended_public_key');
                    $zw_blockchain_api_key = Shopware()->Config()->getByNamespace('ZwWebPaymentBitcoinCash', 'zw_blockchain_api_key');
                    $zw_callback_secret = Shopware()->Config()->getByNamespace('ZwWebPaymentBitcoinCash', 'zw_callback_secret');

                    $config_secret = trim($zw_callback_secret);
                    $secret = strtoupper(md5($config_secret.'-'.$this->session->sUserId));

                    $url = Shopware()->Router()->assemble(array('action' => 'notify', 'controller' => 'PaymentBitcoinCash', 'module' => 'frontend', 'secret' => $secret, 'forceSecure' => true));
                    $callback_url = htmlspecialchars($url);

                    $xpub = trim($zw_extended_public_key);
                    $api_key = trim($zw_blockchain_api_key);
                    $blockchain_url = 'https://api.blockchain.info/v2/receive?xpub='.$xpub.'&callback='.urlencode($callback_url).'&key='.$api_key.'';
                    //$blockchain_url = 'https://api.blockchain.info/v2/receive?xpub='.$xpub.'&callback='.urlencode($callback_url).'&gap_limit=99&key='.$api_key.'';

                    $address = false;
                    $message = false;
                    $description = false;
                    $try = 0;

                    $context = stream_context_create(array('http' => array('ignore_errors' => true)));

                    while ($try < 2) {
                        $response = file_get_contents($blockchain_url, false, $context);
                        $object = json_decode($response);
                        if (!empty($object->{'address'})) {
                            $address = $object->{'address'};
                            break;
                        } else {
                            if (!empty($object->{'message'})) {
                                $message = $object->{'message'};
                                $description = $object->{'description'};
                                break;
                            } else {
                                $try++;
                                sleep(10);
                            }
                        }
                    }

                    if ($address) {
                        $orderNumber = $this->saveOrder($address, md5($address), 17, false);
                        //$orderId = Shopware()->Db()->fetchOne('SELECT `id` FROM `s_order` WHERE `ordernumber` = ?', $orderNumber);
                        $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array('number' => $orderNumber));
                        $orderId = $order->getId();
                        $orderCurrency = $order->getCurrency();
                        $invoiceAmount = $order->getInvoiceAmount();

                        Shopware()->Db()->exec("INSERT INTO `zwilla_free_bitcoincash_address` 
                                (`id_order`,`value_in_BCH`,`address`,`status`,`crdate`) 
                            VALUES
                                ('".(int)$orderId."', '".(double)$value_in_BCH."', '".$address."', 'Pending', CURRENT_TIMESTAMP)");

                        $order->setComment('Please pay ' . $value_in_BCH . ' BCH to this address ' . $address . ' for order number ' . $orderNumber);

                        Shopware()->Models()->flush($order);
                        $this->View()->receivedAddress = 'YES';
                        $this->View()->bitcoincashAddress = $address;
                        $this->View()->valueInBCH = $value_in_BCH;
                        $this->View()->orderNumber = $orderNumber;
                        $this->View()->invoiceAmount = $invoiceAmount;
                        $this->View()->orderCurrency = $orderCurrency;
                    } else {
                        // uncomment 3 lines below for debug
                        //$logfile = fopen(dirname(__FILE__).'/error_log.txt', 'a+');
                        //fwrite($logfile, $response);
                        //fclose($logfile);
                        Shopware()->Pluginlogger()->error('An unrecoverable error occurred: unable to obtain address, check your API key or xPub. ' . $message . ': ' . $description);
                        $this->View()->receivedAddress = 'NO';
                        $this->View()->message = $message;
                        $this->View()->description = $description;
                    }
                } else {
                    $this->redirect(array('controller' => 'checkout'));
                }
            }
        } else {
            $this->redirect(array('controller' => 'checkout'));
        }
    }

    /**
     * Notify action method
     */
    public function notifyAction()
    {
        $transaction_hash = $this->Request()->getParam('transaction_hash');
        $address = $this->Request()->getParam('address');
        $confirmations = $this->Request()->getParam('confirmations');
        $value_in_satoshi = $this->Request()->getParam('value');
        $secret = $this->Request()->getParam('secret');
        $config_secret = Shopware()->Config()->getByNamespace('ZwWebPaymentBitcoinCash', 'zw_callback_secret');

        $result = Shopware()->Db()->fetchRow("SELECT * FROM `zwilla_free_bitcoincash_address` WHERE `address` = '".$address."'");
        if ($result) {
            $id_order = $result['id_order'];
            $to_pay_in_BCH = $result['value_in_BCH'];
            $userId = Shopware()->Db()->fetchOne('SELECT `userID` FROM `s_order` WHERE `id` = ?', $id_order);
            $secret_sent = strtoupper(md5($config_secret.'-'.$userId));

            if ($secret_sent === $secret) {
                if ($confirmations <= 5) {
                    Shopware()->Db()->exec("INSERT IGNORE INTO `zwilla_free_bitcoincash_transaction` 
                        (`transaction_hash`,`address`,`confirmations`,`value_in_satoshi`,`crdate`) 
                    VALUES
                        ('".$transaction_hash."', '".$address."', '".(int)$confirmations."', '".(double)$value_in_satoshi."', CURRENT_TIMESTAMP)");

                    Shopware()->Db()->exec("UPDATE `zwilla_free_bitcoincash_address` SET `status` = 'AwaitingConfirmations' WHERE `address` = '".$address."'");
                    Shopware()->Db()->exec("UPDATE `zwilla_free_bitcoincash_transaction` SET `confirmations` = '".(int)$confirmations."' WHERE `transaction_hash` = '".$transaction_hash."'");

                    echo '*waiting 6 confirmations*';
                    exit;
                } elseif ($confirmations > 5) {
                    Shopware()->Db()->exec("INSERT IGNORE INTO `zwilla_free_bitcoincash_transaction` 
                        (`transaction_hash`,`address`,`confirmations`,`value_in_satoshi`,`crdate`) 
                    VALUES
                        ('".$transaction_hash."', '".$address."', '".(int)$confirmations."', '".(double)$value_in_satoshi."', CURRENT_TIMESTAMP)");

                    Shopware()->Db()->exec("UPDATE `zwilla_free_bitcoincash_transaction` SET `confirmations` = '".(int)$confirmations."' WHERE `transaction_hash` = '".$transaction_hash."'");
                    $total_paid_in_satoshi = (double)Shopware()->Db()->fetchOne("
                        SELECT SUM(`value_in_satoshi`)
                        FROM `zwilla_free_bitcoincash_transaction`
                        WHERE `address` = '".$address."' AND `confirmations` > 5");

                    $total_paid_in_bch = $total_paid_in_satoshi / 100000000;
                    $order = Shopware()->Modules()->Order();

                    if ($total_paid_in_bch >= $to_pay_in_BCH) {
                        if ($total_paid_in_bch == $to_pay_in_BCH) {
                            $order->setPaymentStatus($id_order, 12, true, 'Paid');
                            $order->setOrderStatus($id_order, 1, false, 'In Process');
                            Shopware()->Db()->exec("UPDATE `zwilla_free_bitcoincash_address` SET `status` = 'Paid' WHERE `address` = '".$address."'");
                        } elseif ($total_paid_in_bch > $to_pay_in_BCH) {
                            $order->setPaymentStatus($id_order, 12, true, 'OverPaid');
                            $order->setOrderStatus($id_order, 8, false, 'Clarification Required, OverPaid');
                            Shopware()->Db()->exec("UPDATE `zwilla_free_bitcoincash_address` SET `status` = 'OverPaid' WHERE `address` = '".$address."'");
                        }
                    } elseif ($total_paid_in_bch < $to_pay_in_BCH) {
                        $order->setPaymentStatus($id_order, 11, true, 'UnderPaid');
                        Shopware()->Db()->exec("UPDATE `zwilla_free_bitcoincash_address` SET `status` = 'UnderPaid' WHERE `address` = '".$address."'");
                    }

                    Shopware()->Db()->exec("UPDATE `s_order` SET `cleareddate` = NOW() WHERE `transactionID` = '".$address."'");

                    echo '*ok*';
                    exit;
                }
            } else {
                Shopware()->Pluginlogger()->error('Failed Secret, order ID: ' . $id_order);
                echo '* fail *';
                exit;
            }
        } else {
            echo '*fail*';
            exit;
        }
    }
}