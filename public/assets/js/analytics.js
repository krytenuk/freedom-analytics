/**
 * The report pages' behaviour: charts, the site and period forms, copy buttons, and the live page's updates.
 *
 * Charts are elements with data-chart="trend|donut|bar" holding their data in a JSON script tag (see
 * partial/chart.phtml), drawn with ApexCharts. Colours come from a validated categorical palette, with separate steps
 * for the dark theme, and the charts are drawn again when the theme changes.
 */
(function () {
    'use strict';

    // Categorical slots, in fixed order: blue, orange, aqua, yellow, magenta, green. Never cycled: donuts fold their
    // smallest slices into "Other", which takes the neutral colour.
    const PALETTE = {
        light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300'],
        dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#0ca30c']
    };
    const OTHER = {light: '#a3a29b', dark: '#6d6c66'};

    const charts = [];

    function theme() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    function css(name) {
        return getComputedStyle(document.body).getPropertyValue(name).trim();
    }

    function formatter(format) {
        const number = new Intl.NumberFormat();
        return function (value) {
            if (value === null || value === undefined) {
                return '';
            }
            if (format === 'percent') {
                return (value * 100).toFixed(1) + '%';
            }
            return number.format(Math.round(value * 100) / 100);
        };
    }

    function base(data, mode) {
        return {
            chart: {
                height: data.height || 300,
                fontFamily: 'inherit',
                foreColor: css('--bs-secondary-color') || (mode === 'dark' ? '#c3c2b7' : '#52514e'),
                background: 'transparent',
                toolbar: {show: false},
                zoom: {enabled: false},
                animations: {enabled: false}
            },
            theme: {mode: mode},
            grid: {
                borderColor: mode === 'dark' ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)',
                strokeDashArray: 0
            },
            dataLabels: {enabled: false},
            legend: {position: 'top', horizontalAlign: 'left', markers: {size: 6}},
            tooltip: {theme: mode}
        };
    }

    function options(type, data) {
        const mode = theme();
        const colours = PALETTE[mode];
        const format = formatter(data.format);
        const opts = base(data, mode);

        if (type === 'trend') {
            const many = data.categories.length > 31;
            return Object.assign(opts, {
                chart: Object.assign(opts.chart, {type: 'area'}),
                series: data.series,
                colors: colours.slice(0, data.series.length),
                stroke: {width: 2, curve: 'smooth'},
                fill: {type: 'gradient', gradient: {shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02}},
                markers: {size: many ? 0 : 3, strokeWidth: 2, strokeColors: css('--bs-body-bg') || '#fff',
                    hover: {size: 6}},
                xaxis: {
                    categories: data.categories,
                    tickAmount: Math.min(data.categories.length, 12),
                    axisBorder: {show: false},
                    axisTicks: {show: false},
                    tooltip: {enabled: false},
                    labels: {rotate: 0, hideOverlappingLabels: true}
                },
                yaxis: {min: 0, forceNiceScale: true, labels: {formatter: format}},
                tooltip: {theme: mode, shared: true, intersect: false, y: {formatter: format}},
                // One series needs no legend: the card's title names it
                legend: Object.assign(opts.legend, {show: data.series.length > 1})
            });
        }

        if (type === 'donut') {
            const fills = data.labels.map(function (label, index) {
                return label === 'Other' ? OTHER[mode] : colours[index % colours.length];
            });
            return Object.assign(opts, {
                chart: Object.assign(opts.chart, {type: 'donut'}),
                series: data.values,
                labels: data.labels,
                colors: fills,
                // A thin ring of the card's colour between slices
                stroke: {width: 2, colors: [css('--bs-body-bg') || (mode === 'dark' ? '#1a1a19' : '#fff')]},
                legend: {position: 'bottom', markers: {size: 6}},
                plotOptions: {pie: {donut: {size: '62%', labels: {
                    show: true,
                    total: {show: true, label: data.name, formatter: function (w) {
                        return format(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0));
                    }}
                }}}},
                tooltip: {theme: mode, y: {formatter: format}}
            });
        }

        // An ordered series, such as hours of the day: one colour, as the bars are one measure
        return Object.assign(opts, {
            chart: Object.assign(opts.chart, {type: 'bar'}),
            series: [{name: data.name, data: data.values}],
            colors: [colours[0]],
            plotOptions: {bar: {borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '70%'}},
            xaxis: {categories: data.labels, axisBorder: {show: false}, axisTicks: {show: false},
                labels: {rotate: -45, hideOverlappingLabels: true}},
            yaxis: {min: 0, forceNiceScale: true, labels: {formatter: format}},
            tooltip: {theme: mode, y: {formatter: format}},
            legend: {show: false}
        });
    }

    function destroyCharts() {
        while (charts.length) {
            charts.pop().destroy();
        }
    }

    function draw() {
        if (typeof ApexCharts === 'undefined') {
            return;
        }
        destroyCharts();
        document.querySelectorAll('[data-chart]').forEach(function (element) {
            const source = element.querySelector('script[type="application/json"]');
            if (!source) {
                return;
            }
            const data = JSON.parse(source.textContent);
            let target = element.querySelector('.analytics-chart-canvas');
            if (!target) {
                target = document.createElement('div');
                target.className = 'analytics-chart-canvas';
                element.appendChild(target);
            }
            const chart = new ApexCharts(target, options(element.getAttribute('data-chart'), data));
            chart.render();
            charts.push(chart);
        });
    }

    // Draw again in the other theme's colours when the theme changes
    new MutationObserver(draw).observe(document.documentElement, {attributes: true, attributeFilter: ['data-bs-theme']});

    // The header's site menu, and the period menu, apply as soon as they change
    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    document.querySelectorAll('[data-period-select]').forEach(function (select) {
        const form = select.form;
        const range = form.querySelector('[data-custom-range]');
        const apply = form.querySelector('[data-period-apply]');
        function update() {
            const custom = select.value === 'custom';
            if (range) {
                range.hidden = !custom;
                range.querySelectorAll('input').forEach(function (input) { input.disabled = !custom; });
            }
            if (apply) {
                apply.hidden = !custom;
            }
        }
        update();
        select.addEventListener('change', function () {
            update();
            if (select.value !== 'custom') {
                form.submit();
            }
        });
    });

    // Copy buttons for the tracking code
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            const source = document.getElementById(button.getAttribute('data-copy'));
            if (!source || !navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(source.textContent.trim()).then(function () {
                const html = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Copied';
                setTimeout(function () { button.innerHTML = html; }, 1500);
            });
        });
    });

    /**
     * Asks for new data every few seconds while the tab is visible, and calls update() with it. Stops for good if the
     * answer is not JSON, which means the session has ended and the request was sent to the login page.
     */
    function poll(url, seconds, update, status) {
        let timer = null;
        let paused = false;
        let failures = 0;
        let stopped = false;

        function schedule(delay) {
            clearTimeout(timer);
            if (!stopped && !paused && !document.hidden) {
                timer = setTimeout(tick, delay);
            }
        }

        function tick() {
            fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'})
                .then(function (response) {
                    const type = response.headers.get('Content-Type') || '';
                    if (!response.ok || type.indexOf('application/json') === -1) {
                        throw new Error(response.ok ? 'session' : 'http');
                    }
                    return response.json();
                })
                .then(function (data) {
                    failures = 0;
                    update(data);
                    status('live', data);
                    schedule(seconds * 1000);
                })
                .catch(function (error) {
                    if (error.message === 'session') {
                        stopped = true;
                        status('ended');
                        return;
                    }
                    // Wait longer after each failure, up to five minutes
                    failures++;
                    status('error');
                    schedule(Math.min(seconds * 1000 * Math.pow(2, failures), 300000));
                });
        }

        // Catch up as soon as the tab is shown again
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                clearTimeout(timer);
            } else {
                schedule(0);
            }
        });

        schedule(seconds * 1000);

        return {
            setPaused: function (value) {
                paused = value;
                if (paused) {
                    clearTimeout(timer);
                    status('paused');
                } else {
                    schedule(0);
                }
            }
        };
    }

    // The live page: replaces its panel with the fresh one, and redraws its chart
    document.querySelectorAll('[data-live]').forEach(function (panel) {
        const badge = document.querySelector('[data-live-status]');
        const text = badge && badge.querySelector('[data-live-status-text]');
        const toggle = document.querySelector('[data-live-toggle]');

        function status(state, data) {
            if (!badge || !text) {
                return;
            }
            badge.classList.toggle('is-paused', state === 'paused' || state === 'ended');
            badge.classList.toggle('is-error', state === 'error');
            if (state === 'live') {
                text.textContent = 'Updated ' + data.updated;
            } else if (state === 'paused') {
                text.textContent = 'Paused';
            } else if (state === 'error') {
                text.textContent = 'Could not update, trying again';
            } else if (state === 'ended') {
                text.textContent = 'Stopped: reload the page to sign in again';
                if (toggle) {
                    toggle.hidden = true;
                }
            }
        }

        const poller = poll(panel.getAttribute('data-live'), Number(panel.getAttribute('data-live-interval')) || 10,
            function (data) {
                destroyCharts();
                panel.innerHTML = data.html;
                draw();
                document.title = document.title.replace(/^(\(\d+\) )?/, '(' + data.active + ') ');
            }, status);

        if (toggle) {
            toggle.addEventListener('click', function () {
                const pause = toggle.getAttribute('aria-pressed') !== 'true';
                toggle.setAttribute('aria-pressed', pause ? 'true' : 'false');
                toggle.querySelector('i').className = pause ? 'bi bi-play-fill' : 'bi bi-pause-fill';
                toggle.querySelector('span').textContent = pause ? 'Resume' : 'Pause';
                poller.setPaused(pause);
            });
        }
    });

    // The dashboard's count of visitors on the site now
    document.querySelectorAll('[data-live-count]').forEach(function (element) {
        const value = element.querySelector('[data-live-count-value]');
        const unit = element.querySelector('[data-live-count-unit]');
        poll(element.getAttribute('data-live-count'), Number(element.getAttribute('data-live-interval')) || 10,
            function (data) {
                value.textContent = new Intl.NumberFormat().format(data.active);
                unit.textContent = data.active === 1 ? 'visitor' : 'visitors';
            }, function () {});
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', draw);
    } else {
        draw();
    }
})();
