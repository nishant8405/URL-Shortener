<?php

namespace App\Http\Controllers;

use App\Models\ShortUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ShortUrlController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', ShortUrl::class);
        $user = $request->user();
        $query = ShortUrl::query();

        // Apply the corrected role visibility requirements.
        match ($user->role->name) {
            'Admin' => $query->where('company_id', '=', $user->company_id),
            'Member' => $query->where('user_id', '=', $user->id),
            'SuperAdmin' => $query,
            default => $query->whereRaw('1 = 0'),
        };

        $urls = $query->latest('id')->paginate(15);

        return view('short-urls.index', compact('urls'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', ShortUrl::class);
        $validated = $request->validate([
            'original_url' => ['required', 'string', 'url:http,https', 'max:2048'],
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = Str::random(8);
            try {
                ShortUrl::create([
                    'original_url' => $validated['original_url'],
                    'short_code' => $code,
                    'user_id' => $request->user()->id,
                    'company_id' => $request->user()->company_id,
                ]);

                return redirect()->route('short-urls.index')->with('success', 'Short URL created successfully.');
            } catch (UniqueConstraintViolationException $exception) {
                if (! ShortUrl::where('short_code', $code)->exists()) {
                    throw $exception;
                }
            }
        }

        return back()->withInput()->withErrors(['original_url' => 'Could not allocate a unique short code. Please try again.']);
    }

    public function redirect(Request $request, string $code)
    {
        $shortUrl = ShortUrl::where('short_code', $code)->firstOrFail();
        abort_unless(in_array(strtolower(parse_url($shortUrl->original_url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true), 404);

        return redirect()->away($shortUrl->original_url);
    }
}
