# KKU DORM

ระบบจัดการหอพักมหาวิทยาลัยขอนแก่น สร้างด้วย Laravel, Blade และ Eloquent ตามรูปแบบ MVC ระดับเดียวกับ Laravel Part 03.

**หนึ่ง installation / server / database ใช้สำหรับหนึ่งหอเท่านั้น** เช่น หอ 8 มีเฉพาะสมาชิกหอ 8 หออื่นติดตั้งแยกกัน ห้องพักรองรับสมาชิกที่ใช้งานอยู่ไม่เกิน 2 คน.

## Project Overview

- Google Login เท่านั้น ไม่มี Username/Password Login และไม่มี Register.
- รองรับบัญชี `@kkumail.com` และ `@kku.ac.th` ที่มีอยู่ในระบบและเปิดใช้งานแล้ว.
- Backend ตรวจ Google ID token, domain, role และเจ้าของข้อมูลทุกครั้ง.
- เก็บ timestamp เป็น UTC และแสดงเวลา Asia/Bangkok; ปีการศึกษาเก็บเลข พ.ศ. แยกต่างหาก.
- SQLite เป็นฐานข้อมูล development ที่ตรวจสอบแล้ว. การย้ายไปฐานข้อมูลชนิดอื่นต้องทดสอบ migration, constraints และ transactions ใหม่.

## Requirements

| เครื่องมือ | ข้อกำหนด |
| --- | --- |
| Git | สำหรับ Clone และเลือก branch ของทีม |
| PHP | 8.3 ขึ้นไป ตาม `composer.json`; เครื่องที่ทดสอบใช้ 8.3.32 |
| Composer | 2.x; ใช้ `composer.lock` ติดตั้งเวอร์ชันเดียวกับทีม |
| Node.js | `^20.19.0` หรือ `>=22.12.0` ตาม Vite ที่ติดตั้ง; เครื่องที่ทดสอบใช้ 24.15.0 |
| npm | ใช้กับ `package-lock.json` |
| Database | SQLite พร้อม `pdo_sqlite` และ `sqlite3` |
| Browser | JavaScript เปิดใช้งาน; กล้องมือถือใช้ HTTPS |
| Network | Browser โหลด Google Identity Services และ backend ติดต่อ Google เพื่อตรวจ token |

PHP ต้องมี PDO, OpenSSL, mbstring, fileinfo, DOM/XML และ extensions ที่ Composer ระบุ ตรวจหลังติดตั้งด้วย `composer check-platform-reqs`.

เวอร์ชันจาก lock files: Laravel 13.33.0, Google API Client 2.20.1, Endroid QR Code 6.0.9, html5-qrcode 2.3.8 และ Vite 8.3.1. Python/OpenCV ไม่จำเป็นต่อการรันเว็บหรือชุดทดสอบปกติ.

## Installation

คำสั่งตัวอย่างใช้ **PowerShell** และรันจากโฟลเดอร์โครงการ. ติดตั้งในโฟลเดอร์ใหม่ ไม่สร้าง Laravel ใหม่ทับ checkout เดิม.

### 1. Clone และเลือกชุดโค้ด KKU DORM

```powershell
git clone https://github.com/pairot230/WebApp.git "KKU DORM"
Set-Location "KKU DORM"
git fetch origin
git switch main
git status --short
git branch --show-current
```

ใช้ branch `main` สำหรับชุดโค้ด KKU DORM ที่รวมแล้ว. Checkout ที่ถูกต้องต้องมี `config/dorm.php`, business migrations และ `tests/Feature/SystemIntegrationTest.php`.

### 2. ติดตั้ง dependencies และสร้าง environment

```powershell
composer install
npm ci --ignore-scripts
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
php artisan key:generate
```

ใช้ `composer install` และ `npm ci` เพื่อรักษาเวอร์ชันใน lock files. คำสั่งสร้าง key ใช้กับ installation ใหม่; เก็บ `.env` และ key เดิมเมื่ออัปเดตระบบที่ใช้งานแล้ว.

### 3. สร้างฐานข้อมูลและ build

หลังตรวจ `.env` ตามหัวข้อ Configuration:

