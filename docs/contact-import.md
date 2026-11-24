# Contact import and duplicate review

An operator with access to the installation's Artisan console can preview a local contact list. The default command reads contacts and roles, including inactive and soft-deleted contacts. A separate apply command can create new active contacts and their proposed roles. The existing contact editor handles approved changes to existing people.

## Prepare and preview

Use a local UTF-8 text file with one `CATEGORY | exact name` per line. The accepted categories are `CUSTOMER`, `SUPPLIER`, `SARAF`, and `PARTNER`. In this format, `PARTNER` means the source gives no commercial role. Blank lines and comments beginning with `#` are allowed. The limits are 1 MiB, 1,000 contact lines, and 150 Unicode characters per name. A malformed row rejects the entire file.

Keep the original, working copies, and reports in a private folder outside the repository and web root. Console output and JSON contain actual names. In the intended installation, check which database Laravel is configured to use, then run the preview. These PowerShell examples assume the operator's source file is in a private sibling directory.

```powershell
[Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
$source = (Resolve-Path '..\private\contacts.txt').Path
php artisan contacts:import $source
php artisan contacts:import $source --json | Set-Content -Encoding utf8 '..\private\preview.json'
```

Each group includes its source line numbers and proposed categories. The private report identifies candidate directory records by ID, name, role and active state. Counts refer to groups of exactly matching spellings, so repeated source rows can produce a single action.

### وارد کردن اشخاص عمومی

وقتی فهرست فقط نام یک شخص را می‌دهد، او را با نقش عمومی `PARTNER` ثبت کنید. برای این حالت، مسئول مجاز گزینهٔ `--include-general` را هم در پیش‌نمایش و هم در اجرای تأییدشده می‌دهد. فرمان معمولی ردیف‌های عمومی را برای بررسی نگه می‌دارد. نام‌های مشابه، مشخصات متضاد، نقش متفاوت یک شخص موجود و اشخاص غیرفعال در هر دو حالت به بررسی نیاز دارند.

در فورم معامله، نام شخص عمومی را در خانهٔ خرید یا فروش جستجو کنید. از فهرست، **همان شخص** را با کدش انتخاب کنید. برنامه نقش مورد نیاز آن خانه را به همان کد می‌افزاید. برای صراف، انتخاب از خانهٔ صراف به صلاحیت مدیر عمومی یا حسابدار نیاز دارد. نقش قبلی و تاریخچهٔ حساب حفظ می‌شود.

In PowerShell, capture reports in a protected folder outside the application. Run the first block after verifying the target and backup. Stop and review the saved preview, comparing every held line and candidate ID with the owner.

```powershell
[Console]::OutputEncoding = [Text.UTF8Encoding]::new($false)
$private = (Resolve-Path '..\private').Path
$source = (Resolve-Path (Join-Path $private 'contacts.txt')).Path
$previewText = & php artisan contacts:import $source --include-general --json
if ($LASTEXITCODE) { throw 'Preview failed.' }
$previewText | Set-Content -Encoding UTF8 (Join-Path $private 'general-preview.json')
($previewText -join [Environment]::NewLine | ConvertFrom-Json).counts
```

After review, read the approved fingerprint from that saved file. Keep the same source bytes, mode and target. Save the applied IDs and a second preview in the same protected folder.

```powershell
$review = Get-Content -Raw -Encoding UTF8 (Join-Path $private 'general-preview.json') | ConvertFrom-Json
if ($review.mode -ne 'preview' -or $review.include_general -ne $true) { throw 'Review the general-contact preview.' }
$approved = Read-Host 'Enter the 64-character fingerprint approved by the owner'
if ($approved -cne $review.fingerprint) { throw 'Approval does not match the saved preview.' }
$applied = & php artisan contacts:import $source --include-general "--apply=$approved" --json
if ($LASTEXITCODE) { throw 'Apply failed. Create and review a fresh preview.' }
$applied | Set-Content -Encoding UTF8 (Join-Path $private 'general-applied.json')
$repeat = & php artisan contacts:import $source --include-general --json
if ($LASTEXITCODE) { throw 'Reconciliation failed.' }
$repeat | Set-Content -Encoding UTF8 (Join-Path $private 'general-repeat.json')
if (($repeat -join [Environment]::NewLine | ConvertFrom-Json).counts.create -ne 0) { throw 'Investigate remaining proposed creates.' }
```

| Action | What to review |
|---|---|
| CREATE | Check spelling and role. The importer creates one contact with that source spelling and role. |
| REUSE | One active record exactly matches the name and already has the proposed role. Check its ID. This action writes nothing. |
| REVIEW | The entire group is held. Check the reason codes, related source lines, and any directory candidates. Applying the preview leaves these groups pending. |

