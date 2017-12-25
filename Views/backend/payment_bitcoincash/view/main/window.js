/*
 * (c) LX <lxhost.com@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Ext.define('Shopware.apps.PaymentBitcoinCash.view.main.Window', {
    extend: 'Enlight.app.Window',
    alias: 'widget.bitcoincash-main-window',

    width: 1200,
    height: 500,
    layout: 'border',

    title: '{s name=window/title}BitcoinCash Payments{/s}',

    initComponent: function() {
        var me = this;

        Ext.applyIf(me, {
            items: me.getItems()
        });

        me.callParent(arguments);
    },

    getItems: function() {
        var me = this;
        return [{
            region: 'east',
            xtype: 'bitcoincash-main-detail'
        }, {
            region: 'center',
            xtype: 'bitcoincash-main-list'
        }];
    }
});