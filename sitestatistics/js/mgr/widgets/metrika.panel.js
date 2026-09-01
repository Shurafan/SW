siteStatistics.panel.Metrika = function (config) {
	config = config || {};
	this.settings = {bounce_seconds: 15, bots: [], exclude_users: [], exclude_ips: []};
	Ext.apply(config, {
		border: false,
		baseCls: 'modx-formpanel',
		cls: 'main-wrapper sitestatistics-metrika',
		autoHeight: true,
		items: [{
			xtype: 'toolbar',
			cls: 'sitestatistics-metrika-toolbar',
			items: [{
				xtype: 'tbtext',
				text: _('sitestatistics_metrika_period') + ':'
			}, {
				xtype: 'modx-combo',
				id: 'sitestatistics-metrika-period',
				store: new Ext.data.ArrayStore({
					fields: ['v', 'n'],
					data: [
						[7, _('sitestatistics_metrika_period_week')],
						[30, _('sitestatistics_metrika_period_month')],
						[90, _('sitestatistics_metrika_period_quarter')],
						[365, _('sitestatistics_metrika_period_year')]
					]
				}),
				displayField: 'n',
				valueField: 'v',
				mode: 'local',
				triggerAction: 'all',
				editable: false,
				value: 30,
				width: 160,
				listeners: {
					select: {fn: this.reloadCharts, scope: this}
				}
			}, '->', {
				xtype: 'button',
				text: _('sitestatistics_metrika_refresh'),
				handler: this.reloadCharts,
				scope: this
			}]
		}, {
			xtype: 'box',
			id: 'sitestatistics-metrika-summary',
			cls: 'sitestatistics-metrika-summary',
			html: ''
		}, {
			xtype: 'panel',
			title: _('sitestatistics_metrika_general'),
			cls: 'sitestatistics-metrika-card',
			autoHeight: true,
			items: [{
				xtype: 'box',
				html: '<div class="sitestatistics-metrika-chart-wrap"><canvas id="sitestatistics-metrika-general-chart"></canvas></div>'
			}, {
				xtype: 'panel',
				cls: 'sitestatistics-metrika-settings',
				title: _('sitestatistics_metrika_settings_exclude'),
				border: false,
				items: [{
					xtype: 'box',
					html: '<div class="sitestatistics-metrika-settings-label">' + _('sitestatistics_metrika_exclude_users_label') + '</div>',
					cls: 'sitestatistics-metrika-settings-label-wrap'
				}, {
					xtype: 'box',
					id: 'sitestatistics-metrika-exclude-list',
					cls: 'sitestatistics-metrika-tags',
					html: ''
				}, {
					xtype: 'compositefield',
					width: 480,
					items: [{
						xtype: 'textfield',
						id: 'sitestatistics-metrika-exclude-input',
						emptyText: _('sitestatistics_metrika_exclude_hint'),
						flex: 1,
						listeners: {
							specialkey: {
								fn: function (f, e) {
									if (e.getKey() === e.ENTER) {
										this.addTag('exclude_users', 'sitestatistics-metrika-exclude-input');
									}
								},
								scope: this
							}
						}
					}, {
						xtype: 'button',
						text: _('sitestatistics_metrika_add'),
						handler: function () {
							this.addTag('exclude_users', 'sitestatistics-metrika-exclude-input');
						},
						scope: this
					}]
				}, {
					xtype: 'box',
					html: '<div class="sitestatistics-metrika-settings-label">' + _('sitestatistics_metrika_exclude_ips_label') + '</div>',
					cls: 'sitestatistics-metrika-settings-label-wrap',
					style: {marginTop: '12px'}
				}, {
					xtype: 'box',
					id: 'sitestatistics-metrika-exclude-ips-list',
					cls: 'sitestatistics-metrika-tags',
					html: ''
				}, {
					xtype: 'compositefield',
					width: 480,
					items: [{
						xtype: 'textfield',
						id: 'sitestatistics-metrika-exclude-ips-input',
						emptyText: _('sitestatistics_metrika_exclude_ips_hint'),
						flex: 1,
						listeners: {
							specialkey: {
								fn: function (f, e) {
									if (e.getKey() === e.ENTER) {
										this.addTag('exclude_ips', 'sitestatistics-metrika-exclude-ips-input');
									}
								},
								scope: this
							}
						}
					}, {
						xtype: 'button',
						text: _('sitestatistics_metrika_add'),
						handler: function () {
							this.addTag('exclude_ips', 'sitestatistics-metrika-exclude-ips-input');
						},
						scope: this
					}]
				}, {
					xtype: 'button',
					text: _('sitestatistics_metrika_save'),
					cls: 'primary-button',
					style: {marginTop: '10px'},
					handler: this.saveExcludeSettings,
					scope: this
				}]
			}]
		}, {
			xtype: 'panel',
			title: _('sitestatistics_metrika_traffic'),
			cls: 'sitestatistics-metrika-card',
			style: {marginTop: '16px'},
			autoHeight: true,
			items: [{
				xtype: 'box',
				html: '<div class="sitestatistics-metrika-chart-wrap"><canvas id="sitestatistics-metrika-traffic-chart"></canvas></div>'
			}, {
				xtype: 'panel',
				cls: 'sitestatistics-metrika-settings',
				title: _('sitestatistics_metrika_settings_bots'),
				border: false,
				items: [{
					xtype: 'box',
					id: 'sitestatistics-metrika-bots-list',
					cls: 'sitestatistics-metrika-tags',
					html: ''
				}, {
					xtype: 'compositefield',
					width: 480,
					items: [{
						xtype: 'textfield',
						id: 'sitestatistics-metrika-bots-input',
						emptyText: _('sitestatistics_metrika_bots_hint'),
						flex: 1,
						listeners: {
							specialkey: {
								fn: function (f, e) {
									if (e.getKey() === e.ENTER) {
										this.addTag('bots', 'sitestatistics-metrika-bots-input');
									}
								},
								scope: this
							}
						}
					}, {
						xtype: 'button',
						text: _('sitestatistics_metrika_add'),
						handler: function () {
							this.addTag('bots', 'sitestatistics-metrika-bots-input');
						},
						scope: this
					}]
				}]
			}]
		}, {
			xtype: 'panel',
			title: _('sitestatistics_metrika_behavior'),
			cls: 'sitestatistics-metrika-card',
			style: {marginTop: '16px'},
			autoHeight: true,
			items: [{
				xtype: 'box',
				html: '<div class="sitestatistics-metrika-chart-wrap"><canvas id="sitestatistics-metrika-behavior-chart"></canvas></div>'
			}, {
				xtype: 'panel',
				cls: 'sitestatistics-metrika-settings',
				title: _('sitestatistics_metrika_settings_bounce'),
				border: false,
				items: [{
					xtype: 'compositefield',
					width: 420,
					items: [{
						xtype: 'tbtext',
						text: _('sitestatistics_metrika_bounce_threshold') + ':',
						style: {paddingTop: '6px'}
					}, {
						xtype: 'numberfield',
						id: 'sitestatistics-metrika-bounce-input',
						width: 80,
						allowDecimals: false,
						allowNegative: false,
						value: 15,
						minValue: 0
					}, {
						xtype: 'tbtext',
						text: _('sitestatistics_metrika_seconds'),
						style: {paddingTop: '6px'}
					}, {
						xtype: 'button',
						text: _('sitestatistics_metrika_save'),
						handler: this.saveBounceThreshold,
						scope: this
					}]
				}]
			}]
		}, {
			xtype: 'panel',
			id: 'sitestatistics-metrika-status',
			border: false,
			html: '',
			cls: 'sitestatistics-metrika-status'
		}]
	});
	siteStatistics.panel.Metrika.superclass.constructor.call(this, config);
	this.on('afterrender', function () {
		this.loadSettings(true);
	}, this);
};
Ext.extend(siteStatistics.panel.Metrika, MODx.Panel, {
	charts: {},
	settings: {bounce_seconds: 15, bots: [], exclude_users: [], exclude_ips: []},

	setStatus: function (html, isError) {
		var el = Ext.getCmp('sitestatistics-metrika-status');
		if (!el) return;
		el.update(html ? ('<div class="sitestatistics-metrika-msg' + (isError ? ' is-error' : '') + '">' + html + '</div>') : '');
	},

	loadSettings: function (thenCharts) {
		var me = this;
		MODx.Ajax.request({
			url: siteStatistics.config.connector_url,
			params: {action: 'mgr/metrika/getsettings'},
			listeners: {
				success: {
					fn: function (r) {
						var o = (r && r.object) || {};
						me.settings = {
							bounce_seconds: parseInt(o.bounce_seconds, 10) || 15,
							bots: o.bots || [],
							exclude_users: o.exclude_users || [],
							exclude_ips: o.exclude_ips || []
						};
						me.renderSettings();
						if (thenCharts) me.reloadCharts();
					},
					scope: me
				},
				failure: {
					fn: function () {
						if (thenCharts) me.reloadCharts();
					},
					scope: me
				}
			}
		});
	},

	renderSettings: function () {
		var bounce = Ext.getCmp('sitestatistics-metrika-bounce-input');
		if (bounce) bounce.setValue(this.settings.bounce_seconds || 15);
		this.renderTagList('sitestatistics-metrika-bots-list', this.settings.bots || [], 'bots');
		this.renderTagList('sitestatistics-metrika-exclude-list', this.settings.exclude_users || [], 'exclude_users');
		this.renderTagList('sitestatistics-metrika-exclude-ips-list', this.settings.exclude_ips || [], 'exclude_ips');
	},

	renderTagList: function (boxId, items, field) {
		var box = Ext.getCmp(boxId);
		if (!box) return;
		var html = '';
		Ext.each(items, function (item) {
			html += '<span class="sitestatistics-tag" data-field="' + field + '" data-value="' +
				Ext.util.Format.htmlEncode(item) + '">' +
				Ext.util.Format.htmlEncode(item) +
				' <i class="icon icon-times sitestatistics-tag-remove" title="' + _('sitestatistics_metrika_remove') + '"></i></span>';
		});
		if (!html) {
			html = '<span class="sitestatistics-tag-empty">' + _('sitestatistics_metrika_list_empty') + '</span>';
		}
		box.update(html);
		var el = box.getEl();
		if (!el) return;
		el.select('.sitestatistics-tag-remove').removeAllListeners();
		el.select('.sitestatistics-tag-remove').on('click', function (e, t) {
			e.preventDefault();
			var node = Ext.get(t).findParent('span.sitestatistics-tag', 5, true);
			if (!node) return;
			this.removeTag(node.getAttribute('data-field'), node.getAttribute('data-value'));
		}, this);
	},

	addTag: function (field, inputId) {
		var input = Ext.getCmp(inputId);
		if (!input) return;
		var val = Ext.util.Format.trim(input.getValue() || '');
		if (!val) return;
		var list = [].concat(this.settings[field] || []);
		if (list.indexOf(val) === -1) {
			list.push(val);
		}
		input.setValue('');
		this.settings[field] = list;
		this.renderSettings();
		// Исключения из выборки сохраняются кнопкой «Сохранить»
		if (field === 'exclude_users' || field === 'exclude_ips') {
			return;
		}
		this.saveListField(field, list);
	},

	removeTag: function (field, value) {
		var list = [];
		Ext.each(this.settings[field] || [], function (item) {
			if (item !== value) list.push(item);
		});
		this.settings[field] = list;
		this.renderSettings();
		if (field === 'exclude_users' || field === 'exclude_ips') {
			return;
		}
		this.saveListField(field, list);
	},

	pullPendingExcludeInputs: function () {
		var map = {
			exclude_users: 'sitestatistics-metrika-exclude-input',
			exclude_ips: 'sitestatistics-metrika-exclude-ips-input'
		};
		var field;
		for (field in map) {
			if (!map.hasOwnProperty(field)) continue;
			var input = Ext.getCmp(map[field]);
			if (!input) continue;
			var val = Ext.util.Format.trim(input.getValue() || '');
			if (!val) continue;
			var list = [].concat(this.settings[field] || []);
			if (list.indexOf(val) === -1) {
				list.push(val);
			}
			input.setValue('');
			this.settings[field] = list;
		}
	},

	saveExcludeSettings: function () {
		var me = this;
		me.pullPendingExcludeInputs();
		me.renderSettings();
		MODx.Ajax.request({
			url: siteStatistics.config.connector_url,
			params: {
				action: 'mgr/metrika/savesettings',
				field: 'exclude',
				exclude_users: (me.settings.exclude_users || []).join(','),
				exclude_ips: (me.settings.exclude_ips || []).join(',')
			},
			listeners: {
				success: {
					fn: function (r) {
						var o = (r && r.object) || {};
						if (o.exclude_users) me.settings.exclude_users = o.exclude_users;
						if (o.exclude_ips) me.settings.exclude_ips = o.exclude_ips;
						me.renderSettings();
						me.setStatus(_('sitestatistics_metrika_settings_saved'), false);
						me.reloadCharts();
					},
					scope: me
				},
				failure: {
					fn: function (r) {
						me.setStatus((r && r.message) || _('sitestatistics_metrika_settings_err'), true);
					},
					scope: me
				}
			}
		});
	},

	saveListField: function (field, list) {
		var me = this;
		MODx.Ajax.request({
			url: siteStatistics.config.connector_url,
			params: {
				action: 'mgr/metrika/savesettings',
				field: field,
				value: list.join(',')
			},
			listeners: {
				success: {
					fn: function (r) {
						me.settings[field] = (r.object && r.object.value) || list;
						me.renderSettings();
						me.setStatus(_('sitestatistics_metrika_settings_saved'), false);
						me.reloadCharts();
					},
					scope: me
				},
				failure: {
					fn: function (r) {
						me.setStatus((r && r.message) || _('sitestatistics_metrika_settings_err'), true);
					},
					scope: me
				}
			}
		});
	},

	saveBounceThreshold: function () {
		var me = this;
		var input = Ext.getCmp('sitestatistics-metrika-bounce-input');
		var val = input ? parseInt(input.getValue(), 10) : 15;
		if (isNaN(val) || val < 0) val = 0;
		MODx.Ajax.request({
			url: siteStatistics.config.connector_url,
			params: {
				action: 'mgr/metrika/savesettings',
				field: 'bounce_seconds',
				value: val
			},
			listeners: {
				success: {
					fn: function (r) {
						me.settings.bounce_seconds = (r.object && r.object.value) || val;
						me.setStatus(_('sitestatistics_metrika_settings_saved'), false);
						me.reloadCharts();
					},
					scope: me
				},
				failure: {
					fn: function (r) {
						me.setStatus((r && r.message) || _('sitestatistics_metrika_settings_err'), true);
					},
					scope: me
				}
			}
		});
	},

	reloadCharts: function () {
		var me = this;
		var periodCmp = Ext.getCmp('sitestatistics-metrika-period');
		var days = periodCmp ? periodCmp.getValue() : 30;

		me.setStatus(_('sitestatistics_metrika_loading'), false);

		MODx.Ajax.request({
			url: siteStatistics.config.connector_url,
			params: {
				action: 'mgr/metrika/getdata',
				days: days
			},
			listeners: {
				success: {
					fn: function (r) {
						if (!r || r.success === false) {
							me.setStatus((r && (r.message || r.msg)) || _('sitestatistics_metrika_error'), true);
							return;
						}
						me.setStatus('', false);
						var data = r.object || r;
						// Настройки исключений берём только из getsettings/save, не из getdata
						me.renderSummary(data.summary || {});
						me.renderCharts(data);
					},
					scope: me
				},
				failure: {
					fn: function (r) {
						me.setStatus((r && (r.message || r.msg)) || _('sitestatistics_metrika_error'), true);
					},
					scope: me
				}
			}
		});
	},

	renderSummary: function (s) {
		var box = Ext.getCmp('sitestatistics-metrika-summary');
		if (!box) return;
		var fmt = function (n) {
			n = Number(n) || 0;
			return n.toLocaleString('ru-RU');
		};
		box.update(
			'<div class="sitestatistics-metrika-kpi">' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.users) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_users') + '</div></div>' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.returning) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_returning') + '</div></div>' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.views) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_pageviews') + '</div></div>' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.external) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_external') + '</div></div>' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.direct) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_direct') + '</div></div>' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.internal) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_internal') + '</div></div>' +
				'<div class="kpi"><div class="kpi-val">' + fmt(s.bots) + '</div><div class="kpi-lbl">' + _('sitestatistics_metrika_bots') + '</div></div>' +
			'</div>'
		);
	},

	destroyChart: function (key) {
		if (this.charts[key] && typeof this.charts[key].destroy === 'function') {
			this.charts[key].destroy();
		}
		this.charts[key] = null;
	},

	renderCharts: function (data) {
		if (typeof Chart === 'undefined') {
			this.setStatus(_('sitestatistics_metrika_no_chartjs'), true);
			return;
		}

		var labels = data.labels || [];
		var general = data.general || {};
		var traffic = data.traffic || {};
		var behavior = data.behavior || {};

		this.destroyChart('general');
		this.destroyChart('traffic');
		this.destroyChart('behavior');

		var gEl = document.getElementById('sitestatistics-metrika-general-chart');
		var tEl = document.getElementById('sitestatistics-metrika-traffic-chart');
		var bEl = document.getElementById('sitestatistics-metrika-behavior-chart');
		if (!gEl || !tEl || !bEl) return;

		this.charts.general = new Chart(gEl.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{label: _('sitestatistics_metrika_users'), data: general.users || [], borderColor: '#4e6ef2', backgroundColor: 'rgba(78,110,242,0.12)', tension: 0.25, fill: true, pointRadius: 0, borderWidth: 2},
					{label: _('sitestatistics_metrika_returning'), data: general.returning || [], borderColor: '#05b39a', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2},
					{label: _('sitestatistics_metrika_pageviews'), data: general.views || [], borderColor: '#fc0', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2}
				]
			},
			options: this.chartOptions()
		});

		this.charts.traffic = new Chart(tEl.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{label: _('sitestatistics_metrika_external'), data: traffic.external || [], borderColor: '#4e6ef2', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2},
					{label: _('sitestatistics_metrika_direct'), data: traffic.direct || [], borderColor: '#05b39a', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2},
					{label: _('sitestatistics_metrika_internal'), data: traffic.internal || [], borderColor: '#fc0', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2},
					{label: _('sitestatistics_metrika_bots'), data: traffic.bots || [], borderColor: '#f33', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2}
				]
			},
			options: this.chartOptions()
		});

		this.charts.behavior = new Chart(bEl.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{label: _('sitestatistics_metrika_bounce'), data: behavior.bounceRate || [], borderColor: '#f33', backgroundColor: 'rgba(255,51,51,0.08)', tension: 0.25, fill: true, pointRadius: 0, borderWidth: 2, yAxisID: 'y'},
					{label: _('sitestatistics_metrika_depth'), data: behavior.pageDepth || [], borderColor: '#05b39a', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2, yAxisID: 'y1'}
				]
			},
			options: Ext.apply(this.chartOptions(), {
				scales: {
					y: {type: 'linear', position: 'left', title: {display: true, text: '%'}, beginAtZero: true, suggestedMax: 100},
					y1: {type: 'linear', position: 'right', grid: {drawOnChartArea: false}, beginAtZero: true}
				}
			})
		});
	},

	chartOptions: function () {
		return {
			responsive: true,
			maintainAspectRatio: false,
			interaction: {mode: 'index', intersect: false},
			plugins: {
				legend: {position: 'bottom', labels: {boxWidth: 12, usePointStyle: true}},
				tooltip: {mode: 'index', intersect: false}
			},
			scales: {y: {beginAtZero: true}}
		};
	}
});
Ext.reg('sitestatistics-panel-metrika', siteStatistics.panel.Metrika);
