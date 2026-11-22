@extends(auth()->user()->hasRole('customer') ? 'layouts.customer' : (auth()->user()->hasRole('super_admin_root') ? 'layouts.root' : 'layouts.admin'))

@section('title', 'Mes notifications')

@section('content')
    <div class="page-header">
        <div>
            <h1>Mes notifications</h1>
            <p class="muted">{{ $unreadCount }} notification(s) non lue(s)</p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="button button-secondary" type="submit">Tout marquer comme lu</button>
            </form>
        @endif
    </div>

    <section class="card">
        @forelse($notifications as $notification)
            @php
                $title = data_get($notification->data, 'title', data_get($notification->data, 'message', class_basename($notification->type)));
                $message = data_get($notification->data, 'body', data_get($notification->data, 'description'));
            @endphp
            <article class="notification-row {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                <div class="notification-content">
                    <strong>{{ $title }}</strong>
                    @if($message)<p>{{ $message }}</p>@endif
                    <small class="muted">{{ $notification->created_at?->format('d/m/Y à H:i') }}</small>
                </div>
                @if(!$notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification->getKey()) }}">
                        @csrf
                        <button class="button button-secondary button-small" type="submit">Marquer comme lu</button>
                    </form>
                @else
                    <span class="badge">Lue</span>
                @endif
            </article>
        @empty
            <p class="empty">Vous n'avez aucune notification.</p>
        @endforelse
        <div class="pagination">{{ $notifications->links() }}</div>
    </section>
@endsection

@push('styles')
<style>
.notification-row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 0;border-bottom:1px solid #e8eeeb}
.notification-row.is-unread{border-left:3px solid #176b57;padding-left:13px}
.notification-content{min-width:0}
.notification-content p{margin:7px 0}
.notification-content small{display:block}
@media(max-width:600px){.notification-row{align-items:flex-start;flex-direction:column}}
</style>
@endpush
