# KKU DORM — Phase 2 Architecture

สถานะ: Phase 3–15 สร้าง Database, Authentication, Authorization, Dorm/User, Activity/QR/Attendance/Score, Complaint, Repair Management, Finance, Dashboard, Audit Log, Responsive UI, การทดสอบระบบ และ README แล้ว; รายละเอียดการติดตั้งและข้อจำกัดการตรวจจริงอยู่ใน README.md.

## 1. หลักการและฐานที่ตรวจพบ

- Project: `D:\web_\KKU DORM`, repository `pairot230/WebApp`, branch `kku-dorm`.
- Laravel 13.33.0 ตาม composer.lock, PHP ^8.3, Blade, Vite 8, Tailwind 4.
- Reference: Laravel Part 03 ใช้ named routes, Controller CRUD, `$request->validate()`, Eloquent, session, middleware, Blade และ transaction พร้อม rollback.
- ใช้ MVC ระดับเดียวกัน: Controller รับ request, validate, query/save model และคืน view/redirect. Model ประกาศ relationship. Blade แสดง form/table/error/flash message.
- ไม่เพิ่ม Repository, DTO, Service Layer, SPA, API token authentication หรือระบบ permission แบบ dynamic.
- ใช้ SQLite ใน development; migration ผ่าน Laravel Schema และไม่ผูก SQL กับ SQLite เพื่อย้ายฐานข้อมูลภายหลังได้.
- เก็บวันที่ใน UTC แล้วแสดง Asia/Bangkok; ปีการศึกษาเป็นเลข พ.ศ. แยกจากปีของ timestamp.
- ผู้ใช้ยืนยันหนึ่ง installation ต่อหนึ่งหอ เช่น หอ 8 มีเฉพาะข้อมูลหอ 8. หออื่นใช้เซิร์ฟเวอร์/ฐานข้อมูลแยก ไม่มีการแชร์ข้อมูลข้ามเซิร์ฟเวอร์. คง dorm_id และ hierarchy เดิมเพื่อความสัมพันธ์ของข้อมูล; Superadmin หมายถึงผู้ดูแล installation นี้. หน้าจัดการและ backend ปฏิเสธการสร้างหอที่สอง; Demo seeder ต้องใช้ mapping ของหอเดิม.

## 2. Folder structure ที่จะใช้

```text
app/Http/Controllers/
  Auth/GoogleLoginController.php
  DashboardController.php
  Admin/{User,Dorm,Building,Floor,Room,AcademicYear,Activity,Attendance,
         ScoreHistory,Complaint,RepairRequest,FinancialTransaction,
         MembershipPayment}Controller.php
  Member/{Profile,Activity,Complaint,RepairRequest,Finance,QrCode}Controller.php
  SuperAdmin/{AdminRole,AuditLog}Controller.php
app/Http/Middleware/{EnsureRole,EnsureUserIsActive}.php
app/Models/  (หนึ่ง Model ต่อ business table ในหัวข้อ 4)
resources/views/
  layouts/app.blade.php
  auth/login.blade.php
  dashboards/{superadmin,admin,user}.blade.php
  admin/<module>/{index,show,create,edit}.blade.php
  member/<module>/...
  superadmin/{admins,audit-logs}/...
routes/web.php
config/services.php  (Google client ID)
database/{migrations,seeders,factories}/
tests/Feature/<module>/
docs/ARCHITECTURE.md
```

ใช้ layouts และ Blade partial เฉพาะส่วนซ้ำจริง Controller แยก admin/member เพื่อให้ขอบเขตข้อมูลอ่านง่าย ไม่สร้าง base CRUD controller. เพิ่มไฟล์เมื่อเริ่ม module นั้นเท่านั้น.

## 3. Route structure (เป็นแผน ไม่ใช่ routes ที่ลงทะเบียนแล้ว)

ทุก route ใช้ web middleware, session และ CSRF. Named routes ระบุ prefix ตามกลุ่ม. GET ไม่เปลี่ยนข้อมูล.

| กลุ่ม | Method / Path | Controller / การทำงาน | Middleware |
|---|---|---|---|
| Public | GET / | redirect ไป login หรือ dashboard | web |
| Guest | GET /login | GoogleLoginController.create; name login | guest |
| Guest | POST /auth/google | GoogleLoginController.store; name auth.google | guest, throttle |
| Auth | POST /logout | GoogleLoginController.destroy; name logout | auth |
| Auth | GET /dashboard | DashboardController.index เลือก view ตาม role | auth, active |
| Member | GET /me, /me/scores, /me/qr | ข้อมูล/คะแนน/QR ของผู้ login | auth, active |
| Member | GET /me/activities, /me/activities/{activity} | กิจกรรมในหอของตนและประวัติเข้าร่วม | auth, active |
| Member | GET /me/complaints, /me/complaints/create, /me/complaints/{complaint}; POST /me/complaints | ดูและส่งเรื่องของตน | auth, active |
| Member | GET /me/repairs, /me/repairs/create, /me/repairs/{repair}; POST /me/repairs | ดูและแจ้งซ่อมของตน | auth, active |
| Member | GET /me/finance | ยอดรวมและรายการที่เผยแพร่ของหอตน; การชำระของตน | auth, active |
| Admin | /admin/users, /dorms, /buildings, /floors, /rooms, /academic-years, /activities | resource CRUD; names admin.* | auth, active, role:admin,superadmin |
| Admin | PATCH /admin/activities/{activity}/status | เปิด/ปิด/ยกเลิก | กลุ่ม Admin |
| Admin | GET /admin/activities/{activity}/attendances; POST path เดียวกัน | รายชื่อและ scan QR | กลุ่ม Admin |
| Admin | GET /admin/users/{user}/scores; POST path เดียวกัน | ประวัติและปรับคะแนน | กลุ่ม Admin |
| Admin | GET /admin/complaints, /admin/complaints/{complaint}; PATCH path รายการ | ติดตาม/เปลี่ยน status, assigned_to, note | กลุ่ม Admin |
| Admin | GET /admin/repairs, /admin/repairs/{repair}; PATCH path รายการ | ติดตาม/เปลี่ยน status, assigned_to, note | กลุ่ม Admin |
| Admin | /admin/financial-transactions | index/show/create/store/edit/update; ยกเลิกด้วย PATCH /{transaction}/void | กลุ่ม Admin |
| Admin | /admin/membership-payments | index/show/create/store; PATCH /{payment}/void | กลุ่ม Admin |
| Superadmin | GET /superadmin/admins; PATCH /superadmin/users/{user}/role | แต่งตั้ง/ถอดถอน admin เท่านั้น | auth, active, role:superadmin |
| Superadmin | GET /superadmin/audit-logs, /superadmin/audit-logs/{auditLog} | อ่าน audit | กลุ่ม Superadmin |

URI admin ในแถว resource ทุกตัวอยู่ใต้ `/admin/`. Member route ใช้ชื่อ `member.*`; Superadmin ใช้ `superadmin.*`. ไม่เปิด register/password reset และไม่เปิด endpoint ตรวจ email แบบ anonymous. Helper `checkUserInDatabase(email)` หากจำเป็นภายหลังต้องทำฝั่ง backend หลัง verify token เท่านั้น.

## 4. Database และ Model

ทุก business table ใช้ internal `id` bigint primary key และ timestamps ยกเว้น audit_logs ใช้ created_at อย่างเดียว. Foreign key ใช้ชนิดตรงกับ id. Required เว้นแต่ระบุ `?` (nullable). เงินใช้ decimal(12,2), คะแนน integer แบบ signed; ไม่ใช้ float. Status และ role ใช้ string + validation รายการที่อนุญาต เพื่อย้าย DB ง่าย.

| Table / Model | Fields สำคัญนอกจาก id/timestamps | Constraint |
|---|---|---|
| academic_years / AcademicYear | year unsigned small integer, starts_on?, ends_on?, is_active boolean | unique year; มี active ปีเดียวด้วย transaction ใน Controller |
| dorms / Dorm | code, name, description?, is_active | unique code |
| buildings / Building | dorm_id, code, name, is_active | unique(dorm_id,code) |
| floors / Floor | building_id, number unsigned small integer, name? | unique(building_id,number) |
| rooms / Room | floor_id, number string, capacity unsigned small integer, is_active | unique(floor_id,number); room number ไม่ใช่ global key |
| users / User | name, first_name?, last_name?, email, google_id?, student_id?, faculty?, phone?, role default user, dorm_id?, room_id?, qr_token? UUID, is_active default true, email_verified_at?, remember_token? | unique email, google_id, student_id, qr_token; password เดิมเปลี่ยนเป็น nullable และไม่ใช้ login |
| activities / Activity | dorm_id, academic_year_id, title, description?, starts_at, ends_at, location, score, status draft/open/closed/cancelled, created_by | ends_at > starts_at; score >= 0 |
| attendances / Attendance | user_id, activity_id, checked_in_by, checked_in_at | unique(user_id,activity_id) |
| score_histories / ScoreHistory | user_id, academic_year_id, activity_id?, attendance_id?, score, reason, created_by | unique attendance_id; index(user_id,academic_year_id) |
| complaints / Complaint | user_id, dorm_id, title, description, status pending/reviewing/in_progress/completed/cancelled, assigned_to?, note? | index(dorm_id,status) |
| repair_requests / RepairRequest | user_id, dorm_id, room_id?, reporter_name, reporter_type student/advisor/staff, room_label, category electrical/plumbing/civil/other, description, phone, email, appointment_date, appointment_time, status new/received/in_progress/waiting_parts/completed/cancelled, assigned_to?, note? | index(dorm_id,status); room_label เก็บ snapshot ที่ผู้แจ้งยืนยัน |
| financial_transactions / FinancialTransaction | dorm_id, academic_year_id, user_id?, type income/expense, category, title, amount, transaction_date, description?, status posted/void, is_public default false, created_by, voided_by?, voided_at?, void_reason? | amount > 0; index(dorm_id,academic_year_id,type,status) |
| membership_payments / MembershipPayment | user_id, dorm_id, academic_year_id, financial_transaction_id?, amount, paid_at?, status pending/paid/void, note? | unique(user_id,academic_year_id); unique financial_transaction_id |
| audit_logs / AuditLog | actor_id?, action, target_type, target_id, old_values? JSON, new_values? JSON, created_at | index(target_type,target_id); index(actor_id,created_at) |