```powershell
if (-not (Test-Path -LiteralPath 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null }
php artisan config:clear
php artisan migrate
php artisan db:seed
npm run build
composer check-platform-reqs
```

`db:seed` ปกติสร้างเฉพาะปีการศึกษา ไม่สร้างบัญชีสมาชิก หอ ห้อง หรือข้อมูล Demo. ทำหัวข้อบัญชีผู้ดูแลคนแรกและ Google Login Setup เพื่อเข้าสู่ระบบได้จริง.

## Configuration / .env

เริ่มจาก `.env.example` แล้วแก้ค่าของเครื่องตนเอง:

```dotenv
APP_NAME="KKU DORM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

DORM_ACADEMIC_YEAR=2569
SUPERADMIN_EMAIL=pairoj.c@kkumail.com
GOOGLE_CLIENT_ID=
DORM_DEMO_DATABASE=
DORM_DEMO_ROOM_MAPPING=
```

| ค่า | การใช้งาน |
| --- | --- |
| `APP_KEY` | สร้างด้วย `key:generate` ใน installation ใหม่ |
| `APP_URL` | ตรงกับ origin ที่เปิดเว็บ เช่น `http://localhost:8000` |
| `DB_CONNECTION` | `sqlite` สำหรับวิธีติดตั้งนี้ |
| `DB_DATABASE` | ไม่กำหนดจะใช้ `database/database.sqlite`; หากกำหนดให้ใช้ absolute path ของฐานข้อมูล application |
| `DORM_ACADEMIC_YEAR` | เลข พ.ศ. ที่ AcademicYearSeeder สร้างเมื่อยังไม่มีปีนั้น |
| `SUPERADMIN_EMAIL` | บัญชีที่ controlled seeder ยกระดับเป็น SuperAdmin ไม่ใช่การอนุญาตจาก frontend |
| `GOOGLE_CLIENT_ID` | OAuth Client ID แบบ Web application; ต้องใส่ก่อน Login |
| `DORM_DEMO_DATABASE` | ไฟล์ Demo แยกจาก application database สำหรับ local/testing เท่านั้น |
| `DORM_DEMO_ROOM_MAPPING` | JSON ที่กำหนดหอ/อาคาร/ชั้น/ห้องของ Demo อย่างชัดเจน |

หลังแก้ `.env` รัน `php artisan config:clear`. ไม่ commit `.env` หรือฐานข้อมูลที่มีข้อมูลสมาชิก. สำหรับเซิร์ฟเวอร์ใช้งานจริง ตั้ง `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true` และให้ web server ชี้ document root ไปที่ `public/`. ให้ process เขียน `storage/` และ `bootstrap/cache/` ได้.

ระบบนี้ไม่ใช้ Google Client Secret หรือ Google Password ในการ Login. ค่าการส่ง mail, Redis และ AWS ใน template ไม่จำเป็นสำหรับฟีเจอร์ปัจจุบัน.

## Database Setup / Migration / Seed

Business tables: `users`, `academic_years`, `dorms`, `buildings`, `floors`, `rooms`, `activities`, `attendances`, `score_histories`, `complaints`, `repair_requests`, `financial_transactions`, `membership_payments`, `audit_logs` พร้อม session/cache/jobs ของ Laravel.

```powershell
php artisan migrate
php artisan migrate:status
php artisan db:seed
```

- Migration สร้าง Foreign Keys, unique attendance `(user_id, activity_id)`, unique score ต่อ attendance และ unique membership payment ต่อสมาชิก/ปี.
- ข้อมูลที่มีประวัติอ้างอิงลบไม่ได้; ใช้ปิดใช้งาน/ยกเลิกตามหน้าแต่ละโมดูล.
- AcademicYearSeeder รันซ้ำไม่สร้างปีซ้ำ. หากเพิ่มปีใหม่ด้วย `DORM_ACADEMIC_YEAR` แล้ว seed ระบบจะไม่สลับปี active เดิมให้อัตโนมัติ.
- ไม่ใช้ `migrate:fresh` หรือ rollback กับฐานข้อมูลจริงเพื่อแก้ error โดยไม่ตรวจผลกระทบ; การทดสอบ migration ในชุดทดสอบใช้ฐานข้อมูลแยก.

