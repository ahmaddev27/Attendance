# خطة ترقية Laravel 12 — TAQAT

> **الحالة**: مقترح للمراجعة قبل البدء.
> **المؤلف**: فحص آلي + تدقيق يدوي، 2026-10-01.
> **النطاق**: `apps/api` (Laravel 11.56.1 → Laravel 12.60+).
> **المدخل**: `composer audit` يشير إلى ثغرتين (GHSA-5vg9-5847-vvmq، GHSA-crmm-hgp2-wgrp) لا توجد لهما ترقيعات على فرع 11.x — الترقيع متوفر فقط على 12.60.0 (CRLF) و12.61.1 (Signed URL) وما بعدها.

---

## 1. ملخص تنفيذي (Executive Summary)

| البند | الحالي | المستهدف |
| --- | --- | --- |
| `laravel/framework` | `v11.56.1` | `^12.0` (نثبّت على 12.61.1 على الأقل) |
| CVE: CRLF injection في قاعدة `email` | مكشوف | مُرقّع في 12.60.0+ |
| CVE: Temporary Signed URL path confusion | مكشوف (3 استخدامات) | مُرقّع في 12.61.1+ |
| PHP | 8.4 (CI + VPS) | 8.4 — لا تغيير |
| Carbon | 3.13.2 | 3.x — لا تغيير |
| PHPUnit | 11.5.56 | 11.x — لا تغيير |
| Pest | 3.8.7 | 3.x — لا تغيير |

**الجهد التقديري**: 6–10 ساعات صافية (فحص + ترقية حِزَم + صيانة PhpSpreadsheet 5 + تجربة يدوية). المدة الزمنية الفعلية حتى الإنتاج: **3–5 أيام** (72 ساعة مراقبة staging).

**مستوى المخاطرة**: **متوسط**.
- دليل الترقية الرسمي يُقدّر 5 دقائق للتغييرات على جانب الإطار (impact=low معظمها).
- الخطر الفعلي يأتي من حِزّتَين تابعتَين متأخّرتَين:
  1. `maatwebsite/excel 3.1.70 → 4.0.x` — يتطلب PhpSpreadsheet من `^1.30` إلى `^5.3` (قفزة 4 نسخ رئيسية). ملف واحد عندنا يلامس PhpSpreadsheet مباشرة.
  2. `resend/resend-laravel 0.14.0 → ^1.0.0` — قفزة major واحدة؛ هي الحزمة الوحيدة التي يرفض تركيبها على L12 بدون bump.
- بقية الحِزَم كلها متوافقة بالفعل مع L12/L13 كما هي.

**خطة التراجع**: راجع §7. فرع `upgrade/laravel-12`، لا نُدمج في `main` إلا بعد نجاح CI وفحص يدوي على staging لمدة 72 ساعة.

---

## 2. التغييرات الكاسرة المؤثرة (Breaking Changes That Affect Us)

مصدر مرجعي: <https://laravel.com/docs/12.x/upgrade>. الجدول يُدرج فقط البنود المحتمل أن تلامس الكود عندنا؛ البنود عالية الاحتمال التي لا تمسّنا (مثل `HasUuids`) موثّقة في §2.1.

