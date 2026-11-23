# بررسی لایه ساده کار روزانه — 2026-10-03

کارمند پس از نصب فقط میانبر «سیستم مدیریت شرکت» و حساب شخصی لازم دارد. راهنمای_خیلی_ساده_کاربران.txt هشت مورد در ۲۵ خط دارد؛ فقط شروع، ورود، معامله، حساب، دریافت/پرداخت، ذخیره، گزارش و خروج. راهنماهای مفصل نصب، راه‌اندازی اولیه مدیر و نگهداری محفوظ‌اند.

## نصب تازه و امنیت

FIRST_SETUP.ps1 واقعی با Windows PowerShell 5.1، PHP 8.5.0 و MySQL 8.4.11 در فولدر جدا دارای فاصله و حروف دری اجرا شد. پاسخ پرسش‌ها از harness خصوصی وارد شد؛ همان Read-Host، SecureString، پل stdin، تنظیمات، migrations، فرمان مدیر و ساخت میانبر اجرا شدند. هیچ seed نصب نشد: ۱۹ migration، یک مدیر عمومی با نام دری و رمز تازه؛ تمام جدول‌های تجارتی پیش از کار خالی بودند. رمز دیتابیس شامل علامت‌های نقل‌قول، دلار، backslash و # بود و عیناً از تنظیمات خوانده و به اتصال موفق تبدیل شد.

اگر دیتابیس/دسترسی آماده نباشد، رمز مخفی مدیر محلی MySQL فقط برای schema خالی و حساب اختصاصی همان schema استفاده می‌شود. رمز مدیر ذخیره نمی‌شود؛ حساب موجود reset نمی‌شود؛ صلاحیت global اعطا نمی‌شود. نصب روی .env موجود و schema غیرخالی در آزمون‌های جدا پیش از migration رد شد. فایل تنظیمات اصلی پروژه تغییر نکرد و دیتابیس قبلی شرکت در هیچ فرمان نوشتنی استفاده نشد.

## جریان کار واقعی در نصب آزمون

FIRST_SETUP → START_SYSTEM.bat → مرورگر → ورود مدیر → محصول نخست → معامله مستقیم → تأیید تصفیه → حساب مالی نخست → دریافت → حساب‌ها → نتیجه مالی → BACKUP_SYSTEM.bat → خروج.

- محصول و حساب مالی از رابط ثبت شدند؛ داده نمایشی یا رمز آماده نصب نشد.
- معامله 10000 kg، خرید 10000 USD، فروش 11000 USD و مفاد 1000 USD.
- دریافت تأییدشده 5000 USD؛ حساب مشتری 6000 USD دریافتنی، عرضه‌کننده 10000 USD پرداختنی و صندوق 5000 USD. در این سناریوی جدید پرداخت به عرضه‌کننده انجام نشد.
- گزارش نتیجه مالی همین خرید/فروش/مفاد را نشان داد؛ خطای JavaScript دیده نشد.
- بخش ذخیره از منوی عادی باز شد؛ برای تحویل مستقیم گردش ساختگی صفر ماند.
- پشتیبان دارای database.sql و پوشه فایل‌های خصوصی/عمومی بود. همه SHA-256های manifest و فایل خصوصی ساختگی عیناً تطبیق شدند؛ .env و APP_KEY در پشتیبان نبودند؛ حالت بسته پس از پشتیبان برداشته شد.
- خروج به صفحه ورود برگشت. اطلاعات مالی آزمون ساختگی‌اند؛ بسته تمیز هیچ‌یک را ندارد.

## رفتار Windows

میانبرهای واقعی Desktop با نام دری ساخته شدند؛ میانبر سیستم در حالت سرد برنامه را شروع کرد. راه‌انداز فرمان مرورگر پیش‌فرض Windows را اجرا کرد؛ مراجعه صفحه ورود و فایل‌های رابط مشاهده شد. ورود و کار تجارتی با مرورگر کنترل‌شده بررسی شدند. اجرای مخفی مستقل از عمر پنجره شروع ماند. کلیک دوباره همان PID سالم را استفاده کرد؛ هیچ اجرای دوم ساخته نشد.

پورت عمداً با listener دیگر اشغال شد: فقط پیام کوتاه دری نمایش یافت، listener دیگر حفظ شد و هیچ process دیگری متوقف نشد. پس از آزادشدن پورت، خود میانبر دری سیستم را دوباره شروع کرد. شروع پیش‌فرض فقط روی همین کمپیوتر و loopback است؛ شبکه/وب‌سرور نهایی شرکت را نصب‌کننده طبق راهنمای مفصل آماده می‌کند.