### บัญชีผู้ดูแลคนแรก — ฐานข้อมูลใหม่ที่ไม่ใช้ Demo

ตั้ง `SUPERADMIN_EMAIL` เป็น KKU email ของผู้ดูแลก่อน. เปิด console ของโครงการ:

```powershell
php artisan tinker
```

ใน Tinker รันโค้ดนี้ทีละบรรทัด หรือวางทั้ง block. จะสร้างเฉพาะบัญชีที่ยังไม่มี และไม่แก้บัญชีเดิม:

```php
$user = App\Models\User::firstOrNew(['email' => strtolower(trim(config('dorm.superadmin_email')))]);
if (! $user->exists) {
    $user->name = 'ผู้ดูแลระบบ';
    $user->password = null;
    $user->is_active = true;
    $user->save();
}
exit
```

กลับ PowerShell แล้วแต่งตั้งด้วย controlled seeder:

```powershell
php artisan db:seed --class=SuperAdminSeeder
```

Seeder ตรวจ email ของ KKU และบัญชี active, บันทึก Audit และรันซ้ำไม่แต่งตั้งซ้ำ. หากใช้ Demo และมีบัญชีนี้แล้ว ข้าม Tinker และรัน SuperAdminSeeder หลัง import. ไม่เปลี่ยน role ผ่าน SQL หรือ endpoint สาธารณะ.

หลังตั้ง Google Login และ Login เป็น SuperAdmin แล้ว สร้าง **หอ → อาคาร → ชั้น → ห้อง → สมาชิก** ในหน้าจัดการ. สร้างหอได้เพียงหนึ่งหอต่อ installation. สมาชิกทั่วไปต้องมีรหัสนักศึกษา ห้อง และ email ที่ตรงกับ Google account; Admin แต่งตั้งได้เฉพาะบัญชี active ที่มีหอสังกัด. ผู้ดูแลคนแรกยังไม่ต้องมีหอ/ห้องเพื่อเริ่มตั้งค่าระบบ.

## Google Login Setup

ใช้ Google Identity Services แบบ JavaScript callback: Browser รับ ID token แล้วส่ง `credential` ไป `POST /auth/google`; Laravel ตรวจด้วย Google API Client ก่อนสร้าง session.

1. เปิด Google Cloud Console เลือก/สร้าง project และตั้ง Google Auth Platform / Branding / Audience ให้ตรงกับทีมและนโยบายบัญชี KKU. หากอยู่ Testing ให้กำหนดบัญชีทดสอบที่ใช้จริงตามการตั้งค่าของ Google.
2. สร้าง OAuth Client ชนิด **Web application**.
3. ใส่ Authorized JavaScript origins สำหรับ local เช่น `http://localhost` และ `http://localhost:8000`. ถ้าเปิดด้วย `127.0.0.1` หรือ port อื่น ต้องเพิ่ม origin นั้นด้วย. Production ใช้ origin HTTPS ของเซิร์ฟเวอร์จริง.
4. นำ Client ID ที่ลงท้าย `.apps.googleusercontent.com` ใส่ `GOOGLE_CLIENT_ID` ใน `.env`, ตั้ง `APP_URL` ให้ตรง URL ที่เปิด แล้วรัน `php artisan config:clear`.
5. Flow ปัจจุบันใช้ callback และ backend POST เอง จึงไม่ต้องตั้ง Google redirect URI หรือสร้าง `/auth/google/callback` เพิ่ม.
6. เตรียมบัญชี active ในฐานข้อมูล แล้วเปิด `http://localhost:8000/login` ด้วย browser ปกติและเลือกบัญชี KKU.

ขั้นตอนสร้าง client/origins อ้างอิง [Google Identity Services Setup](https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid). Backend ตรวจลายเซ็น, `aud`, `iss`, `exp`, nonce, `email_verified`, `sub`, email domain และ Workspace `hd`; อ้างอิง [Verify Google ID token](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token). อนุญาตทั้ง email domain และ `hd` เฉพาะ `kkumail.com` / `kku.ac.th`. การกรอก email/role จาก frontend ไม่ให้สิทธิ์ และบัญชีที่ยังไม่มีจะไม่ถูก Register อัตโนมัติ.

