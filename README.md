# Hướng dẫn xây dựng trang Admin quản lý sản phẩm bằng PHP, MySQL và Docker

Dự án này là bộ source PHP thuần cơ bản cho trang quản trị sản phẩm. Hệ thống phù hợp cho bài thực hành môn lập trình web, cơ sở dữ liệu hoặc làm nền tảng mở rộng thành website bán hàng.

## 1. Chức năng chính

- Đăng nhập trang admin.
- Yêu cầu đặt lại mật khẩu quản trị qua email có liên kết dùng một lần, hết hạn sau 30 phút.
- Dashboard thống kê tổng danh mục, tổng sản phẩm, tổng tồn kho và giá trị tồn kho.
- Quản lý danh mục sản phẩm:
  - Thêm danh mục.
  - Sửa danh mục.
  - Xóa danh mục.
  - Bật/tắt trạng thái hiển thị.
- Quản lý sản phẩm: 
  - Thêm sản phẩm.
  - Sửa sản phẩm.
  - Xóa sản phẩm.
  - Upload và quản lý nhiều hình ảnh cho mỗi sản phẩm.
  - Gán thương hiệu, tìm kiếm và lọc sản phẩm theo thương hiệu.
  - Quản lý tên sản phẩm, danh mục, SKU, mô tả, số lượng, đơn giá, trạng thái.
  - Tìm kiếm theo tên sản phẩm hoặc SKU.
  - Lọc theo danh mục.
  - Xuất danh sách sản phẩm ra Excel theo bộ lọc hiện tại.
- Quản lý đơn hàng:
  - Tạo đơn với nhiều sản phẩm và thông tin người nhận.
  - Xem danh sách và chi tiết đơn hàng.
  - Cập nhật trạng thái theo tiến trình và hủy đơn trước khi giao.
  - Kiểm tra tồn kho và trừ kho khi tạo đơn thành công.
  - Hoàn tồn kho và ghi lịch sử nhập kho khi hủy đơn.
  - Lưu tên, SKU và giá sản phẩm tại thời điểm đặt hàng.
- Quản lý khách hàng: thêm, sửa và dùng thông tin khách hàng đã lưu khi tạo đơn.
- Quản lý thương hiệu: thêm, sửa, xóa và bật/tắt trạng thái hiển thị.
- Quản lý nhập xuất kho có lịch sử, tồn kho trước/sau và người thực hiện.
- Kết nối MySQL bằng PDO.
- Validate dữ liệu bằng JavaScript và PHP.
- Bảo vệ form bằng CSRF token.
- Chạy bằng Docker Compose.
- Có phpMyAdmin để kiểm tra dữ liệu.
- REST API JSON cho sản phẩm, danh mục, thương hiệu, khách hàng, đơn hàng và kho; yêu cầu session Admin.

## 2. Cấu trúc thư mục

```text
php_admin_product_manager/
├── app/
│   ├── public/
│   │   ├── index.php
│   │   ├── .htaccess
│   │   ├── assets/
│   │   │   ├── css/admin.css
│   │   │   ├── css/product-features.css
│   │   │   ├── css/orders.css
│   │   │   ├── js/admin.js
│   │   │   ├── js/product-images.js
│   │   │   └── js/orders.js
│   │   └── uploads/products/
│   ├── src/
│   │   ├── auth.php
│   │   ├── password_reset.php
│   │   ├── config.php
│   │   ├── db.php
│   │   ├── excel_export.php
│   │   ├── helpers.php
│   │   ├── category_repository.php
│   │   ├── brand_repository.php
│   │   ├── product_repository.php
│   │   ├── order_repository.php
│   │   ├── customer_repository.php
│   │   ├── inventory_repository.php
│   │   ├── activity_repository.php
│   │   └── api.php
│   └── views/
│       ├── layouts/
│       ├── auth/
│       ├── dashboard/
│       ├── categories/
│       ├── brands/
│       ├── products/
│       ├── orders/
│       ├── customers/
│       ├── inventory/
│       └── activity_logs/
├── database/
│   ├── init.sql
│   └── migrations/
│       ├── 001_create_orders.sql
│       ├── 002_create_customers_and_inventory.sql
│       ├── 003_create_admin_activity_logs.sql
│       ├── 004_add_brands_and_product_images.sql
│       └── 005_create_admin_password_resets.sql
├── docker/php/Dockerfile
├── .env
├── docker-compose.yml
└── README.md
```

## 3. Thiết kế database

Database gồm các bảng chính:

```text
admins
categories
brands
products
product_images
customers
orders
order_items
inventory_movements
admin_activity_logs
admin_password_resets
```

### 3.1. Bảng admins

