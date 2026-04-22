<?php
/**
 * Shared sidebar + header shell for Fega-Lagerstand (Phase 2a).
 *
 * Expects $page + $time_period + (optional) $page_title in the caller
 * scope. Renders:
 *   aside.rieste-sidebar (48px collapsed, 220px expanded on hover)
 *     .rieste-sidebar-brand
 *     .rieste-sidebar-nav
 *     .rieste-sidebar-footer (theme toggle + Zeitraum)
 *   main.rieste-main
 *     header.rieste-header (64px)
 *     section.rieste-content (padding 24px, max-width 1400px)
 *
 * The caller is responsible for closing the content section + main tag
 * in footer.php.
 */

$nav_items = array(
    array('key' => 'markt',         'label' => 'Markt',         'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'),
    array('key' => 'produktdetail', 'label' => 'Produktdetail', 'icon' => 'M4 7h16M4 12h16M4 17h10'),
    array('key' => 'dispo',         'label' => 'Dispo',         'icon' => 'M9 12h6M12 9v6M4 4h16v16H4z'),
    array('key' => 'vergleich',     'label' => 'Vergleich',     'icon' => 'M4 6h16M4 12h16M4 18h10'),
);

$page_titles = array(
    'markt' => 'Marktuebersicht',
    'produktdetail' => 'Produktdetail',
    'dispo' => 'Dispoliste',
    'vergleich' => 'Vergleich',
);
$current_title = isset($page_titles[$page]) ? $page_titles[$page] : 'Lagerbestand';
?>
<aside class="rieste-sidebar">
    <a href="index.php?page=markt" class="rieste-sidebar-brand" title="Fega Lagerbestand">
        <span class="rieste-sidebar-brand-icon" aria-hidden="true">F</span>
        <span class="rieste-sidebar-brand-text">Fega</span>
    </a>
    <nav class="rieste-sidebar-nav">
        <?php foreach ($nav_items as $item):
            $active = ($page === $item['key']) ? ' active' : '';
            $href = 'index.php?page=' . urlencode($item['key']) . '&time_period=' . urlencode($time_period);
        ?>
            <a href="<?php echo $href; ?>"
               class="rieste-sidebar-item<?php echo $active; ?>"
               title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>">
                <svg class="rieste-sidebar-icon" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="<?php echo $item['icon']; ?>"/>
                </svg>
                <span class="rieste-sidebar-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="rieste-sidebar-footer">
        <button type="button" class="rieste-sidebar-item rieste-sidebar-toggle-btn"
                data-rieste-theme-toggle="cycle"
                title="Farbmodus wechseln">
            <svg class="rieste-sidebar-icon" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 3v18M3 12h18"/>
            </svg>
            <span class="rieste-sidebar-label" data-rieste-theme-label>Auto</span>
        </button>
        <a href="https://auth.rieste.org" target="_blank" rel="noreferrer"
           class="rieste-sidebar-item" title="RIESTE Portal">
            <svg class="rieste-sidebar-icon" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
            </svg>
            <span class="rieste-sidebar-label">Portal</span>
        </a>
    </div>
</aside>

<main class="rieste-main">
    <header class="rieste-header">
        <div class="rieste-header-left">
            <h1 class="rieste-header-title"><?php echo htmlspecialchars($current_title, ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <div class="rieste-header-right">
            <label class="rieste-header-field" for="global-time-period">
                <span class="rieste-header-field-label">Zeitraum</span>
                <select id="global-time-period" onchange="updateTimePeriod(this.value)">
                    <?php echo render_time_period_options($time_period); ?>
                </select>
            </label>
        </div>
    </header>
    <section class="rieste-content">