سه اصلاح عملی لازم بود: CRLF بدون BOM برای BAT؛ ShellLinkW برای نام/مسیر دری؛ ورودی حافظه برای فرمان مدیر به جای console code-page در نصب پس‌زمینه. mysqldump روی مسیر دری --result-file قابل اتکا نبود؛ اکنون SQL به فایل بازشده PHP جریان می‌یابد و buffer هر قطعه پاک می‌شود. قواعد معامله، صلاحیت، حسابداری و موجودی تغییر نکردند.

نصب تازه دیگری از سورس نهایی با ورودی فقط در حافظه نیز انجام شد: UTF-8 preamble در ارتباط کنسول Windows تحمل می‌شود و pipe خام بدون flush اضافی بسته می‌شود. ۱۹ migration، یک مدیر و صفر معلومات تجارتی تأیید شد. فایل موقت پاسخ‌های نصب قبلی حذف شد؛ رمز مدیر MySQL در ورودی حافظه باقی می‌ماند و ذخیره نمی‌شود.

## بررسی نهایی

سویت کامل MySQL پس از اصلاح پشتیبان: **۲۸۷ آزمون، ۱۳۲۶ بررسی، بدون خطا یا skip**. آزمون بازیابی موجود، مسیر دارای فاصله/دری را واقعاً آزمایش و رکوردها و هر دو نوع فایل را به schema خالی آزمون برگرداند. چهار آزمون JavaScript موفق؛ Pint و syntax PHP و parser اسکریپت‌های PowerShell موفق. بررسی نسخه کپی‌شده و تطبیق سورس/ZIP و اسکن محرمانگی در manifest نهایی همراه بسته ثبت می‌شود.

این بررسی روی یک کمپیوتر Windows محلی انجام شد. کمپیوتر فزیکی دوم، کاربران همزمان واقعی شرکت و وب‌سرور دائمی شرکت در این مرحله آزمایش نشده‌اند. محدودیت‌های تجارتی تحویل قبلی در راهنماهای مفصل باقی‌اند.

## Startup compatibility repair — 2026-11-07

The installed daily BAT reproduced a WMI process creation failure (Windows return code 8). RUN_DAILY.ps1 now falls back to hidden Shell execution without redirected child handles when WMI fails. START_SYSTEM.bat and BACKUP_SYSTEM.bat clear inherited PSModulePath for Windows PowerShell. OPERATIONAL_COMMON.ps1 uses NullString.Value when removing environment variables so PowerShell 7/.NET does not leave empty APP_KEY/DB_CONNECTION overrides that prevent reading the installed configuration.

Verified on the installed company copy: app:check-system passed; actual START_SYSTEM.bat exited 0; GET /login returned 200 with its email field after the launcher exited; the recorded process creation time matched the listening PHP PID; a repeated BAT launch reused that same PID. Both Dari desktop shortcuts were recreated with targets in the permanent company installation. No migrations, seeds, resets, business record creation, or credential/key changes were performed. The installed company configuration was retained. The full business suite, backup operation, and a fresh second-PC installation were not repeated for this operational repair.


## Shareable support checker — 2026-11-07

CHECK_SYSTEM.bat is a self-contained hybrid batch/PowerShell support check. Place it beside START_SYSTEM.bat and double-click it; SUPPORT_REPORT.txt is written there, or on Desktop if the folder is not writable. The report checks required file/configuration presence, local MySQL service, PHP availability/version, local listener presence, and the existing read-only application requirement command. It includes only allowlisted command messages and recognized launcher failure categories, never raw SQL/PHP exceptions, credential values, configuration contents, business records, or raw logs. It does not migrate, seed, reset, start services, or modify configuration. Actual Windows BAT checks passed for the installed application, missing/partial setup, a path with spaces/Dari/apostrophe, and exclusion of a fake configuration secret canary. Remote friend-PC failure remains undiagnosed until its report is returned.


## MySQL tool selection update — 2026-11-07

A remote screenshot showed rejection of non-8.4 client tools; the remote server version is not yet known. FIRST_SETUP.ps1 now tries PATH and standard Program Files MySQL 8.4 locations, validates both mysql.exe and mysqldump.exe, and allows a folder retry or safe cancellation. It continues to require MySQL Server 8.4 through the existing bridge; version requirements were not relaxed. CHECK_SYSTEM.bat now reports PATH client versions, local MySQL service executable versions, and compatible 8.4 folders without starting services or connecting administratively. Windows PowerShell 5.1 checks passed: a simulated 9.5 tool pair was rejected, a real 8.4 pair selected after it, invalid-folder input retried, and Q cancelled before database setup. The extended checker detected local tools and service executable 8.4 versions. No reinstall/business-suite run was performed; remote success awaits verification. A two-file setup update ZIP is available for existing extracted copies; it contains only FIRST_SETUP.ps1 and CHECK_SYSTEM.bat.