Dùng để lưu tài khoản quản trị.

| Cột | Ý nghĩa |
|---|---|
| id | Khóa chính |
| username | Tên đăng nhập |
| email | Email admin |
| password_hash | Mật khẩu được băm bằng `password_hash()` |
| full_name | Họ tên admin |
| created_at | Ngày tạo |
| updated_at | Ngày cập nhật |

**Tài khoản mặc định:**

```text
Username: admin
Password: admin123
```

**Cơ chế lưu mật khẩu:**

Hệ thống sử dụng hàm PHP `password_hash()` với thuật toán mặc định `PASSWORD_DEFAULT` để tạo password hash.

```php
password_hash('admin123', PASSWORD_DEFAULT);
```

Mỗi lần tạo hash, PHP sử dụng salt ngẫu nhiên nên kết quả có thể khác nhau dù mật khẩu đầu vào giống nhau. Đây là hành vi bình thường.

**Cơ chế xác thực mật khẩu:**

Sử dụng `password_verify()` để kiểm tra mật khẩu người dùng nhập với hash được lưu trong database.

```php
password_verify($password, $admin["password_hash"]);
```

**Hỗ trợ tài khoản cũ sử dụng SHA-256:**

Các tài khoản được tạo từ phiên bản trước có thể đang lưu mật khẩu dưới dạng SHA-256 bằng `SHA2('admin123', 256)`.

Để đảm bảo khả năng tương thích, hệ thống hỗ trợ hai cơ chế xác thực:

1. Ưu tiên kiểm tra bằng `password_verify()` đối với password hash mới.
2. Nếu kiểm tra thất bại, hệ thống kiểm tra hash SHA-256 cũ bằng cách so sánh với `hash("sha256", $password)`.

Nếu tài khoản cũ xác thực SHA-256 thành công, hệ thống tự động tạo password hash mới bằng `password_hash()` và cập nhật vào database thông qua PDO Prepared Statement.

**Lưu ý:** Tài khoản cũ sẽ được nâng cấp tự động sau lần đăng nhập hợp lệ đầu tiên. Người dùng không cần đổi hoặc đặt lại mật khẩu. Sau khi nâng cấp, các lần đăng nhập tiếp theo sử dụng `password_verify()`.

SHA-256 chỉ được duy trì tạm thời để hỗ trợ tương thích với dữ liệu cũ, không được sử dụng để tạo mật khẩu mới.


### 3.2. Bảng categories

Dùng để lưu danh mục/loại sản phẩm.

| Cột | Ý nghĩa |
|---|---|
| id | Khóa chính |
| name | Tên danh mục |
| description | Mô tả danh mục |
| status | Trạng thái hiển thị |
| created_at | Ngày tạo |
| updated_at | Ngày cập nhật |

### 3.3. Bảng brands và product_images

`brands` lưu tên, mô tả và trạng thái hiển thị. Sản phẩm có thể gắn một thương hiệu hoặc để trống; không thể xóa thương hiệu đang được sản phẩm sử dụng.

`product_images` lưu nhiều ảnh cho từng sản phẩm và thứ tự hiển thị. Ảnh đầu tiên được dùng làm ảnh đại diện và đồng bộ vào `products.image_path` để tương thích với dữ liệu cũ.

### 3.4. Bảng products

Dùng để lưu sản phẩm.

| Cột | Ý nghĩa |
|---|---|
| id | Khóa chính |
| category_id | Khóa ngoại đến bảng categories |
| brand_id | Khóa ngoại tùy chọn đến bảng brands |
| name | Tên sản phẩm |
| sku | Mã sản phẩm |
| description | Mô tả sản phẩm |
| quantity | Số lượng tồn kho |
| unit_price | Đơn giá |
| image_path | Đường dẫn ảnh đại diện |
| status | Trạng thái hiển thị |
| created_at | Ngày tạo |
| updated_at | Ngày cập nhật |

Quan hệ:

```text
categories 1 ----- n products
brands 1 --------- n products
products 1 ------- n product_images
```

Một danh mục có nhiều sản phẩm. Một sản phẩm thuộc một danh mục và có thể gắn thương hiệu hoặc lưu nhiều ảnh.

### 3.5. Bảng orders và order_items

`customers` lưu thông tin khách hàng. `orders` lưu tùy chọn liên kết khách hàng cùng bản sao tên người nhận, số điện thoại, địa chỉ, trạng thái, tổng tiền và thời gian tạo. `order_items` lưu từng sản phẩm trong đơn cùng tên, SKU, đơn giá và thành tiền tại thời điểm đặt hàng. Nếu thông tin khách hàng hoặc sản phẩm được cập nhật sau đó, dữ liệu người nhận và chi tiết đơn vẫn được giữ nguyên.

