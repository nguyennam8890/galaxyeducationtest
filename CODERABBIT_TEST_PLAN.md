# Kịch bản test CodeRabbit

## Cách thực hiện
- Mỗi kịch bản = 1 branch riêng = 1 PR riêng
- Tạo branch từ `develop`, push lên, tạo PR về `develop`
- Chờ CodeRabbit review xong, xem nó phát hiện được bao nhiêu lỗi
- Ghi kết quả vào cột "CodeRabbit phát hiện?"

---

## Kịch bản 1: Security - SQL Injection & XSS
**Branch:** `test/security-injection`
**Mục tiêu:** Test khả năng phát hiện lỗi injection

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | SQL Injection trong search (dùng whereRaw với input user) | CourseController.php | |
| 2 | XSS - render HTML từ user input không escape | LessonController.php | |
| 3 | Command Injection qua exec() | ExportController.php | |

---

## Kịch bản 2: Security - Authentication & Authorization
**Branch:** `test/security-auth`
**Mục tiêu:** Test khả năng phát hiện lỗi xác thực/phân quyền

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | Lưu password plain text, không hash | AuthController.php | |
| 2 | So sánh password bằng == thay vì Hash::check | AuthController.php | |
| 3 | User enumeration - tiết lộ email tồn tại | AuthController.php | |
| 4 | Thiếu kiểm tra quyền khi xóa course | CourseController.php | |
| 5 | IDOR - user sửa được data của user khác | EnrollmentController.php | |

---

## Kịch bản 3: Data Leak & Sensitive Info
**Branch:** `test/data-leak`
**Mục tiêu:** Test khả năng phát hiện lộ dữ liệu nhạy cảm

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | Trả password trong API response | AuthController.php | |
| 2 | Hardcode API key / secret trong code | config hoặc controller | |
| 3 | Log sensitive data (password, token) | AuthController.php | |
| 4 | Export toàn bộ user data không filter | ExportController.php | |

---

## Kịch bản 4: Performance
**Branch:** `test/performance`
**Mục tiêu:** Test khả năng phát hiện lỗi hiệu năng

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | N+1 Query - loop query trong foreach | StatsController.php | |
| 2 | Không phân trang - load all records | CourseController.php | |
| 3 | per_page không giới hạn (max 100000) | CourseController.php | |
| 4 | Query trong loop không cache | StatsController.php | |

---

## Kịch bản 5: Logic Bug
**Branch:** `test/logic-bugs`
**Mục tiêu:** Test khả năng phát hiện lỗi logic

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | Rating chấp nhận giá trị -5 đến 100 (đúng là 1-5) | ReviewController.php | |
| 2 | Progress cho phép giá trị âm | EnrollmentController.php | |
| 3 | Slug không unique - trùng slug khi tạo course | CourseController.php | |
| 4 | Race condition khi enroll cùng lúc | EnrollmentController.php | |

---

## Kịch bản 6: File Upload Vulnerability
**Branch:** `test/file-upload`
**Mục tiêu:** Test khả năng phát hiện lỗi upload

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | Không validate file type - upload .php, .exe | UploadController.php | |
| 2 | Không giới hạn file size | UploadController.php | |
| 3 | Path traversal - filename chứa ../ | UploadController.php | |
| 4 | Lưu file vào public folder, ai cũng truy cập được | UploadController.php | |

---

## Kịch bản 7: Code Quality & Best Practices
**Branch:** `test/code-quality`
**Mục tiêu:** Test khả năng review chất lượng code

| # | Lỗi cài vào | File | CodeRabbit phát hiện? |
|---|-------------|------|----------------------|
| 1 | Không dùng Form Request, validate trong controller | CourseController.php | |
| 2 | Hardcode magic number (status = 1, 2, 3) | Course.php | |
| 3 | God method - 1 method 200 dòng làm mọi thứ | StatsController.php | |
| 4 | Không có error handling - no try/catch | ExportController.php | |
| 5 | Dead code - method không được gọi | Nhiều file | |

---

## Quy trình thực hiện từng kịch bản

```bash
# 1. Tạo branch mới từ develop
git checkout develop
git checkout -b test/security-injection

# 2. Code các lỗi theo bảng trên (hoặc nhờ Claude Code tạo)

# 3. Commit & push
git add .
git commit -m "Add feature for testing"
git push -u origin test/security-injection

# 4. Tạo PR trên GitHub (base: develop)

# 5. Chờ CodeRabbit review (2-5 phút)

# 6. Đọc kết quả, ghi vào cột "CodeRabbit phát hiện?"

# 7. Tiếp tục kịch bản tiếp theo
```

---

## Tổng kết (điền sau khi test xong)

| Kịch bản | Tổng lỗi | CR phát hiện | Tỉ lệ |
|----------|----------|-------------|--------|
| 1. Injection | 3 | /3 | % |
| 2. Auth | 5 | /5 | % |
| 3. Data Leak | 4 | /4 | % |
| 4. Performance | 4 | /4 | % |
| 5. Logic Bug | 4 | /4 | % |
| 6. File Upload | 4 | /4 | % |
| 7. Code Quality | 5 | /5 | % |
| **Tổng** | **29** | **/29** | **%** |
