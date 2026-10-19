<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invite a user</title></head>
<body>
<h1>Invite a user</h1><p><a href="{{ route('short-urls.index') }}">Back to URLs</a></p>
@if(session('invitation_url'))
<p>Invitation created. Share this link with the invitee; it expires in seven days:</p>
<p><a href="{{ session('invitation_url') }}">{{ session('invitation_url') }}</a></p>
@endif
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach
<form method="POST" action="{{ route('invitations.store') }}">@csrf
<p><label>Email <input type="email" name="email" value="{{ old('email') }}" required></label></p>
@if(auth()->user()->role->name === 'SuperAdmin')
<p><label>New company name <input name="company_name" value="{{ old('company_name') }}" required maxlength="255"></label></p>
<input type="hidden" name="role" value="Admin"><p>The invitee will be the new company's Admin.</p>
@else
<p>Invite into {{ auth()->user()->company->name }}.</p>
<p><label>Role <select name="role"><option value="Member" @selected(old('role') === 'Member')>Member</option><option value="Admin" @selected(old('role') === 'Admin')>Admin</option></select></label></p>
@endif
<button type="submit">Create invitation</button>
</form></body></html>
