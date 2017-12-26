/*
 * (c) LX <lxhost.com@gmail.com>
 * (c) 2017 Miguel Padilla <miguel.padilla@zwilla.de>
 * Donations: BCH:1L81xy6FoMHpNWxFtKTKGbsz9Sye1sSpSp BTC:1kD11aS83Du87EigaCodD8HVYmurHgT6i  ETH:0x8F2E4fd2f76235f38188C2077978F3a0B278a453
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Ext.define('Shopware.apps.PaymentBitcoinCash.model.main.List', {
	extend: 'Ext.data.Model',
	fields: [
		{ name: 'id', type: 'int' },
		{ name: 'userId',  type: 'string' },
		{ name: 'bitcoincash_address', type: 'string' },
		{ name: 'valueInBCH', type: 'float' },
		{ name: 'total_paid_in_bch', type: 'float' },
		{ name: 'bchStatus', type: 'string' },

		{ name: 'clearedId', type: 'int' },
		{ name: 'statusId', type: 'int' },
		{ name: 'clearedDescription', type: 'string' },
		{ name: 'statusDescription', type: 'string' },

		{ name: 'currency', type: 'string' },
		{ name: 'amount', type: 'float' },
		{ name: 'amountFormat', type: 'string' },
		{ name: 'customer', type: 'string' },
        { name: 'customerId', type: 'string' },
		{ name: 'orderDate', type: 'date' },
		{ name: 'clearedDate', type: 'date' },
		{ name: 'orderNumber', type: 'string' },
		{ name: 'shopId', type: 'int' },
		{ name: 'shopName', type: 'string' },
		{ name: 'paymentDescription', type: 'string' },
		{ name: 'paymentKey', type: 'string' },
		{ name: 'comment', type: 'string' },

		{ name: 'invoiceId', type: 'string' },
		{ name: 'invoiceHash', type: 'string' },
		{ name: 'trackingId', type: 'string' },
		{ name: 'dispatchId', type: 'int' },
		{ name: 'dispatchDescription', type: 'string' },
        { name: 'express', type: 'int'}
	]
});