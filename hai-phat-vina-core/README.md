# Hải Phát Vina Core

Biểu tượng chữ Zalo trong `assets/zalo.svg` lấy từ [Simple Icons](https://github.com/simple-icons/simple-icons/blob/develop/icons/zalo.svg), mã SVG CC0; nhãn hiệu thuộc chủ sở hữu tương ứng.

Plugin nghiệp vụ đi kèm theme Hải Phát Vina. Cài và kích hoạt plugin trước khi đổi theme hoặc bàn giao.

## Dữ liệu được giữ nguyên

- Post types: `hpv_product`, `hpv_project`, `hpv_request`.
- Taxonomy: `hpv_category`.
- Thông tin công ty: option `hpv_settings`; liên kết trang: `hpv_page_ids`.
- Các post meta, shortcode `[hpv_quote_form]`, action `hpv_quote` và nonce cũ được giữ tương thích.
- Yêu cầu khách hàng không được công khai, không có REST endpoint và chỉ tài khoản có quyền `hpv_manage_business` được đọc, sửa, xóa.

## Bàn giao

Tạo tài khoản mới với vai trò **Quản lý website** (`hpv_site_manager`). Vai trò này dùng các quyền biên tập nội dung của Editor, có thể sửa giao diện và thông tin công ty, nhưng không có quyền quản trị plugin, theme, cài đặt hệ thống hoặc người dùng.

Không thay đổi quyền của các vai trò Editor/Author hiện có. Không tự gán vai trò cho tài khoản cũ. Administrator vẫn quản lý đầy đủ.

## Khi thay giao diện

Sản phẩm, công trình và yêu cầu vẫn xuất hiện trong quản trị; thông tin công ty được sửa tại **Hải Phát Vina**. Form chèn được bằng shortcode hoặc block đi kèm. Màu sắc, template và cách trình bày danh sách phụ thuộc theme mới.

Theme Hải Phát Vina có lớp tương thích để hoạt động khi chưa kích hoạt plugin. Vẫn nên giữ plugin hoạt động để dữ liệu và tính năng không phụ thuộc theme.

Gỡ kích hoạt không xóa bài, option, meta, khách hàng hay vai trò. Plugin không tự kích hoạt, không tự nhập dữ liệu mẫu, không tự đổi tài khoản.

## Email

Form lưu yêu cầu trước, sau đó dùng `wp_mail()` để gửi email tới địa chỉ đã cấu hình. Cần cấu hình SMTP theo hosting và gửi thử; trạng thái “máy chủ đã tiếp nhận lệnh gửi” không khẳng định thư đã tới hộp thư.
# Báo cáo lượt liên hệ

Trang `/bao-cao/` có giao diện đăng nhập và báo cáo riêng, sử dụng tài khoản WordPress và cookie đăng nhập hiện có. Quản trị viên xem được báo cáo; để bàn giao, tạo tài khoản riêng trong Người dùng và chọn vai trò **Xem báo cáo**. Vai trò này không có quyền sửa nội dung hoặc cấu hình website. Không chia sẻ tài khoản quản trị. Khôi phục mật khẩu dùng email WordPress hiện có.

Core tạo bảng `{prefix}hpv_events` trong cùng database trên hosting, không cần dịch vụ database riêng hoặc plugin mới. Ghi nhận bấm Zalo, `tel:`, liên kết HTTPS ra ngoài và link có `data-hpv-track="link"`; form chỉ ghi nhận sau khi yêu cầu riêng tư được lưu thành công. Không lưu IP thô, thông tin form, query string, nội dung link hoặc mã nhận diện khách trong bảng tracking. Không gửi các sự kiện này sang GA4 trong phiên bản này.

Báo cáo lọc 7/30/90 ngày theo múi giờ website; dữ liệu sự kiện tự xóa sau 180 ngày qua WP-Cron. Backup database bao gồm bảng tracking; dữ liệu không bị xóa khi tắt plugin. Không có số liệu lịch sử trước khi triển khai. Lượt bấm là số tương tác, không phải số khách duy nhất hay cuộc gọi/tin nhắn thành công. Bỏ qua lượt bấm khi đăng nhập, lọc bot phổ biến và giới hạn 60 sự kiện/phút/IP bằng khóa băm tạm thời; endpoint công khai vẫn có thể bị giả lập, nên không dùng số liệu này để đối soát thanh toán.

Khi triển khai, dùng HTTPS, loại `/bao-cao/` khỏi page cache/CDN và không cache POST `admin-ajax.php`. Kiểm tra email khôi phục mật khẩu. Thông báo quyền riêng tư của website cần mô tả việc thống kê tương tác. Kiểm tra local: `php /opt/hpv-checks/verify-analytics.php` trong container WordPress.

Giao diện báo cáo chia bốn mục: Tổng quan (Zalo, điện thoại, form), Kênh liên hệ (Zalo, điện thoại), Biểu mẫu và Tracking liên kết. Liên kết không cộng vào tổng lượt liên hệ. Mỗi mục có biểu đồ theo ngày và bảng trang nguồn; tổng quan và kênh liên hệ có thêm biểu đồ phân bổ. So sánh tăng/giảm sử dụng khoảng thời gian liền trước cùng độ dài.

Xem thiết kế với dữ liệu minh họa tại `/bao-cao/?demo=1`. Chế độ này chỉ hoạt động khi `WP_DEBUG` bật và hostname WordPress là `localhost` hoặc `127.0.0.1`; luôn cần quyền xem báo cáo. Dữ liệu minh họa được tạo trong bộ nhớ, có nhãn riêng, không ghi database hoặc thay đổi số liệu thật. Font Be Vietnam Pro được phục vụ từ plugin, kèm giấy phép OFL trong `assets/fonts/`.
