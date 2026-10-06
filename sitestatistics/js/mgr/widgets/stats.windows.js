siteStatistics.window.StatUsers = function (config) {
	config = config || {};

	Ext.applyIf(config, {
		stateful: false,
		width: 700,
		autoHeight: true,
		layout: 'anchor',
		title: _('sitestatistics_users'),
		//modal: true,
		items: [{
			xtype: 'sitestatistics-stats-users-grid',
			baseParams: {
				action: 'mgr/statistics/getusers',
				data: config.data,
				show_total: config.show_total
			}
		}],
		buttons: [{
			text: _('sitestatistics_close'),
			id: 'sitestatistics-close-btn',
			handler: function(){this.hide();},
			scope: this
		}]
	});
	siteStatistics.window.StatUsers.superclass.constructor.call(this, config);
};
Ext.extend(siteStatistics.window.StatUsers, MODx.Window);
Ext.reg('sitestatistics-stats-users-window', siteStatistics.window.StatUsers);

/**************************************************************/

siteStatistics.grid.StatUsersGrid = function (config) {
	config = config || {};
	Ext.applyIf(config, {
		columns: [{
			header: 'ID',
			dataIndex: 'user_key',
			sortable: true,
			hidden: true,
			width: 50
		}, {
			header: _('sitestatistics_user'),
			dataIndex: 'fullname',
			sortable: false,
			width: 100,
			renderer: function (value) {
				var name = Ext.util.Format.htmlEncode(value || '');
				return '<a href="#" action="openUserTab">' + name + '</a>';
			}
		}, {
			header: _('sitestatistics_views'),
			dataIndex: 'views',
			sortable: false,
			width: 100
		}, {
			header: '<i class="icon icon-cog" style="margin-left: 2px;"></i>',
			dataIndex: 'user_key',
			sortable: false,
			fixed: true,
			width: 40,
			renderer: function () {
				return '<ul class="sitestatistics-row-actions"><li><button type="button" class="btn btn-default icon icon-cog" action="openUserTab" title="' +
					_('sitestatistics_show_on_users_tab') + '"></button></li></ul>';
			}
		}],
		fields: ['user_key', 'fullname', 'views', 'uid'],
		url: siteStatistics.config.connector_url,
		viewConfig: {
			forceFit: true,
			enableRowBody: true,
			autoFill: true,
			showPreview: true,
			scrollOffset: 0
		},
		paging: true,
		pageSize: 10,
		remoteSort: true,
		autoHeight: true

	});
	siteStatistics.grid.StatUsersGrid.superclass.constructor.call(this, config);
};
Ext.extend(siteStatistics.grid.StatUsersGrid, MODx.grid.Grid, {
	openUserTab: function (btn, e, row) {
		var userKey = row && row.data ? String(row.data.user_key || '') : '';
		if (!userKey) {
			return false;
		}
		var win = this.findParentByType('sitestatistics-stats-users-window');
		if (win) {
			win.close();
		}
		var tabs = Ext.getCmp('sitestatistics-home-tabs');
		if (tabs) {
			tabs.setActiveTab('sitestatistics-tab-users');
		}
		var grid = Ext.getCmp('sitestatistics-grid-users');
		if (!grid) {
			return false;
		}
		var date = Ext.getCmp('sitestatistics-grid-users-date-field');
		var search = Ext.getCmp('sitestatistics-grid-users-search-field');
		if (date) {
			date.setValue('');
		}
		if (search) {
			search.setValue(userKey);
		}
		grid.getStore().baseParams.date = '';
		grid.getStore().baseParams.query = userKey;
		var tb = grid.getBottomToolbar();
		if (tb && tb.doLoad) {
			tb.doLoad(0);
		} else {
			grid.getStore().load();
		}
	},
	onClickHandler: function (e) {
		var elem = e.getTarget('[action=openUserTab]', 10);
		if (!elem) {
			return;
		}
		e.preventDefault();
		var rowIndex = this.getView().findRowIndex(elem);
		var row = rowIndex !== false ? this.getStore().getAt(rowIndex) : null;
		if (!row) {
			return;
		}
		return this.openUserTab(this, e, row);
	}
});
Ext.reg('sitestatistics-stats-users-grid', siteStatistics.grid.StatUsersGrid);