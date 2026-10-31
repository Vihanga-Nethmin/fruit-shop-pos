<?php

namespace Tests\Feature;

class IdempotencyTest extends InventoryTestCase
{
    public function test_the_same_key_and_request_is_replayed_not_repeated(): void
    {
        $shop = $this->setUpShop();
        $key  = ['Idempotency-Key' => 'order-0001'];

        $first  = $this->opening($shop, 1, [], $key)->assertCreated();
        $second = $this->opening($shop, 1, [], $key)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertQty(1, $this->stock($shop['mango'])['physical_qty']);                  // not 2
        $this->assertDatabaseCount('outbox', 1);
    }

    public function test_the_same_key_with_a_different_request_is_a_conflict(): void
    {
        $shop = $this->setUpShop();
        $key  = ['Idempotency-Key' => 'order-0002'];

        $this->opening($shop, 1, [], $key)->assertCreated();
        $this->opening($shop, 2, [], $key)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');

        $this->assertQty(1, $this->stock($shop['mango'])['physical_qty']);
    }

    public function test_a_failed_request_does_not_burn_the_key(): void
    {
        $shop = $this->setUpShop();
        $key  = ['Idempotency-Key' => 'order-0003'];

        $this->opening($shop, -1, [], $key)->assertStatus(422);                              // bad body
        $this->opening($shop, 1, [], $key)->assertCreated()->assertHeaderMissing('Idempotency-Replayed');

        $this->assertQty(1, $this->stock($shop['mango'])['physical_qty']);
    }

    public function test_keys_are_scoped_per_shop(): void
    {
        $a   = $this->setUpShop(self::TENANT_A);
        $b   = $this->setUpShop(self::TENANT_B);
        $key = ['Idempotency-Key' => 'order-0004'];

        $this->opening($a, 1, [], $key)->assertCreated();
        $this->opening($b, 1, [], $key, self::TENANT_B)->assertCreated()->assertHeaderMissing('Idempotency-Replayed');

        $this->assertQty(1, $this->stock($b['mango'], self::TENANT_B)['physical_qty']);
    }

    public function test_a_bad_key_is_rejected(): void
    {
        $shop = $this->setUpShop();

        $this->opening($shop, 1, [], ['Idempotency-Key' => 'bad key!'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_IDEMPOTENCY_KEY');
    }

    public function test_when_required_a_post_without_a_key_is_refused(): void
    {
        $shop = $this->setUpShop();
        config(['idempotency.required' => true]);

        $this->opening($shop, 1)->assertStatus(400)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
        $this->opening($shop, 1, [], ['Idempotency-Key' => 'order-0005'])->assertCreated();
    }
}