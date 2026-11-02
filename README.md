# Hải Phát Vina

WordPress cho Hải Phát Vina: giao diện block chỉnh sửa trực quan và plugin nghiệp vụ độc lập, chuẩn bị để bàn giao cho người vận hành không viết code.

## Cấu trúc

- `hai-phat-vina/`: Block Theme 2.0, template/parts/patterns, màn hình hướng dẫn và chuyển đổi từ theme PHP 1.x.
- `hai-phat-vina-core/`: plugin sản phẩm, công trình, thông tin công ty, yêu cầu riêng tư, form và vai trò Quản lý website.
- `dev/`: môi trường WordPress local, kiểm thử hồi quy và vòng đời deploy/rollback.

## CI/CD

Luồng chính: **commit → push `main` → GitHub Actions kiểm thử → deploy theme + Core lên DirectAdmin**. Không upload ZIP thủ công. Workflow dùng các secret production đã cấu hình, suy ra đường dẫn plugin từ `THEME_PATH`.

Deploy lưu database ngoài document root và code cũ trong staging riêng tư trên cùng filesystem, bật maintenance ngắn hạn, kích hoạt Core và chuyển các trang cũ một lần. Lần deploy sau giữ nội dung/template/style khách đã lưu trong database. Nếu deploy hoặc health check lỗi, workflow khôi phục code/database trước đó. Không tự nhập sản phẩm/bài viết mẫu lên production.

Yêu cầu: WordPress 6.6+, PHP CLI 7.4+, mysqli, SSH/rsync/bash/flock. Xem [vận hành CI/CD](.github/DEPLOYMENT.md). Tài liệu Word dành cho khách được bàn giao ngoài repository.

## Kiểm thử local

```sh
docker compose -f dev/compose.yaml up -d
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/bootstrap.php
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/block-setup.php
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/block-install.php
python3 dev/check-http.py
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/verify.php
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/verify-handover.php
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/verify-site-identity.php
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/verify-editable-blocks.php
```

Môi trường `127.0.0.1:8787` dùng dữ liệu/tài khoản QA và chặn email ra ngoài. Chỉ hai thư mục theme/plugin được deploy; fixtures, tài khoản local và tài liệu khách không được đưa lên hosting. Thay đổi local được triển khai sau commit/push và khi các kiểm thử CI đạt.
