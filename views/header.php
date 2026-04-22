<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagerbestands-Dashboard</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.dataTables.min.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>

    <link rel="stylesheet" href="assets/css/dashboard.css">
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
        </div>
    </header>

    <nav class="tab-navigation">
        <?php
        $tabs = [
            'kpi'            => 'KPI-Dashboard',
            'produktdetail'  => 'Produktdetail',
            'vergleich'      => 'Vergleich Eigen/Fremd',
        ];
        foreach ($tabs as $key => $label):
            $active = ($page === $key) ? ' active' : '';
            $href = "index.php?page={$key}&time_period={$time_period}";
        ?>
            <a href="<?php echo $href; ?>" class="tab-link<?php echo $active; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </nav>

    <main class="dashboard-content">
