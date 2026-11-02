# Thiết kế đã xuất từ WordPress

`current/` do workflow `Sync WordPress design` tạo từ website đang chạy, qua SSH chỉ đọc. Nội dung là mã block HTML và JSON có thể xem diff trong Git; không phải HTML đã render hoặc database dump.

- `pages/<id>.html`: nội dung/bố cục Trang đã công khai và không có mật khẩu. `manifest.json` ánh xạ ID nguồn sang đường dẫn, tên trang và mẫu trang.
- `templates/`, `parts/`: mẫu trang và phần giao diện đang có hiệu lực cho theme Hải Phát Vina, ưu tiên bản người dùng đã lưu. Pattern của theme được mở thành mã block, không render dữ liệu nghiệp vụ.
- `global-styles.json`: phần cấu hình màu/chữ/kiểu hiển thị người dùng đã lưu.
- `navigation/`, `patterns/`: chỉ menu và mẫu đồng bộ công khai được các nguồn trên tham chiếu.

Không xuất Bài viết, Sản phẩm, Công trình, yêu cầu khách, tài khoản, mật khẩu, post meta, cấu hình công ty hoặc file ảnh. Nội dung Trang công khai (kể cả chữ và URL/ID ảnh trong bố cục) có trong bản xuất. Đổi ảnh trong bố cục có thể tạo diff đường dẫn; chỉ tải ảnh lên Thư viện hoặc thêm Bài viết thì không tạo diff.

Lịch chạy dự kiến mỗi 15 phút, có thể trễ do GitHub. Workflow dùng một nhánh `automation/wordpress-design` và một PR đang mở để gom thay đổi. Mỗi commit và tiêu đề PR ghi thời điểm đồng bộ đến giây theo giờ Việt Nam (`YYYY-MM-DD HH:MM:SS ICT`). Không thay đổi thì không tạo commit hoặc đổi tiêu đề PR. PR không tự merge. Nút Run workflow cho phép chạy ngay.

Đây là **bản nguồn thiết kế để review và lưu lịch sử**, không tự ghi ngược vào database. Merge PR không thay đổi website: khách đã lưu thay đổi đó trên website trước rồi. Deploy tiếp tục giữ nội dung khách chỉnh, tránh vòng lặp đồng bộ và ghi đè sửa đổi mới hơn.

Khi cần đưa thiết kế thành mặc định của theme, kỹ thuật có thể đưa HTML từ `templates/` và `parts/` sang vị trí tương ứng trong theme, hợp nhất `global-styles.json` vào `theme.json`, và đưa nguồn Trang thành pattern. Cần xử lý ID menu/mẫu đồng bộ và URL ảnh nếu chuyển sang website khác. Bản xuất không thay thế backup database/uploads.

Repo này công khai: không nhập thông tin riêng tư vào nội dung Trang công khai. Unpublish/xóa Trang sẽ loại file ở lần đồng bộ tiếp theo, nhưng lịch sử Git vẫn giữ những phiên bản trước đã công khai.