| المنطقة | التغيير في L12 | استخدامنا | الأثر | التخفيف |
| --- | --- | --- | --- | --- |
| **Validation — قاعدة `image`** | لم تعد تقبل SVG افتراضياً. | **لا استخدام** لـ`'image'` في أي FormRequest (بحث شامل). المرفقات نحدّد mimes صراحةً عبر `mimes:pdf,jpg,...`. | **لا شيء** | — |
| **Validation — قاعدة `email`** | قاعدة الإيميل الافتراضية تعرّضت لثغرة CRLF (GHSA-5vg9-5847-vvmq)؛ الترقيع يضع فلتر CRLF افتراضي. | 25 ملف يستخدم `'email'` كـstring rule (Employees/Recruitment/Auth). | **إيجابي** — الترقية هي الهدف. سلوك الفحص لا يتغيّر على القيم الصحيحة. | لا تغييرات لازمة. |
| **Container — class property defaults** | `resolve(X::class)` يحترم القيم الافتراضية للخصائص المعرَّفة؛ لم يعد يحقن nullable تلقائياً. | نستخدم `app()`/`resolve()` في 10 ملفات، لكن أغلبها يحقن services (interfaces) لا كائنات بقيم افتراضية nullable. | **منخفض** | المراجعة اليدوية للملفات الـ10 بعد ترقية composer. |
| **Carbon 3** | إزالة دعم Carbon 2. | نعمل على Carbon 3.13.2 منذ L11. | **لا شيء** | — |
| **Eloquent — `HasUuids` → UUIDv7** | `HasUuids` يعيد UUIDv7 افتراضياً. | **لا استخدام** لـ`HasUuids`/`HasVersion4Uuids`/`HasVersion7Uuids`. | **لا شيء** | — |
| **Routing — route name precedence** | عند تكرار اسم route، uncached routing أصبح يختار الأول بدل الأخير. | طبيعة روتاتنا REST بأسماء مميّزة؛ `php artisan route:list` حالياً لا يُظهر تكراراً. | **منخفض** | CI سيلتقط أي اختلاف عبر مقارنة قائمة الروتات قبل/بعد. |
| **Storage — `local` disk root** | يُرسَى افتراضياً على `storage/app/private` بدل `storage/app`. | لا نستدعي `Storage::disk('local')` في `app/` (ناتج grep صفر). المرفقات تستخدم `local` ضمنياً عبر `filesystems.php`. | **متوسط** | تأكيد تعريف `local` صريح في `config/filesystems.php` (موجود مسبقاً عادةً) قبل الترقية؛ وإلا نقل الملفات أو إضافة تعريف. يجب فحص `attachments`/`leaves.attachment.download`/`tasks.attachments.download`. |
| **Requests — `mergeIfMissing()` dot notation** | يفسّر `user.last_name` كـnested. | **لا استخدام** في الكود. | **لا شيء** | — |
| **Concurrency — index mapping** | نتائج `Concurrency::run` بالمفاتيح الأصلية عند تمرير associative array. | لا نستخدم `Illuminate\Support\Facades\Concurrency`. | **لا شيء** | — |
| **Database — Blueprint/Grammar constructor** | يتطلب `Connection` في الـconstructor؛ `setConnection()` محذوف. | لا نُنشئ `Blueprint`/`Grammar` يدوياً (ناتج grep صفر). | **لا شيء** | — |
| **Schema inspection** | `Schema::getTables()` تُرجع كل الـschemas افتراضياً. | لا نستدعي هذه الدوال. | **لا شيء** | — |
| **Auth — `DatabaseTokenRepository` ctor** | `$expires` بالثواني بدل الدقائق. | لا نحقن هذا الصنف يدوياً — نستخدم password broker الافتراضي. | **لا شيء** | — |

### 2.1 بنود كاسرة لا تمسّنا (موثّق)

- `HasUuids`/`HasVersion4Uuids` → لا استخدام.
- Image validation + SVG → لا استخدام.
- `mergeIfMissing` dot notation → لا استخدام.
- Blueprint/Grammar constructor → لا استخدام يدوي.
- Multi-schema `Schema::getTables()` → لا استخدام.
- `Concurrency::run()` → لا استخدام.
- `DatabaseTokenRepository` manual instantiation → لا استخدام.

---

## 3. حالة توافق المكتبات (Package Compatibility)

