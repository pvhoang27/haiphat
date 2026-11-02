# Hải Phát Vina Core

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
