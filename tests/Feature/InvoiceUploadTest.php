<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Storage::fake('local');
    }

    public function test_upload_pdf_returns_202(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices', ['pdf' => $file]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_upload_rejects_non_pdf(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices', ['pdf' => $file]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pdf');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $user = User::factory()->create();
        // 11MB = 11264KB, limit is 10240KB (10MB)
        $file = UploadedFile::fake()->create('invoice.pdf', 11264, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices', ['pdf' => $file]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pdf');
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/invoices', ['pdf' => $file]);

        $response->assertUnauthorized();
    }
}
