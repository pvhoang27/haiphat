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
5. Flush cache/rewrite, tắt maintenance và kiểm tra homepage HTTP cùng các marker của theme mới.
6. Nếu lỗi sau backup, khôi phục code và SQL. Không xóa bản deploy lỗi hoặc bản backup; uploads mới không bị xóa khi rollback.

Deploy lại SHA đang healthy chỉ chạy health check. Retry một lần đã rollback giữ snapshot lần trước với hậu tố riêng. Theo dõi dung lượng các snapshot và dọn theo lịch lưu trữ của chủ website; workflow không tự xóa backup.

`.maintenance` chỉ do deploy này tạo mới được gỡ. CLI được phép đi qua maintenance để hoàn tất hoặc khôi phục. Nếu server bị ngắt bất thường, kiểm tra trạng thái `state.json` trong thư mục backup trước khi xử lý thủ công.

## Nội dung khách chỉnh và quyền

Pipeline không sync database từ local, không tạo tài khoản QA trên hosting và không ghi đè templates/styles/navigation đã được người dùng lưu. Tài khoản chủ website giữ Administrator; người vận hành có vai trò Quản lý website. Không tự đổi vai trò của tài khoản hiện có.

File Word cho người dùng nằm ngoài repo. README trong repo chỉ dành cho kỹ thuật/vận hành.
