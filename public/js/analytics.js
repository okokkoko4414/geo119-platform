(function () {
    var evtSource = new EventSource('/api/e/live');
    evtSource.addEventListener('counters', function (e) {
        var data = JSON.parse(e.data);
        document.getElementById('counter-impressions').textContent = data.impressions;
        document.getElementById('counter-clicks').textContent = data.clicks;
        var ctrEl = document.getElementById('counter-ctr');
        ctrEl.textContent = data.ctr !== null ? data.ctr + '%' : '—';
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

    fetch('/api/analytics/time-series?days=30')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            chart.data.labels = data.map(function (d) { return d.day; });
            chart.data.datasets[0].data = data.map(function (d) { return d.impressions; });
            chart.data.datasets[1].data = data.map(function (d) { return d.clicks; });
            chart.update();
        });

    fetch('/api/analytics/language-breakdown')
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