| Package | المركّبة حالياً | يدعم L12؟ | الإصدار المستهدف | تغييرات كاسرة في الترقية |
| --- | --- | --- | --- | --- |
| `laravel/framework` | v11.56.1 | — | **^12.0** (نثبّت ≥ 12.61.1) | §2 أعلاه. |
| `laravel/sanctum` | v4.3.3 | ✓ (`illuminate/support ^11|^12|^13`) | ^4.3 (بدون تغيير) | لا شيء. |
| `laravel/scout` | v10.25.0 | ✓ (`^11|^12|^13`) | ^10.25 (بدون تغيير) | لا شيء. |
| `laravel/reverb` | v1.11.1 | ✓ (`^10.47|^11|^12|^13`) | ^1.11 (بدون تغيير) | لا شيء. |
| `laravel/tinker` | v2.11.1 | ✓ | ^2.11 | لا شيء. |
| `laravel/pail` (dev) | v1.2.7 | ✓ | ^1.2 | لا شيء. |
| `laravel/pint` (dev) | v1.32.0 | ✓ | ^1.30 | لا شيء. |
| `laravel/sail` (dev) | v1.67.0 | ✓ | ^1.67 | لا نستخدم Sail فعلياً — نشر VPS. |
| `spatie/laravel-permission` | 6.25.0 | ✓ (`^8|…|^12|^13`) | ^6.25 (بدون تغيير) | لا شيء. |
| `spatie/laravel-activitylog` | 4.12.3 | ✓ (`^8|…|^12|^13`) | ^4.12 (بدون تغيير) | لا شيء. |
| `spatie/laravel-medialibrary` | 11.23.7 | ✓ (`^10|^11|^12|^13`) | ^11.23 (بدون تغيير) | لا شيء. |
| `barryvdh/laravel-dompdf` | v3.1.2 | ✓ (`^9|…|^12|^13`) | ^3.1 (بدون تغيير) | لا شيء. |
| `predis/predis` | v2.4.1 | ✓ (framework-agnostic) | ^2.4 | لا شيء. |
| `meilisearch/meilisearch-php` | v1.17.0 | ✓ (framework-agnostic) | ^1.17 (أو ^2.0 لاحقاً) | 2.0 ما زال beta — نُبقي 1.17. |
| `http-interop/http-factory-guzzle` | 1.2.1 | ✓ | ^1.2 | لا شيء. |
| **`maatwebsite/excel`** | 3.1.70 | **✗ عبر 3.x** — 4.0 فقط يدعم L12 | **^4.0** | PhpSpreadsheet 1.30 → 5.3 (4 نسخ major)؛ ملف `AttendanceMonthlyExcelExport.php` يلامس `NumberFormat`/`Fill`/`Worksheet` و`$event->sheet->getDelegate()`. انظر §4.4. |
| **`resend/resend-laravel`** | v0.14.0 | **✗** (`illuminate/support ^10|^11`) | **^1.0** (الأحدث 1.6.0) | قفزة major؛ تعتمد على `resend/resend-php ^1.0` بدل `^0.12`. انظر §4.5. |
| `resend/resend-php` | v0.12.0 | — (مكتبة PHP محضة) | ^1.0 (تابع للـLaravel wrapper) | API قد يختلف — نراجع notification `TaqatNotification` و`MailNotificationTest`. |
| `pestphp/pest` (dev) | v3.8.7 | ✓ (PHPUnit 11.5) | ^3.8 | لا شيء. |
| `pestphp/pest-plugin-laravel` (dev) | v3.2.0 | ✓ | ^3.2 | لا شيء. |
| `pestphp/pest-plugin-mutate` (dev) | v3.0.5 | ✓ | ^3.0 | لا شيء. |
| `phpunit/phpunit` (dev) | 11.5.56 | ✓ | ^11.0 | لا شيء. |
| `nunomaduro/collision` (dev) | v8.9.5 | ✓ | ^8.1 | لا شيء. |
| `mockery/mockery` (dev) | 1.6.15 | ✓ | ^1.6 | لا شيء. |
| `fakerphp/faker` (dev) | v1.24.1 | ✓ | ^1.23 | لا شيء. |

**مكتبات غير مذكورة في المتطلبات الأصلية لكن مستخدمة (موثّقة لإتمام الصورة)**: `barryvdh/laravel-dompdf` موجود في `composer.json`؛ لا نستخدم `laravel/breeze` ولا `laravel/socialite` (كلاهما غير مُثبت).

**حالة BLOCKER**: لا توجد. أعلى حِزَّتَين عالقتَين (`maatwebsite/excel`، `resend/resend-laravel`) أصدر مؤلّفوها نسخاً تدعم L12.

