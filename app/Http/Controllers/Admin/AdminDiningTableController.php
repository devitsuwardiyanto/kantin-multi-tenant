<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCanteen;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Modules\Admin\Services\QrTokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminDiningTableController extends Controller
{
    use AuthorizesRequests, ResolvesManagedCanteen;

    public function __construct(private QrTokenService $qr) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DiningTable::class);
        $canteen = $this->managedCanteen($request);
        $tables = DiningTable::query()->where('canteen_id', $canteen->id)
            ->with('activeToken')->orderBy('code')->get();

        return view('admin.tables.index', compact('canteen', 'tables'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DiningTable::class);
        $canteen = $this->managedCanteen($request);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30',
                Rule::unique('dining_tables', 'code')->where(fn ($q) => $q->where('canteen_id', $canteen->id))],
            'label' => ['required', 'string', 'max:60'],
            'zone' => ['nullable', 'string', 'max:60'],
        ]);

        (new DiningTable)->forceFill([
            'canteen_id' => $canteen->id,
            'code' => $data['code'],
            'label' => $data['label'],
            'zone' => $data['zone'] ?? null,
            'status' => 'active',
        ])->save();

        return redirect()->route('admin.tables.index')->with('status', 'Meja dibuat.');
    }

    public function rotate(DiningTable $table): RedirectResponse
    {
        $this->authorize('rotateQr', $table);
        $plain = $this->qr->rotate($table);

        // Token mentah ditampilkan SATU KALI pada halaman QR (flash, tidak disimpan).
        return redirect()->route('admin.tables.qr', $table)->with('qr_plain', $plain);
    }

    public function qr(DiningTable $table): View
    {
        $this->authorize('rotateQr', $table);
        $plain = session('qr_plain');
        $url = is_string($plain) ? route('customer.scan', ['token' => $plain]) : null;

        return view('admin.tables.qr', compact('table', 'plain', 'url'));
    }
}