## Roles

| Role | สิทธิ์ |
| --- | --- |
| `superadmin` | จัดการทุกโมดูลของ installation, แต่งตั้ง/ถอดถอน Admin และดู Audit Log |
| `admin` | จัดการหอและสมาชิกตามสิทธิ์, กิจกรรม/คะแนน/ร้องเรียน/ซ่อม/การเงิน; แต่งตั้ง/ถอดถอน Admin และจัดการบัญชี SuperAdmin ไม่ได้ |
| `user` | ดูข้อมูล/คะแนน/QR/ห้องของตน, สร้างและติดตาม Complaint/Repair ของตน, ดูกิจกรรมและการเงินที่เปิดเผย |

เปลี่ยนสิทธิ์ผ่านหน้าจัดการสิทธิ์ Admin ของ SuperAdmin. Backend โหลดบัญชีใหม่ทุก request จึงใช้สิทธิ์ล่าสุดแม้ยังมี session เดิม. การแต่งตั้ง SuperAdmin ใช้ขั้นตอน setup ที่ควบคุม ไม่ใช่หน้าแต่งตั้ง Admin.

## Features

- Dorm / Building / Floor / Room / User: CRUD, search/filter, สถานะ, ข้อมูลสมาชิก และห้องละ 2 คน.
- Activity: CRUD, ปีการศึกษา, เวลาไทย และสถานะ draft/open/closed/cancelled.
- QR / Attendance: **QR ประจำตัวสมาชิก**; เจ้าหน้าที่เปิดหน้าสแกนของกิจกรรม → สแกนกล้อง/รูป → ตรวจชื่อ → ยืนยัน → Attendance → คะแนน. สมาชิกหนึ่งคนเช็กชื่อได้หนึ่งครั้งต่อกิจกรรม. กิจกรรมต้อง open และอยู่ในเวลาเช็กชื่อ.
- Score: ประวัติคะแนนและปีการศึกษา; เจ้าหน้าที่เพิ่ม/ลดพร้อมเหตุผลและ Audit ไม่แก้หรือลบประวัติเดิม.
- Complaint: สมาชิกส่งเรื่อง; เจ้าหน้าที่เปลี่ยนสถานะ, ผู้รับผิดชอบและหมายเหตุ. เจ้าของเรื่องอ่านหมายเหตุได้.
- Repair: ฟอร์ม 9 fields, นัดหมาย, search/filter และเจ้าหน้าที่อัปเดต 6 สถานะ; สมาชิกดูเฉพาะของตน.
- Finance: รายรับ/รายจ่าย/ยอดคงเหลือ, ปีการศึกษา, การชำระค่าสมาชิก, pending/posted/voided, ข้อมูลเปิดเผย และ Audit. รายการที่ลงแล้วใช้ยกเลิกพร้อมเหตุผลแทนการลบ.
- Dashboard: แยก SuperAdmin/Admin/User ตามข้อมูลที่มีสิทธิ์ดู.
- Audit: ผู้กระทำ, action, target, วันเวลา, old/new และชื่อผู้กระทำ ณ ตอนบันทึก; UI อ่านได้เฉพาะ SuperAdmin ไม่มีแก้ไข/ลบ Log.
- Responsive UI: ตารางเลื่อนภายในกรอบ, เมนูมือถือ, ฟอร์มและปุ่ม; ตรวจที่ 320/390/768/1440px.

## Demo Database

`demo_hall8_database.db` เป็นข้อมูลอ้างอิง/ตัวอย่างแยกจากฐานข้อมูล application. ไฟล์นี้และ Reference Project ไม่จำเป็นต่อการเปิดเว็บปกติ และไม่ได้รวมใน Git repository. ขอไฟล์จากทีมเมื่อต้องทดสอบ import.

เครื่องพัฒนานี้มี Demo ที่ `D:/web_/demo_hall8_database.db` และ Reference ที่ `D:/web_/Laravel Part 03`. ย้ายไฟล์ไปตำแหน่งของเครื่องตนเอง แล้วแก้ `.env`:

```dotenv
DORM_DEMO_DATABASE="D:/data/demo_hall8_database.db"
DORM_DEMO_ROOM_MAPPING="D:/data/demo-room-mapping.json"
```

ตัวอย่างรูปแบบ JSON เท่านั้น **ไม่ใช่ mapping ที่ยืนยันของ Demo ทั้งไฟล์**:

```json
{
  "dorm_code": "H8",
  "dorm_name": "หอพัก 8",
  "building_code": "A",
  "building_name": "อาคารตัวอย่าง",
  "rooms": [
    { "number": "304", "floor": 3, "capacity": 2 },
    { "number": "414", "floor": 4, "capacity": 2 }
  ]
}
```

ต้องระบุทุกห้องที่มีใน source และตรวจหอ/อาคาร/ชั้นกับข้อมูลจริง ไม่อนุมานชั้นจากเลขห้อง. Seeder ปัจจุบันรับหนึ่งอาคารต่อ mapping และอ่าน `students` columns: `room`, `student_id`, `first_name`, `last_name`, `faculty`, `kku_mail`. หาก source ต่างรูปแบบต้องทำ adapter สำหรับ source นั้น ไม่แก้ business schema ให้ผูกกับ Demo.

```powershell
php artisan config:clear
php artisan db:seed --class=DemoMemberSeeder
php artisan db:seed --class=SuperAdminSeeder
```

DemoMemberSeeder ใช้ได้เฉพาะ local/testing, เปิด source แบบ read-only, ตรวจ mapping/ข้อมูลซ้ำ/ห้องเต็ม, ทำ transaction และรักษาสมาชิกเดิมที่จับคู่ได้โดยไม่ทับ role. หาก student ID/email ชี้คนละบัญชีหรือขาด mapping จะหยุดพร้อม error ไม่ import บางส่วน. ใช้ mapping ของหอเดิมเมื่อมีหออยู่แล้ว. ไม่ตั้ง `DB_DATABASE` ให้ชี้ Demo.

รายชื่อ 5 คนที่ผู้ใช้ระบุมีอยู่ใน Demo แล้ว ดู [Initial members](docs/INITIAL_MEMBERS.md); ไม่มี seeder สร้าง 5 คนซ้ำอีกชุด.

## Future Excel Import

**ยังไม่มีหน้า/คำสั่งนำเข้า Excel ในเวอร์ชันนี้**. โครงสร้าง users และหอ/อาคาร/ชั้น/ห้องรองรับการจับคู่ข้อมูลจริงในอนาคตโดยไม่ต้องใช้ schema ของ Demo เป็นฐาน.

แนวทางต่อยอด: อ่านคอลัมน์จริง → map เป็นข้อมูล application → ตรวจ preview/error ทุกแถว → จับคู่ student ID/email และห้อง → บันทึก transaction. เก็บรหัสนักศึกษาและห้องเป็นข้อความเพื่อรักษาเลขศูนย์/ขีด, ตรวจ duplicate/conflict, ความจุ 2 คนและหอสังกัด, import ซ้ำไม่สร้างซ้ำ และไม่เปลี่ยน role/Google identity จากไฟล์โดยอัตโนมัติ. กำหนดรูปแบบ Excel และนโยบายอัปเดตสมาชิกเดิมร่วมกับทีมก่อนเขียน importer.

## Run Project

วิธีง่าย: build ครั้งแรกแล้วเปิด Laravel server:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

เปิด `http://localhost:8000/login`. หยุด server ด้วย Ctrl+C. เมื่อต้องดูการเปลี่ยน assets แบบต่อเนื่อง เปิดอีก terminal แล้วรัน `npm run dev`. หรือใช้ `composer run dev` เพื่อเปิด Laravel, queue listener และ Vite พร้อมกัน.

หน้าหลักระบบปัจจุบันโหลด CSS/JavaScript จาก `public/css` และ `public/js` โดยตรง; `npm run build` สร้าง Vite assets สำหรับส่วนที่ใช้งาน Vite. หลังแก้ public assets ให้ reload browser. QR scanner library อยู่ใน `public/js/vendor` แล้ว ไม่ต้องโหลดจาก CDN.