`inventory_movements` lưu lịch sử nhập/xuất gồm sản phẩm, loại và số lượng giao dịch, tồn kho trước/sau, ghi chú, người thực hiện và tham chiếu đơn hàng nếu có. Khi tạo đơn, hệ thống khóa các sản phẩm cần đặt trong transaction, kiểm tra trạng thái và số lượng tồn kho, sau đó lưu đơn, chi tiết, trừ tồn kho và ghi giao dịch xuất. Nếu sản phẩm không đủ hàng hoặc có lỗi database, toàn bộ transaction được rollback.

## 4. Cài Docker Desktop

### Windows

1. Tải và cài Docker Desktop.
2. Bật WSL2 nếu Docker yêu cầu.
3. Mở Docker Desktop.
4. Kiểm tra:

```bash
docker --version
docker compose version
```

### macOS

1. Tải Docker Desktop đúng bản Apple Silicon hoặc Intel.
2. Kéo Docker vào Applications.
3. Mở Docker Desktop.
4. Kiểm tra:

```bash
docker --version
docker compose version
```

### Linux

Với Ubuntu, cài Docker Engine và Docker Compose plugin. Sau đó kiểm tra:

```bash
docker --version
docker compose version
```

## 5. Cấu hình biến môi trường

File `.env`:

```env
APP_NAME=PHP Admin Product Manager
APP_URL=http://localhost:8090
APP_ENV=local

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=admin_product_db
DB_USERNAME=admin_user
DB_PASSWORD=admin_password
DB_ROOT_PASSWORD=root_password

SESSION_NAME=ADMIN_PRODUCT_SESSION
UPLOAD_MAX_SIZE=2097152

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-google-app-password
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME=PHP Admin Product Manager
```

Trong Docker, PHP kết nối MySQL bằng hostname `mysql`, không dùng `localhost`.

### Cấu hình Gmail để gửi email thật

Bạn có thể dùng tài khoản Gmail của mình làm tài khoản gửi. Ứng dụng gửi thư bằng SMTP, vì vậy Gmail cần **Mật khẩu ứng dụng (App Password)**; không dùng mật khẩu đăng nhập Gmail thông thường.

1. Bật xác minh 2 bước cho tài khoản Google.
2. Tạo một App Password trong phần bảo mật của tài khoản Google. Nếu tài khoản do tổ chức quản lý hoặc không thấy mục App Password, hãy hỏi quản trị viên hoặc dùng SMTP của nhà cung cấp khác.
3. Trong `.env`, thay `your-email@gmail.com` bằng Gmail của bạn và `your-google-app-password` bằng App Password. Nhập App Password liền nhau, không có dấu cách. `SMTP_USERNAME` và `MAIL_FROM_ADDRESS` thường phải là cùng địa chỉ Gmail đã xác thực.
4. Lưu `.env`. Không chia sẻ App Password, không đưa thông tin thật vào README hoặc nơi lưu mã nguồn công khai.
5. Tạo lại container PHP để áp dụng biến môi trường:

```bash
docker compose up -d --force-recreate php-app
```

Nhà cung cấp SMTP khác cũng dùng được nếu hỗ trợ SMTP AUTH với xác thực `LOGIN`. Điền máy chủ, cổng và thông tin xác thực do nhà cung cấp cấp; ứng dụng hỗ trợ cổng 587 với `SMTP_ENCRYPTION=tls` hoặc cổng 465 với `SMTP_ENCRYPTION=ssl`.

`APP_URL` phải là URL đầy đủ mà người nhận có thể mở để tới trang đặt lại mật khẩu. `http://localhost:8090` chỉ dùng được trên chính máy đang chạy ứng dụng; nếu người nhận mở email trên máy khác, hãy cấu hình domain/địa chỉ public và HTTPS của website.

### Cập nhật email nhận của tài khoản admin

Email Gmail ở phần SMTP là địa chỉ **gửi** thư. Liên kết đặt lại sẽ được gửi tới email lưu trong tài khoản admin, địa chỉ này phải là hộp thư bạn có thể truy cập. Tài khoản mẫu ban đầu dùng `admin@example.com`, không phải địa chỉ nhận email thật.

Trong phpMyAdmin (`http://localhost:8091`), chọn database `admin_product_db`, mở tab **SQL** và chạy câu lệnh sau sau khi thay địa chỉ email:

```sql
UPDATE admins
SET email = 'your-email@gmail.com'
WHERE username = 'admin';
```

