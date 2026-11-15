# Thiết kế đã xuất từ WordPress

`current/` do workflow `Sync WordPress design` tạo từ website đang chạy, qua SSH chỉ đọc. Nội dung là mã block HTML và JSON có thể xem diff trong Git; không phải HTML đã render hoặc database dump.

- `pages/<id>.html`: nội dung/bố cục Trang đã công khai và không có mật khẩu. `manifest.json` ánh xạ ID nguồn sang đường dẫn, tên trang và mẫu trang.
- `templates/`, `parts/`: mẫu trang và phần giao diện đang có hiệu lực cho theme Hải Phát Vina, ưu tiên bản người dùng đã lưu. Pattern của theme được mở thành mã block, không render dữ liệu nghiệp vụ.
- `global-styles.json`: phần cấu hình màu/chữ/kiểu hiển thị người dùng đã lưu.
- `navigation/`, `patterns/`: chỉ menu và mẫu đồng bộ công khai được các nguồn trên tham chiếu.

Không xuất Bài viết, Sản phẩm, Công trình, yêu cầu khách, tài khoản, mật khẩu, post meta, cấu hình công ty hoặc file ảnh. Nội dung Trang công khai (kể cả chữ và URL/ID ảnh trong bố cục) có trong bản xuất. Đổi ảnh trong bố cục có thể tạo diff đường dẫn; chỉ tải ảnh lên Thư viện hoặc thêm Bài viết thì không tạo diff.

Core bắt sự kiện lưu/xóa/unpublish của Trang, template, template part, global styles, menu và mẫu đồng bộ; đồng thời theo dõi mẫu Trang và lựa chọn Trang chủ/Trang bài viết. Các lần lưu được gom khoảng 90 giây, sau đó worker so hash bản xuất với bản đã được workflow xác nhận thành công. Autosave, revision, bản nháp chưa từng công khai và nội dung ngoài phạm vi không gọi GitHub. Một menu/pattern không được tham chiếu hoặc một lần lưu không đổi cũng không gọi Actions vì hash không đổi. Hosting có một cron mỗi phút chỉ xử lý cờ/tác vụ đến hạn; khi không có cờ, nó không xuất hoặc so nội dung website. Không dùng lịch chạy Actions định kỳ. Thời gian xuất hiện PR còn tùy hàng chờ GitHub.

Workflow dùng một nhánh `automation/wordpress-design` và một PR đang mở để gom thay đổi. Mỗi commit và tiêu đề PR ghi thời điểm đồng bộ đến giây theo giờ Việt Nam (`YYYY-MM-DD HH:MM:SS ICT`). Không thay đổi thì không tạo commit hoặc đổi tiêu đề PR. PR không tự merge. Nút Run workflow cho phép chạy ngay.

Đây là **bản nguồn thiết kế để review và lưu lịch sử**, không tự ghi ngược vào database. Khách đã lưu thay đổi đó trên website trước rồi. Sau merge, CI vẫn kiểm thử nhưng bỏ qua triển khai nếu code giống bản production đã healthy; nếu còn code chưa triển khai từ lần trước, pipeline vẫn triển khai code đó. Các bản deploy code giữ nội dung khách chỉnh; CSS/JS/PHP mới vẫn có thể thay đổi cách hiển thị/chức năng.

GitHub nhận yêu cầu chạy chưa đồng nghĩa PR đã lưu thành công. Worker giữ cờ cho tới khi workflow xuất qua SSH, ghi Git/PR và xác nhận đúng hash/generation đã xuất. Nếu mạng/workflow lỗi hoặc bị hủy, tác vụ được thử lại với thời gian chờ tăng dần. Khách sửa tiếp trong lúc sync vẫn được giữ thành tác vụ mới. Những lần retry, kích hoạt ban đầu và chạy thủ công có thể tạo lượt Actions dù không có lần lưu mới.

Khi cần đưa thiết kế thành mặc định của theme, kỹ thuật có thể đưa HTML từ `templates/` và `parts/` sang vị trí tương ứng trong theme, hợp nhất `global-styles.json` vào `theme.json`, và đưa nguồn Trang thành pattern. Cần xử lý ID menu/mẫu đồng bộ và URL ảnh nếu chuyển sang website khác. Bản xuất không thay thế backup database/uploads.

Repo này công khai: không nhập thông tin riêng tư vào nội dung Trang công khai. Unpublish/xóa Trang sẽ loại file ở lần đồng bộ tiếp theo, nhưng lịch sử Git vẫn giữ những phiên bản trước đã công khai.
