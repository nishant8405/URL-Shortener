
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Shortener - Sembark Tech</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f3f4f6;
        }

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            margin: 10px 0;
        }

        button {
            background: #2563eb;
            color: white;
            border: 0;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        .error { color: #dc2626; }
        .success { color: #15803d; }
        .url-item { padding: 15px 0; border-bottom: 1px solid #ddd; }
        a { overflow-wrap: anywhere; }
    </style>
</head>
<body>

    <h1>Sembark Tech URL Shortener</h1>
    <p>{{ auth()->user()->name }} &middot; {{ auth()->user()->role->name }} &middot; {{ auth()->user()->company?->name ?? 'Platform' }}</p>
    <p>Short links can be opened by anyone. Your role determines which URLs appear below.</p>

    @if (in_array(auth()->user()->role->name, ['SuperAdmin', 'Admin']))
        <p><a href="{{ route('invitations.index') }}">Invite users</a></p>
    @endif
    @can('create', App\Models\ShortUrl::class)
    <div class="card">
        <h2>Create Short URL</h2>

        @if (session('success'))
            <p class="success">{{ session('success') }}</p>
        @endif

        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <p class="error">{{ $error }}</p>
            @endforeach
        @endif

        <form method="POST" action="{{ route('short-urls.store') }}">
            @csrf

            <label for="original_url">Original URL</label>
            <input
                type="url"
                id="original_url"
                name="original_url"
                placeholder="https://example.com"
                value="{{ old('original_url') }}"
                required
            >

            <button type="submit">Create Short URL</button>
        </form>
    </div>
    @else
        <p>Your role cannot create short URLs.</p>
    @endcan

    <div class="card">
        <h2>Short URLs</h2>

        @forelse ($urls as $url)
            <div class="url-item">
                <p><strong>Original URL:</strong> {{ $url->original_url }}</p>

                <p>
                    <strong>Short URL:</strong>
                    <a href="{{ route('short-urls.redirect', ['code' => $url->short_code]) }}">
                        {{ route('short-urls.redirect', ['code' => $url->short_code]) }}
                    </a>
                </p>
            </div>
        @empty
            <p>No URLs available to display.</p>
        @endforelse
        <nav aria-label="URL pages">
            @if ($urls->previousPageUrl()) <a href="{{ $urls->previousPageUrl() }}">Previous</a> @endif
            <span>Page {{ $urls->currentPage() }} of {{ $urls->lastPage() }}</span>
            @if ($urls->nextPageUrl()) <a href="{{ $urls->nextPageUrl() }}">Next</a> @endif
        </nav>
    </div>

    <form method="POST" action="{{ url('/logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>

</body>
</html>