Nếu username admin của bạn khác, thay giá trị `admin` cho phù hợp. Hãy đảm bảo địa chỉ email chưa được gán cho tài khoản khác vì email là duy nhất trong bảng `admins`.

## 6. Chạy project

Tại thư mục gốc:

```bash
docker compose build
docker compose up -d
```

Kiểm tra container:

```bash
docker ps
```

Truy cập trang admin:

```text
http://localhost:8090
```

Truy cập phpMyAdmin:

```text
http://localhost:8091
```

Đăng nhập admin:

```text
Username: admin
Password: admin123
```

Đăng nhập phpMyAdmin:

```text
Server: mysql
Username: admin_user
Password: admin_password
Database: admin_product_db
```
## 7. Luồng xử lý đăng nhập

Hệ thống hỗ trợ xác thực cả password hash mới và tài khoản cũ sử dụng SHA-256 trong giai đoạn chuyển đổi.

**Luồng xử lý:**

```text
Admin nhập username/password
            ↓
PHP tìm admin trong bảng admins
            ↓
Kiểm tra mật khẩu bằng password_verify()
            ↓
     ┌──────┴──────┐
     │             │
   Thành công    Thất bại
     │             │
     │       Kiểm tra SHA-256 cũ
     │             │
     │        ┌────┴────┐
     │        │         │
     │      Đúng       Sai
     │        │         │
     │   Tạo hash mới   Từ chối
     │   bằng           đăng nhập
     │   password_hash()
     │        │
     │   Cập nhật DB
     │   bằng PDO
     │        │
     └────────┘
          ↓
   Tạo session đăng nhập
          ↓
   Chuyển vào dashboard
```

**Cơ chế xác thực:**

- `password_verify()`: Kiểm tra mật khẩu với hash mới.
- `hash("sha256", $password)`: Tạo hash SHA-256 để kiểm tra tài khoản cũ.
- `hash_equals()`: So sánh hash SHA-256 cũ một cách an toàn.
- `password_hash($password, PASSWORD_DEFAULT)`: Tạo hash mới khi tài khoản cũ đăng nhập thành công.
- PDO Prepared Statement: Cập nhật password hash mới vào database.

**Tự động nâng cấp tài khoản cũ:**

Khi tài khoản SHA-256 đăng nhập hợp lệ lần đầu tiên, hệ thống tự động chuyển đổi password hash sang định dạng mới và lưu vào database.

Người dùng không cần thực hiện thao tác đổi mật khẩu. Những lần đăng nhập tiếp theo sẽ được xác thực trực tiếp bằng `password_verify()`.

**Session chính:**

```php
$_SESSION["admin_id"]
$_SESSION["admin_username"]
$_SESSION["admin_full_name"]
```


## 8. Quản lý danh mục

Các route:

| Route | Chức năng |
|---|---|
| /categories | Danh sách danh mục |
| /categories/create | Thêm danh mục |
| /categories/edit?id=1 | Sửa danh mục |
| /categories/delete | Xóa danh mục |

Các hàm chính:

```php
category_all()
category_active_all()
category_find($id)
category_create($name, $description, $status)
category_update($id, $name, $description, $status)
category_delete($id)
```

## 8.1. Quản lý thương hiệu

| Route | Chức năng |
|---|---|
| /brands | Danh sách thương hiệu và số sản phẩm đang sử dụng |
| /brands/create | Thêm thương hiệu |
| /brands/edit?id=1 | Sửa thương hiệu |
| /brands/delete | Xóa thương hiệu chưa được sản phẩm sử dụng |

Thương hiệu có tên duy nhất, mô tả và trạng thái hiển thị. Khi xóa, hệ thống từ chối nếu vẫn còn sản phẩm đang gắn thương hiệu đó.

## 9. Quản lý sản phẩm

Các route:

| Route | Chức năng |
|---|---|
| /products | Danh sách sản phẩm |
| /products/create | Thêm sản phẩm |
| /products/edit?id=1 | Sửa sản phẩm |
| /products/delete | Xóa sản phẩm |
| /products/export | Tải danh sách sản phẩm đã lọc dưới dạng Excel (.xlsx) |

Các hàm chính:

```php
product_paginate($keyword, $categoryId, $limit, $offset, $brandId = 0)
product_count_filtered($keyword, $categoryId, $brandId = 0)
product_export_filtered($keyword, $categoryId, $brandId = 0)
product_find($id)
product_create($data)
product_update($id, $data)
product_delete($id)
product_count()
product_total_quantity()
product_total_inventory_value()
product_image_all($productId)
product_image_insert_many($productId, $imagePaths)
```

