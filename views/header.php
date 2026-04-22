<?php
require_once __DIR__ . '/../includes/design.php';
$design = design_load();
$design_version = design_version_hash($design);
$design_origin = design_origin();
$app_slug = design_app_slug();
$design_app_name = isset($design['branding']['app_name']) ? $design['branding']['app_name'] : 'RIESTE';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagerbestands-Dashboard | <?php echo htmlspecialchars($design_app_name, ENT_QUOTES, 'UTF-8'); ?></title>

    <?php echo design_render_pre_hydration(); ?>
    <style id="rieste-design-critical"><?php echo design_render_critical_css($design); ?></style>
    <link rel="stylesheet"
          href="<?php echo htmlspecialchars($design_origin, ENT_QUOTES, 'UTF-8'); ?>/design.css?app=<?php echo urlencode($app_slug); ?>&v=<?php echo urlencode($design_version); ?>">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.dataTables.min.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>

    <link rel="stylesheet" href="assets/css/dashboard.css">

    <script>
      window.RIESTE_APP_SLUG = <?php echo json_encode($app_slug); ?>;
      window.RIESTE_DESIGN_ORIGIN = <?php echo json_encode($design_origin); ?>;
    </script>
    <script src="assets/js/theme.js" defer></script>
</head>
<body>

<div class="dashboard-wrapper">
    <header class="dashboard-header">
        <h1>Lagerbestands-Dashboard</h1>
        <div class="global-controls">
            <label for="global-time-period">Zeitraum:</label>
            <select id="global-time-period" onchange="updateTimePeriod(this.value)">
                <?php echo render_time_period_options($time_period); ?>
            </select>
            <button type="button" class="rieste-theme-toggle"
                    data-rieste-theme-toggle="cycle" title="Farbmodus wechseln">
                <span data-rieste-theme-label>Auto</span>
            </button>
        </div>
    </header>

    <nav class="tab-navigation">
        <?php
        $tabs = [
            'markt'          => 'Markt',
            'produktdetail'  => 'Produktdetail',
            'dispo'          => 'Dispo',
            'vergleich'      => 'Vergleich (alt)',
        ];
        foreach ($tabs as $key => $label):
            $active = ($page === $key) ? ' active' : '';
            $href = "index.php?page={$key}&time_period={$time_period}";
        ?>
            <a href="<?php echo $href; ?>" class="tab-link<?php echo $active; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </nav>

    <main class="dashboard-content">
