<?php

namespace Tests\Feature;

use App\Enums\PartnerRole;
use App\Models\BusinessPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ContactImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_apply_print_private_reports_and_keep_review_groups_on_hold(): void
    {
        $partner = BusinessPartner::create(['name' => 'نمونه آبی', 'is_active' => true]);
        $partner->roles()->create(['role' => PartnerRole::Customer]);
        $path = tempnam(sys_get_temp_dir(), 'contact-test-');
        try {
            file_put_contents($path, "SUPPLIER | نمونه آبی\nSARAF | ستاره بنفش");
            $arguments = ['source' => $path, '--json' => true];
            $this->assertSame(0, Artisan::call('contacts:import', $arguments));
            $preview = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('preview', $preview['mode']);
            $this->assertSame(1, $preview['counts']['review']);
            $this->assertSame(1, $preview['counts']['create']);
            $this->assertSame('نمونه آبی', $preview['candidates'][$partner->id]['name']);
            $this->assertSame(['CUSTOMER'], $preview['candidates'][$partner->id]['roles']);
            $this->assertDatabaseCount('business_partners', 1);

            $this->assertSame(0, Artisan::call('contacts:import', ['source' => $path]));
            $output = Artisan::output();
            $this->assertStringContainsString('REVIEW | line(s) 1', $output);
            $this->assertStringContainsString('CREATE | line(s) 2', $output);
            $this->assertStringContainsString("Compare partner #{$partner->id}", $output);
            $this->assertStringContainsString($preview['fingerprint'], $output);

            $this->assertSame(0, Artisan::call('contacts:import', $arguments + ['--apply' => $preview['fingerprint']]));
            $this->assertSame('applied', json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['mode']);
            $this->assertDatabaseCount('business_partners', 2);
            $this->assertSame([PartnerRole::Customer], $partner->roles()->get()->pluck('role')->all());
        } finally {
            unlink($path);
        }
    }

    public function test_invalid_file_size_stream_wrappers_and_missing_apply_fingerprint_fail_closed(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'contact-test-');
        try {
            file_put_contents($path, str_repeat('x', 1024 * 1024 + 1));
            foreach ([
                ['source' => $path],
                ['source' => 'data://text/plain,PrivateExample'],
                ['source' => 'php://filter/resource='.$path],
                ['source' => sys_get_temp_dir()],
                ['source' => $path, '--apply' => ''],
            ] as $arguments) {
                $this->assertSame(1, Artisan::call('contacts:import', $arguments + ['--json' => true]));
                $error = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertArrayHasKey('error', $error);
                $this->assertStringNotContainsString('PrivateExample', $error['error']);
            }
            $this->assertStringContainsString('fingerprint', $error['error']);
            $this->assertDatabaseEmpty('business_partners');
        } finally {
            unlink($path);
        }
    }
}
