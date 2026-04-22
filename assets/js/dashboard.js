/**
 * Dashboard: Navigation, AJAX, DataTables-Defaults
 */

/**
 * Navigiert zur gleichen Seite mit neuem Zeitraum.
 */
function updateTimePeriod(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('time_period', value);
    window.location.href = url.toString();
}

/**
 * Navigiert zu einer Seite mit allen aktuellen Parametern.
 */
function navigateTo(page, extraParams) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    if (extraParams) {
        for (const [key, val] of Object.entries(extraParams)) {
            url.searchParams.set(key, val);
        }
    }
    window.location.href = url.toString();
}

/**
 * AJAX-Wrapper fuer API-Aufrufe.
 */
function fetchApi(endpoint, params, onSuccess, onError) {
    $.ajax({
        url: 'api/' + endpoint,
        type: 'GET',
        data: params,
        dataType: 'json',
        success: function(data) {
            if (onSuccess) onSuccess(data);
        },
        error: function(xhr, status, error) {
            console.error('API Fehler (' + endpoint + '):', status, error);
            if (onError) onError(status, error);
        }
    });
}

/**
 * Zeigt/versteckt einen Loading-Overlay.
 */
function showLoading(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = containerId + '-loading';
    overlay.textContent = 'Daten werden geladen...';
    container.style.position = 'relative';
    container.appendChild(overlay);
}

function hideLoading(containerId) {
    const el = document.getElementById(containerId + '-loading');
    if (el) el.remove();
}

/**
 * Deutsche DataTables-Sprachkonfiguration.
 */
const DT_LANGUAGE_DE = {
    processing: "Daten werden verarbeitet...",
    search: "Suchen:",
    lengthMenu: "Zeige _MENU_ Eintr\u00e4ge",
    info: "Zeige _START_ bis _END_ von _TOTAL_ Eintr\u00e4gen",
    infoEmpty: "Zeige 0 bis 0 von 0 Eintr\u00e4gen",
    infoFiltered: "(gefiltert aus _MAX_ Gesamteintr\u00e4gen)",
    loadingRecords: "Daten werden geladen...",
    zeroRecords: "Keine passenden Eintr\u00e4ge gefunden",
    emptyTable: "Keine Daten in der Tabelle vorhanden",
    paginate: {
        first: "Erste",
        previous: "Zur\u00fcck",
        next: "N\u00e4chste",
        last: "Letzte"
    }
};

/**
 * Initialisiert eine DataTable mit deutschen Standardeinstellungen.
 */
function initDataTable(selector, extraOptions) {
    const defaults = {
        paging: true,
        searching: true,
        info: true,
        language: DT_LANGUAGE_DE,
    };
    const options = Object.assign({}, defaults, extraOptions || {});

    if ($.fn.DataTable.isDataTable(selector)) {
        $(selector).DataTable().destroy();
    }
    return $(selector).DataTable(options);
}

/**
 * Formatiert eine Zahl mit Tausendertrennzeichen (deutsch).
 */
function formatNumber(num) {
    if (num === null || num === undefined) return '-';
    return Number(num).toLocaleString('de-DE');
}

/**
 * Trend-Pfeil als HTML.
 */
function trendArrow(direction) {
    switch (direction) {
        case 'steigend': return '<span class="trend-up">&#9650;</span>';
        case 'fallend':  return '<span class="trend-down">&#9660;</span>';
        default:         return '<span class="trend-flat">&#9654;</span>';
    }
}
