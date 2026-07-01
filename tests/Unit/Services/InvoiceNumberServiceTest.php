<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_invoice_number_starts_at_one(): void
    {
        $user = User::factory()->create();

        $number = (new InvoiceNumberService)->nextNumberForUser($user);

        $this->assertSame('INV-000001', $number);
    }

    public function test_numbers_increment_sequentially(): void
    {
        $user = User::factory()->create();
        $service = new InvoiceNumberService;

        $first = $service->nextNumberForUser($user);
        Invoice::factory()->for($user)->create(['invoice_number' => $first]);

        $second = $service->nextNumberForUser($user);
        Invoice::factory()->for($user)->create(['invoice_number' => $second]);

        $third = $service->nextNumberForUser($user);

        $this->assertSame('INV-000001', $first);
        $this->assertSame('INV-000002', $second);
        $this->assertSame('INV-000003', $third);
    }

    public function test_numbering_is_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $service = new InvoiceNumberService;

        Invoice::factory()->for($userA)->create(['invoice_number' => $service->nextNumberForUser($userA)]);
        Invoice::factory()->for($userA)->create(['invoice_number' => $service->nextNumberForUser($userA)]);

        $firstForB = $service->nextNumberForUser($userB);

        $this->assertSame('INV-000001', $firstForB);
    }

    public function test_numbers_are_never_reused_after_soft_delete(): void
    {
        $user = User::factory()->create();
        $service = new InvoiceNumberService;

        $invoice = Invoice::factory()->for($user)->create(['invoice_number' => $service->nextNumberForUser($user)]);
        $invoice->delete();

        $next = $service->nextNumberForUser($user);

        $this->assertSame('INV-000002', $next);
    }
}