เก็บ sessions/cache/jobs ของ Laravel เดิม. password_reset_tokens เป็น infrastructure เดิมที่ไม่มี route ใช้งาน; ตัดสินใจลบใน migration เฉพาะเมื่อไม่มีข้อมูลเดิมที่ต้องรักษา. ไม่เพิ่ม pivot roles หรือตาราง permission.

### Relationships

- Dorm hasMany Building, User, Activity, Complaint, RepairRequest, FinancialTransaction, MembershipPayment.
- Building belongsTo Dorm; hasMany Floor. Floor belongsTo Building; hasMany Room. Room belongsTo Floor; hasMany User, RepairRequest.
- User belongsTo Dorm และ Room (nullable สำหรับเจ้าหน้าที่); hasMany Attendance, ScoreHistory, Complaint, RepairRequest, MembershipPayment, FinancialTransaction.
- AcademicYear hasMany Activity, ScoreHistory, FinancialTransaction, MembershipPayment.
- Activity belongsTo Dorm, AcademicYear และ User ผ่าน created_by (`creator`); hasMany Attendance และ ScoreHistory.
- Attendance belongsTo User (`user`), Activity, User ผ่าน checked_in_by (`checkedInBy`); hasOne ScoreHistory.
- ScoreHistory belongsTo User, AcademicYear, Activity?, Attendance?, User ผ่าน created_by (`creator`).
- Complaint/RepairRequest belongsTo User (`reporter`), Dorm และ User? ผ่าน assigned_to (`assignee`); RepairRequest belongsTo Room?.
- FinancialTransaction belongsTo Dorm, AcademicYear, User? (`member`), User (`creator`), User? (`voidedBy`); hasOne MembershipPayment.
- MembershipPayment belongsTo User, Dorm, AcademicYear, FinancialTransaction?.
- AuditLog belongsTo User? (`actor`). target_type ใช้ชื่อ model ที่อนุญาต + target_id เป็น snapshot reference ไม่มี polymorphic framework เพิ่ม.

### Integrity และการเก็บประวัติ

- users.dorm_id เป็นหอที่สังกัดสำหรับทั้งสมาชิก/เจ้าหน้าที่; room_id ต้องอยู่ในหอนั้น. Admin จัดการเฉพาะหอสังกัด; superadmin ครอบคลุมทุกหอ. เลือกหนึ่งหอต่อคนในรุ่นแรก ไม่เพิ่ม pivot จนกว่าต้องใช้หลายหอจริง.
- สมาชิกต้องมีหอและห้องก่อนเปิดใช้งาน; เจ้าหน้าที่มีหอแต่ไม่มีห้องได้. ทุก record ใหม่ตรวจ consistency ของ dorm/year/user/activity ฝั่ง backend.
- FK ของข้อมูลธุรกิจใช้ restrict delete เพื่อรักษาประวัติ; dorm/room/user ปิดใช้งานแทนลบเมื่อมีข้อมูลอ้างอิง. UI แสดงเหตุผลเมื่อลบไม่ได้. actor_id ของ audit nullable + nullOnDelete และเก็บชื่อ/role ผู้ดำเนินการใน snapshot.
- ห้ามแก้หรือลบ attendance และ score history แบบทำลายหลักฐาน; แก้คะแนนด้วยรายการ adjustment signed พร้อม reason และ audit. คะแนนรวม SUM(score) ตาม user/year ไม่เพิ่ม total_score.
- Scan QR: ตรวจสิทธิ์ผู้ scan, token, สมาชิก active และหอตรงกิจกรรม, กิจกรรม open และอยู่ในช่วงเวลา. Insert attendance + score history + audit ใน transaction เดียว; unique constraints ป้องกัน request พร้อมกันและเช็กชื่อซ้ำ.
- QR token เป็น random identifier ไม่เป็น login credential; ดู QR ได้เฉพาะของตน. ไม่ใช้ student_id ที่เดาได้เป็นสิทธิ์เข้าถึง.
- Membership payment ใช้หนึ่งแถวต่อ user/year; เปลี่ยน pending เป็น paid พร้อมสร้าง income และเชื่อม transaction ใน transaction เดียว. รายรับรวมอ่าน financial_transactions เท่านั้น ป้องกันนับสองครั้ง. ยกเลิกต้อง void ทั้งคู่พร้อม audit; หากชำระใหม่ใช้ payment แถวเดิมและ income แถวใหม่ เก็บ income ที่ void ไว้.
- Finance แก้รายการทั่วไปพร้อม audit; income ที่ผูก payment ปรับผ่าน payment workflow เท่านั้น. ยอดรวมใช้ posted เท่านั้น. Member เห็นยอดรวมที่ประกาศและรายการ is_public โดยไม่ส่งชื่อ/email/ข้อมูลจ่ายของคนอื่น.
- Activity ลบได้เฉพาะยังไม่มี attendance/history; เมื่อมีประวัติให้ปิด/ยกเลิก. ไม่เปลี่ยน score/year/dorm หลังเช็กชื่อแล้ว.
- เก็บ audit แบบ append-only ใน transaction เดียวกับ score/role/finance/repair/complaint และข้อมูลหลักที่ถูกเปลี่ยน. ห้ามเก็บ Google token, session token, QR token หรือ secret ใน audit.

## 5. Roles และ Authorization

| การทำงาน | user | admin | superadmin |
|---|---|---|---|
| Profile/room/QR/score/attendance | ของตน | ของตน + สมาชิกในหอสังกัด | ทุกหอ |
| ส่ง complaint/repair | ของตน | ของตน | ของตน |
| จัดการสมาชิก/หอ/กิจกรรม/คะแนน/เรื่องร้องเรียน/ซ่อม/เงิน | ไม่ได้ | ในหอสังกัด | ทุกหอ |
| Finance ที่ประกาศ | ในหอตน + payment ของตน | รายละเอียดในหอ | ทุกหอ |
| Academic years | อ่านปีที่เกี่ยวข้อง | สร้าง/แก้ metadata; ห้ามลบปีที่ถูกใช้ | เช่นเดียวกัน |
| แต่งตั้ง/ถอดถอน admin | ไม่ได้ | ไม่ได้ | ได้ |
| Audit log | ไม่ได้ | ไม่ได้ | ได้ |

EnsureRole อ่าน role จาก authenticated User ใน DB; EnsureUserIsActive ปฏิเสธ inactive และ invalidate session. กลุ่ม admin ต้องตอบ 403 สำหรับ user แม้พิมพ์ URL เอง. Member queries เริ่มจาก `auth()->user()` relationship หรือ where user_id เสมอ และตรวจ owner ก่อน show; รายการคนอื่นตอบ 404 เพื่อไม่เผยว่ามีอยู่.

Admin form ไม่รับ role/google_id/qr_token และเปลี่ยนได้เฉพาะบัญชี user; ห้ามแก้/ลบ/ปิดใช้งาน admin หรือ superadmin ผ่าน User CRUD. Superadmin role route รับเฉพาะ user↔admin, ห้ามแตะ superadmin และห้ามถอดสิทธิ์ตัวเองผ่าน route นี้. บัญชี superadmin แรกจัดเตรียมผ่าน seeder/คำสั่งควบคุมภายหลังด้วย email KKU ที่กำหนดใน environment; ไม่มี public promotion route หรือบัญชี privileged ที่ hard-code.

## 6. Authentication

เลือก Google Identity Services บนหน้า Blade + POST ID token ไป Laravel ตรงตาม MASTER PROMPT. ใช้ Google PHP client library ตรวจ token ใน backend เมื่อ Phase 4; ไม่เขียน JWT verification เอง. ยังไม่ติดตั้ง package ใน Phase 2.

1. หน้า login แสดงปุ่ม Google, ข้อความบัญชี KKU, loading/error; backend สร้าง nonce เก็บ session และส่งเข้า GIS.
2. ส่ง credential ผ่าน same-origin POST พร้อม Laravel CSRF; throttle endpoint. Backend verify signature, aud ตรง GOOGLE_CLIENT_ID, iss, exp, nonce และ email_verified.
3. ตรวจ exact email domain `kkumail.com` หรือ `kku.ac.th`; ต้องมี verified Workspace hd ที่ตรง allowed domain ด้วย เพื่อไม่อาศัย third-party email claim อย่างเดียว. แจ้ง domain ผิดด้วยข้อความ “กรุณาเข้าสู่ระบบด้วยบัญชี KKU เท่านั้น”. ห้ามเชื่อ email หรือ role จาก browser.
4. หา user ที่ถูกจัดเตรียมใน DB และ active; ไม่ auto-register แม้อีเมลผ่าน. อีเมลใหม่ที่ไม่พบแจ้งให้ติดต่อผู้ดูแล.
5. ใช้ Google sub เป็น google_id ที่ unique. การผูกครั้งแรกยอมรับเฉพาะ verified KKU email ตรง user ที่มีอยู่; หลังผูกแล้วต้อง sub และ email ตรง ห้ามย้ายบัญชีอัตโนมัติจาก email claim.
6. Auth::login, regenerate session และล้าง nonce. ใช้ Laravel web session guard; ไม่มี username/password/register, ไม่เก็บ Google password/access token/ID token.
7. Logout ผ่าน POST, Auth::logout, invalidate session, regenerate CSRF token. Production ใช้ HTTPS และ secure/HttpOnly/SameSite cookie.

