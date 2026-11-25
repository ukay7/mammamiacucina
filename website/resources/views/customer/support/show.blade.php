@extends('admin.layout')
@section('title', $ticket->label)
@include('customer.account-style')
@section('content')
<section class="panel panel-content account-hero">
    <div><p class="account-eyebrow">{{ $ticket->label }} · {{ \App\Models\SupportTicket::STATUSES[$ticket->status] }}</p><h2>{{ $ticket->subject }}</h2>
        @if($staff)<p>{{ $ticket->customer->business_name ?: $ticket->customer->name }} · Customer #{{ $ticket->customer_id }}</p>@endif</div>
    <a class="btn btn-outline-primary" href="{{ route($staff ? 'admin.support.index' : 'customer.support.index') }}">Back to support</a>
</section>
@if($staff)
<section class="panel panel-content"><form class="account-filters mb-0" method="post" action="{{ route('admin.support.status', $ticket) }}">
    @csrf @method('PATCH')
    <label>Ticket status<select class="form-control" name="status">@foreach(\App\Models\SupportTicket::STATUSES as $key => $label)<option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>@endforeach</select></label>
    <button class="btn btn-outline-primary" type="submit">Update status</button>
</form></section>
@endif
<section class="panel panel-content account-conversation">
    <div class="account-section-heading"><h2>Conversation</h2></div>
    {{ $messages->links() }}
    <ol class="account-thread" aria-label="Support messages">
        @foreach($messages->getCollection()->reverse() as $message)
            <li data-message-kind="{{ $message->kind }}">
                @if($message->kind === 'status')
                    <small>{{ $message->body }} · {{ \App\Support\BusinessTime::format($message->created_at) }}</small>
                @else
                    <article><header><strong>{{ $message->kind === 'staff' && ! $staff ? 'Customer Service' : $message->author_name }}</strong>
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ \App\Support\BusinessTime::format($message->created_at) }}</time></header>
                        @if($message->kind === 'note')<small class="account-state" data-state="pending">Internal note</small>@endif
                        <p>{{ $message->body }}</p>
                    </article>
                @endif
            </li>
        @endforeach
    </ol>
    <form class="account-reply" method="post" action="{{ route($staff ? 'admin.support.reply' : 'customer.support.reply', $ticket) }}">
        @csrf
        <div class="form-group"><label for="support-reply">Reply</label><textarea class="form-control" id="support-reply" name="body" rows="3" maxlength="6000" required>{{ old('body') }}</textarea></div>
        @if($staff)<label class="account-check"><input type="checkbox" name="internal" value="1" @checked(old('internal'))> Internal note visible only to staff</label>@endif
        <button class="btn btn-primary" type="submit">Send message</button>
    </form>
</section>
@endsection
