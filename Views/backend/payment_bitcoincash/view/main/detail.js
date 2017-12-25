/*
 * (c) LX <lxhost.com@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Ext.define('Shopware.apps.PaymentBitcoinCash.view.main.Detail', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.bitcoincash-main-detail',

    store: 'main.Detail',

    layout: 'anchor',
    border: false,
    width: 400,

    title: '{s name=detail/title}Transactions Details{/s}',

    autoScroll: true,
    bodyPadding: 5,
    collapsible: true,
    disabled: true,

    initComponent: function() {
        var me = this;
        Ext.applyIf(me, {
            columns: me.getColumns()
        });

        me.callParent(arguments);
    },

    getColumns: function() {
        var me = this;
        return [{
            text: '#',
            flex: 1,
            dataIndex: 'number'
        },{
            text: '{s name=detail/columns/transaction_text}Transaction{/s}',
            flex: 1,
            dataIndex: 'transaction_hash'
        },{
            text: '{s name=detail/columns/crdate_text}Created{/s}',
            flex: 1,
            xtype: 'datecolumn',
            format: Ext.Date.defaultFormat + ' H:i:s',
            dataIndex: 'crdate'
        },{
            text: '{s name=detail/columns/update_text}Confirmed{/s}',
            flex: 1,
            xtype: 'datecolumn',
            format: Ext.Date.defaultFormat + ' H:i:s',
            dataIndex: 'update'
        },{
            text: '{s name=detail/columns/confirmations_text}Confirmations{/s}',
            flex: 1,
            dataIndex: 'confirmations'
        },{
            text: '{s name=detail/columns/value_in_bch_text}Value in BCH{/s}',
            flex: 2,
            dataIndex: 'value_in_bch'
        }];
    }
});