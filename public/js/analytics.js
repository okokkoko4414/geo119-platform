(function () {
    var evtSource = new EventSource('/api/e/live');
    evtSource.addEventListener('counters', function (e) {
        var data = JSON.parse(e.data);
        document.getElementById('counter-impressions').textContent = data.impressions;
        document.getElementById('counter-clicks').textContent = data.clicks;
        var ctrEl = document.getElementById('counter-ctr');
        ctrEl.textContent = data.ctr !== null ? data.ctr + '%' : '—';
    });

    evtSource.addEventListener('optimization', function (e) {
        var data = JSON.parse(e.data);
        prependOptimizationRow(data);
    });

    var ctx = document.getElementById('time-series-chart').getContext('2d');
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'Impressions', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.05)', fill: true, tension: 0.2 },
                { label: 'Clicks', data: [], borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.05)', fill: true, tension: 0.2 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#f3f4f6' } } }
        }
    });

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function formatScore(score) {
        return (score * 100).toFixed(1) + '%';
    }

    function prependOptimizationRow(data) {
        var tbody = document.querySelector('#optimizations-table tbody');
        if (!tbody) return;
        var noDataRow = tbody.querySelector('tr[data-empty]');
        if (noDataRow) noDataRow.remove();

        var improvementPct = (data.improvement * 100).toFixed(1);
        var impClass = data.improvement >= 0 ? 'text-green-600' : 'text-red-500';
        var impSign = data.improvement >= 0 ? '+' : '';

        var row = document.createElement('tr');
        row.className = 'border-b border-surface-100 hover:bg-surface-50 cursor-pointer';
        row.style.cursor = 'pointer';
        row.onclick = function () {
            var locale = document.documentElement.lang || 'en';
            window.location = '/' + (locale !== 'en' ? locale + '/' : '') + 'dashboard/optimizations/' + data.id;
        };
        row.innerHTML =
            '<td class="py-3 pr-4 font-medium text-surface-900">' + esc(data.optimization_type) + '</td>'
            + '<td class="py-3 pr-4 text-surface-600">' + esc(data.target_locale) + '</td>'
            + '<td class="py-3 pr-4 text-right text-surface-600">' + formatScore(data.before_score) + '</td>'
            + '<td class="py-3 pr-4 text-right text-surface-600">' + formatScore(data.after_score) + '</td>'
            + '<td class="py-3 pr-4 text-right ' + impClass + '">' + impSign + improvementPct + '%</td>'
            + '<td class="py-3 text-right text-surface-400 text-xs">just now</td>';
        tbody.insertBefore(row, tbody.firstChild);
    }

    fetch('/api/v1/analytics/time-series?days=30')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            chart.data.labels = data.map(function (d) { return d.day; });
            chart.data.datasets[0].data = data.map(function (d) { return d.impressions; });
            chart.data.datasets[1].data = data.map(function (d) { return d.clicks; });
            chart.update();
        });

    fetch('/api/v1/analytics/language-breakdown')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var tbody = document.querySelector('#language-table tbody');
            var noDataText = tbody.getAttribute('data-no-data') || 'No data yet';
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-surface-400">' + esc(noDataText) + '</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function (d) {
                var pctClass = d.pct_change > 0 ? 'text-green-600' : d.pct_change < 0 ? 'text-red-500' : 'text-surface-400';
                var pctText = d.pct_change !== null ? (d.pct_change > 0 ? '+' : '') + d.pct_change + '%' : '—';
                return '<tr class="border-b border-surface-100">'
                    + '<td class="py-3 pr-4 font-medium text-surface-900">' + esc(d.locale) + '</td>'
                    + '<td class="py-3 pr-4 text-right">' + d.impressions.toLocaleString() + '</td>'
                    + '<td class="py-3 pr-4 text-right">' + d.clicks.toLocaleString() + '</td>'
                    + '<td class="py-3 pr-4 text-right">' + (d.ctr !== null ? d.ctr + '%' : '—') + '</td>'
                    + '<td class="py-3 text-right ' + pctClass + '">' + pctText + '</td>'
                    + '</tr>';
            }).join('');
        });
})();
