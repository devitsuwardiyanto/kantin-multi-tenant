<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Tenant;
use App\Modules\Ordering\Exceptions\CartException;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UC-04 Kustomisasi Item Pesanan: grup wajib/opsional (min/maks), opsi habis nonaktif,
 * harga akhir = dasar + modifier, catatan khusus ≤ 200 karakter tanpa tag HTML.
 */
class ItemCustomizationUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private CustomerSession $session;

    private Menu $menu;

    private ModifierOption $regular;

    private ModifierOption $large;

    private ModifierOption $extraShot;

    private ModifierOption $creamCheese;

    protected function setUp(): void
    {
        parent::setUp();

        $canteen = Canteen::factory()->create();
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id, 'display_name' => 'Kopi Serambi']);
        $this->menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Es Kopi Susu', 'base_price' => 15000, 'stock_qty' => 10]);

        $size = ModifierGroup::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ukuran', 'min_select' => 1, 'max_select' => 1]);
        $topping = ModifierGroup::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Topping', 'min_select' => 0, 'max_select' => 2]);
        $this->menu->modifierGroups()->attach([
            $size->id => ['tenant_id' => $tenant->id, 'sort_order' => 1],
            $topping->id => ['tenant_id' => $tenant->id, 'sort_order' => 2],
        ]);

        $option = fn (ModifierGroup $group, string $name, int $delta, bool $available = true): ModifierOption => ModifierOption::factory()->create([
            'tenant_id' => $tenant->id, 'group_id' => $group->id, 'name' => $name, 'price_delta' => $delta, 'is_available' => $available,
        ]);
        $this->regular = $option($size, 'Regular (300 ml)', 0);
        $this->large = $option($size, 'Large (450 ml)', 5000);
        $this->extraShot = $option($topping, 'Extra shot espresso', 3000);
        $this->creamCheese = $option($topping, 'Cream cheese foam', 4000, false);

        $this->session = new CustomerSession;
        $this->session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        $this->app->instance(ResolveCustomerSession::class, new class($this->session) extends ResolveCustomerSession
        {
            public function __construct(private ?CustomerSession $stub) {}

            public function current(Request $request): ?CustomerSession
            {
                return $this->stub;
            }
        });
    }

    protected function tearDown(): void
    {
        Redis::del('cart:'.$this->session->id);

        parent::tearDown();
    }

    public function test_form_shows_required_and_optional_groups_with_sold_out_option_disabled(): void
    {
        Livewire::test('ordering::item-customizer')
            ->dispatch('customize-item', menuId: $this->menu->id)
            ->assertSee('Es Kopi Susu')
            ->assertSee('WAJIB · PILIH 1')
            ->assertSee('opsional, maks 2')
            ->assertSet("multi.{$this->extraShot->group_id}", [])
            ->assertSet("single.{$this->regular->group_id}", null)
            ->assertSeeInOrder(['Cream cheese foam', 'HABIS'])
            ->assertSeeHtml('value="'.$this->creamCheese->id.'" disabled');
    }

    public function test_required_group_must_be_chosen_before_saving(): void
    {
        Livewire::test('ordering::item-customizer')
            ->dispatch('customize-item', menuId: $this->menu->id)
            ->call('confirm')
            ->assertSee('Pilih minimal 1 opsi Ukuran.')
            ->assertNotDispatched('cart-updated');

        $this->assertTrue(app(CartService::class)->view($this->session)->isEmpty());
    }

    public function test_final_price_is_base_plus_modifiers_and_configuration_is_saved(): void
    {
        Livewire::test('ordering::item-customizer')
            ->dispatch('customize-item', menuId: $this->menu->id)
            ->set("single.{$this->regular->group_id}", (string) $this->regular->id)
            ->set("multi.{$this->extraShot->group_id}", [(string) $this->extraShot->id])
            ->set('note', 'Gula aren sedikit saja, ya.')
            ->call('increment')
            ->assertSee('Rp36.000') // (15.000 + 0 + 3.000) × 2
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertDispatched('cart-updated');

        $line = app(CartService::class)->view($this->session)->lines[0];
        $this->assertSame(2, $line->quantity);
        $this->assertSame(3000, $line->modifierTotal);
        $this->assertSame(36000, $line->lineTotal);
        $this->assertSame('Gula aren sedikit saja, ya.', $line->note);
        $this->assertEqualsCanonicalizing(['Regular (300 ml)', 'Extra shot espresso'], array_column($line->modifiers, 'name'));
    }

    public function test_note_is_stripped_of_html_and_limited_to_200_characters(): void
    {
        app(CartService::class)->add($this->session, $this->menu->id, 1, [$this->large->id], '<script>alert(1)</script><b>Tanpa es</b>');
        $this->assertSame('alert(1)Tanpa es', app(CartService::class)->view($this->session)->lines[0]->note);

        Livewire::test('ordering::item-customizer')
            ->dispatch('customize-item', menuId: $this->menu->id)
            ->set("single.{$this->regular->group_id}", (string) $this->regular->id)
            ->set('note', str_repeat('a', 201))
            ->call('confirm')
            ->assertHasErrors(['note' => 'max']);
    }

    public function test_selection_rules_reject_too_many_sold_out_and_unattached_options(): void
    {
        $cart = app(CartService::class);
        $second = ModifierOption::factory()->create([
            'tenant_id' => $this->menu->tenant_id, 'group_id' => $this->regular->group_id, 'name' => 'Jumbo', 'price_delta' => 8000,
        ]);

        foreach ([
            [[$this->regular->id, $second->id], 'Pilih maksimal 1 opsi Ukuran.'],
            [[$this->regular->id, $this->creamCheese->id], 'Pilihan tambahan tidak tersedia.'],
        ] as [$ids, $message]) {
            try {
                $cart->add($this->session, $this->menu->id, 1, $ids);
                $this->fail('Pilihan seharusnya ditolak: '.$message);
            } catch (CartException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }

        $unattached = ModifierGroup::factory()->create(['tenant_id' => $this->menu->tenant_id]);
        $stray = ModifierOption::factory()->create(['tenant_id' => $this->menu->tenant_id, 'group_id' => $unattached->id]);
        $this->expectExceptionMessage('Pilihan tambahan tidak tersedia.');
        $cart->add($this->session, $this->menu->id, 1, [$this->regular->id, $stray->id]);
    }
}