Sản phẩm có thể gắn thương hiệu hoặc để trống; danh sách hỗ trợ lọc theo danh mục và thương hiệu. Dùng tham số `page` để chuyển trang; các bộ lọc được giữ nguyên. Khi không có sản phẩm phù hợp, trang hiển thị thông báo thay vì các liên kết phân trang.

Nút **Xuất Excel** tải toàn bộ sản phẩm khớp với từ khóa, danh mục và thương hiệu đang chọn, không giới hạn theo trang hiện tại. Tệp `.xlsx` bao gồm thông tin sản phẩm, tồn kho, giá và trạng thái.

## 10. Quản lý đơn hàng

| Route | Chức năng |
|---|---|
| /orders | Danh sách đơn hàng |
| /orders/create | Tạo đơn hàng |
| /orders/show?id=1 | Chi tiết đơn hàng |
| /orders/status (POST) | Cập nhật trạng thái hoặc hủy đơn hàng |

Đơn hàng gồm tên người nhận, số điện thoại, địa chỉ giao hàng và một hoặc nhiều sản phẩm. Chỉ sản phẩm đang hiển thị và còn hàng mới có thể được đặt. Giá từng dòng hàng được lưu cùng đơn để lịch sử không đổi khi giá sản phẩm được cập nhật.

Khi tạo đơn có thể chọn khách hàng đã lưu; thông tin người nhận được lấy từ hồ sơ khách hàng và lưu thành bản sao trên đơn. Vẫn có thể nhập thông tin người nhận thủ công.

Trên trang chi tiết đơn hàng, quản trị viên có thể chuyển trạng thái theo đúng thứ tự **Mới → Đã xác nhận → Đang xử lý → Đang giao → Hoàn thành**. Đơn ở trạng thái Mới, Đã xác nhận hoặc Đang xử lý có thể bị hủy. Khi hủy, hệ thống hoàn số lượng sản phẩm còn liên kết với đơn về kho và ghi giao dịch nhập kho tham chiếu đơn hàng trong cùng transaction. Đơn đang giao, hoàn thành hoặc đã hủy không thể hủy; trạng thái Hoàn thành và Đã hủy là trạng thái cuối. Không cần migration riêng để bật chức năng này: cột trạng thái đơn hàng và bảng lịch sử nhập xuất đã có trong schema hiện tại.

Với database Docker mới, chạy lần lượt các migration `database/migrations/001_create_orders.sql` đến `database/migrations/005_create_admin_password_resets.sql` trong phpMyAdmin. Nếu database đã áp dụng migration 001-003, chạy migration 004 rồi 005. Migration 004 thêm bảng thương hiệu, quan hệ tùy chọn với sản phẩm, bảng thư viện ảnh và đưa ảnh đại diện hiện có vào thư viện; migration 005 thêm bảng lưu token đặt lại mật khẩu. Cài đặt mới tạo schema đầy đủ từ `database/init.sql`.

## 10.1. Quản lý khách hàng

| Route | Chức năng |
|---|---|
| /customers | Danh sách khách hàng |
| /customers/create | Thêm khách hàng |
| /customers/edit?id=1 | Sửa thông tin khách hàng |

Thông tin gồm tên, số điện thoại, email, địa chỉ và ghi chú. Khách hàng được giữ lại để bảo toàn tham chiếu lịch sử đơn hàng.

## 10.2. Nhập xuất kho

| Route | Chức năng |
|---|---|
| /inventory | Lịch sử nhập xuất kho |
| /inventory/create | Tạo phiếu nhập hoặc xuất |

Mỗi giao dịch cập nhật tồn kho và lưu lịch sử trong cùng một transaction. Giao dịch xuất bị từ chối nếu số lượng vượt tồn kho. Tạo đơn hàng cũng tự động ghi giao dịch xuất kho; sửa sản phẩm không thể thay đổi trực tiếp số lượng tồn.

## 10.3. Log hoạt động Admin

| Route | Chức năng |
|---|---|
| /activity-logs | Xem tối đa 200 hoạt động quản trị gần nhất |

Hệ thống ghi nhận đăng nhập/đăng xuất thành công, thêm/sửa/xóa danh mục, thương hiệu, sản phẩm và khách hàng, tạo đơn, cập nhật hoặc hủy trạng thái đơn, cùng thao tác nhập/xuất kho. Mỗi bản ghi lưu tên Admin tại thời điểm thao tác, loại và mã đối tượng, mô tả, địa chỉ IP và thời gian. Nếu tài khoản Admin bị xóa, lịch sử vẫn được giữ lại.

Với database hiện có, chạy `database/migrations/003_create_admin_activity_logs.sql` trong phpMyAdmin. Cài đặt mới tạo bảng từ `database/init.sql`.

