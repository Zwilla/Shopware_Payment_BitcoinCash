/*
 * (c) LX <lxhost.com@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Ext.define('Shopware.apps.PaymentBitcoinCash.store.main.Detail', {
    extend: 'Ext.data.Store',
    model: 'Shopware.apps.PaymentBitcoinCash.model.main.Detail',
    proxy: {
        type: 'ajax',
        url : '{url action=getDetails}',
        reader: {
            type: 'json',
            root: 'data'
        }
    },
    remoteSort: true,
    remoteFilter: true
});