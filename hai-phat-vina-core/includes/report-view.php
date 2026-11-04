<?php
defined('ABSPATH') || exit;
$labels = array('zalo' => 'Bấm Zalo', 'phone' => 'Bấm gọi điện', 'form' => 'Form thành công', 'link' => 'Bấm liên kết');
$days = isset($_GET['days']) && in_array((int) $_GET['days'], array(7, 30, 90), true) ? (int) $_GET['days'] : 30;
$today = current_datetime();
$start = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
$end = $today->modify('+1 day')->format('Y-m-d');
$totals = array_fill_keys(array_keys($labels), 0);
$daily = array();
$pages = array();
if (is_user_logged_in() && hpv_analytics_allowed()) {
    global $wpdb;
    $table = $wpdb->prefix . 'hpv_events';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(created_at) AS day, event_type, COUNT(*) AS count FROM $table WHERE created_at >= %s AND created_at < %s GROUP BY day,event_type ORDER BY day", $start, $end));
    foreach ($rows as $row) {
        if (isset($totals[$row->event_type])) { $totals[$row->event_type] += (int) $row->count; }
        $daily[$row->day] = ($daily[$row->day] ?? 0) + (int) $row->count;
    }
    $pages = $wpdb->get_results($wpdb->prepare("SELECT page_path, SUM(event_type='zalo') AS zalo, SUM(event_type='phone') AS phone, SUM(event_type='form') AS form, SUM(event_type='link') AS link, COUNT(*) AS total FROM $table WHERE created_at >= %s AND created_at < %s GROUP BY page_path ORDER BY total DESC LIMIT 20", $start, $end));
}
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Báo cáo liên hệ | Hải Phát Vina</title><link rel="stylesheet" href="<?php echo esc_url(HPV_CORE_URL . 'assets/report.css?v=1'); ?>"></head>
<body><header class="report-header"><a href="<?php echo esc_url(home_url('/')); ?>">Hải Phát Vina</a><span>Báo cáo website</span><?php if (is_user_logged_in()) : ?><a href="<?php echo esc_url(wp_logout_url(hpv_report_url())); ?>">Đăng xuất</a><?php endif; ?></header>
<main>
<?php if (!is_user_logged_in()) : ?>
<section class="login-panel"><h1>Theo dõi lượt liên hệ</h1><p>Đăng nhập để xem báo cáo website Hải Phát Vina.</p>
<?php if ($error) : ?><p class="error" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
<form method="post" action="<?php echo esc_url(hpv_report_url()); ?>"><?php wp_nonce_field('hpv_report_login', 'hpv_report_nonce'); ?>
<label for="username">Tên đăng nhập hoặc email</label><input id="username" name="username" autocomplete="username" required maxlength="254">
<label for="password">Mật khẩu</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Đăng nhập</button></form><a class="recovery" href="<?php echo esc_url(wp_lostpassword_url(hpv_report_url())); ?>">Quên mật khẩu?</a></section>
<?php elseif (!hpv_analytics_allowed()) : ?>
<section class="login-panel"><h1>Chưa có quyền xem báo cáo</h1><p>Liên hệ người quản lý website để được cấp quyền Xem báo cáo.</p></section>
<?php else : ?>
<div class="report-title"><div><h1>Lượt liên hệ từ website</h1><p><?php echo esc_html(wp_date('d/m/Y', strtotime($start)) . ' – ' . $today->format('d/m/Y')); ?> · Giờ <?php echo esc_html(wp_timezone_string()); ?></p></div>
<form method="get"><label for="days">Khoảng thời gian</label><div class="filter"><select id="days" name="days"><?php foreach (array(7, 30, 90) as $option) : ?><option value="<?php echo (int) $option; ?>" <?php selected($days, $option); ?>><?php echo (int) $option; ?> ngày gần đây</option><?php endforeach; ?></select><button type="submit">Xem</button></div></form></div>
<section class="metrics" aria-label="Tổng lượt tương tác"><?php foreach ($labels as $type => $label) : ?><div><h2><?php echo esc_html($label); ?></h2><strong><?php echo esc_html(number_format_i18n($totals[$type])); ?></strong></div><?php endforeach; ?></section>
<section class="report-section"><h2>Tương tác theo ngày</h2>
<?php if (!array_sum($totals)) : ?><p class="empty">Chưa có tương tác trong khoảng thời gian này. Số liệu sẽ xuất hiện khi khách bấm liên hệ hoặc gửi form thành công.</p><?php else : ?>
<div class="chart" role="img" aria-label="Biểu đồ tổng tương tác theo ngày. Số liệu chi tiết trong bảng bên dưới."><?php $max = max(1, ...array_values($daily)); for ($i = 0; $i < $days; $i++) : $date = $today->modify('-' . ($days - 1 - $i) . ' days')->format('Y-m-d'); $count = $daily[$date] ?? 0; ?><div class="bar-column" title="<?php echo esc_attr($date . ': ' . $count); ?>"><div class="bar" style="height:<?php echo esc_attr((string) round($count / $max * 100, 2)); ?>%"></div></div><?php endfor; ?></div><div class="chart-labels"><span><?php echo esc_html($start); ?></span><span><?php echo esc_html($today->format('Y-m-d')); ?></span></div>
<details><summary>Xem số liệu từng ngày</summary><table><thead><tr><th>Ngày</th><th>Tương tác</th></tr></thead><tbody><?php foreach ($daily as $date => $count) : ?><tr><td><?php echo esc_html($date); ?></td><td><?php echo (int) $count; ?></td></tr><?php endforeach; ?></tbody></table></details>
<?php endif; ?></section>
<section class="report-section"><h2>Trang mang lại tương tác</h2><div class="table-wrap"><table><thead><tr><th scope="col">Trang</th><?php foreach ($labels as $label) : ?><th scope="col"><?php echo esc_html($label); ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($pages as $page) : ?><tr><th scope="row"><?php echo esc_html($page->page_path); ?></th><?php foreach ($labels as $type => $label) : ?><td><?php echo esc_html(number_format_i18n((int) $page->$type)); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$pages) : ?><tr><td colspan="5">Chưa có dữ liệu.</td></tr><?php endif; ?></tbody></table></div></section>
<aside class="report-notes"><p>Mỗi lần bấm được tính là một lượt, không phải số khách hàng. Bấm Zalo hoặc gọi điện chưa xác nhận cuộc trò chuyện đã diễn ra. Form chỉ được tính khi website lưu yêu cầu thành công.</p><p>Liên kết gồm link ra ngoài website và link được chọn theo dõi. Lượt bấm khi đăng nhập không được tính. Trình chặn hoặc kết nối mạng có thể làm thiếu lượt bấm.</p><p>Bắt đầu ghi nhận: <?php echo esc_html(get_option('hpv_analytics_started', '')); ?>. Không có số liệu trước thời điểm này.</p></aside>
<?php endif; ?>
</main><footer>Hải Phát Vina · Báo cáo nội bộ</footer></body></html>