ก่อนทดสอบจริงต้องตั้ง GOOGLE_CLIENT_ID, allowed JavaScript origins และตรวจบัญชี KKU จริงว่ามี hd ตามเงื่อนไข. หากบัญชีหน่วยงานไม่เป็น Workspace ให้ตรวจนโยบายยืนยันตัวตนก่อนเปลี่ยนเงื่อนไข; ห้ามลดการตรวจโดยเงียบ.

## 7. Demo และ Excel ในอนาคต

ข้อมูลสมาชิกที่ผู้ใช้ยืนยันและบัญชี SuperAdmin เริ่มต้นอยู่ใน [INITIAL_MEMBERS.md](INITIAL_MEMBERS.md). ใช้เป็นข้อกำหนดสำหรับ Phase 3; SUPERADMIN_EMAIL กำหนดเป็น pairoj.c@kkumail.com ผ่าน environment.

Demo SQLite เปิดแบบ read-only; มี students 124 แถว/62 ห้อง ไม่มี relationship แบบ FK. Map student_id, first_name, last_name, faculty, kku_mail→email และ room→Room ผ่าน dorm/building/floor ที่ผู้ดูแลกำหนด. ห้ามเดาชั้น/อาคารจากเลขห้องอย่างเดียว.

119 email เป็น mock.kku.test ใช้เฉพาะ fixture ทดสอบและ UI ไม่ผ่าน production login; 5 email เป็น kkumail.com ไม่ได้แปลว่ามีสิทธิ์ Google จริง. สร้าง sample activities/finance แยกใน seeders ภายหลังและติดป้าย demo ชัดเจน.

Schema ใช้ internal ids; student_id/room number เป็น string เพื่อรักษาขีดและเลขศูนย์. Excel column names ไม่กำหนดชื่อ DB columns. ไม่สร้าง import tables ในตอนนี้. ภายหลังใช้ Controller ที่แยกงาน Upload→Mapping→Validation→Preview→Confirm→Insert/Update→Report และ transaction ต่อชุดที่เหมาะสม. รายงาน imported/updated/skipped/failed พร้อม row; ห้าม import role จากไฟล์หรือทับ privileged users. จับคู่ room ด้วย dorm/building/floor/number และตรวจ duplicate email/student_id ก่อนเขียน.

## 8. Review และแผนตรวจใน Phase ถัดไป

- ตรวจแล้ว: 14 business tables ครบ MASTER PROMPT; ทุก FK ชี้ table ที่กำหนด; nullable รองรับ staff, manual score, pending payment; unique รองรับ duplicate attendance และปีละหนึ่ง payment.
- ตรวจแล้ว: member ownership, admin dorm scope, protected roles และ superadmin-only audit/role route ครบ; authentication ไม่มี password flow.
- ตรวจแล้ว: Controllers/Models/Blade/middleware/transaction ระดับใกล้ Reference; เพิ่ม complexity เฉพาะ integrity และ security ที่ requirement ต้องการ.
- Phase 3: migrate บนฐานทดสอบ, ตรวจ FK/unique/rollback, relationships และ demo seed โดยไม่เปลี่ยนไฟล์ demo ต้นฉบับ.
- Phase 4–5: token invalid/expired/aud/nonce/domain/hd/sub mismatch, unknown/inactive user, session/logout, role escalation, cross-dorm และ ownership tests.
- Phase 7: simultaneous duplicate scan, transaction rollback, score adjustment/year consistency.
- Phase 10: payment duplicate/concurrent request, void/re-pay, no double count, finance privacy และ audit rollback.
- Phase 13–14: Desktop/Tablet/Mobile, forms/errors/empty/loading/confirm และ Feature tests ของทุก module.
- AGENTS.md ขอ Laravel Boost ก่อนแก้ application; Phase 2 เป็นเอกสารเท่านั้น. จัด setup ตามคำสั่งนี้ก่อน implementation ใน Phase 3 โดยไม่ติดตั้ง dependency ระหว่างวางแผน.

## Sources

- Laravel authentication: https://laravel.com/docs/13.x/authentication
- Google ID token verification: https://developers.google.com/identity/gsi/web/guides/verify-google-id-token

## Phase 3 — ผลการดำเนินงาน

- Migration สร้างครบ 14 business tables (รวม users เดิมที่เพิ่ม columns) และรักษา infrastructure tables เดิม.
- Models ใช้ explicit relationship return types และ casts. User ไม่เปิด mass assignment ให้ role/google_id/qr_token และซ่อน identifiers สำคัญเมื่อ serialize.
- DatabaseSeeder เรียก AcademicYearSeeder เท่านั้น; run ซ้ำไม่สร้างปีซ้ำ. ไม่มี test@example.com seed ใน application database.
- DemoMemberSeeder แยกจาก default seed ใช้ source read-only และ mapping ที่กำหนดเอง. ตั้ง DORM_DEMO_DATABASE และ DORM_DEMO_ROOM_MAPPING แล้วเรียก `php artisan db:seed --class=DemoMemberSeeder` เฉพาะ local/testing.
- Mapping JSON ต้องมี dorm_code, dorm_name, building_code, building_name และ rooms ซึ่งแต่ละรายการมี number (string), floor (integer), capacity (integer). ไม่เดาชั้นจากเลขห้อง; ต้องครอบคลุมห้องทั้งหมดใน source. Import ข้ามสมาชิกเดิมที่ identity ตรงและไม่เปลี่ยน role; identity conflict ทำให้ transaction rollback.
- หลังนำเข้าสมาชิก ตั้ง SUPERADMIN_EMAIL=pairoj.c@kkumail.com แล้วเรียก `php artisan db:seed --class=SuperAdminSeeder`; ต้องพบสมาชิก active ก่อน ไม่สร้างสมาชิกซ้ำ. มี audit และเรียกซ้ำไม่เพิ่ม audit ซ้ำ.
- Local database migrate และ seed ปี 2569 แล้ว; ยังไม่มีการนำเข้า 124 คน เพราะยังไม่กำหนด mapping หอ/อาคาร/ชั้น. Demo และ Reference ไม่ถูกแก้ไข.
- ตรวจ migration rollback/re-run ในฐานทดสอบแยก; down ถอด columns/FKs/tables ที่เพิ่ม และรักษา users เดิม. password คง nullable ระหว่าง rollback เพื่อไม่ทำลายบัญชีที่ไม่มี password.
- Feature tests 12 รายการ/122 assertions ผ่าน; ตรวจ relationships, FK, unique attendance/score/payment, scoped room, signed score, transaction rollback, audit actor deletion, source hash และ seed idempotence. SQLite integrity ok และ foreign_key_check ไม่พบปัญหา.
- ติดตั้ง Laravel Boost ตาม AGENTS.md และอ่าน guidelines ที่สร้างใหม่. ไม่มี Feature หรือ Route ใหม่; หยุดรอ Phase 4.

## Phase 4 — Authentication

- ใช้ Google Identity Services บน Blade และ google/apiclient 2.20.1 ตรวจลายเซ็น token ฝั่ง backend. Application session ใช้ Laravel web guard; ไม่มี password login/register/reset routes.
- ตรวจ aud, iss, exp, email_verified, sub, nonce และ exact email/Workspace domain จาก token ที่ verified แล้ว. อนุญาต kkumail.com และ kku.ac.th; hd ต้องเป็นหนึ่งในสอง domain นี้. ไม่รับ email/role จาก frontend และไม่ auto-register.
- Nonce อายุ 10 นาที ใช้ครั้งเดียว; login endpoint มี CSRF และ throttle 10 requests/minute. Google HTTP client จำกัด timeout. Token ไม่ถูกบันทึกลง DB/session/old input.
- ผูก Google sub กับสมาชิก active ที่มีอยู่เท่านั้น; ไม่ย้ายบัญชีหรือเปลี่ยน role จาก Google claim. Login regenerate session; POST logout invalidate session และ regenerate CSRF token. Middleware ปิด session ของบัญชี inactive.
- หน้า login มีข้อความไทยตาม requirement, Google icon, Loading/Error/Retry และ responsive CSS. หน้า /dashboard ตอนนี้เป็นหน้ารับรองการเข้าสู่ระบบเท่านั้น ยังไม่ใช่ Dashboard ของ Phase 11.
- ตั้ง GOOGLE_CLIENT_ID ใน .env เป็น Web OAuth client และเพิ่ม Authorized JavaScript origins ให้ตรง URL ที่เปิด เช่น http://localhost:8000 และ http://127.0.0.1:8000. GIS callback POST ไม่ต้อง Client Secret หรือ OAuth redirect URI ใน flow นี้. Production ใช้ HTTPS และ SESSION_SECURE_COOKIE=true.
- ก่อนทดสอบบัญชีจริง ต้องนำเข้าสมาชิกผ่าน mapping ตาม Phase 3 แล้วรัน SuperAdminSeeder สำหรับ pairoj.c@kkumail.com. Demo students ไม่ใช่ application users โดยตรง.
- Tests: PHP suite 41 tests/263 assertions รวม token signed RSA ผ่าน Google verifier จริงด้วย JWKS fixture และปฏิเสธ forged signature/wrong audience/issuer/expired token. Test keys ใน tests/Fixtures เป็น synthetic keys สำหรับทดสอบ ไม่มีความสัมพันธ์กับ Google หรือ credentials จริง.
- Frontend tests 5 รายการผ่าน ครอบคลุม credential + CSRF submission, redirect, domain errors, network failures และ Google script failures. ตรวจ Blade compile, routes, formatting, Composer validation/audit และเปิดหน้า login จริงใน Browser แล้ว.
- ยังไม่ยืนยัน Google Login กับบัญชี KKU จริง: GOOGLE_CLIENT_ID ยังว่าง และ application users ยังไม่มีข้อมูล. ต้องตรวจ hd ของบัญชีจริงตามข้อกำหนดก่อนถือว่า live integration ผ่าน.
- หยุดอยู่ Phase 4 ไม่เริ่ม Phase 5.

