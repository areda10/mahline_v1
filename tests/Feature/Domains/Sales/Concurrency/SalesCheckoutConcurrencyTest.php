<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Sales\Concurrency;

use App\Domains\Cooperatives\Models\Cooperative;
use App\Domains\Identity\Authorization\Models\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Users\Models\User;
use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Localization\Models\Address;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Models\ProductUnit;
use App\Domains\Sales\Contracts\SalesCartManager;
use App\Domains\Sales\Models\SalesCart;
use App\Domains\Sales\Models\SalesCheckoutAttempt;
use App\Domains\Sales\Services\SalesCheckoutService;
use Database\Seeders\ProductUnitSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class SalesCheckoutConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductUnitSeeder::class);
        Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'is_active' => true],
        );
    }

    public function test_two_processes_confirm_the_same_cart_simultaneously_in_separate_transactions(): void
    {
        if (! function_exists('pcntl_fork') || DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('This concurrency test requires pcntl and MySQL row-level locking.');
        }

        $customer = User::factory()->create([
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
        $customer->roles()->attach(Role::query()->where('slug', 'customer')->value('id'));
        $address = Address::factory()->forUser($customer)->create();

        $cooperative = Cooperative::factory()->active()->create();
        $product = Product::factory()->forCooperative($cooperative)->create([
            'price' => '12.50',
            'currency' => 'MAD',
            'unit_id' => ProductUnit::query()->where('code', 'u')->value('id'),
            'is_active' => true,
        ]);
        InventoryStock::query()->create([
            'cooperative_id' => (string) $cooperative->getKey(),
            'stockable_type' => 'product',
            'stockable_id' => (string) $product->getKey(),
            'unit_code' => 'u',
            'quantity_on_hand' => '5.000',
        ]);

        $manager = app(SalesCartManager::class);
        $cart = $manager->getOrCreateCustomerCart((string) $customer->getKey());
        $manager->addItem((string) $cart->getKey(), (string) $product->getKey(), null, '1.000', 1);

        $directory = sys_get_temp_dir().'/mahline-checkout-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            self::fail('Unable to create the concurrency test directory.');
        }

        $readyFiles = [$directory.'/worker-1.ready', $directory.'/worker-2.ready'];
        $resultFiles = [$directory.'/worker-1.result', $directory.'/worker-2.result'];
        $children = [];

        try {
            // Hold the cart row before either worker starts. Both independent
            // processes then queue on the same InnoDB row lock.
            DB::connection()->beginTransaction();
            SalesCart::query()->whereKey($cart->getKey())->lockForUpdate()->firstOrFail();

            foreach ([0, 1] as $index) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    self::fail('Unable to fork a checkout worker.');
                }

                if ($pid === 0) {
                    try {
                        config([
                            'database.connections.checkout_worker' => config('database.connections.mysql'),
                        ]);
                        DB::setDefaultConnection('checkout_worker');
                        DB::purge('checkout_worker');
                        DB::reconnect('checkout_worker');

                        file_put_contents($readyFiles[$index], 'ready');

                        $result = app(SalesCheckoutService::class)->confirm(
                            (string) $customer->getKey(),
                            (string) $cart->getKey(),
                            (string) $address->getKey(),
                            'parallel-checkout-idempotency-0001',
                        );

                        file_put_contents($resultFiles[$index], json_encode([
                            'order_id' => $result['order_id'],
                            'response' => $result,
                        ], JSON_THROW_ON_ERROR));
                    } catch (Throwable $exception) {
                        file_put_contents($resultFiles[$index], json_encode([
                            'error' => $exception::class.': '.$exception->getMessage(),
                        ], JSON_THROW_ON_ERROR));
                    }

                    exit(0);
                }

                $children[] = $pid;
            }

            $deadline = microtime(true) + 10;
            while ((! file_exists($readyFiles[0]) || ! file_exists($readyFiles[1])) && microtime(true) < $deadline) {
                usleep(10_000);
            }

            self::assertFileExists($readyFiles[0]);
            self::assertFileExists($readyFiles[1]);

            // Give both workers time to issue SELECT ... FOR UPDATE while the
            // parent still owns the lock, then release them together.
            usleep(300_000);
            DB::connection()->commit();

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                self::assertSame(0, pcntl_wexitstatus($status));
            }

            $first = json_decode((string) file_get_contents($resultFiles[0]), true, 512, JSON_THROW_ON_ERROR);
            $second = json_decode((string) file_get_contents($resultFiles[1]), true, 512, JSON_THROW_ON_ERROR);

            self::assertArrayNotHasKey('error', $first, $first['error'] ?? '');
            self::assertArrayNotHasKey('error', $second, $second['error'] ?? '');
            self::assertSame($first['order_id'], $second['order_id']);
            self::assertDatabaseCount('orders', 1);
            self::assertDatabaseCount('sales_checkout_attempts', 1);
            self::assertSame(1, $cart->fresh()->activeItems()->count());
            self::assertDatabaseHas('sales_cart_items', [
                'cart_id' => $cart->getKey(),
                'product_id' => $product->getKey(),
                'status' => 'ordered',
            ]);
            self::assertSame(1, SalesCheckoutAttempt::query()->count());
        } finally {
            if (DB::connection()->transactionLevel() > 0) {
                DB::connection()->rollBack();
            }

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status, WNOHANG);
            }

            foreach (array_merge($readyFiles, $resultFiles) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