## 11. Upload hình ảnh

Hình sản phẩm được lưu tại:

```text
app/public/uploads/products
```

Quy tắc upload:

| Quy tắc | Giá trị |
|---|---|
| Định dạng | JPG, PNG, WEBP, GIF |
| Số ảnh mỗi lần tải | Tối đa 10 |
| Dung lượng mỗi ảnh | Tối đa 2MB |
| Tổng dung lượng request | Tối đa 24MB |
| Tên file | Tạo tự động bằng thời gian + chuỗi ngẫu nhiên |
| Vị trí lưu đường dẫn | `product_images`; ảnh đầu tiên đồng bộ vào `products.image_path` |

Khi sửa sản phẩm, có thể thêm nhiều ảnh hoặc chọn ảnh cần xóa. Ảnh đại diện sẽ tự chuyển sang ảnh đầu tiên còn lại.

## 12. Validate dữ liệu

### Client-side

Files:

```text
app/public/assets/js/admin.js
app/public/assets/js/product-images.js
```

Kiểm tra:

- Tên sản phẩm không rỗng.
- Phải chọn danh mục.
- Số lượng không âm.
- Đơn giá không âm.
- Ảnh tải lên đúng định dạng, tối đa 10 ảnh và 2MB mỗi ảnh.

### Server-side

File:

```text
app/public/index.php
```

Hàm:

```php
validate_product_input($_POST)
```

Server vẫn kiểm tra lại để tránh trường hợp người dùng tắt JavaScript hoặc gửi request thủ công.

## 13. CSRF token

Mỗi form có token:

```php
<?= csrf_field() ?>
```

Khi submit, hệ thống kiểm tra bằng:

```php
verify_csrf()
```

## 14. Lệnh Docker thường dùng

Build:

```bash
docker compose build
```

Chạy:

```bash
docker compose up -d
```

Xem log:

```bash
docker compose logs
```

Dừng:

```bash
docker compose down
```

Dừng và xóa database volume:

```bash
docker compose down -v
```

Chạy lại từ đầu:

```bash
docker compose down -v
docker compose up -d --build
```

## 15. Docker push image

Build image:

```bash
docker build -t php-admin-product-manager:1.0 -f docker/php/Dockerfile .
```

Đăng nhập Docker Hub:

```bash
docker login
```

Tag image:

```bash
docker tag php-admin-product-manager:1.0 yourdockerhub/php-admin-product-manager:1.0
```

Push image:

```bash
docker push yourdockerhub/php-admin-product-manager:1.0
```

## 16. Test case đề xuất

### Đăng nhập

| Test | Kết quả |
|---|---|
| Sai username | Báo lỗi |
| Sai password | Báo lỗi |
| Đúng admin/admin123 | Vào dashboard |

### Danh mục

| Test | Kết quả |
|---|---|
| Tên rỗng | Báo lỗi |
| Thêm hợp lệ | Lưu database |
| Sửa danh mục | Cập nhật database |
| Xóa danh mục chưa có sản phẩm | Xóa thành công |
| Xóa danh mục đang có sản phẩm | MySQL chặn do khóa ngoại |

### Sản phẩm

| Test | Kết quả |
|---|---|
| Tên sản phẩm rỗng | Báo lỗi |
| Chưa chọn danh mục | Báo lỗi |
| Số lượng âm | Báo lỗi |
| Đơn giá âm | Báo lỗi |
| Upload file không phải ảnh | Báo lỗi |
| Upload ảnh trên 2MB | Báo lỗi |
| Thêm sản phẩm hợp lệ | Lưu database và ảnh |
| Thêm nhiều ảnh cho sản phẩm | Lưu tất cả ảnh, hiển thị ảnh đầu tiên làm đại diện |
| Sửa sản phẩm | Thêm ảnh mới, xóa ảnh được chọn và cập nhật ảnh đại diện |
| Xóa sản phẩm | Xóa record, liên kết ảnh và các file ảnh |
| Không có sản phẩm khớp tìm kiếm/lọc | Hiển thị thông báo rỗng, không lỗi |
| Có tối đa 10 sản phẩm khớp | Hiển thị một trang, không có nút chuyển trang |
| Có hơn 10 sản phẩm khớp | Hiển thị nhiều trang; nút Trước/Sau và số trang hoạt động |
| Chuyển trang khi đang tìm kiếm/lọc danh mục hoặc thương hiệu | Giữ nguyên từ khóa và bộ lọc đã chọn |
| Chọn hơn 10 ảnh hoặc ảnh sai định dạng/quá 2MB | Hiển thị lỗi, không lưu dữ liệu |

### Đơn hàng

