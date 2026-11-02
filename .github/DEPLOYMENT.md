# Production CI/CD

Push `main` kích hoạt `.github/workflows/deploy.yml`. Chỉ deploy sau khi kiểm thử WordPress thực, form, migration và phân quyền đạt. Workflow không tạo hoặc phân phối ZIP.

## Secrets hiện có của environment `production`

- `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`.
- `DEPLOY_PRIVATE_KEY`, `DEPLOY_KNOWN_HOSTS`.
- `THEME_PATH`: đường dẫn tuyệt đối kết thúc `/wp-content/themes/hai-phat-vina`.

Không cần thêm secret cho plugin: workflow suy ra WordPress root và `wp-content/plugins/hai-phat-vina-core`. Không commit khóa, mật khẩu hoặc wp-config/database dump.

## Yêu cầu server

SSH, rsync, bash, flock, PHP CLI 7.4+ với mysqli và WordPress 6.6+. Tài khoản deploy cần ghi thư mục theme/plugin, uploads cho việc nhập ảnh mẫu cũ, file maintenance, và một thư mục backup ngoài document root. Theme Hải Phát Vina phải đang active; workflow không chuyển một website đang dùng theme khác.

## Các bước deploy

1. Upload vào staging theo full commit SHA tại `wp-content/.hpv-releases/<sha>`.
2. Khóa deploy, bật maintenance, sao lưu các bảng theo table prefix của website. SQL và trạng thái được giữ ở `haiphat-deploy-backups/<sha>` bên ngoài document root; quyền thư mục 0700, SQL 0600. Code cũ nằm tại staging `<sha>/previous-code` (0700) trên cùng filesystem để rename an toàn.
3. Đổi thư mục theme/plugin bằng rename, giữ bản trước để rollback.
4. Kích hoạt Core qua WordPress API. Nếu chưa chuyển sang block, chuyển trang hiện có một lần; không chạy demo seeder trên production.
   Lần đầu cập nhật sơ đồ khách yêu cầu, hệ thống tạo các Trang còn thiếu với nội dung trống, tạo menu mới và sắp xếp footer. Trang có sẵn được giữ nguyên; header/footer cũ được lưu trong option `hpv_customer_structure_backup_v1` và bản SQL. Nếu một trang trùng slug đang là nháp/riêng tư/có mật khẩu, giữ nguyên trang đó và tạo Trang công khai trống riêng với slug khác. Những lần deploy sau không chạy lại bước này.
5. Flush cache/rewrite, tắt maintenance và kiểm tra homepage HTTP cùng các marker của theme mới.
6. Nếu lỗi sau backup, khôi phục code và SQL. Không xóa bản deploy lỗi hoặc bản backup; uploads mới không bị xóa khi rollback.

Deploy lại SHA đang healthy chỉ chạy health check. Retry một lần đã rollback giữ snapshot lần trước với hậu tố riêng. Theo dõi dung lượng các snapshot và dọn theo lịch lưu trữ của chủ website; workflow không tự xóa backup.

`.maintenance` chỉ do deploy này tạo mới được gỡ. CLI được phép đi qua maintenance để hoàn tất hoặc khôi phục. Nếu server bị ngắt bất thường, kiểm tra trạng thái `state.json` trong thư mục backup trước khi xử lý thủ công.

## Nội dung khách chỉnh và quyền

Pipeline không sync database từ local, không tạo tài khoản QA trên hosting và không ghi đè templates/styles/navigation đã được người dùng lưu. Tài khoản chủ website giữ Administrator; người vận hành có vai trò Quản lý website. Không tự đổi vai trò của tài khoản hiện có.

File Word cho người dùng nằm ngoài repo. README trong repo chỉ dành cho kỹ thuật/vận hành.

## Đồng bộ ngược thiết kế

`.github/workflows/sync-design.yml` chạy ở phút 7/22/37/52 mỗi giờ, hoặc Run workflow trên `main`. Lịch GitHub có thể trễ; repository public không hoạt động trong thời gian dài cũng có thể bị GitHub tắt lịch, cần kiểm tra trạng thái workflow khi bảo trì.

Workflow dùng cùng environment/secrets SSH với deploy và giữ shared lock trên server trong lúc xuất, tránh đọc giữa một lần migration. Concurrency group riêng tránh lịch đồng bộ hủy một deploy đang chờ. Nó chạy CLI xuất nguồn qua stdin, không tạo endpoint web hoặc đặt GitHub token trên hosting. Chỉ đọc thiết kế đã công khai, xác thực đường dẫn/metadata rồi cập nhật `site-design/current/` qua nhánh riêng. Nhánh này dành cho tự động hóa; chỉnh code trên nhánh khác. Không có force-push, auto-approve hoặc auto-merge.

Trong Settings → Actions → General cần bật **Allow GitHub Actions to create and approve pull requests** để token Actions tạo PR. Quyền mặc định vẫn `read`; chỉ workflow đồng bộ xin `contents: write` và `pull-requests: write`. GitHub gom tạo và duyệt PR trong cùng một setting, nhưng workflow này không duyệt PR. PR do bot tạo có thể cần người có quyền write bấm Approve workflows to run để chạy bộ kiểm thử PR; bản xuất luôn được validator kiểm tra ngay trong workflow đồng bộ. Merge vào `main` vẫn chạy CI đầy đủ trước deploy.

Nếu nguồn không đổi, workflow không tạo commit hoặc đổi tiêu đề PR. Nếu có thay đổi, commit và tiêu đề PR mang thời điểm đồng bộ đến giây theo múi giờ `Asia/Ho_Chi_Minh`; một branch/PR mở gom toàn bộ các lần sửa cho tới khi merge. Trang unpublish/xóa biến mất khỏi snapshot hiện tại ở lần chạy kế tiếp; bản cũ vẫn còn trong Git history. Snapshot là nguồn thiết kế để review/khôi phục thủ công, không tự nhập lại lên website. Việc đồng bộ không bao gồm bài viết, dữ liệu khách hoặc file ảnh. Chi tiết tại `site-design/README.md`.