## Resolve held groups

- For `uncategorized`, an owner who wants a general directory entry can authorize `--include-general` without assigning a commercial role. When a specific commercial role is confirmed, put that category in a **private working copy** and obtain a new preview.
- For `conflicting_source_roles` or `existing_role_conflict`, check whether the name describes one person in several roles or two different people. For a verified multi-role person, use the authorized partner editor to maintain the roles on that person's existing record. Retain all valid roles. Record the partner ID and reviewed source lines in the private review notes.
- For `possible_source_alias` or `possible_existing_alias`, compare the listed names and supporting details. The comparison considers Arabic/Dari letter forms, diacritics, spacing, reordered words, exchange titles, and certain small spelling differences. It is a prompt for human review. If an alias is verified, use the established spelling and role in the working copy. If similar names belong to distinct people, an authorized user can create or identify each separately through the partner screens and record the distinct IDs.
- For `multiple_exact_matches` or `inactive_or_deleted`, investigate by ID and consult the record owner. Keep the lines held until the identity and active status are settled. Leave historical entries and relationships attached to their original IDs.

Keep a private line-to-ID decision log for cases resolved manually. A fresh import of the original list will continue to flag ambiguous names. Preview the working copy again after edits. To approve only some otherwise eligible groups, use a reviewed working copy that retains every unresolved look-alike and conflict for those groups, then check the new preview carefully. Removing one side of a conflict can change the proposed action.

### Apply an owner's decisions to the original source

An operator can save the owner's confirmed identities in a private UTF-8 JSON file. Each case lists every original line for the reviewed spellings, including repeated entries. The source hash binds these decisions to the exact original bytes. A `same` decision chooses the display spelling from `canonical_line` and a role present in the source case. A `distinct` decision keeps each spelling and category separately. Distinguish identical spellings belonging to different people through the contact editor and record their distinct IDs privately.

```powershell
$decisions = Join-Path $private 'decisions.json'
# Example line numbers stand for the identities the owner actually reviewed.
$approvedIdentities = [ordered]@{
    source_sha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $source).Hash.ToLowerInvariant()
    decisions = @(
        @{lines=@(2,3);identity='same';canonical_line=2;role='SUPPLIER'},
        @{lines=@(4,5);identity='distinct'}
    )
}
[IO.File]::WriteAllText($decisions, ($approvedIdentities | ConvertTo-Json -Depth 5), [Text.UTF8Encoding]::new($false))
$options = @('--include-general', "--decisions=$decisions", '--json')
$previewText = & php artisan contacts:import $source @options
if ($LASTEXITCODE) { throw 'Decision preview failed.' }
$previewText | Set-Content -Encoding UTF8 (Join-Path $private 'decision-preview.json')
```

Review the proposed groups and all entries under `source_entries`. After backup and authorization, pass the same `@options` and the newly reviewed fingerprint to the apply command. Save the report and preview again with the same options. Each original source line in a created or reused group shares that group's returned partner ID. Keep a record of every original spelling. Possible matches with other directory records, inactive contacts and role conflicts still go to review. The importer never moves transactions between existing contacts.

## Apply and reconcile

After an authorized reviewer approves the eligible groups and a backup of the intended installation is available, pass the fingerprint from that exact preview.

```powershell
$review = Get-Content -Raw '..\private\preview.json' | ConvertFrom-Json
php artisan contacts:import $source "--apply=$($review.fingerprint)" --json |
    Set-Content -Encoding utf8 '..\private\applied.json'
if ($LASTEXITCODE -ne 0) { throw 'Apply failed. Read the error and preview again.' }
php artisan contacts:import $source
```

Apply rechecks the source bytes, database target, actions and candidates inside a transaction under the contact-creation lock shared with inline entry. A stale fingerprint rejects the batch. The applied report contains the IDs of newly created and reused contacts. Compare them with the approved rows and retain the held line numbers for follow-up. With the same source and directory, the next preview should show zero creates. Existing phone numbers, addresses, notes, roles, balances, and transaction links stay on their existing records.

## Fictional demonstration report

A disposable SQLite database started with one fictional active supplier. The nine input rows included one existing supplier, two identical customer rows, one new saraf, one uncategorized contact, two possible aliases, and two conflicting categories for another name.

| Run | Source rows | Groups | Create | Reuse | Hold |
|---|---:|---:|---:|---:|---:|
| Initial preview and approved apply | 9 | 7 | 2 | 1 | 4 |
| Second preview and approved apply | 9 | 7 | 0 | 3 | 4 |

The final database contained three contacts and three role assignments. Opening balances, deals, money movements and financial transactions each contained zero records.