| Test | Kết quả |
|---|---|
| Bỏ trống thông tin người nhận | Hiển thị lỗi, không tạo đơn |
| Không thêm sản phẩm | Hiển thị lỗi, không tạo đơn |
| Tạo đơn với nhiều sản phẩm đủ tồn kho | Lưu đơn và chi tiết, trừ tồn kho chính xác |
| Đặt số lượng lớn hơn tồn kho | Báo lỗi, không tạo đơn và không trừ kho |
| Hai yêu cầu đồng thời tranh sản phẩm tồn kho thấp | Không thể bán vượt tồn kho |
| Mở chi tiết đơn | Hiển thị đúng người nhận, sản phẩm, giá và tổng tiền đã lưu |
| Cập nhật trạng thái đơn | Chỉ chuyển theo đúng tiến trình; trạng thái mới được lưu |
| Hủy đơn trước khi giao | Chuyển sang Đã hủy, hoàn tồn kho đúng số lượng và ghi giao dịch nhập tham chiếu đơn |
| Hủy đơn đang giao/đã hoàn thành/đã hủy | Từ chối thao tác; trạng thái, tồn kho và lịch sử không đổi |

### Khách hàng và kho

| Test | Kết quả |
|---|---|
| Bỏ trống tên, số điện thoại hoặc địa chỉ khách hàng | Hiển thị lỗi, không lưu |
| Email không hợp lệ | Hiển thị lỗi, không lưu |
| Thêm hoặc sửa khách hàng hợp lệ | Lưu và hiển thị trong danh sách |
| Tạo phiếu nhập kho hợp lệ | Tăng tồn và lưu đúng tồn trước/sau |
| Xuất kho không vượt tồn | Giảm tồn và lưu giao dịch |
| Xuất nhiều hơn tồn kho | Hiển thị lỗi, tồn kho và lịch sử không đổi |
| Tạo đơn hàng thành công | Giảm tồn và tự ghi giao dịch xuất có tham chiếu đơn |

### Thương hiệu

| Test | Kết quả |
|---|---|
| Thêm/sửa thương hiệu hợp lệ | Lưu và hiển thị trong danh sách |
| Tên thương hiệu rỗng hoặc trùng | Hiển thị lỗi, không lưu |
| Gán thương hiệu cho sản phẩm | Hiển thị trong danh sách và bộ lọc sản phẩm |
| Xóa thương hiệu đang được sản phẩm sử dụng | Từ chối xóa và giữ nguyên dữ liệu |

### Log hoạt động Admin

| Test | Kết quả |
|---|---|
| Đăng nhập hoặc đăng xuất thành công | Ghi hoạt động kèm Admin và thời gian |
| Thêm/sửa/xóa dữ liệu danh mục, sản phẩm hoặc khách hàng | Ghi đúng loại thao tác và đối tượng |
| Tạo đơn, cập nhật/hủy trạng thái đơn, nhập/xuất kho | Ghi mô tả thao tác tương ứng |
| Mở /activity-logs | Hiển thị bản ghi mới nhất; nội dung được escape an toàn |

## 17. REST API

API trả JSON tại đường dẫn `/api` và dùng chung tài khoản Admin với trang quản trị. Ngoại trừ đăng nhập, mọi endpoint yêu cầu session Admin. Các request `POST`, `PUT`, `PATCH` và `DELETE` cần gửi CSRF token trong header `X-CSRF-Token`.

### Đăng nhập và CSRF

Đăng nhập nhận JSON, trả session cookie và CSRF token:

```http
POST /api/auth/login
Content-Type: application/json

{"username":"admin","password":"admin123"}
```

Giữ cookie session từ response và gửi token ở header `X-CSRF-Token` cho các request ghi dữ liệu. Có thể lấy lại token hiện tại bằng `GET /api/auth/csrf-token`. Đăng xuất bằng `POST /api/auth/logout`.

### Endpoint

| Tài nguyên | Endpoint | Phương thức |
|---|---|---|
| Danh mục | `/api/categories`, `/api/categories/{id}` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| Thương hiệu | `/api/brands`, `/api/brands/{id}` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| Sản phẩm | `/api/products`, `/api/products/{id}` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| Khách hàng | `/api/customers`, `/api/customers/{id}` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| Đơn hàng | `/api/orders`, `/api/orders/{id}` | `GET`, `POST`; chi tiết hỗ trợ `GET`, `PATCH` |
| Giao dịch kho | `/api/inventory` | `GET`, `POST` |
| Log hoạt động | `/api/activity-logs` | `GET` |

