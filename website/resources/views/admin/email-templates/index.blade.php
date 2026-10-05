@extends('admin.layout')
@section('title','Manage Email — Templates')
@section('content')
<p>Edit the subject and plain-text message used by each outgoing email. Placeholders insert the recipient name and secure action link automatically.</p>
<div class="panel panel-content table-responsive"><table class="table"><thead><tr><th>Email type</th><th>Subject</th><th>Action</th></tr></thead><tbody>
@foreach($definitions as $key=>$definition)<tr><td>{{ $definition['label'] }}</td><td>{{ $templates[$key]->subject??$definition['subject'] }}</td><td><a class="btn btn-outline-primary" href="{{ route('admin.email.templates.edit',$key) }}">Edit template</a></td></tr>@endforeach
</tbody></table></div>
@endsection
