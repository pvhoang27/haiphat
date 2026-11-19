# Production CI/CD

Push `main` kích hoạt `.github/workflows/deploy.yml`. Sau khi kiểm thử WordPress thực, form, migration và phân quyền đạt, workflow đọc revision production đã healthy qua SSH và so với commit cần triển khai. Chỉ thay đổi `hai-phat-vina/`, `hai-phat-vina-core/`, `dev/deploy/`, workflow deploy hoặc bộ lọc `dev/changed-deploy.py` mới upload/deploy; snapshot/tài liệu không đổi code sẽ bỏ qua maintenance và triển khai. So với production thay vì commit trước push giúp không bỏ sót code nếu workflow trước thất bại hoặc bị thay thế trong hàng chờ. Không đọc được revision hợp lệ thì triển khai thận trọng. Run workflow trên `main` chủ động chạy deploy dù code không đổi. Workflow không tạo hoặc phân phối ZIP.

## Secrets hiện có của environment `production`

- `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`.
- `DEPLOY_PRIVATE_KEY`, `DEPLOY_KNOWN_HOSTS`.
- `THEME_PATH`: đường dẫn tuyệt đối kết thúc `/wp-content/themes/hai-phat-vina`.
- `DESIGN_SYNC_DISPATCH_TOKEN`: fine-grained PAT chỉ chọn repo `pvhoang27/haiphat`, quyền repository **Actions: Read and write**. Không cần Contents/Pull requests write cho token này. Đặt ngày hết hạn phù hợp và xoay token trước khi hết hạn.

Workflow suy ra WordPress root và `wp-content/plugins/hai-phat-vina-core`. Không commit khóa, mật khẩu, token hoặc wp-config/database dump. Token dispatch được truyền bằng SSH stdin từ file tạm quyền 0600; không có trong command line, database WordPress hay thư mục public.

## Yêu cầu server

SSH, rsync, bash, flock, crontab, PHP CLI 7.4+ với mysqli/proc_open và WordPress 6.6+. Tài khoản deploy cần ghi thư mục theme/plugin, uploads cho việc nhập ảnh mẫu cũ, file maintenance, một thư mục backup ngoài document root và crontab của chính tài khoản đó. PHP chạy web cần đọc được thư mục riêng này nếu dùng WP-Cron qua request; cron CLI của tài khoản deploy luôn xử lý hàng đợi độc lập với lượt truy cập. Theme Hải Phát Vina phải đang active; workflow không chuyển một website đang dùng theme khác.

## Các bước deploy

1. Upload vào staging theo full commit SHA tại `wp-content/.hpv-releases/<sha>`.
2. Khóa deploy, bật maintenance, sao lưu các bảng theo table prefix của website. SQL và trạng thái được giữ ở `haiphat-deploy-backups/<sha>` bên ngoài document root; quyền thư mục 0700, SQL 0600. Code cũ nằm tại staging `<sha>/previous-code` (0700) trên cùng filesystem để rename an toàn.
3. Đổi thư mục theme/plugin bằng rename, giữ bản trước để rollback.
4. Kích hoạt Core qua WordPress API. Nếu chưa chuyển sang block, chuyển trang hiện có một lần; không chạy demo seeder trên production.
   Lần đầu cập nhật sơ đồ khách yêu cầu, hệ thống tạo các Trang còn thiếu với nội dung trống, tạo menu mới và sắp xếp footer. Trang có sẵn được giữ nguyên; header/footer cũ được lưu trong option `hpv_customer_structure_backup_v1` và bản SQL. Nếu một trang trùng slug đang là nháp/riêng tư/có mật khẩu, giữ nguyên trang đó và tạo Trang công khai trống riêng với slug khác. Những lần deploy sau không chạy lại bước này.
   Danh sách chi nhánh được thêm lại một lần vào cột footer hiện có; mã nguồn footer trước thay đổi nằm trong `hpv_footer_branches_backup_v1`. Địa chỉ vẫn đọc từ Thông tin công ty. Bản đồ mặc định tìm theo địa chỉ; có thể cấu hình URL/mã iframe nhúng của Google Maps cho từng cơ sở khi cần ghim chính xác hơn.
5. Flush cache/rewrite, tắt maintenance và kiểm tra homepage HTTP cùng các marker của theme mới.
6. Nếu lỗi sau backup, khôi phục code và SQL. Không xóa bản deploy lỗi hoặc bản backup; uploads mới không bị xóa khi rollback.

Deploy lại SHA đang healthy chỉ chạy health check. Retry một lần đã rollback giữ snapshot lần trước với hậu tố riêng. Theo dõi dung lượng các snapshot và dọn theo lịch lưu trữ của chủ website; workflow không tự xóa backup.

`.maintenance` chỉ do deploy này tạo mới được gỡ. CLI được phép đi qua maintenance để hoàn tất hoặc khôi phục. Nếu server bị ngắt bất thường, kiểm tra trạng thái `state.json` trong thư mục backup trước khi xử lý thủ công.

## Nội dung khách chỉnh và quyền

