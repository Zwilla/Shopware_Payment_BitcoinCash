/*
 * (c) LX <lxhost.com@gmail.com>
 * (c) 2017 Miguel Padilla <miguel.padilla@zwilla.de>
 * Donations: BCH:1L81xy6FoMHpNWxFtKTKGbsz9Sye1sSpSp BTC:1kD11aS83Du87EigaCodD8HVYmurHgT6i  ETH:0x8F2E4fd2f76235f38188C2077978F3a0B278a453
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Ext.define('Shopware.apps.PaymentBitcoinCash.store.main.List', {
	extend: 'Ext.data.Store',
	model: 'Shopware.apps.PaymentBitcoinCash.model.main.List',
	proxy: {
        type: 'ajax',
        url : '{url action=getList}',
        reader: {
            type: 'json',
            root: 'data'
        }
    },
    remoteSort: true,
    remoteFilter: true
});