<?php

/*
 * (c) LX <lxhost.com@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace ZwWebPaymentBitcoinCash;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Plugin;
use ZwWebPaymentBitcoinCash\Components\BitcoinCash\AddressValidator;
use Shopware\Bundle\AttributeBundle\Service\DataLoader;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;

class ZwWebPaymentBitcoinCash extends Plugin
{
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentBitcoinCash' => 'onGetControllerPathFrontend',
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_PaymentBitcoinCash' => 'onGetControllerPathBackend',
        );
    }

    public function install(InstallContext $context)
    {
        $sql_a = "CREATE TABLE IF NOT EXISTS `zwilla_free_bitcoincash_address` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_order` int(11),
            `value_in_BCH` double NOT NULL,
            `address` varchar(64) NOT NULL,
            `status` enum('Pending','AwaitingConfirmations','UnderPaid','Paid','OverPaid') NOT NULL DEFAULT 'Pending',
            `crdate` datetime NOT NULL,
            `update` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `id_order` (`id_order`),
            UNIQUE KEY `address` (`address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        Shopware()->Db()->exec($sql_a);

        $sql_b = "CREATE TABLE IF NOT EXISTS `zwilla_free_bitcoincash_transaction` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `transaction_hash` varchar(255) NOT NULL,
            `address` varchar(64) NOT NULL,
            `confirmations` tinyint(3) unsigned NOT NULL DEFAULT '0',
            `value_in_satoshi` double NOT NULL,
            `crdate` datetime NOT NULL,
            `update` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `transaction_hash` (`transaction_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        Shopware()->Db()->exec($sql_b);

        $database_name = Shopware()->Db()->fetchOne('SELECT DATABASE()');
        $database_engine = Shopware()->Db()->fetchOne("SELECT `ENGINE` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA`='".$database_name."' AND `TABLE_NAME`='zwilla_free_bitcoincash_address'");

        if ($database_engine == 'InnoDB') {
            $result1 = Shopware()->Db()->fetchOne("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE `TABLE_NAME` = 'zwilla_free_bitcoincash_address' AND `CONSTRAINT_NAME` = 'zwilla_free_bitcoincash_address_ibfk_1' AND `TABLE_SCHEMA` = '".$database_name."'");
            if (empty($result1)) {
                Shopware()->Db()->exec("ALTER IGNORE TABLE `zwilla_free_bitcoincash_address` ADD CONSTRAINT `zwilla_free_bitcoincash_address_ibfk_1` FOREIGN KEY (`id_order`) REFERENCES `s_order_attributes` (`orderID`) ON DELETE CASCADE ON UPDATE CASCADE");
            }

            $result2 = Shopware()->Db()->fetchOne("SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE `TABLE_NAME` = 'zwilla_free_bitcoincash_transaction' AND `CONSTRAINT_NAME` = 'zwilla_free_bitcoincash_transaction_ibfk_1' AND `TABLE_SCHEMA` = '".$database_name."'");
            if (empty($result2)) {
                Shopware()->Db()->exec("ALTER IGNORE TABLE `zwilla_free_bitcoincash_transaction` ADD CONSTRAINT `zwilla_free_bitcoincash_transaction_ibfk_1` FOREIGN KEY (`address`) REFERENCES `zwilla_free_bitcoincash_address` (`address`) ON DELETE CASCADE ON UPDATE CASCADE");
            }
        }

        $this->createMyPayment();

        parent::install($context);
    }


    /**
     * @inheritdoc
     */
    public function uninstall(UninstallContext $context)
    {
        parent::uninstall($context);
    }


    /**
     * @param UpdateContext $contex
     * @return array|bool
     * @internal param string $version
     */
    public function update(UpdateContext $contex)
    {
        $version ='0.0.2';
        if ($version == '0.0.1') {
            return false;
        }

        parent::update($context);
    }


    /**
     * Returns the path to a frontend controller for an event.
     *
     * @return string
     */
    public function onGetControllerPathFrontend(\Enlight_Event_EventArgs $args)
    {
        Shopware()->Template()->addTemplateDir($this->getPath() . '/Views/');

        return $this->getPath() . '/Controllers/Frontend/PaymentBitcoinCash.php';
    }


    /**
     * Returns the path to a backend controller for an event.
     *
     * @return string
     */
    public function onGetControllerPathBackend(\Enlight_Event_EventArgs $args)
    {
        Shopware()->Template()->addTemplateDir($this->getPath() . '/Views/');
        Shopware()->Snippets()->addConfigDir($this->getPath() . '/Snippets/');

        return $this->getPath() . '/Controllers/Backend/PaymentBitcoinCash.php';
    }


    /**
     * Creates and save the payment row.
     */
    private function createMyPayment()
    {
        $options = array(
                'name' => 'zwillaweb_payment_bitcoincash',
                'description' => 'BitcoinCash',
                'action' => 'payment_bitcoincash',
                'active' => 1,
                'position' => 0,
                'additionalDescription' => 'Pay save and secured with BitcoinCash.'
            );

        $payment = Shopware()->Models()->getRepository('Shopware\Models\Payment\Payment')->findOneBy(array('name' => 'zwillaweb_payment_bitcoincash'));

        if ($payment === null) {
            $payment = new \Shopware\Models\Payment\Payment();
            $payment->setName($options['name']);
            Shopware()->Models()->persist($payment);
        };
        $payment->fromArray($options);
        Shopware()->Models()->flush($payment);

        return $payment;
    }
}