<?php
defined('ABSPATH') || exit;
function hpv_report_icon($name) {
    $paths = array(
        'overview' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'contact' => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.6L3 21l1.9-5.7A8.5 8.5 0 1 1 21 11.5Z"/>',
        'zalo' => '<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.6L3 21l1.9-5.7A8.5 8.5 0 1 1 21 11.5Z"/><path d="M9 8h6l-6 7h6"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.8 2.1Z"/>',
        'forms' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9zM9 11h6M9 15h4"/>',
        'links' => '<path d="m10 13 4-4M8 16l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0M13 8l1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0" transform="translate(1 0)"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2"/>',
        'activity' => '<path d="M3 17 8 12l4 3 8-10M14 5h6v6"/>',
        'pages' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1ZM9 21v-8h6v8"/>',
        'external' => '<path d="M15 3h6v6M10 14 21 3M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/>',
        'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'distribution' => '<path d="M21 12A9 9 0 1 1 12 3v9ZM15 3.5V9h5.5"/>',
    );
    if ($name === 'form') { $name = 'forms'; }
    if ($name === 'link') { $name = 'links'; }
    echo '<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['activity']) . '</svg>';
}
function hpv_report_logout() {
    echo '<form class="logout-form" method="post" action="' . esc_url(hpv_report_url()) . '">';
    wp_nonce_field('hpv_report_logout', 'hpv_report_logout', false);
    echo '<button type="submit" title="Đăng xuất" aria-label="Đăng xuất">'; hpv_report_icon('logout'); echo '</button></form>';
}
function hpv_report_change($value, $previous) {
    if (!$previous) { echo '<span class="change neutral">' . ($value ? 'Mới' : '—') . '</span>'; return; }
    $change = ($value - $previous) / $previous * 100;
    echo '<span class="change ' . ($change >= 0 ? 'positive' : 'negative') . '">' . ($change >= 0 ? '↗ ' : '↘ ') . esc_html(number_format_i18n(abs($change), 1)) . '%</span>';
}
function hpv_report_sparkline($values, $type) {
    $max = max(1, ...array_values($values)); $points = array(); $count = count($values);
    foreach (array_values($values) as $index => $value) { $points[] = round($index / max(1, $count - 1) * 150, 1) . ',' . round(38 - $value / $max * 30, 1); }
    echo '<svg class="sparkline sparkline-' . esc_attr($type) . '" viewBox="0 0 150 42" aria-hidden="true"><polyline points="' . esc_attr(implode(' ', $points)) . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
}
function hpv_report_chart($daily, $types, $labels) {
    $max = 1;
    foreach ($daily as $values) { $max = max($max, ...array_values($values)); }
    $step = max(1, (int) ceil($max / 4)); $max = $step * 4;
    $count = count($daily); $dates = array_keys($daily);
    echo '<div class="main-chart"><svg viewBox="0 0 760 248" role="img" aria-label="Xu hướng tương tác theo ngày; bảng số liệu chi tiết ở bên dưới.">';
    for ($i = 0; $i <= 4; $i++) { $y = 20 + $i * 46; echo '<line class="grid-line" x1="38" y1="' . $y . '" x2="744" y2="' . $y . '"/><text class="axis-text" x="24" y="' . ($y + 4) . '" text-anchor="end">' . (int) ($max - $i * $step) . '</text>'; }
    foreach ($types as $type) {
        $points = array();
        foreach (array_values($daily) as $index => $values) { $points[] = round(38 + $index / max(1, $count - 1) * 706, 2) . ',' . round(204 - $values[$type] / $max * 184, 2); }
        echo '<polyline class="chart-line line-' . esc_attr($type) . '" points="' . esc_attr(implode(' ', $points)) . '"/>';
    }
    foreach (array_values($daily) as $index => $values) {
        $x = 38 + $index / max(1, $count - 1) * 706;
        $tooltip = (new DateTimeImmutable($dates[$index]))->format('d/m/Y');
        foreach ($types as $type) { $tooltip .= ' · ' . $labels[$type] . ': ' . $values[$type]; }
        echo '<rect class="chart-hit" x="' . esc_attr((string) ($x - 706 / $count / 2)) . '" y="14" width="' . esc_attr((string) (706 / $count)) . '" height="196"><title>' . esc_html($tooltip) . '</title></rect>';
    }
    for ($i = 0; $i <= 5; $i++) { $index = (int) round($i / 5 * ($count - 1)); echo '<text class="axis-text" x="' . (38 + $i / 5 * 706) . '" y="235" text-anchor="' . ($i === 0 ? 'start' : ($i === 5 ? 'end' : 'middle')) . '">' . esc_html((new DateTimeImmutable($dates[$index]))->format('d/m')) . '</text>'; }
    echo '</svg></div>';
}
function hpv_report_donut($totals, $types, $total) {
    echo '<div class="donut-wrap"><svg viewBox="0 0 200 200" role="img" aria-label="Phân bổ lượt liên hệ theo kênh"><circle cx="100" cy="100" r="76" class="donut-track"/>';
    $offset = 0;
    foreach ($types as $type) {
        $share = $total ? $totals[$type] / $total * 100 : 0;
        echo '<circle class="donut-segment line-' . esc_attr($type) . '" cx="100" cy="100" r="76" pathLength="100" stroke-dasharray="' . esc_attr((string) max(0, $share - ($share ? 1 : 0))) . ' ' . esc_attr((string) (100 - max(0, $share - ($share ? 1 : 0)))) . '" stroke-dashoffset="' . esc_attr((string) -$offset) . '"/>';
        $offset += $share;
    }
    echo '</svg><div class="donut-center"><strong>' . esc_html(number_format_i18n($total)) . '</strong><span>Lượt liên hệ</span></div></div>';
}
function hpv_report_page_name($path) {
    $known = array('/' => 'Trang chủ', '/lien-he/' => 'Liên hệ', '/bao-gia/' => 'Yêu cầu báo giá', '/san-pham/' => 'Sản phẩm', '/gioi-thieu/' => 'Giới thiệu');
    if (isset($known[$path])) { return $known[$path]; }
    $id = url_to_postid(home_url($path));
    return $id ? get_the_title($id) : ucfirst(str_replace('-', ' ', basename(untrailingslashit($path))));
}
