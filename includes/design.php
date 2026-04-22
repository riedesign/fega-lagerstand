<?php
/**
 * RIESTE Design-System loader — PHP 7.0-compatible, stale-while-revalidate.
 *
 * Fetches auth.rieste.org/api/design.json?app=fega with a 2-second timeout,
 * caches the response in /tmp, falls back to the last successful cache for
 * up to 24 hours, and finally to baked-in defaults.
 *
 * Usage in views/header.php:
 *   require_once __DIR__ . '/../includes/design.php';
 *   $design = design_load();
 *   $design_version = design_version_hash($design);
 *
 * PHP 7.0-disziplin: keine fn(), kein match(), keine typed props, keine ?->
 */

if (!function_exists('design_load')) {

    function design_origin() {
        $envOrigin = getenv('DESIGN_ORIGIN');
        if ($envOrigin !== false && $envOrigin !== '') {
            return rtrim($envOrigin, '/');
        }
        return 'https://auth.rieste.org';
    }

    function design_app_slug() {
        $envSlug = getenv('DESIGN_APP_SLUG');
        if ($envSlug !== false && $envSlug !== '') {
            return $envSlug;
        }
        return 'fega';
    }

    function design_cache_path() {
        $tmp = sys_get_temp_dir();
        return $tmp . '/rieste_design_fega.json';
    }

    function design_hard_defaults() {
        return array(
            'schema_version' => '1.0.0',
            'preset' => 'rieste_navy',
            'colors' => array(
                'light' => array(
                    'primary' => '#1e3a5f',
                    'primary-hover' => '#15283f',
                    'primary-contrast' => '#ffffff',
                    'accent' => '#2e6da4',
                    'focus-ring' => '#60a5fa',
                    'sidebar-bg' => '#1e3a5f',
                    'sidebar-text' => '#cbd5e1',
                    'sidebar-text-active' => '#ffffff',
                    'bg-base' => '#f8fafc',
                    'bg-raised' => '#ffffff',
                    'bg-sunken' => '#f1f5f9',
                    'bg-hover' => '#f1f5f9',
                    'border' => '#e2e8f0',
                    'text-primary' => '#0f172a',
                    'text-secondary' => '#475569',
                    'text-muted' => '#64748b',
                    'text-disabled' => '#94a3b8',
                    'link' => '#2e6da4',
                    'link-hover' => '#1e3a5f',
                    'success-text' => '#15803d',
                    'success-bg' => '#dcfce7',
                    'warning-text' => '#b45309',
                    'warning-bg' => '#fef3c7',
                    'error-text' => '#dc2626',
                    'error-bg' => '#fee2e2',
                    'info-text' => '#1e40af',
                    'info-bg' => '#dbeafe',
                ),
                'dark' => array(
                    'primary' => '#60a5fa',
                    'primary-hover' => '#93c5fd',
                    'primary-contrast' => '#0f172a',
                    'accent' => '#93c5fd',
                    'focus-ring' => '#fbbf24',
                    'sidebar-bg' => '#0f172a',
                    'sidebar-text' => '#94a3b8',
                    'sidebar-text-active' => '#ffffff',
                    'bg-base' => '#0f172a',
                    'bg-raised' => '#1e293b',
                    'bg-sunken' => '#020617',
                    'bg-hover' => '#1e293b',
                    'border' => '#334155',
                    'text-primary' => '#f1f5f9',
                    'text-secondary' => '#cbd5e1',
                    'text-muted' => '#94a3b8',
                    'text-disabled' => '#64748b',
                    'link' => '#93c5fd',
                    'link-hover' => '#bfdbfe',
                    'success-text' => '#4ade80',
                    'success-bg' => '#14532d',
                    'warning-text' => '#fbbf24',
                    'warning-bg' => '#713f12',
                    'error-text' => '#f87171',
                    'error-bg' => '#7f1d1d',
                    'info-text' => '#60a5fa',
                    'info-bg' => '#1e3a8a',
                ),
                'brand' => array(
                    'accent-red' => '#dc2626',
                    'accent-navy' => '#1e3a5f',
                ),
            ),
            'radius' => array('sm' => '4px', 'md' => '8px', 'lg' => '12px'),
            'shadow' => array(
                'sm' => '0 1px 2px rgba(0,0,0,0.06)',
                'md' => '0 4px 8px rgba(0,0,0,0.08)',
                'lg' => '0 12px 40px rgba(0,0,0,0.15)',
            ),
            'typography' => array(
                'font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
                'size' => array(
                    'xs' => '10px', 'sm' => '12px', 'base' => '13px',
                    'lg' => '16px', 'xl' => '18px', '2xl' => '24px',
                ),
            ),
            'mode' => array('default' => 'auto', 'allow_user_toggle' => true),
            'branding' => array(
                'app_name' => 'RIESTE',
                'logo_url_light' => '/static/logos/rieste-logo.svg',
                'logo_url_dark' => '',
                'favicon_url' => '/static/logos/favicon.ico',
                'footer_text' => 'Rieste Unternehmensgruppe',
            ),
        );
    }

    function design_fetch_remote() {
        $origin = design_origin();
        $app = design_app_slug();
        $url = $origin . '/api/design.json?app=' . urlencode($app);
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => 2,
                'ignore_errors' => true,
            ),
        ));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }
        $parsed = json_decode($raw, true);
        if (!is_array($parsed) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $parsed;
    }

    function design_atomic_write($path, $content) {
        // Write to <path>.tmp, then rename — atomic on POSIX.
        $tmp = $path . '.tmp';
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            return false;
        }
        $written = @fwrite($fh, $content);
        @fclose($fh);
        if ($written === false) {
            @unlink($tmp);
            return false;
        }
        return @rename($tmp, $path);
    }

    /**
     * Load the active design config with stale-while-revalidate semantics.
     *
     * @param int $ttl Seconds to keep cache fresh without re-fetching (default 300)
     * @param int $staleTolerance Seconds to serve stale cache if fetch fails (default 24h)
     * @return array
     */
    function design_load($ttl = 300, $staleTolerance = 86400) {
        $cache = design_cache_path();
        $now = time();
        $cacheAge = PHP_INT_MAX;
        $cacheData = null;

        if (is_file($cache)) {
            $mtime = @filemtime($cache);
            if ($mtime !== false) {
                $cacheAge = $now - $mtime;
            }
            $raw = @file_get_contents($cache);
            if ($raw !== false && $raw !== '') {
                $parsed = json_decode($raw, true);
                if (is_array($parsed) && json_last_error() === JSON_ERROR_NONE) {
                    $cacheData = $parsed;
                }
            }
        }

        // Fresh cache -> use it without touching the network
        if ($cacheData !== null && $cacheAge < $ttl) {
            return $cacheData;
        }

        // Cache stale -> try remote fetch
        $remote = design_fetch_remote();
        if ($remote !== null) {
            design_atomic_write($cache, json_encode($remote));
            return $remote;
        }

        // Remote failed -> serve stale cache if within tolerance
        if ($cacheData !== null && $cacheAge < $staleTolerance) {
            return $cacheData;
        }

        // Last resort -> baked-in defaults
        return design_hard_defaults();
    }

    function design_version_hash($design) {
        return substr(sha1(json_encode($design)), 0, 12);
    }

    function design_color($design, $mode, $key, $fallback = '') {
        if (!is_array($design)) return $fallback;
        if (!isset($design['colors']) || !is_array($design['colors'])) return $fallback;
        if (!isset($design['colors'][$mode]) || !is_array($design['colors'][$mode])) return $fallback;
        if (!isset($design['colors'][$mode][$key])) return $fallback;
        $val = $design['colors'][$mode][$key];
        return is_string($val) ? $val : $fallback;
    }

    function design_radius($design, $key, $fallback = '4px') {
        if (isset($design['radius'][$key]) && is_string($design['radius'][$key])) {
            return $design['radius'][$key];
        }
        return $fallback;
    }

    /**
     * Render the critical inline <style> block with the 10 most important
     * vars for first-paint correctness (light + dark).
     */
    function design_render_critical_css($design) {
        $keys = array(
            'primary', 'primary-contrast', 'bg-base', 'bg-raised',
            'sidebar-bg', 'text-primary', 'border', 'focus-ring',
            'success-text', 'warning-text', 'error-text', 'info-text',
        );
        $out = ":root {\n";
        foreach ($keys as $k) {
            $out .= "  --color-{$k}: " . design_color($design, 'light', $k, '#000') . ";\n";
        }
        $out .= "  --radius-sm: " . design_radius($design, 'sm', '4px') . ";\n";
        $out .= "  --radius-md: " . design_radius($design, 'md', '8px') . ";\n";
        $out .= "  --radius-lg: " . design_radius($design, 'lg', '12px') . ";\n";
        $out .= "}\n";
        $out .= "[data-theme=\"dark\"] {\n";
        foreach ($keys as $k) {
            $out .= "  --color-{$k}: " . design_color($design, 'dark', $k, '#fff') . ";\n";
        }
        $out .= "}\n";
        return $out;
    }

    /**
     * Blocking pre-hydration script — sets data-theme before first paint.
     */
    function design_render_pre_hydration() {
        return "<script>(function(){try{" .
            "var s=localStorage.getItem('rieste-theme');" .
            "var m=s||'auto';" .
            "var d=m==='dark'||(m==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches);" .
            "var h=document.documentElement;" .
            "h.setAttribute('data-theme',d?'dark':'light');" .
            "h.setAttribute('data-bs-theme',d?'dark':'light');" .
            "}catch(e){}})();</script>";
    }
}
