<?php
/*
 * (c) LX <lxhost.com@gmail.com>
 * (c) 2017 Miguel Padilla <miguel.padilla@zwilla.de>
 * Donations: BCH:1L81xy6FoMHpNWxFtKTKGbsz9Sye1sSpSp BTC:1kD11aS83Du87EigaCodD8HVYmurHgT6i  ETH:0x8F2E4fd2f76235f38188C2077978F3a0B278a453
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Shopware_Controllers_Backend_PaymentBitcoinCash extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * List payments action.
     *
     * Outputs the payment data as json list.
     */
    public function getListAction()
    {
        $limit = $this->Request()->getParam('limit', 20);
        $start = $this->Request()->getParam('start', 0);
        $filter = $this->Request()->getParam('filter', false);

        $subShopFilter = null;
        if ($filter && !empty($filter)) {
            $filter = array_pop($filter);
            if ($filter['property'] == 'shopId') {
                $subShopFilter = (int)$filter['value'];
            }
        }

        if ($sort = $this->Request()->getParam('sort')) {
            $sort = current($sort);
        }
        $direction = empty($sort['direction']) || $sort['direction'] == 'DESC' ? 'DESC' : 'ASC';
        $property = empty($sort['property']) ? 'orderDate' : $sort['property'];

        if ($filter) {
            if ($filter['property'] == 'search') {
                $this->Request()->setParam('search', $filter['value']);
            }
        }

        $select = $this->get('db')
            ->select()
            ->from(array('o' => 's_order'), array(
                new Zend_Db_Expr('SQL_CALC_FOUND_ROWS o.id'),
                'clearedId' => 'cleared',
                'statusId' => 'status',
                'amount' => 'invoice_amount',
                'currency',
                'orderDate' => 'ordertime',
                'orderNumber' => 'ordernumber',
                'shopId' => 'subshopID',
                'bitcoincash_address' => 'transactionID',
                'comment' => 'customercomment',
                'clearedDate' => 'cleareddate',
                'trackingId' => 'trackingcode',
                'customerId' => 'u.userID',
                'invoiceId' => new Zend_Db_Expr('(' . $this->get('db')
                        ->select()
                        ->from(array('s_order_documents'), array('ID'))
                        ->where('orderID=o.id')
                        ->order('ID DESC')
                        ->limit(1) . ')'),
                'invoiceHash' => new Zend_Db_Expr('(' . $this->get('db')
                        ->select()
                        ->from(array('s_order_documents'), array('hash'))
                        ->where('orderID=o.id')
                        ->order('ID DESC')
                        ->limit(1) . ')'),
                'total_paid_in_satoshi' => new Zend_Db_Expr('(' . $this->get('db')
                        ->select()
                        ->from(array('zwilla_free_bitcoincash_transaction'), array('SUM(value_in_satoshi)'))
                        ->where('address = o.transactionID')
                        ->where('confirmations > 5') . ')')
            ))
            ->joinLeft(
                array('shops' => 's_core_shops'),
                'shops.id = o.subshopID',
                array(
                    'shopName' => 'shops.name'
                )
            )
            ->join(
                array('p' => 's_core_paymentmeans'),
                'p.id = o.paymentID',
                array(
                    'paymentDescription' => 'p.description'
                )
            )
            ->joinLeft(
                array('so' => 's_core_states'),
                'so.id = o.status',
                array(
                    'statusDescription' => 'so.description'
                )
            )
            ->joinLeft(
                array('sc' => 's_core_states'),
                'sc.id = o.cleared',
                array(
                    'clearedDescription' => 'sc.description'
                )
            )
            ->joinLeft(
                array('bcha' => 'zwilla_free_bitcoincash_address'),
                'bcha.address = o.transactionID',
                array(
                    'bchStatus' => 'bcha.status', 'valueInBCH' => 'bcha.value_in_BCH'
                )
            )
            ->joinLeft(
                array('u' => 's_user_billingaddress'),
                'u.userID = o.userID',
                array()
            )
            ->joinLeft(
                array('b' => 's_order_billingaddress'),
                'b.orderID = o.id',
                new Zend_Db_Expr("
					IF(b.id IS NULL,
						IF(u.company='', CONCAT(u.firstname, ' ', u.lastname), u.company),
						IF(b.company='', CONCAT(b.firstname, ' ', b.lastname), b.company)
					) AS customer
				")
            )
            ->joinLeft(
                array('d' => 's_premium_dispatch'),
                'd.id = o.dispatchID',
                array(
                    'dispatchDescription' => 'd.name'
                )
            )
            ->where('p.name LIKE ?', 'zwillaweb_payment_bitcoincash')
            ->where('o.status >= 0')
            ->order(array($property . ' ' . $direction))
            ->limit($limit, $start);

        if ($search = $this->Request()->getParam('search')) {
            $search = trim($search);
            $search = $this->get('db')->quote($search);

            $select->where('o.transactionID LIKE ' . $search
                . ' OR o.ordernumber LIKE ' . $search
                . ' OR u.firstname LIKE ' . $search
                . ' OR u.lastname LIKE ' . $search
                . ' OR b.firstname LIKE ' . $search
                . ' OR b.lastname LIKE ' . $search
                . ' OR b.company LIKE ' . $search
                . ' OR u.company LIKE ' . $search);
        }

        if ($subShopFilter) {
            $select->where('o.subshopID = ' . $subShopFilter);
        }
        $rows = $this->get('db')->fetchAll($select);
        $total = $this->get('db')->fetchOne('SELECT FOUND_ROWS()');

        foreach ($rows as &$row) {
            if ($row['clearedDate'] == '0000-00-00 00:00:00') {
                $row['clearedDate'] = null;
            }
            if (isset($row['clearedDate'])) {
                $row['clearedDate'] = new DateTime($row['clearedDate']);
            }
            $row['orderDate'] = new DateTime($row['orderDate']);
            $row['amountFormat'] = Shopware()->Currency()->toCurrency($row['amount'], array('currency' => $row['currency']));
            $row['total_paid_in_bch'] = $row['total_paid_in_satoshi'] / 100000000;
        }

        $this->View()->assign(array('data' => $rows, 'total' => $total, 'success' => true));
    }

    /**
     * Helper which registers a shop
     *
     * @param $shopId
     * @throws Exception
     */
    private function registerShopByShopId($shopId)
    {
        /** @var Shopware\Models\Shop\Repository $repository */
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');

        if (empty($shopId)) {
            $shop = $repository->getActiveDefault();
        } else {
            $shop = $repository->getActiveById($shopId);
            if (!$shop) {
                throw new \Exception("Shop {$shopId} not found");
            }
        }

        $bootstrap = Shopware()->Bootstrap();
        $shop->registerResources($bootstrap);
    }

    /**
     * Will register the correct shop for a given bitcoincash_address.
     *
     * @param $bitcoincash_address
     */
    public function registerShopBybitcoincash_address($bitcoincash_address)
    {
        // Query shopId and api-user if available
        $sql = '
            SELECT s_order.`subshopID`
            FROM s_order
            LEFT JOIN s_order_attributes
              ON s_order_attributes.orderID = s_order.id
            WHERE s_order.transactionID = ?
        ';
        $result = $this->get('db')->fetchOne($sql, array($bitcoincash_address));

        if (!empty($result)) {
            $this->registerShopByShopId($result);
        }
    }

    /**
     * Get payment details
     */
    public function getDetailsAction()
    {
        $filter = $this->Request()->getParam('filter');
        if (isset($filter[0]['property']) && $filter[0]['property'] == 'bitcoincash_address') {
            $this->Request()->setParam('bitcoincash_address', $filter[0]['value']);
        }
        $bitcoincash_address = $this->Request()->getParam('bitcoincash_address');

        // Load the correct shop in order to use the correct api credentials
        $this->registerShopBybitcoincash_address($bitcoincash_address);

        $select = $this->get('db')
            ->select()
            ->from(array('zwilla_free_bitcoincash_transaction' => 'zwilla_free_bitcoincash_transaction'), array(
                'transaction_hash' => 'transaction_hash',
                'confirmations' => 'confirmations',
                'value_in_satoshi' => 'value_in_satoshi',
                'crdate' => 'crdate',
                'update' => 'update'
            ))
            ->where('address = ?', $bitcoincash_address);

        $rows = $this->get('db')->fetchAll($select);
        $total = $this->get('db')->fetchOne('SELECT FOUND_ROWS()');

        $nr = 0;
        foreach ($rows as &$row) {
            $nr++;
            $row['number'] = $nr;
            $row['value_in_bch'] = $row['value_in_satoshi'] / 100000000;
        }

        if ($total > 0) {
            $this->View()->assign(array('data' => $rows, 'total' => $total, 'success' => true));
        } else {
            $this->View()->assign(array('success' => false, 'message' => 'Transaction not found for this order', 'errorCode' => 10007));
        }
    }

    public function indexAction()
    {
        $this->View()->loadTemplate("backend/payment_bitcoincash/app.js");
            $this->View()->assign(array(
                    "data" => '$store',
                    "total" => '$total',
                    "success" => true
                )
            );
    }
}