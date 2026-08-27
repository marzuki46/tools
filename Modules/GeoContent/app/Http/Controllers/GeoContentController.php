<?php

namespace Modules\GeoContent\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\GeoContent\Models\GeoProject;
use Modules\GeoContent\Jobs\FetchCompetitorFactsJob;
use Modules\GeoContent\Jobs\GenerateCriticalQuestionsJob;
use Modules\GeoContent\Jobs\GenerateGeoContentJob;
use Modules\SeoCluster\Services\WordPressService;

class GeoContentController extends Controller
{
    public function index(Request $request)
    {
        $projects = GeoProject::where('user_id', auth()->id())->latest()->paginate(20);
        return view('geocontent::index', compact('projects'));
    }

    public function create()
    {
        return view('geocontent::create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'keyword_utama' => 'required|string|max:255',
            'competitor_urls' => 'required|array|min:1|max:5',
            'competitor_urls.*' => 'required|url|max:500',
            'mode' => 'required|in:baru,revisi',
            'wp_post_id' => 'nullable|integer',
            'locale' => 'nullable|in:id,en',
        ]);

        $project = GeoProject::create([
            'user_id' => auth()->id(),
            'api_key_website_id' => $request->attributes->get('api_key_website_id') ?? null,
            'keyword_utama' => $validated['keyword_utama'],
            'competitor_urls' => $validated['competitor_urls'],
            'mode' => $validated['mode'],
            'wp_post_id' => $validated['wp_post_id'] ?? null,
            'locale' => $validated['locale'] ?? 'id',
            'status' => 'draft',
        ]);

        // Untuk mode revisi: fetch before snapshot via WordPress REST
        if ($project->mode === 'revisi' && $project->wp_post_id) {
            try {
                $wp = app(WordPressService::class);
                // Coba ambil kredensial per-website jika ada
                if ($project->api_key_website_id) {
                    $site = \App\Models\ApiKeyWebsite::find($project->api_key_website_id);
                    if ($site && $site->wp_url) {
                        $wp->setSiteCredentials($site->wp_url, $site->wp_username, $site->wp_app_password);
                    }
                }
                $post = $wp->getPostContent((int) $project->wp_post_id);
                $project->update(['wp_post_before_snapshot' => $post['content'] ?? '']);
            } catch (\Throwable $e) {
                // Biarkan tetap lanjut, snapshot bisa kosong
            }
        }

        FetchCompetitorFactsJob::dispatch($project->id);

        return redirect()->route('geocontent.show', $project)->with('success', 'Project GEO dibuat, fase 1-2 sedang diproses.');
    }

    public function show(GeoProject $project)
    {
        Gate::authorize('view', $project);
        $project->load(['sourceFacts', 'criticalFindings', 'contents', 'diff', 'keywordResearch']);
        return view('geocontent::show', compact('project'));
    }

    public function fetchFacts(GeoProject $project)
    {
        Gate::authorize('update', $project);
        FetchCompetitorFactsJob::dispatch($project->id);
        return back()->with('success', 'Fetch fakta kompetitor diantrikan.');
    }

    public function generateQuestions(GeoProject $project)
    {
        Gate::authorize('update', $project);
        GenerateCriticalQuestionsJob::dispatch($project->id);
        return back()->with('success', 'Generate pertanyaan kritis diantrikan.');
    }

    public function generate(GeoProject $project)
    {
        Gate::authorize('update', $project);
        GenerateGeoContentJob::dispatch($project->id);
        return back()->with('success', 'Generate konten AIDA diantrikan.');
    }

    public function publish(GeoProject $project, Request $request)
    {
        Gate::authorize('update', $project);
        $content = $project->contents()->latest()->first();
        if (!$content) return back()->withErrors(['msg' => 'Belum ada konten.']);

        $wp = app(WordPressService::class);
        if ($project->api_key_website_id) {
            $site = \App\Models\ApiKeyWebsite::find($project->api_key_website_id);
            if ($site && $site->wp_url) {
                $wp->setSiteCredentials($site->wp_url, $site->wp_username, $site->wp_app_password);
            }
        }

        try {
            if ($project->mode === 'revisi' && $project->wp_post_id) {
                $wp->updatePost((int) $project->wp_post_id, ['content' => $content->final_content, 'title' => $content->meta_title ?? $project->keyword_utama]);
            } else {
                $wp->publishPost($content->meta_title ?? $project->keyword_utama, $content->final_content, ['slug' => \Illuminate\Support\Str::slug($project->keyword_utama)], null);
            }
            $project->update(['status' => 'published']);
            return back()->with('success', 'Publish berhasil.');
        } catch (\Throwable $e) {
            return back()->withErrors(['msg' => 'Publish gagal: ' . $e->getMessage()]);
        }
    }

    public function review(GeoProject $project)
    {
        Gate::authorize('update', $project);
        $project->update(['status' => 'review']);
        return back()->with('success', 'Ditandai untuk review.');
    }
}