Pipeline không sync database từ local, không tạo tài khoản QA trên hosting và không ghi đè templates/styles/navigation đã được người dùng lưu. Tài khoản chủ website giữ Administrator; người vận hành có vai trò Quản lý website. Không tự đổi vai trò của tài khoản hiện có.

File Word cho người dùng nằm ngoài repo. README trong repo chỉ dành cho kỹ thuật/vận hành.

## Đồng bộ ngược thiết kế theo sự kiện

Trước khi merge bản chuyển đổi, thêm `DESIGN_SYNC_DISPATCH_TOKEN` vào environment `production` và bảo đảm hosting cho phép tài khoản deploy dùng crontab. Có thể Run workflow deploy với `preflight_only=true` trên nhánh triển khai để kiểm tra SSH/PHP/crontab mà không upload, sửa database hoặc sửa cron. Chạy thủ công trên nhánh khác chỉ được phép với cờ preflight; triển khai thật vẫn chỉ trên `main`. Workflow cũng kiểm tra token/crontab trước khi đổi code.

Chuyển đổi theo hai bước: giữ lịch sync cũ trong lần triển khai Core mới, xác nhận worker production đã tự dispatch và workflow đã ACK thành công, rồi mới gỡ `schedule` trong một thay đổi workflow riêng. Nếu cấu hình hosting/token chưa hoạt động thì giữ lịch cũ để không mất đồng bộ. Trạng thái vận hành cuối cùng chỉ có sự kiện và nút Run workflow, không có schedule định kỳ.

Sau khi deploy healthy, `configure-design-sync.php` ghi `design-sync-config.php` và `run-design-sync.php` quyền 0600 trong `haiphat-deploy-backups/` ngoài document root. Nó thêm đúng một cron mỗi phút, dùng marker riêng, giữ các cron đang có và lưu bản crontab trước thay đổi. Cron đọc cờ chờ và chỉ xuất nội dung khi có tác vụ đến hạn; không polling nội dung. Không tắt WP-Cron của các plugin khác. Khi kích hoạt lần đầu có một snapshot bắt kịp các sửa đổi từ lần sync cũ; những lần cấu hình sau không tạo snapshot kích hoạt lại. Đổi token secret rồi Run workflow deploy trên `main` để cập nhật token riêng trên hosting.

Các hook của Core đánh dấu generation và gom khoảng 90 giây từ lần lưu cuối. Worker dùng bộ xuất hiện có để so SHA-256 với snapshot đã đồng bộ thành công. Chỉ hash khác mới gọi API `workflow_dispatch` cho `sync-design.yml` trên `main`. API chỉ nhận hash để theo dõi, không nhận nội dung website. Token này chỉ có quyền Actions trong repo; token tạo commit/PR vẫn là `GITHUB_TOKEN` bên trong Actions.

Workflow dùng environment/secrets SSH hiện có và shared deploy lock, xuất qua CLI stdin, không tạo endpoint web. Nó xác thực đường dẫn/metadata, cập nhật `site-design/current/` rồi ACK qua SSH đúng hash/generation đã xuất. ACK sau khi Git/PR thành công; API accepted không xóa cờ. Retry lỗi mạng tăng dần từ 1 phút đến 6 giờ; chờ ACK bắt đầu 15 phút và tăng tới 6 giờ nếu cùng snapshot liên tục không hoàn tất. Save mới vẫn được xử lý sớm, kể cả đang chờ ACK cũ. Các CLI deploy/export/ACK không phát lại sự kiện sync.

Trong Settings → Actions → General cần bật **Allow GitHub Actions to create and approve pull requests** để token Actions tạo PR. Quyền mặc định vẫn `read`; chỉ workflow sync xin `contents: write` và `pull-requests: write`. Workflow không auto-approve/auto-merge. PR được tạo bằng `GITHUB_TOKEN` không tự kích hoạt workflow `pull_request`; validator luôn chạy ngay trong sync, và merge `main` vẫn chạy CI đầy đủ trước kiểm tra/deploy production.

Nếu nguồn không đổi, không gọi Actions từ worker; một lần sync thủ công/retry cũng không tạo commit hoặc đổi tiêu đề PR nếu không có diff. Commit/tiêu đề PR ghi giờ Việt Nam đến giây; một branch/PR mở gom các lần sửa cho tới khi merge. Xóa/unpublish loại nguồn khỏi snapshot nhưng Git history giữ bản cũ. Snapshot chỉ dành cho review/khôi phục thủ công, không tự nhập lại website. Phạm vi xuất giữ nguyên, xem `site-design/README.md`.

Nếu cần chẩn đoán, xem option `hpv_design_sync_last_error` (chỉ mã lỗi/thời điểm), cờ `hpv_design_sync_dirty_generation` và cron `hpv_design_sync_process` qua CLI quản trị hiện có. Kiểm tra lượt sync thất bại trên Actions. Nút Run workflow của sync trên `main` luôn dùng được để bắt kịp thủ công. Nếu quay về cơ chế cũ, khôi phục lịch trong workflow và gỡ riêng dòng crontab mang marker `hai-phat-vina-design-sync-`; không xóa các cron khác hay backup.
