<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Accept invitation</title></head>
<body><h1>Accept invitation</h1>
<p>Create your {{ $invitation->role }} account for {{ $invitation->email }}.</p>
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<form method="POST" action="{{ route('invitations.accept', $token) }}">@csrf
<p><label>Name <input name="name" value="{{ old('name') }}" required maxlength="255"></label></p>
<p><label>Password <input type="password" name="password" minlength="8" autocomplete="new-password" required></label></p>
<p><label>Confirm password <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label></p>
<button type="submit">Create account</button>
</form></body></html>