---

## 4. خطة الترقية خطوة بخطوة (Step-by-Step Upgrade Plan)

### 4.1 التحضير
1. **فرع**: `git checkout -b upgrade/laravel-12`.
2. **قاعدة بيانات نظيفة محلياً**: `php artisan migrate:fresh --env=testing` و`php artisan test` الآن للحصول على baseline أخضر قبل أي تعديل.
3. **لقطة من `php artisan route:list --json > /tmp/routes.before.json`** و`php artisan schedule:list > /tmp/schedule.before.txt`.

### 4.2 تحديث `composer.json`
في `apps/api/composer.json`:
```jsonc
"require": {
    "php": "^8.2",                                  // بلا تغيير — متوافق مع L12
    "barryvdh/laravel-dompdf": "^3.1",              // bump minor
    "http-interop/http-factory-guzzle": "^1.2",
    "laravel/framework": "^12.0",                   // ← جوهر الترقية
    "laravel/reverb": "^1.0",
    "laravel/sanctum": "^4.0",
    "laravel/scout": "^10.11",
    "laravel/tinker": "^2.9",
    "maatwebsite/excel": "^4.0",                    // ← major bump
    "meilisearch/meilisearch-php": "^1.10",
    "predis/predis": "^2.0",
    "resend/resend-laravel": "^1.0",                // ← major bump
    "spatie/laravel-activitylog": "^4.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/laravel-permission": "^6.0"
}
```
الأقسام `require-dev` تبقى كما هي (Pest 3 / PHPUnit 11 متوافقة بالفعل).

### 4.3 `composer update` + أول `php artisan test`
```powershell
composer update --with-all-dependencies
php artisan test --testdox
```
- متوقّع: 2 إلى 5 كسر في `Reports/*ExcelExport*` و`MailNotificationTest`. سنعالج في §4.4 و§4.5.

### 4.4 ترقية `maatwebsite/excel` 3→4
- نقطة التلامس الوحيدة: `apps/api/app/Modules/Reports/Exports/AttendanceMonthlyExcelExport.php`.
  - يستخدم `WithEvents` + `AfterSheet` + `$event->sheet->getDelegate()` + `PhpOffice\PhpSpreadsheet\Style\NumberFormat` + `PhpOffice\PhpSpreadsheet\Style\Fill` + `PhpOffice\PhpSpreadsheet\Worksheet\Worksheet`.
  - جميع المعاملات موجودة في PhpSpreadsheet 5.x بنفس التواقيع.
- أعمال التحقق:
  1. `php artisan test --filter=AttendanceReportExportTest` ← يجب أن ينجح بلا تعديل.
  2. اختبار يدوي: `GET /api/reports/attendance/monthly/export?format=xlsx` ويفتح الملف في Excel 365 — خاصة RTL + freeze pane + تنسيق الأرقام.
- Interface الجديد `Maatwebsite\Excel\Concerns\Export` اختياري — لسنا مضطرين لإضافته.
- Return types كلها حاضرة في ملفنا سلفاً (`collection(): Collection`, `headings(): array`, `styles(Worksheet): array`, …) — لا تعديل مطلوب.

### 4.5 ترقية `resend/resend-laravel` 0.14 → 1.x
- نقطة التلامس: `apps/api/app/Modules/Notifications/Notifications/TaqatNotification.php` + `app/Providers/AppServiceProvider.php` (bootstrap) + `config/mail.php` (driver=`resend`) + `tests/Feature/MailNotificationTest.php`.
- API سطح العرض (`Resend::emails()->send(...)` + mail transport `resend`) لم يتغيّر؛ bump رئيسي كان لتسوية `resend-php` 0.12 → 1.0 (ثبات signatures).
- أعمال التحقق:
  1. `php artisan test --filter=MailNotificationTest` ← fakes لا شبكة.
  2. يدوي على staging: trigger أي notification عبر Mail → تأكد وصولها من Resend dashboard.

