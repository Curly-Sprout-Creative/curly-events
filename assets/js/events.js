/**
 * Curly Events — front-end scripts.
 *
 * 1. Calendar toggle + AJAX month navigation (Events Index widget).
 * 2. Hide empty card-meta spans (location/time/category).
 */
(function () {
	'use strict';

	var cfg = window.curlyEvents || {};
	var REST_URL = cfg.restUrl || '/wp-json/curly-events/v1/calendar';

	/**
	 * Calendar toggle / in-place month navigation.
	 */
	function initCalendarWidget() {
		var toggle = document.querySelector('.calendar-toggle');
		var wrap = document.querySelector('.calendar-toggle-wrap');
		if (!toggle || !wrap) return;

		var loaded = false;
		var CAL_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H64C28.7 64 0 92.7 0 128v16 48V448c0 35.3 28.7 64 64 64H384c35.3 0 64-28.7 64-64V192 144 128c0-35.3-28.7-64-64-64H344V24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H152V24zM48 192h352V448c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192z"/></svg>';
		var CLOSE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>';

		function defaultMonth() {
			var el = document.querySelector('.post-card-date');
			if (el) {
				var d = new Date(el.textContent.trim());
				if (!isNaN(d.getTime())) return { m: d.getMonth() + 1, y: d.getFullYear() };
			}
			var now = new Date();
			return { m: now.getMonth() + 1, y: now.getFullYear() };
		}

		function fetchCalendar(m, y, cb) {
			fetch(REST_URL + '?m=' + m + '&y=' + y)
				.then(function (r) { return r.json(); })
				.then(function (d) { if (d && d.html) cb(d.html); })
				.catch(function () {});
		}

		function setState(open) {
			var svg = toggle.querySelector('.oxy-svg-icon2 svg');
			var text = toggle.querySelector('.oxy-text');
			if (svg) svg.outerHTML = open ? CLOSE_SVG : CAL_SVG;
			if (text) text.textContent = open ? 'Close Calendar' : 'Calendar View';
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			wrap.classList.toggle('is-open', open);
		}

		function bindNav(root) {
			root.querySelectorAll('.cal-nav').forEach(function (a) {
				a.addEventListener('click', function (e) {
					e.preventDefault();
					var m = a.getAttribute('data-cal-m');
					var y = a.getAttribute('data-cal-y');
					if (!m || !y) return;
					fetchCalendar(m, y, function (html) {
						var cal = root.querySelector('.evt-cal-wrapper');
						var tmp = document.createElement('div');
						tmp.innerHTML = html;
						var fresh = tmp.querySelector('.evt-cal-wrapper');
						if (cal && fresh) { cal.replaceWith(fresh); bindNav(root); }
					});
				});
			});
		}

		function openCalendar() {
			if (wrap.classList.contains('is-open')) return;
			if (!loaded) {
				var dm = defaultMonth();
				fetchCalendar(dm.m, dm.y, function (html) {
					wrap.innerHTML = html;
					loaded = true;
					bindNav(wrap);
				});
			}
			setState(true);
		}

		toggle.addEventListener('click', function () {
			if (wrap.classList.contains('is-open')) {
				setState(false);
			} else {
				openCalendar();
			}
		});

		toggle.setAttribute('role', 'button');
		toggle.setAttribute('tabindex', '0');
		toggle.setAttribute('aria-expanded', 'false');
		toggle.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle.click(); }
		});

		// Events > Settings: open the calendar automatically on page load.
		if (cfg.calendarOpen) {
			openCalendar();
		}
	}

	/**
	 * Hide empty card-meta spans (O6 cards leave a stray gap).
	 */
	function initHideEmptyCardMeta() {
		document.querySelectorAll('.post-card-date-and-tax > span, .event-location, .event-recurrence').forEach(function (el) {
			if (el.textContent.trim() === '') {
				el.style.display = 'none';
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initCalendarWidget();
		initHideEmptyCardMeta();
	});
})();