Danh sách sản phẩm hỗ trợ `keyword`, `category_id`, `brand_id`, `page` và `per_page` (mặc định 20, tối đa 100). Kết quả có `data` và thông tin phân trang trong `meta`. `PUT`/`PATCH` cập nhật các trường được gửi; trường không gửi giữ nguyên. Tồn kho sản phẩm không cập nhật trực tiếp qua sản phẩm; dùng giao dịch kho để đảm bảo có lịch sử.

Ví dụ tạo sản phẩm (gửi kèm cookie session và CSRF token sau khi đăng nhập):

```json
{
  "name": "Laptop Demo",
  "category_id": 1,
  "brand_id": null,
  "sku": "LAP-DEMO-001",
  "description": "Sản phẩm mẫu",
  "quantity": 5,
  "unit_price": 12500000,
  "status": 1
}
```

Tạo đơn bằng `POST /api/orders` với `items` là danh sách `{ "product_id": ..., "quantity": ... }`; nếu không chọn `customer_id`, cần gửi `customer_name`, `customer_phone` và `shipping_address`. Cập nhật hoặc hủy đơn bằng `PATCH /api/orders/{id}` với `{"status":"confirmed"}` hoặc trạng thái hợp lệ tiếp theo (`processing`, `shipping`, `completed`, `cancelled`). Logic tạo đơn và chuyển trạng thái dùng chung kiểm tra tồn kho của ứng dụng.

Tạo giao dịch nhập/xuất kho bằng `POST /api/inventory` với `product_id`, `movement_type` (`in` hoặc `out`), `quantity` và `note` tùy chọn. Ảnh sản phẩm được xem qua API nhưng thêm/xóa ảnh vẫn thực hiện trong giao diện quản trị.

Các response thành công có dạng `{"data": ...}`; lỗi có dạng `{"error":{"message":"..."}}`. API sử dụng mã HTTP phù hợp, gồm `400` cho request không hợp lệ, `401` khi chưa đăng nhập, `404` khi không tìm thấy tài nguyên, `409` khi xung đột dữ liệu, `419` khi CSRF không hợp lệ và `422` khi dữ liệu không đạt kiểm tra.

## 18. Lỗi thường gặp

### Không kết nối được MySQL

Kiểm tra `.env`:

```env
DB_HOST=mysql
```

Không dùng `localhost` trong Docker container.

### Database không có bảng

Nếu MySQL volume đã tồn tại, `init.sql` không chạy lại. Xử lý:

```bash
docker compose down -v
docker compose up -d --build
```

Không muốn xóa dữ liệu hiện có thì chỉ chạy các migration chưa áp dụng. Chạy riêng migration 004 nếu chưa áp dụng thương hiệu/ảnh sản phẩm, và migration 005 để bật quên mật khẩu qua email.

### Upload ảnh lỗi

Kiểm tra thư mục:

```text
app/public/uploads/products
```

Có thể cấp quyền:

```bash
chmod -R 775 app/public/uploads
```

### Cổng bị trùng

Nếu `8090` hoặc `8091` bị trùng, sửa trong `docker-compose.yml`.

## 19. Quên mật khẩu qua email

1. Đảm bảo `.env` đã có cấu hình SMTP hợp lệ theo mục 5 và `APP_URL` là địa chỉ người nhận có thể truy cập.
2. Đảm bảo email của tài khoản admin là hộp thư thật mà bạn có thể mở. Làm theo mục 5 để thay email mẫu `admin@example.com` nếu cần.
3. Với database hiện có, chạy `database/migrations/005_create_admin_password_resets.sql` một lần trong phpMyAdmin. Không cần chạy lại migration nếu bảng `admin_password_resets` đã tồn tại. Database mới được tạo đầy đủ bảng từ `database/init.sql`.
4. Trên trang đăng nhập, chọn **Quên mật khẩu?**, nhập email admin và gửi yêu cầu. Mở email, nhấn liên kết và đặt mật khẩu mới có ít nhất 12 ký tự.

Liên kết chỉ dùng được một lần và hết hạn sau 30 phút. Nếu email không tới, kiểm tra thư rác, email admin, cấu hình SMTP và nhật ký ứng dụng (`docker compose logs php-app`). Không gửi App Password hoặc thông tin đăng nhập SMTP cho người khác.

## Các chức năng đã hoàn thành

- Thay SHA256 bằng `password_hash()`.
- Thêm phân trang.
- Thêm đơn hàng
- Thêm khách hàng và nhập xuất kho
- Cập nhật trạng thái và hủy đơn hàng.
- Thêm log hoạt động admin.
- Thêm nhiều ảnh cho một sản phẩm.
- Thêm thương hiệu.
- Thêm xuất danh sách sản phẩm ra Excel.