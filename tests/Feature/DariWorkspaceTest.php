<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DariWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_navigation_has_seven_ordered_text_links_and_one_current_page(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $html = $this->actingAs($manager)->get(route('office.configuration'))->assertOk()
            ->assertDontSee('زبان رابط')->assertDontSee('نوع سیستم')->getContent();
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $menu = $document->querySelector('#office-menu');
        $this->assertNotNull($menu);
        $this->assertNull($document->querySelector('[data-sidebar]'));
        $labels = [];
        foreach ($menu->querySelectorAll('a') as $link) {
            $labels[] = trim($link->textContent);
            $this->assertNull($link->querySelector('i, img, svg'));
        }
        $this->assertSame(['داشبورد', 'معاملات', 'حساب‌ها', 'روزنامچه', 'ذخیره', 'گزارش‌ها', 'تنظیمات'], $labels);
        $this->assertCount(1, $menu->querySelectorAll('[aria-current="page"]'));
        $this->assertSame('تنظیمات', trim($menu->querySelector('[aria-current="page"]')->textContent));
        $this->assertNotNull($document->querySelector('button[aria-controls="office-menu"][aria-expanded="false"]'));
        $this->assertNotNull($document->querySelector('header form[method="POST"]'));
    }

    public function test_empty_deal_returns_readable_party_quantity_and_rate_errors(): void
    {
        $manager = User::factory()->create(['role' => UserRole::GeneralManager]);
        $this->actingAs($manager)->from(route('deals.index'))->post(route('deals.store'), [
            'product_id' => Product::factory()->create()->id,
            'deal_date' => '1405/09/03',
            'submit_action' => 'complete',
        ])->assertSessionHasErrors([
            'supplier_name' => 'لطفاً عرضه‌کننده را انتخاب کنید.',
            'customer_name' => 'لطفاً مشتری را انتخاب کنید.',
            'source_quantity_kg' => 'مقدار دریافت را وارد کنید.',
            'source_rate_per_ton' => 'نرخ خرید هر تُن را وارد کنید.',
            'destination_rate_per_ton' => 'نرخ فروش هر تُن را وارد کنید.',
        ]);
        $this->assertDatabaseCount('deals', 0);
    }

    public function test_filter_password_and_route_errors_use_employee_labels(): void
    {
        $errors = Validator::make(['saraf_id' => 1, 'counterparty_id' => 1, 'password' => 'a', 'page' => 0],
            ['saraf_id' => 'different:counterparty_id', 'password' => 'min:12|confirmed', 'page' => 'integer|min:1'])->errors();
        foreach (['saraf_id' => 'صراف', 'password' => 'رمز عبور', 'page' => 'شماره صفحه'] as $field => $label) {
            $this->assertStringContainsString($label, $errors->first($field));
            $this->assertDoesNotMatchRegularExpression('/[a-z_]/i', $errors->first($field));
        }
    }
}