กล้องบนมือถือที่เปิดผ่าน IP เครื่องพัฒนาแบบ HTTP อาจใช้งานไม่ได้ ให้ใช้ HTTPS หรือเลือกไฟล์รูป QR. localhost exception ของ browser ใช้กับเครื่องที่เปิด browser นั้น ไม่ใช่ IP ของเครื่องอื่น.

## Testing

```powershell
composer test
node --test tests/Frontend/*.test.cjs
php vendor/bin/pint --test
php artisan view:cache
php artisan route:cache
php artisan route:clear
npm run build
```

ตรวจซ้ำแบบสุ่มลำดับเมื่อทดสอบการทำงานร่วมกัน:

```powershell
php artisan test --compact --order-by=random --random-order-seed=20261007
```

`phpunit.xml` ใช้ `APP_ENV=testing`, SQLite `:memory:`, session/cache แบบ array. ไม่ใช้ฐานข้อมูล application ในชุดทดสอบปกติ. `composer test` ล้าง config cache ก่อนรัน เพื่อไม่ใช้ cached database configuration.

ผล Phase 14: **167 PHP tests / 2,184 assertions และ JavaScript 13 tests ผ่าน** ครอบคลุมทั้งสาม Role, Guest/direct URL, token verification, validation, CRUD, migration, relationships, unique constraints, duplicate attendance, transactions/rollback, privacy, Audit และการเรนเดอร์ 53 หน้าด้วยข้อมูลยาว. ฐานข้อมูล application ตรวจ integrity ผ่าน ไม่มี FK error หรือ Attendance ซ้ำ.

ผลตรวจ README ใน Phase 15: `composer install --dry-run` ผ่าน, command names/configuration/local links ตรงกับไฟล์จริง, ขั้นตอน bootstrap ผู้ดูแลจากฐานข้อมูลว่างและรันซ้ำผ่าน. `composer test -- --compact` ผ่าน **168 tests / 2,200 assertions** รวม DocumentationSetupTest และ Pint ทั้งโครงการผ่าน. ไม่มีการเปลี่ยนข้อมูลสมาชิกจริงระหว่างตรวจ.

ตรวจ responsive ซ้ำโดย export หน้าจาก test fixture แยก:

```powershell
$env:UI_PREVIEW='1'
try { php artisan test --compact tests/Feature/ResponsiveUiTest.php } finally { Remove-Item Env:UI_PREVIEW }
php -S 127.0.0.1:8013 -t storage/framework/testing/responsive
```

เปิดตัวอย่าง `http://127.0.0.1:8013/member-repairs-create.html`; รายชื่อหน้าอยู่ใน `screens.json`. Preview เป็น HTML จากข้อมูลทดสอบ ลิงก์/ฟอร์มยังอ้าง route ของเว็บจริง ใช้ตรวจ layout ไม่ใช้บันทึกข้อมูล. หยุด server เมื่อเสร็จ.

การทดสอบอัตโนมัติไม่แทนการ Login ด้วยบัญชี Google จริงหรือเปิดกล้องบนโทรศัพท์จริง. ณ Phase 15 เครื่องนี้ยังไม่มี Google Client ID และ application database ยังไม่มีสมาชิก จึงต้องตั้งค่า/เตรียมบัญชีตามขั้นตอนข้างต้นก่อนตรวจสองกรณีนี้.

## Common Errors

