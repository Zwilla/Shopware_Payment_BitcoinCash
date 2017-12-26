/*
 * (c) LX <lxhost.com@gmail.com>
 * (c) 2017 Miguel Padilla <miguel.padilla@zwilla.de>
 * Donations: BCH:1L81xy6FoMHpNWxFtKTKGbsz9Sye1sSpSp BTC:1kD11aS83Du87EigaCodD8HVYmurHgT6i  ETH:0x8F2E4fd2f76235f38188C2077978F3a0B278a453
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

Ext.define('Shopware.apps.PaymentBitcoinCash.controller.Main', {
    extend: 'Enlight.app.Controller',

    refs: [
        { ref: 'window', selector: 'bitcoincash-main-window' },
        { ref: 'detail', selector: 'bitcoincash-main-detail' },
        { ref: 'list', selector: 'bitcoincash-main-list' },
        { ref: 'shopCombo', selector: 'bitcoincash-main-list [name=shopId]' }
    ],

    stores: ['main.List', 'main.Detail'],
    models: ['main.List', 'main.Detail'],
    views: ['main.Window', 'main.List', 'main.Detail'],

    snippets:  {
        error: {
            title: '{s name="ErrorTitle"}Transaction not found{/s}',
            general: '{s name="errorMessageGeneral"}<b>Possible cause:</b><br>[0]<br><br><b>Actual error message:</b><br>[1]{/s}',
            10007: '{s name="invalidTransaction"}The transaction is not valid or not known to BitcoinCash, not paid yet{/s}'
        }
    },

    /**
     * The main window instance
     * @object
     */
    mainWindow: null,

    init: function () {
        var me = this;

        // Init main window
        me.mainWindow = me.getView('main.Window').create({
            autoShow: true,
            scope: me
        });

        // Register events
        me.control({
            'bitcoincash-main-list': {
                selectionchange: me.onSelectionChange,
                shopSelectionChanged: me.onShopSelectionChanged
            },
            'bitcoincash-main-list [name=searchfield]': {
                change: me.onSearchForm
            }
        });
    },

    /**
     * Returns the currently selected shop.
     *
     * If there is no vaild shop selected, the first shop is returned, if that fails, 0 is returned.
     * In the later case, the controller should select the shop via getActiveDefault()
     *
     * @returns int
     */
    getSelectedShop: function() {
        var me = this,
            shopCombo = me.getShopCombo(),
            shopId = shopCombo.getValue(),
            first = shopCombo.store.first();

        if (typeof(shopId) != "number") {
            if (first && first.get('id')) {
                return first.get('id');
            }
            return 0;
        }

        return shopId;
    },

    /**
     * Callback function called when the user changed the shop selection combo
     *
     * @param shopId
     */
    onShopSelectionChanged: function(shopId) {
        var me = this,
            grid = me.getList(),
            store = grid.store;

        if (typeof(shopId) != "number" && shopId != '' && shopId != null) {
            return;
        }
        store.clearFilter(true);
        store.filter('shopId', shopId);
    },

    /**
     * Callback function triggered when the user enters something into the search field
     *
     * @param field
     * @param value
     */
    onSearchForm: function(field, value) {
        var me = this;
        var store = me.getStore('main.List');
        if (value.length === 0 ) {
            store.load();
        } else {
            store.load({
                filters : [{
                    property: 'search',
                    value: '%' + value + '%'
                }]
            });
        }
    },

    /**
     * Callback function triggered when the user clicks on an entry in the list
     *
     * @param table
     * @param records
     */
    onSelectionChange: function(table, records) {
        var me = this;
        var formPanel = me.getDetail(),
            record = records.length ? records[0] : null;

        me.detailStore = me.getStore('main.Detail');
        var shopId = me.getSelectedShop();

        if(record) {
            formPanel.setLoading(true);
            me.detailStore.load({
                extraParams: {
                    'shopId': shopId
                },
                filters : [{
                    property: 'bitcoincash_address',
                    value: record.get('bitcoincash_address')
                }],
                callback: me.onLoadDetail,
                scope: me
            });
            formPanel.enable();
            formPanel.setLoading(false);
        } else {
            me.getStore('main.Detail').removeAll();
            formPanel.disable();
        }
    },

    /**
     * Displays a sticky notification if available. Else the default growlmessage is shown
     *
     * @param title
     * @param message
     */
    showGrowlMessage: function(title, message) {
        if (typeof Shopware.Notification.createStickyGrowlMessage == 'function') {
            Shopware.Notification.createStickyGrowlMessage({
                title: title,
                text: message
            });
        } else {
            Shopware.Notification.createGrowlMessage(title, message);
        }
    },

    /**
     * Convenience function which will look up any given error code in order to give the user some more
     * info about what happened and what he can do about it.
     *
     * @param title
     * @param error
     * @param code
     */
    showBitcoinCashErrorMessage: function(title, error, code) {
        var me = this,
            message;

        if (!code || !me.snippets.error[code]) {
            message = error;
        } else {
            message = Ext.String.format(me.snippets.error.general, me.snippets.error[code], error);
        }

        me.showGrowlMessage(title, message);
    },

    /**
     * Callback function for the "load details" ajax request
     *
     * @param records
     * @param operation
     * @param success
     */
    onLoadDetail: function(records, operation, success) {
        var me = this,
            formPanel = me.getDetail(),
            detail = (records && records.length) ? records[0] : null,
            status, pending, fields,
            error, errorCode;

        if(!detail) {
            formPanel.disable();
            formPanel.setLoading(false);
            if (!success) {
                me.getStore('main.Detail').removeAll();
                error = operation.request.proxy.reader.rawData.message;
                errorCode = operation.request.proxy.reader.rawData.errorCode;
                me.showBitcoinCashErrorMessage(me.snippets.error.title, error, errorCode);
            }
            return;
        }
        formPanel.setLoading(false);
    }
});