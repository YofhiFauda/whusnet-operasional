<?php

namespace App\Http\Controllers\Master;

use App\Enums\FopTaskPriority;
use App\Http\Controllers\Controller;
use App\Models\TicketIssueCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class TicketIssueCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $categories = TicketIssueCategory::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($status === 'active', function ($query) {
                $query->where('is_active', true);
            })
            ->when($status === 'inactive', function ($query) {
                $query->where('is_active', false);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.ticket-issue-categories.index', compact('categories', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.ticket-issue-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        TicketIssueCategory::create($validated);

        return redirect()
            ->route('master.ticket-issue-categories.index')
            ->with('success', 'Kategori issue "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(TicketIssueCategory $category): View
    {
        return view('master.ticket-issue-categories.edit', compact('category'));
    }

    public function update(Request $request, TicketIssueCategory $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);

        $category->update($validated);

        return redirect()
            ->route('master.ticket-issue-categories.index')
            ->with('success', 'Kategori issue "'.$category->name.'" berhasil diperbarui.');
    }

    public function toggleStatus(TicketIssueCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        $statusText = $category->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Kategori issue \"{$category->name}\" berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?TicketIssueCategory $category = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('ticket_issue_categories', 'name')->ignore($category),
            ],
            'default_priority' => ['required', new Enum(FopTaskPriority::class)],
            'sla_source' => ['required', Rule::in(['paket', 'prioritas'])],
            'is_active' => 'required|boolean',
        ]);
    }
}
