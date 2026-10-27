<?php

namespace App\Http\Controllers;

use App\Models\ManualArticle;
use App\Models\ManualArticleRevision;
use App\Models\Permission;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManualBookController extends Controller
{
    public function index(Request $request): View
    {
        $search = LikeSearch::sanitize((string) $request->query('q', ''));
        $articles = $this->visibleArticles($request->user())
            ->with('publishedRevision')
            ->when($search !== '', fn (Builder $query) => $query->whereHas('publishedRevision', function (Builder $revision) use ($search): void {
                $revision->where(function (Builder $match) use ($search): void {
                    $match->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('body_markdown', 'like', "%{$search}%");
                });
            }))
            ->orderBy('category')->orderBy('title')->paginate(15)->withQueryString();

        return view('manuals.index', compact('articles', 'search'));
    }

    public function show(Request $request, string $slug): View
    {
        $article = $this->visibleArticles($request->user())
            ->with('publishedRevision')->where('slug', $slug)->firstOrFail();

        return view('manuals.show', [
            'article' => $article,
            'revision' => $article->publishedRevision,
            'isPreview' => false,
            'html' => Str::markdown($article->publishedRevision->body_markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }

    public function context(Request $request): RedirectResponse
    {
        $routeName = (string) $request->query('route', '');
        $article = $this->visibleArticles($request->user())
            ->whereHas('publishedRevision', fn (Builder $query) => $query->whereJsonContains('route_names', $routeName))
            ->orderBy('id')->first();

        return redirect()->route($article ? 'manuals.show' : 'manuals.index', $article ? $article->slug : []);
    }

    public function manage(): View
    {
        return view('manuals.manage', [
            'articles' => ManualArticle::query()->with('publishedRevision')->latest('updated_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('manuals.form', ['article' => new ManualArticle, 'permissions' => $this->permissions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedArticle($request);
        $article = DB::transaction(function () use ($validated, $request): ManualArticle {
            $article = ManualArticle::create($validated + [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $article->recordRevision($request->user()->id);

            return $article;
        });

        $destination = $request->user()->hasPermission('manuals.update') ? 'manuals.edit' : 'manuals.manage';

        return redirect()->route($destination, $destination === 'manuals.edit' ? $article : [])->with('success', 'Draf panduan tersimpan.');
    }

    public function edit(ManualArticle $article): View
    {
        return view('manuals.form', [
            'article' => $article->load('publishedRevision', 'revisions'),
            'permissions' => $this->permissions(),
        ]);
    }

    public function preview(ManualArticle $article): View
    {
        return view('manuals.show', [
            'article' => $article,
            'revision' => $article,
            'isPreview' => true,
            'html' => Str::markdown($article->body_markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }

    public function update(Request $request, ManualArticle $article): RedirectResponse
    {
        $validated = $this->validatedArticle($request, $article);
        DB::transaction(function () use ($article, $validated, $request): void {
            $article = ManualArticle::query()->lockForUpdate()->findOrFail($article->id);
            $article->update($validated + ['updated_by' => $request->user()->id]);
            $article->recordRevision($request->user()->id);
        });

        return redirect()->route('manuals.edit', $article)->with('success', 'Revisi disimpan sebagai draf.');
    }

    public function publish(Request $request, ManualArticle $article): RedirectResponse
    {
        DB::transaction(function () use ($article, $request): void {
            $article = ManualArticle::query()->lockForUpdate()->findOrFail($article->id);
            $revision = $article->revisions()->first() ?? $article->recordRevision($request->user()->id);
            $article->update([
                'published_revision_id' => $revision->id,
                'published_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        $destination = $request->user()->hasPermission('manuals.update') ? 'manuals.edit' : 'manuals.manage';

        return redirect()->route($destination, $destination === 'manuals.edit' ? $article : [])->with('success', 'Panduan diterbitkan.');
    }

    public function unpublish(Request $request, ManualArticle $article): RedirectResponse
    {
        $article->update([
            'published_revision_id' => null,
            'published_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        $destination = $request->user()->hasPermission('manuals.update') ? 'manuals.edit' : 'manuals.manage';

        return redirect()->route($destination, $destination === 'manuals.edit' ? $article : [])->with('success', 'Panduan ditarik dari publikasi.');
    }

    public function restore(Request $request, ManualArticle $article, ManualArticleRevision $revision): RedirectResponse
    {
        abort_unless($revision->manual_article_id === $article->id, 404);
        DB::transaction(function () use ($article, $revision, $request): void {
            $article = ManualArticle::query()->lockForUpdate()->findOrFail($article->id);
            $article->update([
                'title' => $revision->title,
                'category' => $revision->category,
                'summary' => $revision->summary,
                'body_markdown' => $revision->body_markdown,
                'required_permission' => $revision->required_permission,
                'route_names' => $revision->route_names ?? [],
                'updated_by' => $request->user()->id,
            ]);
            $article->recordRevision($request->user()->id);
        });

        return redirect()->route('manuals.edit', $article)->with('success', 'Revisi dipulihkan sebagai draf.');
    }

    private function visibleArticles(User $user): Builder
    {
        $query = ManualArticle::query()->whereNotNull('published_revision_id');
        $access = app(EffectiveAccessService::class);
        if ($access->userCan($user, '*')) {
            return $query;
        }

        $allowed = Permission::query()->whereNotNull('code')->pluck('code')
            ->filter(fn (string $code): bool => $access->userCan($user, $code))->all();

        return $query->whereHas('publishedRevision', fn (Builder $revision) => $revision
            ->whereNull('required_permission')->orWhereIn('required_permission', $allowed));
    }

    private function validatedArticle(Request $request, ?ManualArticle $article = null): array
    {
        if ($request->has('route_names_text')) {
            $names = preg_split('/[\r\n,]+/', (string) $request->input('route_names_text'));
            $request->merge(['route_names' => array_values(array_filter(array_map('trim', $names ?: [])))]);
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'required_permission' => ['nullable', 'string', Rule::exists('permissions', 'code')],
            'route_names' => ['nullable', 'array', 'max:10'],
            'route_names.*' => ['required', 'string', 'distinct', Rule::in(array_keys(Route::getRoutes()->getRoutesByName()))],
            'route_names_text' => ['nullable', 'string', 'max:1000'],
        ];
        if ($article === null) {
            $rules['slug'] = ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('manual_articles', 'slug')];
        }

        $validated = $request->validate($rules);
        unset($validated['route_names_text']);
        $validated['route_names'] ??= [];

        return $validated;
    }

    private function permissions(): array
    {
        return Permission::query()->whereNotNull('code')->orderBy('code')->pluck('code')->all();
    }
}