| อาการ | ตรวจ/แก้ |
| --- | --- |
| Clone แล้วไม่มีโมดูล KKU DORM | ตรวจ branch/commit และให้ทีมเผยแพร่ชุดโค้ดล่าสุดก่อน; ไม่สร้าง Laravel ใหม่ทับ |
| `vendor/autoload.php` ไม่พบ | รัน `composer install` ในโฟลเดอร์โครงการ |
| PHP/extension ไม่ครบ | ตรวจ `php --version`, `php --ini`, `composer check-platform-reqs`; เปิด PDO SQLite/OpenSSL/DOM และ extensions ที่รายงาน |
| Node engine ไม่รองรับ | ใช้ Node เวอร์ชันตาม Requirements แล้วติดตั้งด้วย `npm ci --ignore-scripts` |
| ไม่มี application encryption key | Installation ใหม่รัน `php artisan key:generate`; ระบบเดิมใช้ key เดิม |
| SQLite file does not exist | สร้าง `database/database.sqlite` หรือแก้ absolute `DB_DATABASE` แล้ว `config:clear` |
| `no such table` / sessions / cache | ตรวจ database ที่ใช้และรัน `php artisan migrate` |
| เขียน log/cache ไม่ได้ | ตรวจสิทธิ์ `storage/` และ `bootstrap/cache/` |
| หน้า Login แจ้งระบบยังไม่พร้อม | ตั้ง `GOOGLE_CLIENT_ID`, ตรวจ `APP_URL`, `config:clear` และ reload |
| Google แจ้ง client/origin ไม่ถูกต้อง | ตรวจ Client ID แบบ Web application และ Authorized JavaScript origins ให้ตรง scheme/host/port; localhost กับ 127.0.0.1 เป็นคนละ origin |
| Popup Google ถูกปิดกั้น | ใช้ browser ปกติ เปิด JavaScript ตรวจ popup/CSP และ COOP ของ web server ตาม Google Setup |
| บัญชีไม่ได้รับอนุญาต / 403 | ตรวจ active member, email/domain/Workspace `hd`, Google subject ที่ผูก และ role; ไม่มี Register อัตโนมัติ |
| SuperAdminSeeder ไม่พบบัญชี | เตรียมบัญชี active ที่ email ตรง `SUPERADMIN_EMAIL` ก่อน ไม่สร้างซ้ำหาก import แล้ว |
| 419 / Login nonce หมดอายุ | Reload `/login` แล้วลองใหม่ ตรวจ session/cookie/APP_URL และ CSRF; ไม่ปิด CSRF เพื่อแก้ |
| กล้องเปิดไม่ได้ | อนุญาตกล้อง ใช้ HTTPS บนโทรศัพท์ หรือเลือกรูป QR |
| เช็กชื่อ 409 | สมาชิกเช็กชื่อกิจกรรมนี้แล้ว ตรวจรายชื่อ ไม่เพิ่มคะแนนซ้ำ |
| เช็กชื่อ 422 | ตรวจ QR ประจำตัว, student ID, active member/dorm, กิจกรรม open และเวลาเริ่ม/สิ้นสุด |
| Form 422 หรือย้อนกลับพร้อม error | อ่าน validation error เช่น ห้องเต็ม, ข้อมูลซ้ำ, หอสังกัดไม่ตรง, วันนัดหมายผ่านแล้ว หรือข้อมูลสิทธิ์ที่ส่งไม่ได้ |
| สร้างหอที่สองไม่ได้ | หนึ่ง installation ใช้หนึ่งหอ; หออื่นติดตั้งเซิร์ฟเวอร์/ฐานข้อมูลแยก |
| ลบสมาชิก/ห้อง/กิจกรรมไม่ได้ | มีประวัติอ้างอิง ใช้ระงับ/ปิด/ยกเลิกตามโมดูล |
| ค่าสมาชิกซ้ำในปีเดียวกัน | ตรวจรายการเดิม; แก้/ยกเลิกพร้อมเหตุผลก่อนลงรายการที่ถูกต้อง |
| Demo import error | ตรวจไฟล์ทั้งสอง, mapping ครบทุกห้อง, source แยกจาก app DB, APP_ENV local/testing และ ID/email conflicts |
| UI เก่าหรือ Vite manifest error | Reload, รัน `npm run build`, ปิด Vite dev server ที่ไม่ใช้ และ `php artisan view:clear` |
| Test ใช้ config/database ผิด | รัน `php artisan config:clear` หรือใช้ `composer test` แล้วตรวจ `phpunit.xml` |

