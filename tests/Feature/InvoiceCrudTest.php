<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_invoices(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'completed']);
        Invoice::factory()->pending()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices?status=completed');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_only_shows_own_invoices(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Invoice::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_show_returns_invoice_with_items(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_show_returns_403_for_other_users_invoice(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertForbidden();
    }

    public function test_show_returns_404_for_nonexistent_invoice(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices/99999');

        $response->assertNotFound();
    }

    public function test_download_returns_pdf_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'stored_path' => 'invoices/test-file.pdf',
            'original_filename' => 'my-invoice.pdf',
        ]);

        Storage::disk('local')->put('invoices/test-file.pdf', 'fake-pdf-content');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}/download");

        $response->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_download_returns_403_for_other_users_invoice(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}/download");

        $response->assertForbidden();
    }

    public function test_delete_removes_invoice_and_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'stored_path' => 'invoices/to-delete.pdf',
        ]);

        Storage::disk('local')->put('invoices/to-delete.pdf', 'fake-pdf-content');

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Invoice deleted.']);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        Storage::disk('local')->assertMissing('invoices/to-delete.pdf');
    }

    public function test_delete_returns_403_for_other_users_invoice(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertForbidden();
    }
}
