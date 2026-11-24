<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EmployeeErrorPageTest extends TestCase
{
    #[TestWith([403])]
    #[TestWith([404])]
    #[TestWith([405])]
    #[TestWith([409])]
    #[TestWith([419])]
    #[TestWith([429])]
    #[TestWith([500])]
    #[TestWith([503])]
    public function test_employee_receives_a_dari_recovery_page_with_the_original_status(int $status): void
    {
        config(['app.debug' => false]);
        Route::get('/error-page-test', fn () => abort($status, 'Internal diagnostic detail'));
        $this->get('/error-page-test')->assertStatus($status)
            ->assertSee('<html lang="fa" dir="rtl">', false)
            ->assertSee(__('errors.'.$status)[0])->assertSee('بازگشت به نفت‌یار')
            ->assertDontSee('Internal diagnostic detail')->assertDontSee('Forbidden');
    }
}
