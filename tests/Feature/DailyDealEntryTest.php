<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Product;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\HistoricalDeal;
use Tests\TestCase;

class DailyDealEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_product_dropdown_lists_the_active_catalog_and_preserves_the_historical_default(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        $diesel = Product::factory()->create(['name' => 'دیزل', 'code' => 'DSL']);
        $petrol = Product::factory()->create(['name' => 'پترول', 'code' => 'PTR']);
        $other = Product::factory()->create(['name' => 'LPG']);
        $inactive = Product::factory()->create(['name' => 'Petrol', 'is_active' => false]);
        Deal::create(['deal_date' => '2026-01-01', 'product_id' => $other->id, 'status' => DealStatus::Draft]);

        $html = $this->actingAs($user)->get(route('office.transactions'))->assertOk()->getContent();
        $form = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('#new-deal');
        $this->assertCount(1, $form->querySelectorAll('[name="product_id"]'));
        $select = $form->querySelector('select#product_id[name="product_id"]');
        $this->assertNotNull($select);
        $this->assertNull($select->parentNode->querySelector('button'));
        $options = [];
        foreach ($select->querySelectorAll('option:not([value=""])') as $option) {
            $options[$option->getAttribute('value')] = $option->textContent;
        }
        $this->assertEquals([$diesel->id => 'دیزل', $petrol->id => 'پترول', $other->id => 'LPG'], $options);
        $this->assertSame((string) $other->id, $form->querySelector('#product_id option[selected]')->getAttribute('value'));
        $this->assertNull($form->querySelector("#product_id option[value='{$inactive->id}']"));
        $this->assertSame('register', $form->querySelector('button[type=submit]')->getAttribute('value'));
        $this->assertNull($form->querySelector('button[value=draft]'));
        $this->assertNotNull($form->querySelector('textarea[name=notes]'));
        $this->assertNull($form->querySelector('details textarea[name=notes]'));

        $this->post(route('deals.store'), [
            'submit_action' => 'register', 'deal_date' => '1405/07/11', 'product_id' => $petrol->id,
            'supplier_name' => 'عرضه‌کنندهٔ نمونه', 'customer_name' => 'خریدار نمونه',
            'source_quantity_kg' => '1000', 'final_quantity_kg' => '1000',
            'source_rate_per_ton' => '480', 'destination_rate_per_ton' => '510',
        ])->assertSessionHasNoErrors();
        $this->assertSame($petrol->id, Deal::where('status', DealStatus::Completed)->sole()->product->id);
        $this->assertSame($other->id, Deal::where('status', DealStatus::Draft)->sole()->product->id);
    }

    public function test_multiline_notes_and_other_product_survive_validation_and_draft_editing(): void
    {
        $user = User::factory()->create(['role' => UserRole::GeneralManager]);
        Product::factory()->create(['name' => 'Diesel']);
        $other = Product::factory()->create(['name' => 'Other fuel']);
        $notes = "تحویل صبح\nشماره بار ۲";
        $data = ['product_id' => $other->id, 'deal_date' => '1405/06/08', 'notes' => $notes];
        $this->actingAs($user)->from(route('deals.index'))->post(route('deals.store'), $data + ['submit_action' => 'register'])
            ->assertSessionHasErrors('source_quantity_kg')->assertSessionHasInput('notes', $notes);
        $this->assertDatabaseCount('deals', 0);
        $returned = $this->get(route('deals.index'))->assertOk()->assertSee($notes)->getContent();
        $selected = HTMLDocument::createFromString($returned, LIBXML_NOERROR)->querySelector('#new-deal #product_id option[selected]');
        $this->assertSame((string) $other->id, $selected->getAttribute('value'));
        $this->post(route('deals.store'), $data + ['submit_action' => 'draft'])->assertSessionHasErrors('submit_action');
        $this->assertDatabaseEmpty('deals');
        $draft = HistoricalDeal::draft(array_replace($data, ['deal_date' => '2026-08-30']), $user->id);
        $this->assertSame(DealStatus::Draft, $draft->status);
        $this->assertSame($notes, $draft->notes);
        $html = $this->get(route('deals.edit', $draft))->assertOk()->getContent();
        $form = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('#new-deal');
        $this->assertSame($notes, $form->querySelector('textarea[name=notes]')->textContent);
        $this->assertSame((string) $other->id, $form->querySelector('#product_id option[selected]')->getAttribute('value'));
        $this->get(route('deals.show', $draft))->assertOk()->assertSee($notes);
    }
}
