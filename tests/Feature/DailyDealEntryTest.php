<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use App\Services\DealService;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyDealEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_choices_use_active_catalog_records_and_preserve_the_historical_default(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $diesel = Product::factory()->create(['name' => 'Road fuel', 'code' => 'DSL']);
        $petrol = Product::factory()->create(['name' => 'پطرول']);
        $other = Product::factory()->create(['name' => 'Other fuel']);
        $inactive = Product::factory()->create(['name' => 'Petrol', 'is_active' => false]);
        Deal::create(['deal_date' => '2026-01-01', 'product_id' => $other->id, 'status' => DealStatus::Draft]);

        $html = $this->actingAs($user)->get(route('office.transactions'))->assertOk()->getContent();
        $form = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('#new-deal');
        $ids = [];
        foreach ($form->querySelectorAll('button[type=button][data-quick-product]') as $button) {
            $ids[] = (int) $button->getAttribute('data-quick-product');
        }
        $this->assertEqualsCanonicalizing([$diesel->id, $petrol->id], $ids);
        $this->assertSame((string) $other->id, $form->querySelector('#product_id option[selected]')->getAttribute('value'));
        $this->assertNull($form->querySelector("#product_id option[value='{$inactive->id}']"));
        $this->assertSame('register', $form->querySelector('button[type=submit]')->getAttribute('value'));
        $this->assertNull($form->querySelector('button[value=draft]'));
        $this->assertNotNull($form->querySelector('textarea[name=notes]'));
        $this->assertNull($form->querySelector('details textarea[name=notes]'));
    }

    public function test_multiline_notes_and_other_product_survive_validation_and_draft_editing(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $other = Product::factory()->create(['name' => 'Other fuel']);
        $notes = "تحویل صبح\nشماره بار ۲";
        $data = ['product_id' => $other->id, 'deal_date' => '1405/06/08', 'notes' => $notes];
        $this->actingAs($user)->from(route('deals.index'))->post(route('deals.store'), $data + ['submit_action' => 'complete'])
            ->assertSessionHasErrors('source_quantity_kg')->assertSessionHasInput('notes', $notes);
        $this->assertDatabaseCount('deals', 0);
        $this->get(route('deals.index'))->assertOk()->assertSee($notes);
        $this->post(route('deals.store'), $data + ['submit_action' => 'draft'])->assertSessionHasErrors('submit_action');
        $this->assertDatabaseEmpty('deals');
        $draft = app(DealService::class)->create(array_replace($data, ['deal_date' => '2026-08-30', 'submit_action' => 'draft']), $user->id);
        $this->assertSame(DealStatus::Draft, $draft->status);
        $this->assertSame($notes, $draft->notes);
        $html = $this->get(route('deals.edit', $draft))->assertOk()->getContent();
        $form = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('#new-deal');
        $this->assertSame($notes, $form->querySelector('textarea[name=notes]')->textContent);
        $this->assertSame((string) $other->id, $form->querySelector('#product_id option[selected]')->getAttribute('value'));
        $this->get(route('deals.show', $draft))->assertOk()->assertSee($notes);
    }
}