## Phase 5 — Authorization

- EnsureRole middleware ตรวจ role จาก User ฝั่ง backend แบบ exact match. Unknown role ไม่ผ่าน. ลงทะเบียน alias role ใน bootstrap/app.php.
- EnsureUserIsActive โหลด User ใหม่จาก DB ทุก protected request เพื่อให้การถอดสิทธิ์/ระงับบัญชีมีผลแม้ session เดิมยังอยู่. ค่า role ใน session หรือ request ไม่กำหนดสิทธิ์.
- UserPolicy ใช้ Laravel convention discovery: viewAny/view/update/delete/changeRole. Superadmin เข้าถึงทุกหอ; admin ดูแลเฉพาะ user ในหอสังกัดและไม่จัดการ admin/superadmin; user ดูได้เฉพาะบัญชีตนเอง. ห้ามลบตัวเองผ่าน policy.
- /me ใช้ authenticated User เท่านั้น ไม่รับ user_id/email ที่ผู้เรียกส่งมาสำหรับเลือกสมาชิก. /admin/users และ /admin/users/{user} เป็นหน้ารายชื่อ/ข้อมูลแบบอ่านอย่างเดียวสำหรับตรวจสิทธิ์ ยังไม่มี CRUD ของ Phase 6.
- /admin/* ตรวจ auth + active + role:superadmin,admin. /superadmin/* ตรวจ auth + active + role:superadmin. User พิมพ์ URL admin โดยตรงได้ 403; guest ถูกส่ง login หรือได้ 401 เมื่อเรียก JSON.
- Admin member listing จำกัด role=user และ dorm_id ใน query; การแสดงรายบุคคลตรวจ UserPolicy อีกครั้ง. Admin ที่ไม่มีหอสังกัดเข้า member listing ไม่ได้.
- Superadmin ใช้ /superadmin/admins และ PATCH /superadmin/users/{user}/role เพื่อแต่งตั้ง/ถอดถอน admin. รับ role user/admin เท่านั้น, ตรวจ policy ซ้ำหลัง lock target, บันทึก role + audit ใน transaction เดียว. การแต่งตั้งต้องมีหอสังกัดและสมาชิก active. ไม่เปลี่ยน superadmin หรือสิทธิ์ตนเองผ่าน endpoint นี้.
- Role form มี CSRF และ confirmation; menu visibility เป็นเพียง UI ส่วนการอนุญาตจริงอยู่ที่ middleware/policy/controller. User.role/google_id/qr_token ไม่เป็น mass assignable.
- User model มี role=user และ is_active=true เป็นค่าเริ่มต้นตรงกับ database เพื่อให้ policy ใช้ User ที่เพิ่งสร้างได้ทันที.
- Tests ผ่าน 53 tests/354 assertions รวม Superadmin, Admin, User, guest, unknown role, direct URL, cross-dorm, protected accounts, session role spoofing, demotion ระหว่าง session, CSRF และ rollback เมื่อ audit ล้มเหลว. Blade compile และ formatting ผ่าน.
- ไม่แก้ Demo Database/Reference ไม่ติดตั้ง dependency ใหม่และไม่เพิ่ม permission tables. ยังไม่มี application members จาก Demo; live Google setup ที่ค้างใน Phase 4 ยังต้อง Client ID และ room mapping.
- หยุดหลัง Phase 5; ยังไม่เริ่ม Phase 6.

## Phase 6 — Dorm & User Management

- Resource CRUD routes สำหรับ dorms/buildings/floors/rooms/users ใต้กลุ่ม admin เดิม. ใช้ Controllers, Eloquent, Laravel Validation, Policies และ Blade; ไม่มี Service/Repository layer หรือ dependencies ใหม่.
- แต่ละรายการมี Search/Filter, pagination พร้อมรักษา query string, show/create/edit forms, CSRF, delete confirmation, error/empty/flash states. Filter โครงสร้างตาม parent; Dorm/Building/Room และ User มี status filter; User เพิ่ม role/dorm/room filters และค้นหารหัส/ชื่อ/email/phone/เลขห้อง.
- Superadmin จัดการทุกหอและสร้างหอใหม่ได้; admin จัดการเฉพาะหอสังกัด และสร้างอาคาร/ชั้น/ห้อง/สมาชิกในหอตน. ไม่เปิดสิทธิ์สร้างหอใหม่ให้ admin เพราะจะอยู่นอกขอบเขตหอสังกัดเดิม. User อ่าน /me เท่านั้น.
- Policy ตรวจ resource และ parent ที่ส่งมาทุก write; options/list queries มี dorm scope. โครงสร้างเดิมไม่ย้าย parent ผ่าน edit เพื่อรักษา user.dorm_id และประวัติ. Unique code/number มี scope ตาม hierarchy; foreign keys ป้องกันการลบข้อมูลอ้างอิงพร้อมข้อความแนะนำแทน error 500.
- ผู้ใช้ยืนยันความจุห้องละ 2 คน: Room::CAPACITY=2, Room forms และ DemoMemberSeeder รับ capacity=2 เท่านั้น. User assignment ตรวจจำนวน active occupants และ lock ห้องใน transaction; คนที่ 3 หรือการเปิดใช้งานคนที่ 3 ถูกปฏิเสธ. สมาชิก inactive ไม่กินความจุ แต่เก็บ room reference ไว้เป็นข้อมูลเดิม. UI แสดงผู้พักปัจจุบัน/ห้องว่าง.
- User form มี student_id, name, email, phone, dorm, room, status; role แสดงแบบอ่านอย่างเดียว. สร้างบัญชี role=user โดยไม่มี password พร้อม random QR token; role เปลี่ยนผ่าน Superadmin role endpoint เดิม. ห้าม input role/google_id/qr_token/password ผ่าน CRUD.
- ห้องที่เลือกต้องอยู่ในหอที่เลือก; active occupant ใช้ได้เฉพาะ room/building/dorm ที่ active. ห้องเต็มต้องย้ายไปห้องว่างหรือระงับสมาชิกเดิมก่อน. Update ของคนในห้องเดิมไม่นับตนเองซ้ำ.
- Email ของบัญชีที่ผูก Google แล้วเปลี่ยนผ่าน CRUD ไม่ได้ เพื่อรักษา identity binding; email ใหม่ normalize lowercase และตรวจ uniqueness. Google domain restriction ยังคงอยู่ที่ authentication ไม่ปิดกั้น sample contact emails ในหน้าจัดการข้อมูล.
- User create/update/delete พร้อม audit ใน transaction; ลบคนมีประวัติไม่ได้ ให้ระงับบัญชีแทน. Admin จัดการ admin/superadmin ไม่ได้; self-delete และ self-deactivation ถูกปฏิเสธ.
- Tests ผ่าน 71 tests/563 assertions ครอบคลุม CRUD ทุกระดับ, rendering, unique validation, FK delete, parent reassignment, cross-dorm, privilege tampering, student room assignment, capacity 2, reactivation, search/filter scope และ CSRF. Migration status ปกติ; ไม่เพิ่ม migration ใน Phase นี้.
- Demo Database และ Reference ไม่ถูกแก้ไข. สมาชิก Demo ยังไม่นำเข้าเพราะยังไม่มี hierarchy mapping; Google live setup ที่ค้างยังต้อง Client ID. ไม่เริ่ม Phase 7.

## Phase 7 — Activity / QR / Attendance / Score

- Activity resource CRUD ใต้ admin middleware และ ActivityPolicy; Superadmin ทุกหอ, Admin เฉพาะหอสังกัด. มี Search/Status/Academic Year filter, pagination, รายละเอียดและรายชื่อผู้เข้าร่วม. สถานะ draft/open/closed/cancelled เปลี่ยนผ่านหน้าแก้ไข; backend ตรวจ open และช่วง starts_at/ends_at รวมถึงหอที่ active ก่อนเช็กชื่อ.
- เวลาใน form เป็น Asia/Bangkok และเก็บ UTC ด้วยความละเอียดนาที. คะแนนกิจกรรมไม่ติดลบ. เมื่อมี attendance/score history แล้วห้ามเปลี่ยน dorm, academic year, score หรือลบกิจกรรม. ปิด/ยกเลิกกิจกรรมไม่ลบหรือย้อนคะแนนเดิม; ใช้รายการปรับคะแนนพร้อมเหตุผลหากต้องแก้ไข.
- ใช้ endroid/qr-code 6.0.9 (PHP 8.3) สร้าง SVG ของ random UUID ประจำตัวสมาชิก. ภาพอ่านได้เฉพาะเจ้าของผ่าน /me/qr/image, private/no-store; ไม่ใส่ student ID/email ใน QR และไม่เปิดรับ user_id เพื่อเลือกคนอื่น. บัญชีเดิมที่ไม่มี token สร้างครั้งเดียวภายใน transaction; QR token ไม่เป็น password หรือสิทธิ์เข้าสู่ระบบ.
- html5-qrcode 2.3.8 อ่านกล้อง/รูปภาพจากไฟล์ JS ใน public/js/vendor พร้อม license จึงไม่ต้องพึ่ง CDN. ดู API จาก https://github.com/endroid/qr-code และ https://github.com/mebjas/html5-qrcode. กล้องบนโทรศัพท์ต้อง HTTPS และการอนุญาตกล้อง; localhost ใช้พัฒนาบนเครื่องได้.
- เจ้าหน้าที่เลือกกิจกรรม → สแกน QR → backend preview ชื่อ/รหัสสมาชิก → ตรวจคนตรงกับ QR → ยืนยัน. Preview ไม่สร้าง Attendance/Score. Backend store ตรวจซ้ำทุกครั้งโดยไม่เชื่อผล preview หรือข้อมูลสมาชิก/คะแนนจาก frontend. Random token ป้องกันการเดารหัสนักศึกษา แต่ QR ที่ถูกคัดลอกยังต้องอาศัยเจ้าหน้าที่ตรวจเจ้าของจริง.
- Attendance store ล็อก activity และ member ใน transaction เดียว ตรวจสิทธิ์ actor, สมาชิก active มี student_id และ role ที่รู้จัก, หอตรงกัน, สถานะและเวลา, duplicate ก่อนสร้าง Attendance + ScoreHistory + AuditLog. Admin เช็กชื่อ Superadmin ไม่ได้; Superadmin ซึ่งเป็นสมาชิกนักศึกษาสามารถเข้าร่วมได้. รับเฉพาะ qr_token; user_id/activity_id/score/checked_in_by ที่ปลอมส่งมาถูกปฏิเสธ. ใช้ transaction retries 3 สำหรับ concurrency/deadlock.
- Constraint เดิม unique(user_id, activity_id) และ unique(score_histories.attendance_id) เป็นด่านฐานข้อมูลป้องกันซ้ำ. ไม่เพิ่ม migration เพราะ schema/FKs ที่สร้าง Phase 3 รองรับครบ. กิจกรรมคะแนน 0 ยังมี attendance/history ได้; คนเดียวเข้าร่วมหลายกิจกรรมได้.
- /me/scores ใช้ authenticated user เท่านั้น คำนวณยอดจากประวัติที่กรองปี ไม่ใช้ score column บน users. /activities แสดงกิจกรรมเผยแพร่ในหอปัจจุบันและประวัติที่ตนเคยเข้าร่วม แม้ย้ายหอแล้ว. ไม่แสดง attendance ของคนอื่น.
- Admin ดู/ปรับคะแนนผ่าน UserPolicy เดิม: จัดการเฉพาะสมาชิก role=user ในหอสังกัด; Superadmin ทุกคน. ปรับคะแนนเป็นรายการใหม่แบบบวก/ลบที่ต้องมีเหตุผลและปีการศึกษา พร้อม created_by และ audit ใน transaction. ไม่มี endpoint แก้/ลบประวัติเดิม หรือรับ attendance/activity/creator จาก frontend.
- Scanner มี loading/error, preview/confirm/cancel, ห้ามส่งซ้ำขณะรอ response, CSRF, timeout, stop camera, file scanning; check-in/preview จำกัด 60 requests/minute ต่อบัญชี. Backend duplicate ตอบ 409; activity/member validation ตอบ 422; scope/role ตอบ 403.
- ทดสอบรวมผ่าน 93 tests/756 assertions (Phase 7 เพิ่ม 22 tests) และ frontend 10 tests (scanner เพิ่ม 5). ครอบคลุม CRUD/status/timezone, private/stable QR, forged token/fields, cross-dorm/direct URL/roles, duplicate, zero score, closed/expired activity, protected accounts, signed adjustment/year totals, own history, CSRF และ atomic rollback เมื่อ ScoreHistory/Audit ล้มเหลว.
- ทดสอบ SVG QR round-trip ด้วย OpenCV ถอดเป็น UUID ตรงกัน. Pint, Blade cache, Vite build, Composer validate และ npm audit ผ่าน (0 vulnerabilities). แก้ dependency เครื่องมือเดิม source-map-js และ override shell-quote รุ่นที่แก้ช่องโหว่ โดยไม่เปลี่ยน major version ของ concurrently.
- ยังไม่ได้ทดสอบกล้องโทรศัพท์จริงหรือ Google login ด้วยบัญชีจริง เพราะ application members/Client ID ยังไม่ได้ตั้งค่าจาก Phase ก่อน. การทดสอบใช้ฐานแยก ไม่เพิ่มสมาชิกตัวอย่างลงฐาน application. Reference และ Demo ไม่ถูกแก้ไข; หยุดหลัง Phase 7.

## Phase 8 — Complaint

- ใช้ complaints schema/FKs เดิม ไม่มี dependency, migration หรือ table ใหม่. Member routes /complaints รองรับ index/create/store/show; admin routes /admin/complaints รองรับ index/show/update. ไม่มี endpoint ให้สมาชิกแก้สถานะ มอบหมาย หรือแก้/ลบเรื่องเดิม.
- สร้างจาก title/description เท่านั้น; user_id และ dorm_id มาจากบัญชีใน session, status=pending, assigned_to/note ว่าง. บัญชีต้อง active และมีหอที่ใช้งาน. ปฏิเสธ field ที่ปลอม owner/dorm/status/assignee/note. Audit การสร้างอยู่ใน transaction เดียวกับ Complaint.
- สถานะ pending/reviewing/in_progress/completed/cancelled แสดงเป็น รอดำเนินการ/กำลังตรวจสอบ/กำลังดำเนินการ/ดำเนินการเสร็จสิ้น/ยกเลิก. เจ้าหน้าที่เปลี่ยนสถานะได้ทั้ง 5 แบบโดยไม่ลบประวัติ audit.
- Member index query ผ่าน user.complaints และ show ตรวจเจ้าของ แม้ปลอม query user_id หรือเดา URL. ประวัติคงหอ ณ วันที่แจ้งและเจ้าของเดิมเมื่อย้ายหอ.
- ComplaintPolicy: Superadmin จัดการทุกเรื่องภายใน installation; Admin เฉพาะเรื่องในหอสังกัดและไม่จัดการเรื่องของ Superadmin. Routes ตรวจ auth/active/role เพิ่มจาก Policy. หน้าเรื่องของฉันยังจำกัดเจ้าของแม้ actor เป็นเจ้าหน้าที่.
- ผู้รับผิดชอบต้องเป็น Admin active ในหอของเรื่อง; Superadmin เลือก Superadmin active ได้เพิ่มเติม. ตรวจซ้ำขณะ lock complaint/assignee ใน transaction; ordinary user, เจ้าหน้าที่ inactive/ต่างหอ และ Admin ที่พยายามมอบหมาย Superadmin ถูกปฏิเสธ. ปลดมอบหมายและล้างหมายเหตุได้.
- หมายเหตุเป็นผลดำเนินการที่แสดงให้เจ้าของเรื่องเห็น; ไม่ใช่ช่องบันทึกลับ. แสดงผู้รับผิดชอบเฉพาะชื่อ. Blade escape title/description/note. มี search/status filter, pagination, empty/error/success states และ loading ขณะส่งเรื่อง.
- Update รับเฉพาะ status/assigned_to/note; เก็บ audit old/new พร้อม actor และ rollback หาก audit ล้มเหลว. การกดบันทึกโดยไม่มีการเปลี่ยนแปลงไม่เพิ่ม audit.
- ผู้ใช้ยืนยันใช้หนึ่งหอต่อเซิร์ฟเวอร์/ฐานข้อมูล. DormPolicy และ DormController ปฏิเสธการสร้างหอที่สองและซ่อนปุ่มเพิ่มหลังตั้งหอแรก; DemoMemberSeeder ปฏิเสธ mapping ของหออื่น. ไม่ hard-code ว่าทุก installation ต้องชื่อหอ 8 และไม่ลบ hierarchy/dorm_id เดิม. หออื่นต้องติดตั้งและตั้งค่าฐานข้อมูลแยก.
- Tests ผ่าน 109 tests/978 assertions รวม Complaint 15 กรณี และ single-dorm 1 กรณี: create/view/list, ทั้ง 5 สถานะ, assignment/note/clear, forged input, own data/direct URL, roles/cross-dorm/protected Superadmin, moved member history, XSS escaping, CSRF และ audit rollback. Pint/Blade compile/route inspection/migration status ผ่าน.
- ไม่แก้ Reference/Demo และไม่ seed สมาชิกหรือร้องเรียนตัวอย่างลง application database. หยุดหลัง Phase 8; ยังไม่เริ่ม Phase 9.

## Phase 9 — Repair Management

- ใช้ repair_requests schema และ relationships เดิม. เพิ่ม Member/Admin RepairRequestController, RepairRequestPolicy, Blade, routes และ navigation; ไม่เพิ่ม table/migration/dependency. ระบบยังใช้หนึ่งหอต่อเซิร์ฟเวอร์และฐานข้อมูลแยกจากหออื่น.
- /repairs มี create/store/index/show; /admin/repairs มี index/show/update. ฟอร์มชื่อ แจ้งซ่อม และปุ่ม บันทึกข้อมูล พร้อมทั้ง 9 fields: reporter_name, reporter_type, room_label, category, description, phone, email, appointment_date, appointment_time. เติมชื่อ/ห้อง/โทรศัพท์/email จากบัญชีเป็นค่าเริ่มต้น แก้ข้อมูลติดต่อที่ใช้กับงานนี้ได้โดยไม่เปลี่ยนบัญชี.
- Reporter types: student/advisor/staff → นักศึกษา/ที่ปรึกษาหอพัก/เจ้าหน้าที่หอ. เป็นข้อมูลผู้แจ้งของงาน ไม่ใช่ role หรือการอนุญาต. Categories: electrical/plumbing/wood_masonry/other → งานไฟฟ้า/งานประปา/งานไม้ / งานปูน/อื่นๆ.
- Status: new/received/in_progress/waiting_parts/completed/cancelled → แจ้งใหม่/รับเรื่องแล้ว/กำลังดำเนินการ/รออะไหล่/เสร็จสิ้น/ยกเลิก. เริ่มต้น new จาก backend. เจ้าหน้าที่เปลี่ยนได้ทั้ง 6 แบบ; ไม่มี transition workflow เพิ่มเติม.
- Backend ผูก user_id/dorm_id/room_id จาก authenticated member ที่ active และมีหอใช้งานเท่านั้น. ปฏิเสธ owner/dorm/room/status/assigned_to/note ที่ frontend ปลอมส่งมา. ห้องอ้างอิงต้องอยู่ในหอของบัญชี; room_label เป็นคำอธิบายห้อง/สำนักงานจาก form ส่วน room_id เป็นห้องจริงของบัญชี. เจ้าหน้าที่ที่ไม่มี room_id แจ้งจากสำนักงานได้.
- Appointment date/time เป็นวันและเวลานัดหมายท้องถิ่น Asia/Bangkok เก็บเป็นคู่ date/time ไม่ใช่ UTC timestamp. ตรวจ format และต้องเป็นเวลาในอนาคต รวมกรณีนัดวันนี้แต่เวลาผ่านไปแล้ว และช่วงหลังเที่ยงคืนไทยที่ยังเป็นวันก่อนหน้าใน UTC. created_at/updated_at ยังคง UTC แสดงเวลาไทย.
- Search ชื่อผู้แจ้ง/ห้อง/รายละเอียด/phone/email โดย grouped OR เพื่อไม่หลุด owner/dorm scope. Filters status/category พร้อม pagination/query string และสถานะไม่พบข้อมูล/validation error/success/loading ตอน submit.
- Member index ใช้ user.repairRequests และ show ตรวจเจ้าของเสมอ; query user_id หรือการเดา URL ไม่ทำให้เห็นงานคนอื่น. ประวัติหอ ห้อง ชื่อและช่องทางติดต่อ ณ วันที่แจ้งไม่เปลี่ยนตามบัญชีที่ย้ายหอ/ห้อง.
- RepairRequestPolicy และ auth/active/role middleware ใช้ pattern ของ Complaint: Superadmin จัดการภายใน installation; Admin เฉพาะหอตนและไม่จัดการงานของ Superadmin. Admin URL ดู/อัปเดตตรวจ backend เสมอ. ไม่เปิด update/delete ให้ Member.
- Admin update รับเฉพาะ status ปฏิเสธการแก้ข้อมูลแจ้งเดิม. Create/update พร้อม audit ใน transaction และ retry 3; ถ้า audit ล้มเหลว rollback งานหรือสถานะทั้งหมด. No-op status update ไม่เพิ่ม audit. Blade escape ข้อมูลจาก form.
- Tests ผ่าน 128 tests/1264 assertions; เพิ่ม Repair 19 tests ครอบคลุมทั้ง 9 fields/3 reporter types/4 categories/6 statuses, validation, future Bangkok appointment, search/filter isolation, direct URL/roles/protected Superadmin, forged fields, FK room/dorm consistency, moved-member history, XSS, CSRF และ audit rollback. Pint, Blade cache, routes ทั้ง 7 และ migration status ผ่าน.
- ไม่แก้ Reference หรือ Demo และไม่เพิ่มงานซ่อมตัวอย่างลง application database. หยุดหลัง Phase 9; ยังไม่เริ่ม Phase 10.

## Phase 10 — Finance

- ใช้ financial_transactions/membership_payments schema เดิม ไม่มี migration/table/dependency ใหม่. เพิ่ม Admin/Member FinanceController, FinancialTransactionPolicy, views, routes และ navigation โดยคงระดับ MVC/Validation/Eloquent/transaction ตาม Reference.
- รายรับมีสมาชิก จำนวนเงิน ปีการศึกษา วันที่ รายการ สถานะ และประเภทรายรับ (ค่าส่วนกลาง/รายรับอื่น). รายจ่ายมีวันที่ รายการ ประเภท จำนวนเงิน รายละเอียด ผู้บันทึก และปีการศึกษา. ผู้บันทึก/หอพักมาจาก backend ใน installation หอเดียว; ห้าม frontend ปลอม creator/dorm/void metadata.
- จำนวนเงินมากกว่า 0 ทศนิยมไม่เกิน 2 ตำแหน่ง สูงสุด 9999999999.99 ตาม decimal(12,2). Reject negative/zero/scientific notation/เกิน precision. ยอดรวมอ่านค่าที่ cast decimal:2 แล้วรวมด้วย integer cents ไม่ใช้ floating-point sum; รองรับยอดคงเหลือติดลบ.
- pending/posted/voided แสดง รอยืนยัน/บันทึกแล้ว/ยกเลิก. ยอดรายรับ รายจ่าย และคงเหลือนับเฉพาะ posted ตามปีที่เลือกและสิทธิ์ดู. Search/status filters เปลี่ยนรายการด้านล่าง แต่ยอดรวมยังเป็นยอดของปีที่เลือกตามคำอธิบายใน UI. มีรายรับ/รายจ่ายแยก pagination และรักษา query string.
- Admin finance index/create/show/edit/update และ POST void; ไม่มี destroy. เปลี่ยน amount/title/date/expense category/description/status/public flag ได้พร้อม audit. type/member/year และ income category เดิมเปลี่ยนไม่ได้เพื่อคงความสัมพันธ์; หากระบุผิดให้ยกเลิกพร้อมเหตุผลแล้วสร้างใหม่. posted ไม่เปลี่ยนกลับ pending; voided แก้ไขหรือยกเลิกซ้ำไม่ได้.
- Void เก็บรายการเดิมพร้อมเหตุผล actor เวลา และ audit โดยไม่นับในยอดรวม. ไม่มีการลบประวัติการเงินหรือยกเลิกโดยไม่มีเหตุผล. ข้อมูล member/year/creator เดิมไม่เปลี่ยนตามบัญชีที่ย้ายหอหรือ inactive; แก้จำนวนเงินในประวัติเดิมได้ตามสิทธิ์.
- ค่าส่วนกลางเชื่อม MembershipPayment แบบ unique(user_id, academic_year_id). Pending transaction จองรายการรายปี และ posted sync เป็น paid พร้อม amount/paid_at. เก็บ transaction date เป็นวันที่ท้องถิ่นไทย; paid_at ใช้เริ่มวันตามวันที่ชำระใน Asia/Bangkok แล้วแปลง UTC.
- Lock member/transaction/payment ใน transaction และ retry 3. ห้ามค่าส่วนกลางใหม่ซ้ำในปีที่มี pending/paid transaction; ปีอื่นรับได้. Pending payment ที่ยังไม่ผูก transaction สามารถบันทึกรับชำระได้. Void sync payment=voided; รับรายการแก้ไขใหม่ได้โดย reuse annual payment row และ audit การเปลี่ยน transaction reference ส่วน transaction เดิมคง voided.
- Finance create/update/void และ MembershipPayment sync มี audit actor/action/target/old/new ใน transaction เดียวทั้งหมด. No-op finance update ไม่เพิ่ม audit. ทดสอบให้ membership audit สำเร็จก่อน finance audit ล้มเหลวแล้ว rollback ทั้งข้อมูลการเงิน การชำระและ audit ย่อย.
- FinancialTransactionPolicy: Superadmin จัดการรายการของ installation; Admin เฉพาะหอตนและไม่จัดการรายรับของ Superadmin. สร้างรายรับให้สมาชิกที่ active ในหอของระบบเท่านั้น. Backend ตรวจ role/resource/member scope ทั้ง create/update/void. direct URL และ role revocation ไม่ผ่าน.
- /finance สำหรับสมาชิกอ่านเฉพาะ is_public=true/status=posted ในหอของตนและปีที่เลือก. Query public/status/dorm/user ที่ปลอมไม่เปลี่ยน scope. แสดงรายการ/date/year/category/amount และยอดรวมเฉพาะข้อมูลเปิดเผย โดยไม่แสดงสมาชิก ผู้บันทึก รายละเอียดภายใน หรือรายการ private/pending/voided. ไม่มี public detail หรือ write endpoints.
- Blade escape รายการ/รายละเอียด/ประเภท พร้อม form errors/success/empty states/loading และ confirmation ก่อน void. เปิดเผยเป็นค่าเริ่มต้น false; การเปิด/ปิดเผยมี audit และตรวจการถอนออกจากยอดสมาชิก.
- Tests ผ่าน 145 tests/1465 assertions รวม Finance เพิ่ม 17 tests: income/expense/forms, exact cents/max amount/negative balance, academic-year totals, public isolation, annual fee duplicate/pending/replacement, edit/void/no-op audit, protected identity/role/cross-dorm, inactive/moved member, expense edit/disclosure/XSS, CSRF และ atomic rollback. Pint, Blade cache, route inspection 8 routes และ migration status ผ่าน.
- Reference/Demo ไม่ถูกแก้ไข ไม่ seed รายการการเงินหรือสมาชิกตัวอย่างลง application database. หยุดหลัง Phase 10; ยังไม่เริ่ม Phase 11.

## Phase 11 — Dashboard

- /dashboard เปลี่ยนจาก signed-in view เป็น DashboardController@index ภายใต้ auth/active/role middleware เดิม. เลือก Blade dashboards.superadmin/admin/user จาก role ในบัญชี backend หลัง refresh ทุก request; ไม่รับ role/user/dorm จาก query เพื่อเปลี่ยนขอบเขต.
- UI เป็น summary cards, ตัวกรองปีการศึกษา, รายการสั้นและลิงก์ไป module เดิม ใช้ Blade/CSS grid แบบเรียบง่ายตาม Reference. ไม่เพิ่ม chart library, dependency, table, migration, service layer หรือ SPA. ชื่อ/email ที่แสดงใน header เป็นของบัญชีที่เข้าสู่ระบบเท่านั้น.
- Superadmin: จำนวนสมาชิกทั้งหมด (รวมทุก role/สถานะตามหน้ารายชื่อที่มีสิทธิ์), Admin, Activities, Attendance, คะแนนรวมแบบ signed, Complaints, Repairs และ Income/Expense/Balance. ลิงก์ Admin management แสดงเฉพาะ Superadmin. Installation ยังเป็นหนึ่งหอต่อเซิร์ฟเวอร์/ฐานข้อมูล.
- Admin: สมาชิก role=user ในหอสังกัด, กิจกรรม, attendance ที่มีสิทธิ์เห็น, คะแนนของสมาชิกที่จัดการได้, complaints/repairs ที่ไม่ใช่ของ Superadmin, ห้องพัก และการเงินผ่าน visibleTo scope เดิม. ไม่แสดง aggregate ของสมาชิก/หอ/รายการที่ไม่มีสิทธิ์ และไม่มีลิงก์แต่งตั้ง Admin. Admin ไม่มีหอสังกัดได้รับ 403 ตามสิทธิ์จัดการเดิม.
- Complaint/Repair summary แสดงจำนวนทั้งหมดและจำนวนที่ยังไม่ completed/cancelled. ข้อมูลสมาชิก/ห้อง/complaint/repair เป็นข้อมูลทั้งหมดที่มีสิทธิ์ดู; activity/attendance/score/finance กรองปีที่เลือกตามคำอธิบายในหน้า. ไม่สร้างปีขึ้นเองเมื่อไม่มีข้อมูล.
- User: คะแนนจาก own scoreHistories, จำนวนกิจกรรมที่เข้าร่วมจาก own attendances, QR image endpoint ของตน, ห้อง/อาคาร/ชั้น/หอของบัญชี (ห้องละ 2 คน), complaint/repair counts และล่าสุด 3 เรื่องของตน. ไม่โหลดหรือแสดงชื่อเพื่อนร่วมห้อง. บัญชีที่ยังไม่มีห้องแสดง empty state.
- กิจกรรมเปิดให้เข้าร่วมแสดงสูงสุด 5 รายการในหอปัจจุบัน status=open และยังไม่พ้น ends_at กรองปีเมื่อเลือก. ไม่แสดง draft/กิจกรรมหออื่น/กิจกรรมหมดเวลา. ประวัติร้องเรียน/ซ่อมของตนยังอยู่แม้ย้ายหอ.
- User financial dashboard นับเฉพาะ is_public=true/status=posted ในหอของบัญชีและปีที่เลือก ไม่เผย private/pending/voided หรือยอดที่รวมข้อมูลลับ. Management finance ใช้ FinancialTransaction::totals เดิม (integer cents, posted only) ภายใต้สิทธิ์; ไม่มีสูตรยอดใหม่ที่ต่างจาก Phase 10.
- ลิงก์ประวัติคะแนนและการเงินรักษา year filter; management activity list รับ year filter เดิม. Dashboard เป็น read-only; QR image ใช้การสร้าง token ครั้งเดียวตาม Phase 7 เดิม.
- Tests ผ่าน 153 tests/1546 assertions รวม Dashboard เพิ่ม 8 tests: metrics ของทุก Role, scopes/hidden records/public money, academic-year filtering, empty/unassigned accounts, direct URL/forged query/unknown roles, role revocation/inactive session, bounded own latest lists, expired activities และ XSS escaping. Pint, Blade cache, route inspection และ diff check ผ่าน; Google authentication redirect/dashboard tests เดิมยังผ่าน.
- ไม่แก้ Reference/Demo ไม่ seed สมาชิกหรือข้อมูลตัวอย่างลง application database และไม่เริ่ม Phase 12. หยุดหลัง Phase 11.


## Phase 12 — Audit Log

- ใช้ audit_logs เดิมสำหรับการเปลี่ยนคะแนน, แต่งตั้ง/ถอดถอน Admin, แก้ไข Finance, Repair และ Complaint. บันทึก actor_id, action, target_type/target_id, created_at, old_values และ new_values ภายใน transaction เดียวกับข้อมูลหลัก.
- เพิ่ม migration actor_snapshot เพื่อเก็บ id/name ของผู้กระทำ ณ เวลาบันทึก แม้ชื่อเปลี่ยนหรือบัญชีถูกลบภายหลัง. Log เดิมที่ไม่มี snapshot ใช้ actor relationship เมื่อยังมีบัญชี; ไม่สร้างข้อมูลประวัติย้อนหลังที่ไม่ทราบ.
- คะแนนปรับมือและคะแนนจาก attendance บันทึก total_score ก่อน/หลังตามสมาชิกและปีการศึกษา พร้อมข้อมูลรายการคะแนนหรือ attendance. แสดง user.role_changed เป็นแต่งตั้ง Admin หรือถอดถอน Admin ตาม role ใหม่.
- AuditLog ปฏิเสธการแก้ไข/ลบผ่าน Eloquent และปิดบัง password, Google/QR tokens และ credentials แบบ recursive ทั้งตอนบันทึกและแสดงผล. ไม่เก็บ email ใน actor snapshot.
- เพิ่ม AuditLogPolicy, SuperAdmin/AuditLogController, routes /superadmin/audit-logs และ /superadmin/audit-logs/{auditLog}, Blade list/detail และ navigation เฉพาะ Superadmin. Backend ตรวจ active role ทุก request; ไม่มี route สำหรับแก้ไขหรือลบ Log.
- หน้ารายการกรองผู้กระทำ, action, target, target id และช่วงวันที่ พร้อม pagination. ช่วงวันที่และเวลาแสดง Asia/Bangkok โดย query timestamp UTC. รายละเอียดแสดง Old/New JSON แบบ escaped และปิดบังข้อมูลลับ รวม Log เดิม.
- Migration application database ผ่านและตรวจสถานะ Ran. ทดสอบ rollback/re-migrate, สิทธิ์ Superadmin/Admin/User/guest, direct URL, role revocation, inactive account, snapshot หลังเปลี่ยนชื่อ/ลบบัญชี, read-only, redaction/XSS, filters/pagination/timezone และ Audit ครบทุก action ที่กำหนด.
- Tests รวมผ่าน 161 tests / 1646 assertions รวม AuditLogTest ใหม่ 8 tests. Pint, Blade cache และ route inspection ผ่าน. ไม่เพิ่ม dependency ไม่แก้ Reference/Demo และไม่ seed ข้อมูลตัวอย่าง. หยุดหลัง Phase 12.


## Phase 13 — Responsive UI

- คง MVC, routes, authorization, schema และ dependencies เดิม. แยก CSS ของ layout เดิมไป public/css/app.css และปรับ auth.css ร่วมสำหรับขนาดจอและการตัดบรรทัดข้อความยาว.
- Navigation บนจอไม่เกิน 600px เปิด/ปิดด้วย button และ aria-expanded/aria-controls; Escape ปิดและคืน focus. เมื่อเปลี่ยนขนาดจอให้เมนู Desktop แสดงเสมอ. หาก JavaScript ใช้งานไม่ได้ เมนูยังแสดงตามปกติ.
- Tables กำหนดความกว้างขั้นต่ำเพื่ออ่านได้และเลื่อนภายใน .table-wrapper โดยไม่ทำให้หน้าล้น. เพิ่ม keyboard focus/region label; จำกัดความกว้าง cells และตัดข้อความยาว. ปุ่ม/เมนูและ controls มีพื้นที่อย่างน้อย 44px; mobile filters เรียงเต็มความกว้าง. Inputs 16px, textarea resize แนวตั้ง, QR image/video/canvas จำกัดในกรอบ.
- แก้ dashboard grid ให้รองรับพื้นที่แคบ และ override Blade pagination view ของ Laravel เป็นปุ่มเรียบง่ายที่ไม่พึ่ง Tailwind ซึ่ง layout นี้ไม่ได้โหลด. ยังคง links/query string/สถานะหน้าปัจจุบันตาม paginator เดิม.
- ResponsiveUiTest เรนเดอร์ 53 หน้าผ่าน Laravel routes และข้อมูลทดสอบแยก มีชื่อ/email/description ยาว, ยอดเงินสูงและ pagination. ใช้ UI_PREVIEW=1 เพื่อ export หน้าที่เรนเดอร์พร้อม assets ไป storage/framework/testing/responsive สำหรับตรวจ browser โดยไม่เพิ่ม auth bypass หรือเปลี่ยนข้อมูล application database.
- ตรวจ browser ที่ความกว้าง 320/390/768/1440px ครบ 212 page-size checks: document scrollWidth ไม่เกิน clientWidth และ controls นอก table ไม่ล้น. ตรวจเมนูเปิด/ปิด/Escape, พื้นที่กด navigation, กรอก form, submit enabled และเลื่อนตารางด้วย ArrowRight (scrollLeft เพิ่มโดยหน้าไม่ล้น).
- Tests รวม 162 tests / 1758 assertions ผ่าน; JavaScript 13 tests ผ่าน (Google login/scanner เดิมและ navigation ใหม่). Pint, Blade cache และ diff check ผ่าน. Browser preview ตรวจ UI ด้วยข้อมูลทดสอบและ Login สถานะยังไม่ตั้งค่า Google; ไม่ได้ทดสอบ Google account หรือกล้องบนโทรศัพท์จริงใน Phase นี้.
- ไม่แก้ Reference/Demo ไม่เพิ่ม dependency และไม่เริ่ม Phase ถัดไป. ปิด preview server และคืนค่าขนาด browser หลังตรวจ. หยุดหลัง Phase 13.


## Phase 14 — Testing

- ตรวจ Authentication, Authorization, User/Dorm/Room Management, Activity, QR, Attendance, Score, Complaint, Repair, Finance, Audit และ Dashboard ด้วยฐานข้อมูล SQLite :memory: แยกจาก application database.
- เพิ่ม SystemIntegrationTest 5 tests: ทุก protected route และ HTTP method ปฏิเสธ Guest; ทุก management route ปฏิเสธ User; ทุก Superadmin route ปฏิเสธ Admin; Admin เรนเดอร์ management GET screens ที่อนุญาตทั้งหมด; missing ids/routes และ wrong HTTP methods ได้ 404/405 และ malformed filters ได้ 422.
- ทดสอบ workflow ผ่าน HTTP ทั้ง Admin และ Superadmin: เพิ่มสมาชิกคนที่สองของห้อง, QR, สร้าง Complaint/Repair, preview/attendance, duplicate 409, ปรับคะแนน signed, อัปเดตสถานะ, ลงรายจ่าย, ยอดเงินและคะแนน Dashboard, ติดตามข้อมูลของตน, แต่งตั้ง/ถอดถอน Admin และ Audit. ตรวจ redirect ว่าไม่มี validation errors และตรวจ foreign_key_check หลัง workflow.
- ชุดเดิมครอบคลุม Google signature/audience/issuer/expiry/nonce/domain และ CSRF/logout, การปลอม role/owner/คะแนน, การถอนสิทธิ์ระหว่าง session, การเข้าถึงตรง URL, การรั่วข้อมูลข้ามสมาชิก, unique attendance/score/payment, room capacity 2, orphan FKs, deletion restrictions, rollback เมื่อ Audit/score บันทึกไม่สำเร็จ, migration rollback/re-migrate และการเรนเดอร์ทุกหน้า.
- Full suite ผ่าน 167 tests / 2184 assertions ด้วยลำดับสุ่ม seed 20261007; SystemIntegrationTest ผ่าน 5 tests / 426 assertions; JavaScript ผ่าน 13 tests. Pint, Blade cache, route cache/clear, npm build, composer validate และ platform requirements ผ่าน. Application routes 83 routes; migrations ทั้งหมด Ran.
- ตรวจ application SQLite แบบ read-only: integrity_check=ok, foreign_key_check=0 errors, duplicate attendance groups=0. ข้อมูลจริงยังไม่มี users และ GOOGLE_CLIENT_ID ยังว่าง จึงยังไม่ได้ยืนยัน live Google account login หรือกล้องโทรศัพท์จริง; Authentication ถูกทดสอบด้วย Google library และ RSA-signed test tokens.
- ไม่พบข้อผิดพลาดใน application code ที่ต้องแก้จากการตรวจครั้งนี้. เพิ่ม test coverage และปรับเอกสารเท่านั้น ไม่แก้ Reference/Demo ไม่ seed ข้อมูลตัวอย่าง ไม่เพิ่ม dependencies. หยุดหลัง Phase 14.


## Phase 15 — README / Documentation

- แทน README template ของ Laravel ด้วยคู่มือ KKU DORM ภาษาไทยครบ Project Overview, Requirements, Installation, Configuration/.env, Database/Migration/Seed, Google Login Setup, Roles/Features, Demo Database, Future Excel Import, Run Project, Testing และ Common Errors.
- ตรวจเวอร์ชันจาก lock files, Node engine จาก package ที่ติดตั้ง, config/env keys, artisan commands, routes, seeders, local links และ scripts ของ Composer/npm. Google Client/Origins/Callback และ token verification อ้างอิงเอกสาร Google Identity Services ทางการ.
- อธิบายการเตรียมผู้ดูแลคนแรกแบบไม่มี Demo และแบบ import Demo ก่อน controlled SuperAdminSeeder, การสร้างหอ/อาคาร/ชั้น/ห้อง/สมาชิก, mapping ตัวอย่างที่ต้องตรวจและเติมทุกห้อง, source read-only/local-only และ Excel importer ที่ยังไม่มีในเวอร์ชันนี้.
- DocumentationSetupTest ตรวจ bootstrap ด้วยโค้ดใน README บนฐานข้อมูลใหม่แยก: default seed ไม่มีบัญชี, passwordless Superadmin ไม่ต้องมีหอเพื่อเริ่ม setup, รันซ้ำไม่สร้างบัญชี/ปี/Audit ซ้ำ และเข้า Dashboard/สร้างหอแรกได้.
- Composer install dry-run ผ่าน; Composer test ตาม README ผ่าน 168 tests / 2200 assertions; Pint ทั้งโครงการผ่าน. ชุด JavaScript 13 tests และ build ผ่านจาก Phase 14 โดยไม่มีการเปลี่ยน frontend ใน Phase 15.
- งาน Phase 1–15 ยังมี local changes ที่ไม่ได้ commit/push. README ระบุให้ทีมเผยแพร่ชุดโค้ด/branch ก่อน Clone; ไม่อ้างว่า remote มีฟีเจอร์ล่าสุดแล้ว. ยังต้องตั้ง Google Client ID และเตรียมสมาชิก/ข้อมูลหอก่อน live login; Demo/Reference ไม่ได้รวมใน repository และไม่ได้แก้.
- ไม่มี feature/schema/dependency เพิ่มใน Phase 15. หยุดหลัง README พร้อมรายงานภาพรวมโครงการและข้อจำกัดที่ยังต้องตั้งค่าภายนอก.


## Final Review — 2026-10-08

- ตรวจ Architecture/Coding Level จาก ProductController และ CheckoutController ของ Laravel Part 03 แบบ read-only. โครงการยังใช้ MVC, request validation, explicit fields/Eloquent, named routes, Blade, policies/middleware และ transactions โดยไม่เพิ่ม Repository/Service/DTO/SPA.
- ตรวจทั้ง 20 หัวข้อที่ผู้ใช้กำหนด. ไม่พบข้อผิดพลาดใน application code ที่ต้องแก้. เพิ่ม regression coverage สำหรับ invalid hierarchy parent IDs: 3 cases / 39 assertions; ไม่มีการเปลี่ยน feature, schema, dependencies หรือ Business Rule.
- Final suite 171 tests / 2239 assertions ผ่านแบบสุ่ม seed 20261008; JS 13 tests ผ่าน; npm build, Pint, Blade cache และ route cache/clear ผ่าน. Composer/npm audit รายงาน 0 ช่องโหว่ ณ วันที่ตรวจ.
- SQLite application ตรวจ read-only: integrity ok, foreign_key_check ไม่มี error, duplicate attendance groups 0. มี academic_years 1 แต่ users/dorms/rooms 0; migration ทั้งหมด Ran.
- ตรวจหลักฐาน Responsive 53 หน้า / 4 widths / 212 checks และ README links/config/commands; ไม่พบ page/form overflow หรือ local link ขาด. ใช้หลักฐาน browser จาก Phase 13 ซึ่ง UI ยังไม่เปลี่ยน และยืนยัน render tests ปัจจุบันผ่าน.
- สถานะพร้อม development/testing; ยังไม่พร้อมเปิดใช้งานจริงจนตั้ง Google Client ID, เตรียมข้อมูล, HTTPS/production config และตรวจ live login/กล้องจริง. Excel import ยังคงเป็นงานต่อยอดตามขอบเขตเดิม.
- Git branch kku-dorm: modified 15, untracked 139, staged 0, total 154 files. ไม่มี commit/push/deploy, ไม่ลบ .git และไม่แก้ Reference/Demo.
