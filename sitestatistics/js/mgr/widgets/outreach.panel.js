siteStatistics.panel.Outreach = function (config) {
	config = config || {};
	Ext.apply(config, {
		border: false,
		baseCls: 'modx-formpanel',
		cls: 'main-wrapper',
		html: '<div id="sitestatistics-outreach-root" style="font:14px/1.45 Arial,Helvetica,sans-serif;color:#1f2937;">' +
			'<div style="margin-bottom:12px;color:#6b7280;">' + _('outreach_loading') + '</div>' +
			'</div>',
		listeners: {
			afterrender: {
				fn: function () {
					this.loadStats();
				},
				scope: this
			}
		}
	});
	siteStatistics.panel.Outreach.superclass.constructor.call(this, config);
};
Ext.extend(siteStatistics.panel.Outreach, Ext.Panel, {
	loadStats: function () {
		var me = this;
		var root = Ext.get('sitestatistics-outreach-root');
		if (!root) return;
		root.update('<div style="margin-bottom:12px;color:#6b7280;">' + _('outreach_loading') + '</div>');
		MODx.Ajax.request({
			url: siteStatistics.config.connector_url,
			params: { action: 'mgr/outreach/getdata' },
			listeners: {
				success: {
					fn: function (r) {
						var o = (r && r.object) ? r.object : (r || {});
						if (o && o.statusCode && Number(o.statusCode) >= 400) {
							root.update('<div style="color:#b91c1c;">' + _('outreach_error') +
								(o.message ? ' (' + Ext.util.Format.htmlEncode(String(o.message)) + ')' : '') +
								'</div>');
							return;
						}
						root.update(me.renderHtml(o));
						var btn = Ext.get('sitestatistics-outreach-refresh');
						if (btn) {
							btn.removeAllListeners();
							btn.on('click', function () { me.loadStats(); });
						}
					}
				},
				failure: {
					fn: function (r) {
						var msg = _('outreach_error');
						try {
							if (r && r.message) msg += ' (' + Ext.util.Format.htmlEncode(String(r.message)) + ')';
						} catch (e) {}
						root.update('<div style="color:#b91c1c;">' + msg + '</div>');
					}
				}
			}
		});
	},
	esc: function (s) {
		return Ext.util.Format.htmlEncode(String(s == null ? '' : s));
	},
	renderHtml: function (o) {
		o = o || {};
		var pct = Number(o.progressPct || 0);
		if (isNaN(pct)) pct = 0;
		var bar = Math.max(0, Math.min(100, pct));
		var last = o.lastSentAt ? String(o.lastSentAt).replace('T', ' ').replace(/\.\d+Z?$/, ' UTC') : '—';
		var updated = o.updatedAt ? String(o.updatedAt).replace('T', ' ').replace(/\.\d+Z?$/, ' UTC') : '—';
		var today = (o.sentToday || 0) + ' / ' + (o.dailyLimit || 0);
		if (o.remainingToday != null) today += ' (ещё ' + o.remainingToday + ')';
		var me = this;
		function card(label, value, color) {
			return '<div style="flex:1;min-width:140px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;">' +
				'<div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;">' + label + '</div>' +
				'<div style="margin-top:6px;font-size:26px;font-weight:700;color:' + (color || '#111827') + ';">' + value + '</div>' +
				'</div>';
		}
		var recent = '';
		if (Ext.isArray(o.recentClicks) && o.recentClicks.length) {
			recent = '<div style="margin-top:16px;"><div style="font-weight:600;margin-bottom:8px;">' +
				_('outreach_recent_clicks') + '</div><div style="font-size:13px;color:#4b5563;line-height:1.55;">';
			Ext.each(o.recentClicks.slice(0, 8), function (c) {
				var ts = c.ts ? String(c.ts).replace('T', ' ').replace(/\.\d+Z?$/, '') : '';
				recent += '<div>' + me.esc(ts) + ' · ' + me.esc(c.email || '') +
					(c.company ? ' · ' + me.esc(c.company) : '') +
					' · <span style="color:#0b3d5c;">' + me.esc(c.campaign || 'kp-gis') + '</span></div>';
			});
			recent += '</div></div>';
		}
		return '' +
			'<div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:18px;">' +
			card(_('outreach_total'), o.total || 0) +
			card(_('outreach_sent'), o.sent || 0, '#0b7a4b') +
			card(_('outreach_pending'), o.pending || 0, '#0b3d5c') +
			card(_('outreach_failed'), o.failed || 0, '#b91c1c') +
			card(_('outreach_today'), today, '#d6452e') +
			card(_('outreach_clicks'), (o.clicks || 0) + ' / ' + (o.clicksUnique || 0), '#7c3aed') +
			card(_('outreach_followup'), o.followupD3Sent || 0, '#0b7a4b') +
			'</div>' +
			'<div style="margin-bottom:8px;font-weight:600;">' + _('outreach_progress') + ': ' + bar + '%</div>' +
			'<div style="height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-bottom:16px;">' +
			'<div style="height:100%;width:' + bar + '%;background:linear-gradient(90deg,#0b3d5c,#d6452e);"></div></div>' +
			'<div style="color:#4b5563;font-size:13px;line-height:1.6;">' +
			'<div><strong>' + _('outreach_campaign') + ':</strong> ' + me.esc(o.campaign || 'kp-gis') + '</div>' +
			'<div><strong>' + _('outreach_last') + ':</strong> ' + me.esc(last) + '</div>' +
			'<div><strong>' + _('outreach_updated') + ':</strong> ' + me.esc(updated) + '</div>' +
			'<div style="margin-top:10px;"><a href="https://studiowest.ru/k-p-gis" target="_blank">' + _('outreach_kp_link') + '</a></div>' +
			'</div>' +
			recent +
			'<div style="margin-top:16px;">' +
			'<button type="button" class="x-btn" id="sitestatistics-outreach-refresh">' + _('outreach_refresh') + '</button>' +
			'</div>';
	}
});
Ext.reg('sitestatistics-panel-outreach', siteStatistics.panel.Outreach);
