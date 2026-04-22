/*
 * RIESTE theme.js — Fega
 * ----------------------
 * 1. Theme toggle (Auto / Hell / Dunkel) — persists to localStorage
 * 2. Chart.js sync — re-reads --color-* vars and calls chart.update('none')
 *    whenever data-theme changes (MutationObserver)
 * 3. ETag-polling — fetches auth.rieste.org/api/design.json every 30s and
 *    rewrites :root vars live
 *
 * Relies on window.RIESTE_DESIGN_ORIGIN + window.RIESTE_APP_SLUG being set
 * by views/header.php before this script runs.
 */
(function () {
    var ORIGIN = (window.RIESTE_DESIGN_ORIGIN || 'https://auth.rieste.org').replace(/\/$/, '');
    var SLUG = window.RIESTE_APP_SLUG || 'fega';
    var ETAG_KEY = 'rieste_design_etag';
    var THEME_KEY = 'rieste-theme';

    function readCssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function applyTokens(cfg) {
        try {
            var root = document.documentElement;
            var mode = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            var palette = (cfg.colors && cfg.colors[mode]) || {};
            Object.keys(palette).forEach(function (k) {
                root.style.setProperty('--color-' + k, palette[k]);
                var hex = palette[k].match(/^#([0-9a-f]{6})$/i);
                if (hex) {
                    var n = parseInt(hex[1], 16);
                    root.style.setProperty('--color-rgb-' + k, ((n >> 16) & 255) + ' ' + ((n >> 8) & 255) + ' ' + (n & 255));
                }
            });
            if (cfg.colors && cfg.colors.brand) {
                Object.keys(cfg.colors.brand).forEach(function (k) {
                    root.style.setProperty('--color-brand-' + k, cfg.colors.brand[k]);
                });
            }
            if (cfg.radius) {
                Object.keys(cfg.radius).forEach(function (k) {
                    root.style.setProperty('--radius-' + k, cfg.radius[k]);
                });
            }
            if (cfg.shadow) {
                Object.keys(cfg.shadow).forEach(function (k) {
                    root.style.setProperty('--shadow-' + k, cfg.shadow[k]);
                });
            }
            if (cfg.layout) {
                Object.keys(cfg.layout).forEach(function (k) {
                    root.style.setProperty('--layout-' + k.replace(/_/g, '-'), cfg.layout[k]);
                });
            }
            if (cfg.density) {
                Object.keys(cfg.density).forEach(function (k) {
                    root.style.setProperty('--density-' + k, cfg.density[k]);
                });
            }
        } catch (e) { /* swallow */ }
        refreshCharts();
    }

    /* --- Theme toggle --- */

    function currentMode() {
        return localStorage.getItem(THEME_KEY) || 'auto';
    }

    function isDark(mode) {
        if (mode === 'dark') return true;
        if (mode === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function applyMode(mode) {
        var dark = isDark(mode);
        var h = document.documentElement;
        h.setAttribute('data-theme', dark ? 'dark' : 'light');
        h.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        h.setAttribute('data-theme-mode', mode);
        // Update toggle labels
        document.querySelectorAll('[data-rieste-theme-label]').forEach(function (el) {
            el.textContent = mode === 'light' ? 'Hell' : mode === 'dark' ? 'Dunkel' : 'Auto';
        });
    }

    function cycle() {
        var order = ['auto', 'light', 'dark'];
        var next = order[(order.indexOf(currentMode()) + 1) % order.length];
        localStorage.setItem(THEME_KEY, next);
        applyMode(next);
    }

    /* --- Chart.js sync --- */

    window.RIESTE_CHARTS = window.RIESTE_CHARTS || [];

    function refreshCharts() {
        if (typeof Chart === 'undefined') return;
        try {
            // Update Chart.js defaults so newly-created charts pick up the palette
            var textColor = readCssVar('--color-text-primary') || '#0f172a';
            var gridColor = readCssVar('--color-border') || '#e2e8f0';
            Chart.defaults.color = textColor;
            if (Chart.defaults.scale && Chart.defaults.scale.grid) {
                Chart.defaults.scale.grid.color = gridColor;
            }
            if (Chart.defaults.scales) {
                ['x', 'y', 'linear', 'category', 'time'].forEach(function (ax) {
                    if (Chart.defaults.scales[ax]) {
                        Chart.defaults.scales[ax].ticks = Chart.defaults.scales[ax].ticks || {};
                        Chart.defaults.scales[ax].ticks.color = textColor;
                        Chart.defaults.scales[ax].grid = Chart.defaults.scales[ax].grid || {};
                        Chart.defaults.scales[ax].grid.color = gridColor;
                    }
                });
            }
            Chart.defaults.plugins = Chart.defaults.plugins || {};
            Chart.defaults.plugins.tooltip = Chart.defaults.plugins.tooltip || {};
            Chart.defaults.plugins.tooltip.backgroundColor = readCssVar('--color-tooltip-bg') || '#111827';
            Chart.defaults.plugins.tooltip.titleColor = readCssVar('--color-tooltip-text') || '#f9fafb';
            Chart.defaults.plugins.tooltip.bodyColor = readCssVar('--color-tooltip-text') || '#f9fafb';
        } catch (e) { /* swallow */ }

        (window.RIESTE_CHARTS || []).forEach(function (chart) {
            try { if (chart && typeof chart.update === 'function') chart.update('none'); } catch (e) { /* swallow */ }
        });
    }

    /* --- ETag polling --- */

    function tick() {
        var etag = localStorage.getItem(ETAG_KEY) || '';
        var url = ORIGIN + '/api/design.json?app=' + encodeURIComponent(SLUG);
        var headers = {};
        if (etag) headers['If-None-Match'] = etag;
        fetch(url, { headers: headers, credentials: 'omit' })
            .then(function (r) {
                if (r.status === 304) return null;
                if (r.status !== 200) return null;
                var e = r.headers.get('ETag');
                if (e) localStorage.setItem(ETAG_KEY, e);
                return r.json();
            })
            .then(function (cfg) { if (cfg) applyTokens(cfg); })
            .catch(function () { /* swallow */ });
    }

    /* --- Density (Phase 2c) --- */
    function currentDensity() {
        return localStorage.getItem('rieste-density') || 'comfortable';
    }
    function applyDensity(d) {
        document.documentElement.setAttribute('data-density', d);
        document.querySelectorAll('[data-rieste-density-label]').forEach(function (el) {
            var labels = { compact: 'Kompakt', comfortable: 'Komfortabel', spacious: 'Weit' };
            el.textContent = labels[d] || 'Komfortabel';
        });
    }
    function cycleDensity() {
        var order = ['comfortable', 'compact', 'spacious'];
        var next = order[(order.indexOf(currentDensity()) + 1) % order.length];
        localStorage.setItem('rieste-density', next);
        applyDensity(next);
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-rieste-density-toggle="cycle"]') : null;
        if (t) { e.preventDefault(); cycleDensity(); }
    });
    applyDensity(currentDensity());

    /* --- Boot --- */

    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target.closest('[data-rieste-theme-toggle="cycle"]') : null;
        if (t) { e.preventDefault(); cycle(); }
    });

    // Observe data-theme changes and repaint charts
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.attributeName === 'data-theme') {
                refreshCharts();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    // System scheme changes (only effective in 'auto' mode)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (currentMode() === 'auto') applyMode('auto');
    });

    // Apply current mode labels + kick the poll loop
    applyMode(currentMode());
    tick();
    setInterval(tick, 30000);
})();