รายละเอียดโครงสร้างและผลแต่ละ Phase อยู่ใน [Architecture](docs/ARCHITECTURE.md). การใช้งานจริงยังต้องจัดเตรียม Google client, บัญชีสมาชิกและข้อมูลหอของ installation; ไม่มีข้อมูล Demo ถูกเติมลงฐานข้อมูลจริงอัตโนมัติ.


## Final Review — 8 October 2026

- ตรวจ Architecture/Coding Level เทียบ Laravel Part 03: คง MVC, Controller validation, Eloquent, Blade และ transactions; ไม่มี feature หรือชั้น architecture เพิ่มใน Final Review.
- ตรวจครบ Authentication/Authorization, User/Dorm/Room, Activity/QR/Attendance/Score, Complaint/Repair, Finance/Dashboard/Audit, Security, Responsive, README และ Git.
- เพิ่มกรณี validation สำหรับ parent ids ผิดชนิดใน Building/Floor/Room (array/nested array/null/string) และตรวจว่าไม่มีข้อมูลถูกเปลี่ยน. ไม่พบ application error ที่ต้องแก้จากการตรวจนี้.
- Full suite ผ่าน **171 tests / 2239 assertions** ด้วยลำดับสุ่ม seed 20261008; JavaScript 13 tests ผ่าน; Pint, npm build, Blade cache และ route cache/clear ผ่าน.
- Composer audit --locked และ npm audit ไม่พบ advisory/vulnerability ที่รายงาน ณ วันที่ตรวจ. Application SQLite integrity=ok, Foreign Key errors=0, duplicate attendance groups=0; migrations ทั้งหมด Ran.
- Responsive มีผล browser verification 53 หน้าที่ 320/390/768/1440px รวม 212 checks ไม่พบ page/form overflow; UI ไม่ได้เปลี่ยนหลังตรวจนั้น และชุด render tests ปัจจุบันยังผ่าน.
- **พร้อมสำหรับ development/testing แต่ยังไม่พร้อมเปิดใช้งานจริง**: GOOGLE_CLIENT_ID ยังว่าง, users/dorms/rooms ยังไม่มีข้อมูล, APP_ENV=local/APP_DEBUG=true. ต้องเตรียมข้อมูล ตั้ง Google/HTTPS/production environment แล้วตรวจ live login และกล้องโทรศัพท์ก่อนเปิดใช้งาน.
- Git branch kku-dorm มี modified 15 files และ untracked 139 files; staged 0. งานยังไม่ได้ commit/push และไม่มีการ publish/deploy จาก Final Review. Reference/Demo ไม่ถูกแก้ไข.


### ทางเข้าใช้งานชั่วคราวบนเครื่องพัฒนา

ตั้ง `APP_ENV=local` และ `DORM_TEMPORARY_LOGIN=true` ใน `.env` แล้วรัน:

```powershell
php artisan config:clear
php artisan db:seed --class=TemporaryMemberSeeder
```

เปิด `/login` เพื่อเลือกบัญชีทั้ง 5 คนตามข้อมูลใน `config/dorm.php`: ธนชัยและพุฒิพงศ์เป็น Admin, ไพโรจน์เป็น SuperAdmin, พีรพงษ์และธามเป็น User. Seeder เพิ่มหรือปรับสมาชิกตามอีเมล ไม่สร้างซ้ำ และบันทึก Audit. หากยังไม่มีห้อง จะสร้างหอ 8 อาคาร H8 ชั้น 3/4 และห้องที่ระบุสำหรับชุดตัวอย่างนี้เท่านั้น; ไม่ใช้โครงสร้างนี้เป็นกฎสำหรับการนำเข้า Excel.

โหมดนี้ไม่ตรวจตัวตนผ่าน Google จึงเปิดได้เฉพาะ `local` และบัญชีในรายการที่ยัง active. สิทธิ์ยังอ้างอิง Role ในฐานข้อมูล. ปิดด้วย `DORM_TEMPORARY_LOGIN=false` แล้วรัน `php artisan config:clear` เพื่อกลับไปใช้ Google Login. `.env.example` ปิดโหมดนี้ไว้โดยค่าเริ่มต้น.

หลังเพิ่มโหมดชั่วคราว ทดสอบ PHP ผ่าน 175 tests / 2315 assertions.
