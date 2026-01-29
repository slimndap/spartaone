<?php
$activityCharts = (isset($activityCharts) && is_array($activityCharts)) ? $activityCharts : [];
if ($activityCharts === []) {
    return;
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const charts = <?php echo json_encode($activityCharts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const rootStyles = getComputedStyle(document.documentElement);
        const accent = rootStyles.getPropertyValue('--brand-accent').trim() || '#f3c623';
        const toRgba = (color, alpha) => {
            if (color.startsWith('#')) {
                const hex = color.replace('#', '');
                const value = hex.length === 3
                    ? hex.split('').map((ch) => ch + ch).join('')
                    : hex;
                const intVal = parseInt(value, 16);
                const r = (intVal >> 16) & 255;
                const g = (intVal >> 8) & 255;
                const b = intVal & 255;
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            if (color.startsWith('rgb(')) {
                return color.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`);
            }
            if (color.startsWith('rgba(')) {
                return color.replace(/rgba\(([^)]+)\)/, `rgba($1, ${alpha})`);
            }
            return color;
        };

        charts.forEach((chartData) => {
            const canvas = document.getElementById(chartData.id);
            if (!canvas) {
                return;
            }
            const axisColor = getComputedStyle(canvas.parentElement).color;
            const backgroundColors = chartData.opacities.map((opacity) => toRgba(accent, opacity));
            const zoneLabelShort = {
                '3K': '3K',
                '5K': '5K',
                '10K': '10K',
                'Half Marathon': 'HM',
                'Marathon': 'M',
                'Aeroob': 'A',
                'Recovery': 'R',
                'Onbekend': '?',
            };
            const datasets = [
                {
                    label: 'Tempo zones',
                    data: chartData.lapMidpoints.map((x, index) => ({
                        x,
                        y: chartData.zoneLevels[index],
                    })),
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    pointHitRadius: 8,
                    showLine: false,
                    backgroundColor: 'transparent',
                    borderColor: 'transparent',
                },
            ];
            const lapStackedBars = {
                id: 'lapStackedBars',
                afterDatasetsDraw(chart) {
                    const {ctx, chartArea, scales} = chart;
                    const xScale = scales.x;
                    const yScale = scales.y;
                    if (!xScale || !yScale) {
                        return;
                    }
                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(chartArea.left, chartArea.top, chartArea.width, chartArea.height);
                    ctx.clip();
                    const gap = 2;
                    chartData.lapRanges.forEach((range, index) => {
                        const [start, end] = range;
                        const xLeft = xScale.getPixelForValue(start) + gap;
                        const xRight = xScale.getPixelForValue(end) - gap;
                        if (xRight <= xLeft) {
                            return;
                        }
                        const zoneLevel = chartData.zoneLevels[index] || 0;
                        const fill = backgroundColors[index] || accent;
                        for (let segment = 0; segment < zoneLevel; segment += 1) {
                            const yBottom = yScale.getPixelForValue(segment);
                            const yTop = yScale.getPixelForValue(segment + 1);
                            const rectTop = Math.min(yTop, yBottom);
                            const rectHeight = Math.abs(yBottom - yTop);
                            ctx.fillStyle = fill;
                            ctx.fillRect(xLeft, rectTop, xRight - xLeft, rectHeight);
                        }
                    });
                    ctx.restore();
                },
            };
            const lapXAxisLabels = {
                id: 'lapXAxisLabels',
                afterDraw(chart) {
                    const {ctx, chartArea, scales} = chart;
                    const xScale = scales.x;
                    if (!xScale) {
                        return;
                    }
                    ctx.save();
                    ctx.fillStyle = axisColor;
                    ctx.font = '11px system-ui, -apple-system, "Segoe UI", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'top';
                    chartData.lapRanges.forEach((range, index) => {
                        const [start, end] = range;
                        const xLeft = xScale.getPixelForValue(start);
                        const xRight = xScale.getPixelForValue(end);
                        const width = xRight - xLeft;
                        if (width < 28) {
                            return;
                        }
                        const label = chartData.labels[index] || '';
                        if (!label) {
                            return;
                        }
                        const xCenter = xLeft + width / 2;
                        const y = chartArea.bottom + 6;
                        ctx.fillText(label, xCenter, y, width);
                    });
                    ctx.restore();
                },
            };
            new Chart(canvas.getContext('2d'), {
                type: 'scatter',
                data: {
                    labels: chartData.labels,
                    datasets,
                },
                plugins: [lapStackedBars, lapXAxisLabels],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: { bottom: 24 },
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (items) => {
                                    const index = items[0]?.dataIndex ?? null;
                                    return index !== null ? (chartData.labels[index] || '') : '';
                                },
                                label: (context) => {
                                    const index = context.dataIndex;
                                    const pieces = [
                                        chartData.distances[index]
                                            ? `${chartData.distances[index].toFixed(2)} km`
                                            : null,
                                        chartData.durations[index] || null,
                                        chartData.paces[index] ? `${chartData.paces[index]}/km` : null,
                                        chartData.zones[index] || null,
                                    ];
                                    return pieces.filter(Boolean).join(' · ');
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            type: 'linear',
                            grid: { display: false },
                            ticks: { display: false },
                            suggestedMin: 0,
                            suggestedMax: chartData.totalDistance || undefined,
                            title: {
                                display: false,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMin: 0,
                            suggestedMax: Math.max(chartData.zoneStackOrder.length, 1),
                            ticks: {
                                color: axisColor,
                                stepSize: 1,
                                callback: (value) => {
                                    if (!Number.isInteger(value) || value <= 0) {
                                        return '';
                                    }
                                    const label = chartData.zoneStackOrder[value - 1] ?? '';
                                    return zoneLabelShort[label] ?? label;
                                },
                            },
                        },
                    },
                },
            });
        });
    });
</script>
