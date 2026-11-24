# نفت‌یار ـ بسته تحویلی شرکت

برنامه برای مدیریت معاملات مواد نفتی، ذخیره، حساب اشخاص، دریافت/پرداخت، روزنامچه و راپورها است. کارمندان پس از نصب فقط مرورگر و حساب کاربری لازم دارند.

نصب تازه با دیتابیس خالی MySQL و مدیر عمومی شخصی آغاز می‌شود. هیچ حساب پیش‌فرض، رمز آماده، معامله، مشتری، عرضه‌کننده یا سند خصوصی در بسته نصب نشده است.

راه ساده پیشنهادی:

- نصب‌کننده یک بار روی FIRST_SETUP.bat دو بار کلیک کند؛ نصب هدایت‌شده، دیتابیس خالی جدا، رمزهای مخفی و مدیر نخست آماده می‌شوند. تنظیمات قبلی یا دیتابیس غیرخالی بازنویسی نمی‌شود.
- کارمند فقط میانبر «سیستم مدیریت شرکت» روی صفحه کمپیوتر را باز کند؛ مرورگر خودکار باز می‌شود. پنجره‌ای برای روشن نگه‌داشتن لازم نیست. سپس با حساب شخصی وارد شود.
- مسئول مجاز از میانبر «پشتیبان سیستم شرکت» پشتیبان بگیرد. فولدر Backups با تاریخ و ساعت باز می‌شود؛ رمز ذخیره‌شده نصب به صورت داخلی استفاده می‌شود.
- برای کارمندان فقط راهنمای_خیلی_ساده_کاربران.txt کافی است. راهنماهای زیر برای مدیر و نگهداری حفظ شده‌اند.

راهنماهای مفصل:

- راهنمای_نصب_سیستم.txt ـ نصب‌کننده تخنیکی: PHP/MySQL، دیتابیس و حساب جدا، تنظیمات، مدیر اول، شبکه، پشتیبان و بازیابی.
- راهنمای_راه_اندازی_اولیه.txt ـ مدیر عمومی: حداقل محصول برای معامله مستقیم؛ ذخیره و حساب مالی تنها هنگام نیاز.
- راهنمای_کاربر.txt ـ کارمندان: ثبت و پیگیری کار روزانه با اصطلاحات دری.

نسخه آزمایش‌شده PHP 8.5.0 و MySQL 8.4.11 است؛ این بسته PHP 8.5.x و MySQL 8.4.x می‌خواهد. افزونه‌های دقیق در راهنمای نصب و CHECK_REQUIREMENTS درج شده‌اند. در بسته ZIP تحویلی، vendor و public/build موجود است و برای اولین اجرا و کار روزانه به Composer و Node/npm نیاز نیست.

برای نصب نخست، بسته ZIP را باز کنید و روی FIRST_SETUP.bat دو بار کلیک کنید. پنجره نصب انگلیسی باز می‌شود و پس از پایان تا فشردن کلید باقی می‌ماند. این روش به تغییر تنظیمات عمومی PowerShell نیاز ندارد. روش خط فرمان برای نگهداری:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\FIRST_SETUP.ps1
~~~

نصب‌کننده PHP/MySQL و ابزارهای آن را از قبل نصب کند. برنامه مسیر ابزارها، آدرس محلی و اطلاعات ورود را می‌پرسد؛ اگر لازم باشد با رمز مخفی مدیر محلی MySQL فقط دیتابیس خالی انتخاب‌شده و حساب اختصاصی آن را آماده می‌کند. هیچ رمز پیش‌فرض یا seed ندارد. برای استفاده روی کمپیوترهای دیگر، نصب وب‌سرور و میانبر آدرس شرکت را مطابق راهنمای تخنیکی انجام دهید؛ شروع یک‌کلیکی پیش‌فرض روی همین کمپیوتر است.

روش دستی برای نگهداری، پس از تکمیل تنظیمات نصب:

~~~powershell
php artisan key:generate
php artisan migrate --force
php artisan app:create-manager --name="مدیر عمومی"
php artisan app:check-system
powershell -NoProfile -ExecutionPolicy Bypass -File .\START_SYSTEM.ps1
~~~

پیش از تنظیم .env به دیتابیس خالی تازه، این فرمان‌ها را اجرا نکنید. migrate:fresh و db:wipe روی دیتابیس شرکت ممنوع‌اند.

شروع ساده برای شبکه داخلی محدود است؛ برای اجرای دوامدار نصب‌کننده وب‌سرور Apache یا IIS/FastCGI با HTTPS، ریشه public و شروع خودکار را تنظیم کند. پورت MySQL یا برنامه را به اینترنت باز نکنید.

فایل .env و کلید نصب خصوصی‌اند. پشتیبان شامل دیتابیس و اسناد است و فقط به مسئول مجاز داده می‌شود. بازیابی هدف غیرخالی را پاک نمی‌کند؛ فولدر و دیتابیس خالی جدا لازم دارد.

محدودیت‌های نسخه: حسابداری عملیاتی است؛ لغو/برگرداندن مالی تأییدشده، انتقال پول بین معاملات، واردکردن کامل Excel تاریخی، تعدیل/انتقال عمومی موجودی، ویرایش کامل هویت شرکت، خروجی مستقل تمام راپورها، نرخ زنده اسعار، معاشات و محاسبه مفاد موترها موجود نیست. بهای نامعلوم موجودی و نتیجه ناقص به جای مفاد ساختگی علامت‌گذاری می‌شود. مانده پول منفی ممکن است؛ کنترول پول واقعی مسئول شرکت است.

برای برنامه‌نویس بعدی: سورس کامل، migrations، tests، lockfiles و فایل‌های اصلی رابط همراه است. راهنمای نگهداری در handoff/راهنمای_نگهداری_تخنیکی.txt قرار دارد. نسخه/commit، نتیجه بررسی‌ها و SHA-256 فایل ZIP در Naftyar-Windows-<commit>.manifest.json همراه تحویل ثبت می‌شود.

برای واردکردن فهرست اشخاص، اپراتور نخست پیش‌نمایش را بررسی کند. [راهنمای واردکردن اشخاص و بررسی نام‌های مشابه](docs/contact-import.md)

## Build from source

This repository contains the November 7, 2026 source snapshot of Naftyar, a Dari-language petroleum wholesale, inventory, and operational finance platform.

To prepare the Windows installer from this repository, use PHP 8.5 with the extensions listed in the [requirements check](handoff/CHECK_REQUIREMENTS.ps1), Composer, Node.js 22.12 or newer, and npm. Install MySQL 8.4 before running the installer.

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-company-release.ps1 -Commit HEAD
~~~

The builder installs production PHP packages and builds Vite assets in a fresh export of the selected commit. It writes a Windows ZIP, SHA-256 file and JSON manifest to `release/`. It refuses to overwrite a previous package. Use `-OutputDirectory` to select a new location.

Verify the ZIP against the separately supplied checksum before extracting it into a fresh directory. Run `FIRST_SETUP.bat` there. Select a new empty MySQL database and create the first manager during setup. `BUILD.json` identifies the revision, lockfiles and build tools. `SOURCE_FILES.sha256.txt` covers all other packaged files.

For developer tests in a separate checkout, enable PHP's SQLite extension, install the development dependencies and use a temporary key:

~~~powershell
composer install --no-interaction
npm ci
npm run build
$env:APP_KEY = php -r "echo 'base64:', base64_encode(random_bytes(32));"
php vendor/bin/phpunit -c phpunit.xml --do-not-cache-result
Remove-Item Env:APP_KEY
node --test tests/frontend/decimal.test.mjs
~~~