### 4.6 بعد نجاح الاختبارات
1. `php artisan route:list --json > /tmp/routes.after.json` — `diff` مع before = 0.
2. `php artisan schedule:list > /tmp/schedule.after.txt` — `diff` = 0.
3. `php artisan migrate:status` — كل الـmigrations `Ran`.
4. `composer audit` — يجب أن يُظهر 0 ثغرة.
5. commit واحد لكل خطوة منطقية (bump framework / fix excel / fix resend / lock update).

### 4.7 Smoke test يدوي قبل الدفع
| التدفّق | الخطوة | التحقق |
| --- | --- | --- |
| Login (SPA) | POST `/sanctum/csrf-cookie` ثم `/login` | يحصل على 204/200 + session cookie |
| Login (Mobile) | POST `/login` ببيانات موظف | يرجّع Bearer token |
| Scan check-in | QR scan via `/scan` → PIN prompt | ينشئ attendance row |
| Scan check-out | نفس الخطوات مرة ثانية | يُنهي السجل بـ`check_out_at` |
| Leave submit | إرسال طلب إجازة بمرفق PDF | يُحفظ، تصل notification |
| Leave download | فتح `attachment_url` (signed) | تنزيل ناجح، 403 بعد انتهاء المدة |
| Convert lead | `POST /recruitment/leads/{id}/convert` | ينشئ client + case |
| Monthly attendance export | `?format=xlsx` | ملف يفتح في Excel مع RTL |
| Welcome SMS | إنشاء موظف جديد | SMS يصل (MTCSMS) |
| Mail notification | trigger `TaqatNotification` | يصل عبر Resend |

### 4.8 النشر
1. **Deploy to staging** عبر GitHub Actions (manual trigger `deploy-rehearsal.yml`).
2. **72 ساعة مراقبة** على staging — تحديداً:
   - `storage/logs/laravel.log` — صفر deprecation من Laravel 11.
   - Resend dashboard — صفر 4xx جديد.
   - `php artisan queue:failed` — صفر job جديد في الـfailed.
3. **Promote to prod** عبر `deploy.yml` (push إلى `main`).
4. **24 ساعة مراقبة** post-deploy قبل اعتبار الترقية مغلقة.

---

## 5. قائمة تدقيق يدوية قبل الدفع (Pre-push Checklist)

- [ ] `composer update --with-all-dependencies` نجح بدون `--ignore-platform-reqs`.
- [ ] `composer audit` → 0 vulnerabilities.
- [ ] `php artisan test --parallel` → 93 passed (88 Feature + 5 Unit).
- [ ] `php artisan test --coverage --min=70` إن كانت التغطية الحالية قريبة.
- [ ] `diff /tmp/routes.before.json /tmp/routes.after.json` → فارغ.
- [ ] `diff /tmp/schedule.before.txt /tmp/schedule.after.txt` → فارغ.
- [ ] `php artisan migrate:status` → كل الصفوف `Ran` بلا pending.
- [ ] `php artisan config:cache && php artisan route:cache && php artisan event:cache` → تنجح.
- [ ] `php artisan optimize:clear` بعد الاختبار (لا نشحن caches إلى git).
- [ ] Vendor `laravel/framework` ≥ 12.61.1 (الترقيع الذي يحلّ CVEs).
- [ ] Vendor `maatwebsite/excel` ≥ 4.0.
- [ ] Vendor `resend/resend-laravel` ≥ 1.0.
- [ ] جدول Smoke test (§4.7) كله ✓.
- [ ] grep عن `TODO: L12` / `FIXME: L12` → فارغ.

---

## 6. المخاطر والتخفيف (Risks + Mitigations)

مرتّبة حسب الخطورة.

