# BÁO CÁO ĐỒ ÁN CUỐI MÔN
## Hệ thống Điểm danh Nhận diện Khuôn mặt

---

## 1.1. Mô tả bài toán

### Đặt vấn đề

Trong môi trường doanh nghiệp và tổ chức hiện đại, việc quản lý chấm công nhân viên là một nghiệp vụ thiết yếu nhưng tiềm ẩn nhiều bất cập khi thực hiện thủ công. Các phương pháp truyền thống như ký tên, bấm thẻ từ hay nhập liệu thủ công dễ dẫn đến gian lận (chấm công hộ), sai sót dữ liệu và tốn kém nhân lực quản lý. Ngoài ra, việc tổng hợp báo cáo chuyên cần theo ngày/tuần/tháng đòi hỏi nhiều thời gian xử lý.

### Bài toán đặt ra

Xây dựng một **hệ thống điểm danh tự động bằng nhận diện khuôn mặt** kết hợp giữa thiết bị phần cứng nhúng (Raspberry Pi 4) và ứng dụng web quản lý (Laravel Dashboard), nhằm:

- Tự động xác định danh tính nhân viên thông qua camera và thuật toán nhận diện khuôn mặt, loại bỏ hoàn toàn việc chấm công thủ công.
- Ghi nhận giờ vào/ra của từng nhân viên theo ca làm việc đã được lên lịch trước, tự động phân loại trạng thái: đúng giờ, đi trễ, về sớm, vắng mặt.
- Cung cấp giao diện web cho quản trị viên và quản lý để theo dõi, điều chỉnh dữ liệu chấm công, quản lý nhân viên, phòng ban và ca làm việc.
- Xuất báo cáo chuyên cần dưới dạng Excel/PDF phục vụ công tác nhân sự.

### Phạm vi hệ thống

Hệ thống được triển khai gồm hai thành phần chính:

**1. Thiết bị biên (Raspberry Pi 4):**
- Đặt tại cổng ra/vào của tổ chức, kết nối với camera USB/CSI.
- Chạy chương trình Python liên tục, xử lý khung hình từ camera ở tần số ~30 FPS (thực tế xử lý nhận diện mỗi 3 khung để tối ưu CPU).
- Sử dụng thư viện `face_recognition` (dựa trên dlib và mạng nơ-ron sâu ResNet) để phát hiện và so khớp khuôn mặt với dữ liệu vector đặc trưng 128 chiều đã lưu trữ.
- Tự động phân biệt sự kiện **Check-in** hay **Check-out** dựa trên thời gian ca làm việc: nếu thời điểm nhận diện nằm trong nửa đầu ca → ghi nhận vào, nằm trong nửa sau ca → ghi nhận ra.
- Hỗ trợ **hoạt động offline**: khi mất kết nối mạng, các bản ghi được lưu tạm vào SQLite cục bộ và tự động đồng bộ lên server khi kết nối được khôi phục.
- Thực hiện **đồng bộ delta** dữ liệu mã hóa khuôn mặt (chỉ tải về những bản ghi mới/cập nhật từ lần đồng bộ cuối), tiết kiệm băng thông.

**2. Ứng dụng web quản lý (Laravel Dashboard):**
- Giao diện web đa vai trò: Super Admin, Admin, Manager, Employee.
- Quản lý toàn bộ danh mục: nhân viên, phòng ban, ca làm việc, lịch ca, thiết bị.
- Quản trị viên tải ảnh khuôn mặt nhân viên lên hệ thống; server tự động chạy job nền (Queue Job) để trích xuất vector đặc trưng 128 chiều qua script Python và lưu vào cơ sở dữ liệu.
- Cung cấp **REST API** cho thiết bị Raspberry Pi: xác thực thiết bị bằng token, đẩy dữ liệu điểm danh lên server, lấy danh sách mã hóa khuôn mặt, truy vấn ca làm việc đang hoạt động.
- Dashboard tổng quan thời gian thực: thống kê nhân viên có mặt/vắng mặt/đi trễ trong ngày, biểu đồ tỉ lệ chuyên cần 7 ngày gần nhất, danh sách lượt chấm công gần đây (cập nhật mỗi 10 giây).
- Cho phép quản lý chỉnh sửa, bổ sung thủ công các bản ghi điểm danh bất thường.
- Xuất báo cáo Excel/PDF theo khoảng thời gian và phòng ban tùy chọn.

### Các tác nhân trong hệ thống

| Tác nhân | Vai trò |
|---|---|
| **Super Admin** | Toàn quyền quản lý hệ thống: nhân viên, phòng ban, ca làm việc, thiết bị, báo cáo |
| **Admin** | Quản lý nhân viên, ca làm việc, điểm danh trong phạm vi được cấp |
| **Manager** | Xem và chỉnh sửa điểm danh của nhân viên trong phòng ban mình quản lý |
| **Employee** | Xem lịch sử điểm danh cá nhân |
| **Thiết bị Raspberry Pi 4** | Tác nhân phần cứng: nhận diện khuôn mặt và gửi dữ liệu điểm danh lên server qua API |

### Yêu cầu chức năng chính

1. **Nhận diện khuôn mặt và ghi nhận điểm danh tự động** tại cổng vào/ra.
2. **Đăng ký khuôn mặt nhân viên** qua giao diện web (tải ảnh lên, tự động trích xuất đặc trưng).
3. **Quản lý ca làm việc**: định nghĩa ca theo giờ, phân công cho từng nhân viên hoặc cả phòng ban, theo ngày trong tuần và khoảng thời gian hiệu lực.
4. **Tính toán trạng thái chuyên cần**: đúng giờ, trễ, về sớm, vắng mặt, nghỉ phép — dựa trên quy định ca và dung sai cho phép.
5. **Đồng bộ dữ liệu offline**: thiết bị không cần kết nối mạng liên tục; dữ liệu được đệm cục bộ và đồng bộ khi có mạng.
6. **Báo cáo và xuất dữ liệu** theo nhiều định dạng.
7. **Giám sát thiết bị**: theo dõi trạng thái online/offline của từng Raspberry Pi qua heartbeat định kỳ.

### Yêu cầu phi chức năng

- **Thời gian thực**: nhận diện và ghi nhận trong vòng vài giây từ khi xuất hiện trước camera.
- **Độ chính xác**: ngưỡng tin cậy nhận diện tối thiểu 50% (cấu hình được); ảnh khuôn mặt tại thời điểm chấm công được lưu lại để kiểm tra.
- **Khả năng mở rộng**: hỗ trợ nhiều thiết bị Raspberry Pi hoạt động đồng thời trên cùng một server.
- **Bảo mật**: xác thực thiết bị bằng token; phân quyền vai trò cho người dùng web; mật khẩu được mã hóa bcrypt.
- **Tính sẵn sàng**: hoạt động bình thường kể cả khi mất kết nối internet tạm thời (offline mode).

---

## 1.2. Sơ đồ chức năng tổng quát

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.3. Biểu đồ trường hợp sử dụng (Use Case)

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.4. Biểu đồ hoạt động

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.5. Biểu đồ trình tự

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.6. Biểu đồ Lớp (Class Diagram)

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.7. Biểu đồ luồng dữ liệu (Database Diagram)

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.8. Biểu đồ mối quan hệ giữa các dữ liệu

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.9. Thiết kế giao diện (các giao diện chính)

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.10. Thiết kế giải thuật

*(Sẽ được bổ sung theo yêu cầu)*

---

## 1.11. Thiết kế cách tiến hành Test

*(Sẽ được bổ sung theo yêu cầu)*
