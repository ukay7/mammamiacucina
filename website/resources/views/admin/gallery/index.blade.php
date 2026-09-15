@extends('admin.layout')
@section('title','Gallery Events')
@section('content')
<div class="mmc-toolbar"><p>Create an event, then upload its pictures.</p><a class="btn btn-primary" href="{{ route('admin.gallery.create') }}">Add Event</a></div>
<div class="panel"><div class="panel-content table-responsive"><table class="table"><thead><tr><th>Event</th><th>Date</th><th>Photos</th><th>Status</th><th></th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->title }}</td><td>{{ $event->event_date?->format('d M Y') ?? '—' }}</td><td>{{ $event->photos_count }}</td><td>{{ $event->is_active?'Active':'Hidden' }}</td><td><a class="btn btn-outline-primary" href="{{ route('admin.gallery.edit',$event) }}">Manage Photos / Edit</a></td></tr>@empty<tr><td colspan="5">No gallery events yet.</td></tr>@endforelse</tbody></table>{{ $events->links() }}</div></div>
@endsection