| # | الخطر | الاحتمال | الأثر | التخفيف |
| --- | --- | --- | --- | --- |
| 1 | **PhpSpreadsheet 5.x يُغيّر سلوك RTL/freeze pane في `AttendanceMonthlyExcelExport`** | متوسط | متوسط — تقرير شهري يُستخدم شهرياً | smoke test يفتح XLSX في Excel 365 + LibreOffice على staging قبل الإنتاج. rollback فوري عبر `composer require maatwebsite/excel:^3.1` إن ظهرت regression. |
| 2 | **Resend transport يغيّر معاملات (مثل reply-to) بين 0.14 و1.x** | منخفض | متوسط — إشعارات الموظفين | `MailNotificationTest` + فحص Resend dashboard على staging لمدة 24 ساعة قبل prod. |
| 3 | **تغيير container (defaults للخصائص nullable) يكسر حقن غير مرئي** | منخفض | منخفض | 93 اختبار يغطي معظم الـcontrollers/services. مراجعة يدوية لـ10 ملفات بها `resolve()`/`app()`. |
| 4 | **Local filesystem يتحوّل إلى `storage/app/private`** | منخفض | متوسط — مرفقات الإجازات والمهام | تأكيد `config/filesystems.php` يحوي تعريف `local` صريح (مسار قديم). المرفقات مخزّنة عبر Spatie Medialibrary والذي يعرّف disk خاص به — لذا الأثر معدوم على الأرجح، لكن نفحص `media` و`attachments` routes يدوياً. |
| 5 | **تضارب في اسم route يسبب تحويل أول/آخر** | منخفض | منخفض | `diff` قبل/بعد `route:list` في §4.6. |
| 6 | **إشعارات Reverb/WebSocket تتوقف** | منخفض | متوسط | 3 ملفات تستخدم `broadcast()`؛ `php artisan reverb:start` + فحص browser channel اليدوي على staging. |
| 7 | **Fonts cache لـDompdf تخرب (المجلد `storage/fonts/`)** | منخفض | منخفض | المجلد موجود (`DejaVuSans*.ufm.json` untracked حالياً). إعادة توليد عبر طباعة أول report PDF على staging. |
| 8 | **CI timing**: deployment workflow معدّل بـcommit `6ae741b`؛ قد يحتاج تعديل إذا رفع composer.lock المحذور | منخفض | منخفض | الـworkflow يُشغّل `composer install` من lock؛ طالما lock محدّث على `main` سيعمل. |

---

## 7. التراجع (Rollback Procedure)

**مستويان**:

### 7.1 أثناء التطوير (قبل merge)
```powershell
git checkout main
git branch -D upgrade/laravel-12
# أو احتفظ بالفرع لتحليل سبب الفشل
```

### 7.2 بعد merge لكن قبل الإنتاج
```powershell
git revert <merge-commit>
git push origin main
# CI سيُعيد النشر إلى staging على L11.56.1
```

### 7.3 بعد الإنتاج (كارثة)
1. SSH إلى VPS.
2. `cd /var/www/taqat && git fetch && git reset --hard <last-known-good-commit>`.
3. `docker compose exec api composer install --no-dev --optimize-autoloader`.
4. `docker compose exec api php artisan migrate:rollback --step=0` (الترقية لا تُضيف migrations؛ لا rollback DB لازم).
5. `docker compose exec api php artisan config:clear && php artisan optimize`.
6. `docker compose restart api queue reverb`.

**نقطة الحفظ قبل الترقية**:
- Tag قبل البدء: `git tag pre-l12-upgrade-$(date +%Y%m%d) && git push --tags`.
- نسخة كاملة من DB على VPS: `backup.yml` workflow يُشغَّل يدوياً ليلة الترقية.

---

## 8. مكتبات بديلة لو blocker (Alternatives If Blockers)

لا يوجد BLOCKER فعلي. مع ذلك، للحالات الاحتياطية:

