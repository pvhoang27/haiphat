# Hải Phát Vina — ghi chú kỹ thuật

Block Theme 2.0 dùng native template parts, patterns và Query Loop. Yêu cầu WordPress 6.6+, PHP 7.4+. Phát hành qua CI/CD của repository; xem `.github/DEPLOYMENT.md` ở repository gốc.

## Phân tách trách nhiệm

- `templates/`, `parts/`, `theme.json`, `assets/css/blocks.css`: giao diện và editor.
- `inc/patterns.php`: 16 mẫu website; `inc/editor.php`: ba mẫu nội dung bài viết.
- `inc/migration.php`: chuyển đổi một lần, giữ nội dung/menu/ảnh đã có; lưu dữ liệu gốc phục vụ phục hồi.
- `inc/admin.php`: màn hình thao tác nhanh, thông tin công ty và trợ giúp trong ứng dụng.
- Plugin `hai-phat-vina-core`: CPT/taxonomy/meta, dữ liệu công ty, yêu cầu riêng tư, form, functional blocks và vai trò Quản lý website.
- Các template PHP và helper tương thích còn được giữ để phục hồi bản cũ; template PHP cũ không được cung cấp cho trang mới trong Block Theme.

## Quyền chỉnh sửa

Quản lý website được biên tập nội dung, menu, template/parts, Global Styles, tên/logo, công ty và yêu cầu khách. Không mở quyền quản lý hệ thống hay sửa code. Editor/Author gốc không bị tăng quyền.

Phần chữ trong các functional block được sửa bằng attributes/Inspector; dữ liệu dùng chung sửa trong nghiệp vụ. Xác thực form, consent, nonce, rate limit và quyền đọc thông tin khách do server xử lý.

## Tính bền vững của nội dung

Nội dung trang, Navigation, template parts, templates và Global Styles đã lưu nằm trong database. Deploy code không ghi đè các bản chỉnh sửa đó. Thay đổi mẫu PHP chỉ áp dụng khi chèn mẫu mới hoặc khi chưa có bản template tùy chỉnh; không tự reset nội dung khách để ép thiết kế mới.

Database giữ tên post type/meta/options cũ. Bản chuyển đổi không xóa nội dung và không thay ảnh đại diện thật. Nội dung HTML cũ có thể còn trong Classic block; người dùng có thể chọn Chuyển thành khối trong editor.

## Kiểm chứng

CI chạy PHP/JS/shell lint, WordPress thật, HTTP/form tests, kiểm tra quyền và khả năng giữ nội dung khi chạy migration lặp. Tài liệu người dùng dạng Word được giao ngoài repo.

Font/ảnh có ghi nguồn tại `ASSETS.md`, `ASSETS-v1.1.md`; ảnh mẫu là ảnh minh họa. GPL v2+; font SIL OFL.
