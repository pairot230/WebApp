# KKU DORM — Initial members

ข้อมูลที่ผู้ใช้ระบุหลัง Phase 2 มีอยู่แล้วในตาราง students ของ Demo Database. ตรวจแบบ read-only แล้วพบครบทั้ง 5 คน คนละหนึ่งแถว โดยห้อง รหัส ชื่อ นามสกุล คณะ และ email ตรงกับรายการด้านล่าง. ยังไม่ได้นำเข้า application database.

| ห้องพัก | รหัสนักศึกษา | ชื่อ | นามสกุล | คณะ | KKU Mail | Role |
|---|---|---|---|---|---|---|
| 304 | 673380520-8 | นายธนชัย | ทองใบ | วิทยาลัยการคอมพิวเตอร์ | thanachai.th@kkumail.com | user |
| 305 | 673380534-7 | นายไพโรจน์ | ฉ่องสวนอ้อย | วิทยาลัยการคอมพิวเตอร์ | pairoj.c@kkumail.com | superadmin |
| 310 | 673380335-3 | นายพุฒิพงศ์ | พานิชพันธุ์ | วิทยาลัยการคอมพิวเตอร์ | pudtipong.p@kkumail.com | user |
| 316 | 673380334-5 | นายพีรพงษ์ | ราษีทอง | วิทยาลัยการคอมพิวเตอร์ | pheeraphong.r@kkumail.com | user |
| 414 | 673380322-2 | นายธาม | อะทอยรัมย์ | วิทยาลัยการคอมพิวเตอร์ | tharm.a@kkumail.com | user |

## แนวทางนำไปใช้

- ผู้ใช้กำหนดให้ pairoj.c@kkumail.com เป็น SuperAdmin โดยตรง; สมาชิกอีกสี่คนใช้ role user ตามค่าเริ่มต้น.
- บัญชี SuperAdmin จัดเตรียมผ่านขั้นตอน setup ที่ควบคุม โดยกำหนด SUPERADMIN_EMAIL=pairoj.c@kkumail.com ใน environment; ไม่ให้ browser หรือ public route กำหนด role และไม่เพิ่มสิทธิ์เพียงเพราะ email ผ่าน domain.
- เก็บชื่อและรหัสตามข้อมูลที่ส่งมา รวมคำนำหน้าใน first_name; ไม่ตัดหรือแปลงรหัสนักศึกษาเป็นตัวเลข.
- ต้องกำหนดหอ อาคาร และชั้นให้ห้องเหล่านี้ก่อนเชื่อม room_id; ไม่อนุมานโครงสร้างจากเลขห้องอย่างเดียว.
- นำเข้าทั้ง 5 คนจากแถวเดิมใน Demo Database พร้อมสมาชิกคนอื่น ไม่สร้าง seeder สมาชิก 5 คนซ้ำแยกต่างหาก. ตรวจ student_id และ email เพื่อจับคู่ application users; หากชี้คนละบัญชีให้รายงาน conflict ไม่ทับข้อมูล. การนำเข้าซ้ำต้องไม่สร้างสมาชิกซ้ำ.
- Demo students ไม่มีคอลัมน์ role; กำหนด superadmin ให้บัญชี pairoj.c@kkumail.com ใน application database ตามคำสั่งผู้ใช้ผ่าน controlled setup เท่านั้น โดยไม่แก้ Demo Database.
- ยังต้อง Google Login และตรวจ token ตาม Architecture; รายชื่อที่มีอยู่ไม่ใช่หลักฐานว่า login ผ่านแล้ว.
- ไม่แก้ Reference Project หรือ Demo Database.