| الحزمة | إذا رفضت upstream دعم L12 | الخطة البديلة |
| --- | --- | --- |
| `maatwebsite/excel` → 4.0 | إذا كسر RTL/charts | (a) الانتظار؛ (b) تثبيت `3.1.*` مع patch لـ`illuminate/support` constraint عبر `composer.json conflict`؛ (c) الاستبدال بـ`openspout/openspout` مباشرة لتصدير XLSX (نموذج صغير — سطر واحد في Service). |
| `resend/resend-laravel` → 1.0 | إذا كسر notification أو mail transport | (a) الانتظار؛ (b) تبديل MAIL_MAILER إلى `smtp` مؤقتاً مع mailgun/postmark عبر `services.php`؛ (c) fork + patch الـ`illuminate/support` constraint. |
| `laravel/scout` | غير متوقع (يدعم L12 حالياً) | التبديل إلى `typesense/typesense-php` مباشرة + ServiceProvider مخصّص (استثمار كبير). |
| `laravel/reverb` | غير متوقع | التبديل إلى Pusher channels مع نفس Laravel Echo. |
| `spatie/*` | غير متوقع | Spatie يدعم L12/L13 في كل حِزَمنا سلفاً. |

**القاعدة**: إن ظهر BLOCKER في اللحظة الأخيرة، نُجمّد `laravel/framework` على 11.x ونطبّق monkey-patch لـCVEs يدوياً عبر `patches` composer plugin (`cweagans/composer-patches`) على ملفَي `Email.php` و`UrlGenerator.php`. هذا خطّ دفاع أخير فقط.

---

## 9. الوقت التقديري (Time Estimate)

| القسم | التقدير | ملاحظات |
| --- | --- | --- |
| §4.1 التحضير + baseline | 30 دقيقة | باختصار. |
| §4.2 تعديل composer.json | 10 دقائق | ملف واحد. |
| §4.3 `composer update` + أول test run | 20 دقيقة | networking + اختبار كامل. |
| §4.4 maatwebsite/excel 3→4 | 1.5–3 ساعات | يتوقّف على ما يظهر من كسر في PhpSpreadsheet؛ أرجّح 1.5 ساعة. |
| §4.5 resend-laravel 0→1 | 30–60 دقيقة | تغييرات محدودة. |
| §4.6–4.7 Smoke test يدوي | 1.5 ساعة | 10 تدفّقات × ~10 دقائق. |
| §4.8 Staging + 72h مراقبة | 72 ساعة wall-time (0 ساعة عمل) | تلقائي. |
| تحضير PR + review | 1 ساعة | لقطات، changelog، اختبار. |
| Deploy prod + مراقبة | 1 ساعة | تلقائي + تحقق. |
| **المجموع الصافي** | **6–10 ساعات عمل** | +72 ساعة مراقبة. |
| **buffer +50%** | **9–15 ساعة** | في حالة ظهور مفاجآت PhpSpreadsheet. |

---

## 10. ملاحق

### 10.1 ملفات مُستخدِمة لـ`temporarySignedRoute` (الهدف المباشر من ترقية CVE)
- `apps/api/app/Modules/Tasks/Services/TaskAttachmentService.php:58`
- `apps/api/app/Modules/Tasks/Resources/TaskAttachmentResource.php:50`
- `apps/api/app/Modules/Leaves/Resources/LeaveRequestResource.php:56`

### 10.2 ملفات مُستخدِمة لقاعدة `'email'` (CRLF CVE)
25 ملف — القائمة الكاملة في `apps/api/app/{Models,Modules/Employees,Modules/Recruitment,Modules/Auth,Console/Commands}`. لا تحتاج تعديل؛ الترقيع في طبقة الإطار نفسها.

### 10.3 اختبارات ذات صلة بالترقية مباشرة
- `tests/Feature/MailNotificationTest.php` (Resend)
- `tests/Feature/AttendanceReportExportTest.php` (Excel)
- `tests/Feature/LeaveReportExportTest.php` (Excel)
- `tests/Feature/AuthTest.php` (email + container)
- `tests/Feature/SearchTest.php` (Scout + Meilisearch)
- `tests/Feature/MotivationTest.php` (AI service — container)

### 10.4 مراجع
- دليل الترقية الرسمي: <https://laravel.com/docs/12.x/upgrade>
- CVE CRLF: <https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq> (مُرقّع في 12.60.0)
- CVE Signed URL: <https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp> (مُرقّع في 12.61.1)
- Laravel-Excel 4.x upgrade: <https://docs.laravel-excel.com/4.x/getting-started/upgrade.html>
- Resend releases: <https://github.com/resend/resend-laravel/releases>
