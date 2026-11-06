<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/report-ui.php';
$labels = array('zalo' => 'Zalo', 'phone' => 'Điện thoại', 'form' => 'Biểu mẫu');
$views = array('overview' => 'Tổng quan', 'forms' => 'Biểu mẫu');
$view = isset($_GET['view']) && is_string($_GET['view']) && isset($views[$_GET['view']]) ? $_GET['view'] : 'overview';
$days = isset($_GET['days']) && is_scalar($_GET['days']) && in_array((int) $_GET['days'], array(7, 30, 90), true) ? (int) $_GET['days'] : 30;
$today = current_datetime();
$start_date = $today->modify('-' . ($days - 1) . ' days');
$start = $start_date->format('Y-m-d');
$end = $today->modify('+1 day')->format('Y-m-d');
$previous_start = $start_date->modify('-' . $days . ' days')->format('Y-m-d');
$demo = defined('WP_DEBUG') && WP_DEBUG && in_array(wp_parse_url(home_url(), PHP_URL_HOST), array('localhost', '127.0.0.1'), true) && isset($_GET['demo']) && $_GET['demo'] === '1';
$totals = $previous = array_fill_keys(array_keys($labels), 0);
$series = $pages = array();
if (is_user_logged_in() && hpv_analytics_allowed()) {
    global $wpdb;
    $table = $wpdb->prefix . 'hpv_events';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(created_at) AS day, event_type, COUNT(*) AS count FROM $table WHERE created_at >= %s AND created_at < %s GROUP BY day,event_type ORDER BY day", $previous_start, $end));
    foreach ($rows as $row) {
        if (!isset($totals[$row->event_type])) { continue; }
        if ($row->day < $start) { $previous[$row->event_type] += (int) $row->count; continue; }
        $totals[$row->event_type] += (int) $row->count;
        $series[$row->day][$row->event_type] = (int) $row->count;
    }
    $pages = $wpdb->get_results($wpdb->prepare("SELECT page_path, SUM(event_type='zalo') AS zalo, SUM(event_type='phone') AS phone, SUM(event_type='form') AS form FROM $table WHERE created_at >= %s AND created_at < %s GROUP BY page_path", $start, $end), ARRAY_A);
    if ($demo) {
        $totals = array_fill_keys(array_keys($labels), 0); $series = array();
        for ($i = 0; $i < $days; $i++) {
            $date = $start_date->modify('+' . $i . ' days')->format('Y-m-d');
            $values = array('zalo' => 8 + ($i * 7 % 17) + (int) ($i / 4), 'phone' => 5 + ($i * 3 % 11), 'form' => 2 + ($i * 5 % 7));
            $series[$date] = $values;
            foreach ($values as $type => $count) { $totals[$type] += $count; }
        }
        foreach ($totals as $type => $count) { $previous[$type] = (int) round($count * .81); }
        $pages = array();
        $remaining = $totals;
        foreach (array('/' => .31, '/san-pham/mai-xep-luon-song/' => .24, '/san-pham/bat-hdpe/' => .18, '/lien-he/' => .16, '/bao-gia/' => .11) as $path => $weight) {
            $page = array('page_path' => $path);
            foreach ($totals as $type => $count) {
                $page[$type] = $path === '/bao-gia/' ? $remaining[$type] : (int) floor($count * $weight);
                $remaining[$type] -= $page[$type];
            }
            $pages[] = $page;
        }
    }
}
$types = $view === 'forms' ? array('form') : array('zalo', 'phone', 'form');
$total = $prior = 0;
foreach ($types as $type) { $total += $totals[$type]; $prior += $previous[$type]; }
$daily = array();
for ($i = 0; $i < $days; $i++) {
    $date = $start_date->modify('+' . $i . ' days')->format('Y-m-d');
    $daily[$date] = array_intersect_key(array_merge(array_fill_keys(array_keys($labels), 0), $series[$date] ?? array()), array_flip($types));
}
foreach ($pages as &$page) { $page['total'] = array_sum(array_intersect_key($page, array_flip($types))); }
unset($page);
$pages = array_values(array_filter($pages, function ($page) { return $page['total'] > 0; }));
usort($pages, function ($a, $b) { return $b['total'] <=> $a['total']; });
$peak = 0; $peak_date = '';
foreach ($daily as $date => $values) { if (array_sum($values) > $peak) { $peak = array_sum($values); $peak_date = $date; } }
$params = array('days' => $days);
if ($demo) { $params['demo'] = '1'; }
$name = is_user_logged_in() ? wp_get_current_user()->display_name : '';
$descriptions = array('overview' => 'Theo dõi Zalo, điện thoại và biểu mẫu trong một không gian.', 'forms' => 'Tổng hợp các biểu mẫu đã được website tiếp nhận thành công.');
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo esc_html($views[$view]); ?> | Hải Phát Analytics</title><link rel="stylesheet" href="<?php echo esc_url(HPV_CORE_URL . 'assets/report.css?v=2'); ?>"></head>
<body class="<?php echo is_user_logged_in() && hpv_analytics_allowed() ? 'report-app' : 'report-auth'; ?>">
<?php if (!is_user_logged_in() || !hpv_analytics_allowed()) : ?>
<main class="auth-layout"><div class="auth-story"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-mark">H</span><span>Hải Phát Vina<small>Analytics workspace</small></span></a><div><span class="story-tag">Một góc nhìn rõ ràng hơn</span><h1>Hiểu tương tác.<br>Nắm bắt cơ hội.</h1><p>Mỗi kết nối đều có một điểm bắt đầu.<br>Theo dõi hiệu quả website của bạn tại đây.</p><div class="story-graphic" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div></div><small>Hải Phát Vina · Không gian báo cáo riêng</small></div>
<div class="auth-form-wrap"><section class="login-panel">
<?php if (!is_user_logged_in()) : ?><span class="login-symbol"><?php hpv_report_icon('lock'); ?></span><h2>Chào mừng trở lại</h2><p>Đăng nhập để xem báo cáo website của bạn.</p>
<?php if ($error) : ?><p class="error" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
<form method="post" action="<?php echo esc_url(add_query_arg(array_merge($params, array('view' => $view)), hpv_report_url())); ?>"><?php wp_nonce_field('hpv_report_login', 'hpv_report_nonce'); ?><label for="username">Tên đăng nhập hoặc email</label><input id="username" name="username" autocomplete="username" required maxlength="254" placeholder="Nhập tên đăng nhập"><label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Nhập mật khẩu"><button class="primary-button" type="submit">Đăng nhập <?php hpv_report_icon('arrow'); ?></button></form>
<?php else : ?><h2>Chưa có quyền xem báo cáo</h2><p>Liên hệ người quản lý website để được cấp quyền Xem báo cáo.</p><?php hpv_report_logout(); ?><?php endif; ?>
</section><small class="auth-footer">Báo cáo website Hải Phát Vina</small></div></main>
<?php else : ?>
<aside class="sidebar"><a class="brand" href="<?php echo esc_url(hpv_report_url()); ?>"><span class="brand-mark">H</span><span>Hải Phát Vina<small>Analytics workspace</small></span></a><div class="workspace-label">Không gian làm việc</div><nav aria-label="Báo cáo"><?php foreach ($views as $key => $label) : ?><a class="nav-item <?php echo $view === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array_merge($params, array('view' => $key)), hpv_report_url())); ?>" <?php if ($view === $key) { echo 'aria-current="page"'; } ?>><?php hpv_report_icon($key); ?><span><?php echo esc_html($label); ?></span><?php if ($view === $key) : ?><span class="nav-dot"></span><?php endif; ?></a><?php endforeach; ?></nav><div class="sidebar-bottom"><a class="site-link" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener"><?php hpv_report_icon('external'); ?> Xem website</a><div class="profile"><span class="avatar"><?php echo esc_html(strtoupper(substr($name, 0, 1))); ?></span><div><strong><?php echo esc_html($name); ?></strong><small>Tài khoản báo cáo</small></div><?php hpv_report_logout(); ?></div></div></aside>
<div class="workspace"><header class="topbar"><div class="breadcrumb">Báo cáo <span>/</span> <strong><?php echo esc_html($views[$view]); ?></strong></div><div class="site-status"><i></i> haiphatvina.com</div></header>
<main class="report-main">
<?php if ($demo) : ?><div class="demo-banner"><?php hpv_report_icon('info'); ?><span>Đang xem dữ liệu minh họa trên local</span><a href="<?php echo esc_url(remove_query_arg('demo')); ?>">Xem dữ liệu thật</a></div><?php endif; ?>
<div class="page-heading"><div><h1><?php echo esc_html($views[$view]); ?><span class="heading-dot">.</span></h1><p><?php echo esc_html($descriptions[$view]); ?></p></div><form class="date-filter" method="get"><input type="hidden" name="view" value="<?php echo esc_attr($view); ?>"><?php if ($demo) : ?><input type="hidden" name="demo" value="1"><?php endif; ?><label class="sr-only" for="days">Khoảng thời gian</label><?php hpv_report_icon('calendar'); ?><select id="days" name="days" onchange="this.form.submit()"><?php foreach (array(7, 30, 90) as $option) : ?><option value="<?php echo (int) $option; ?>" <?php selected($days, $option); ?>><?php echo (int) $option; ?> ngày gần đây</option><?php endforeach; ?></select><noscript><button type="submit">Xem</button></noscript></form></div>
<div class="period-line"><span><?php echo esc_html($start_date->format('d/m/Y') . ' – ' . $today->format('d/m/Y')); ?></span><span>Cập nhật <?php echo esc_html($today->format('H:i')); ?></span></div>
<section class="metric-grid" aria-label="Chỉ số chính">
<article class="metric metric-lead"><div class="metric-top"><span><?php echo $view === 'forms' ? 'Biểu mẫu thành công' : 'Tổng lượt liên hệ'; ?></span><span class="metric-icon"><?php hpv_report_icon('activity'); ?></span></div><strong class="metric-value"><?php echo esc_html(number_format_i18n($total)); ?></strong><div class="metric-bottom"><?php hpv_report_change($total, $prior); ?><span>so với <?php echo (int) $days; ?> ngày trước</span></div><?php hpv_report_sparkline(array_map('array_sum', $daily), 'lead'); ?></article>
<?php if (count($types) > 1) : foreach ($types as $type) : ?><article class="metric metric-<?php echo esc_attr($type); ?>"><div class="metric-top"><span><?php echo esc_html($type === 'form' ? 'Form thành công' : 'Lượt bấm ' . $labels[$type]); ?></span><span class="metric-icon"><?php hpv_report_icon($type); ?></span></div><strong class="metric-value"><?php echo esc_html(number_format_i18n($totals[$type])); ?></strong><div class="metric-bottom"><?php hpv_report_change($totals[$type], $previous[$type]); ?><span>so với kỳ trước</span></div><?php hpv_report_sparkline(array_map(function ($values) use ($type) { return $values[$type] ?? 0; }, $daily), $type); ?></article><?php endforeach; else : ?>
<article class="metric"><div class="metric-top"><span>Trang có tương tác</span><span class="metric-icon"><?php hpv_report_icon('pages'); ?></span></div><strong class="metric-value"><?php echo count($pages); ?></strong><div class="metric-bottom"><span>trang trong thời gian đã chọn</span></div></article><article class="metric"><div class="metric-top"><span>Trung bình mỗi ngày</span><span class="metric-icon"><?php hpv_report_icon('activity'); ?></span></div><strong class="metric-value"><?php echo esc_html(number_format_i18n($total / $days, 1)); ?></strong><div class="metric-bottom"><span>lượt / ngày</span></div></article><article class="metric"><div class="metric-top"><span>Ngày hiệu quả nhất</span><span class="metric-icon"><?php hpv_report_icon('calendar'); ?></span></div><strong class="metric-value"><?php echo esc_html($peak_date ? (new DateTimeImmutable($peak_date))->format('d/m') : '—'); ?></strong><div class="metric-bottom"><span><?php echo (int) $peak; ?> lượt tương tác</span></div></article>
<?php endif; ?></section>
<div class="charts-layout <?php echo count($types) === 1 ? 'wide-chart' : ''; ?>"><section class="panel trend-panel"><div class="panel-heading"><div><h2><?php echo $view === 'forms' ? 'Xu hướng gửi biểu mẫu' : 'Xu hướng liên hệ'; ?></h2><p>Phân tích theo từng ngày</p></div><span class="panel-badge">Theo ngày</span></div><div class="chart-legend"><?php foreach ($types as $type) : ?><span><i class="dot dot-<?php echo esc_attr($type); ?>"></i><?php echo esc_html($labels[$type]); ?></span><?php endforeach; ?></div><?php hpv_report_chart($daily, $types, $labels); ?>
<details class="daily-details"><summary>Xem bảng số liệu theo ngày <?php hpv_report_icon('chevron'); ?></summary><div class="table-wrap"><table><thead><tr><th>Ngày</th><?php foreach ($types as $type) : ?><th><?php echo esc_html($labels[$type]); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($daily as $date => $values) : ?><tr><td><?php echo esc_html((new DateTimeImmutable($date))->format('d/m/Y')); ?></td><?php foreach ($types as $type) : ?><td><?php echo (int) $values[$type]; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></details></section>
<?php if (count($types) > 1) : ?><section class="panel distribution-panel"><div class="panel-heading"><div><h2>Phân bổ kênh</h2><p>Tỷ trọng lượt liên hệ</p></div><?php hpv_report_icon('distribution'); ?></div><?php hpv_report_donut($totals, $types, $total); ?><div class="distribution-legend"><?php foreach ($types as $type) : ?><div><span><i class="dot dot-<?php echo esc_attr($type); ?>"></i><?php echo esc_html($labels[$type]); ?></span><strong><?php echo esc_html(number_format_i18n($totals[$type])); ?></strong><small><?php echo $total ? esc_html(number_format_i18n($totals[$type] / $total * 100, 1)) : '0'; ?>%</small></div><?php endforeach; ?></div></section><?php endif; ?></div>
<section class="panel pages-panel"><div class="panel-heading"><div><h2><?php echo $view === 'forms' ? 'Trang mang lại biểu mẫu' : 'Hiệu quả theo trang'; ?></h2><p>Những trang mang lại nhiều tương tác nhất.</p></div><span class="panel-badge"><?php echo count($pages); ?> trang</span></div><div class="table-wrap"><table class="pages-table"><thead><tr><th scope="col">Trang nguồn</th><?php foreach ($types as $type) : ?><th scope="col"><?php echo esc_html(count($types) === 1 ? 'Form thành công' : $labels[$type]); ?></th><?php endforeach; ?><?php if (count($types) > 1) : ?><th scope="col">Tổng lượt</th><?php endif; ?><th scope="col">Tỷ trọng</th></tr></thead><tbody>
<?php foreach (array_slice($pages, 0, 20) as $page) : ?><tr><th scope="row"><div class="page-cell"><span class="page-icon"><?php hpv_report_icon($page['page_path'] === '/' ? 'home' : 'pages'); ?></span><span><strong><?php echo esc_html(hpv_report_page_name($page['page_path'])); ?></strong><small><?php echo esc_html($page['page_path']); ?></small></span></div></th><?php foreach ($types as $type) : ?><td><?php echo esc_html(number_format_i18n((int) $page[$type])); ?></td><?php endforeach; ?><?php if (count($types) > 1) : ?><td class="total-cell"><?php echo esc_html(number_format_i18n($page['total'])); ?></td><?php endif; ?><td><div class="share-cell"><span><?php echo $total ? esc_html(number_format_i18n($page['total'] / $total * 100, 1)) : '0'; ?>%</span><i style="--share:<?php echo esc_attr((string) min(100, $total ? round($page['total'] / $total * 100) : 0)); ?>%"></i></div></td></tr><?php endforeach; ?>
<?php if (!$pages) : ?><tr><td class="empty-state" colspan="<?php echo count($types) + (count($types) > 1 ? 3 : 2); ?>"><?php hpv_report_icon($view); ?><strong>Chưa có dữ liệu trong giai đoạn này</strong><span>Thử chọn khoảng thời gian khác để xem báo cáo.</span></td></tr><?php endif; ?></tbody></table></div></section>
<footer class="report-footer"><span>Hải Phát Vina Analytics</span><span><?php echo esc_html($today->format('d/m/Y')); ?></span></footer>
</main></div>
<?php endif; ?>
<script src="<?php echo esc_url(HPV_CORE_URL . 'assets/report.js?v=1'); ?>" defer></script>
</body></html>
