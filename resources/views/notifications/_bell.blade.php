@auth
    @php
        $notificationUser = auth()->user();
        $notificationUnreadCount = $notificationUser->unreadNotifications()->count();
        $notificationPreview = $notificationUser->notifications()->latest()->limit(5)->get();
    @endphp
    <div class="mahline-notification" aria-label="Notifications du compte">
        <a class="mahline-notification-trigger" href="{{ route('notifications.index') }}" aria-label="Notifications, {{ $notificationUnreadCount }} non lue(s)" title="Notifications">
            <svg class="mahline-notification-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            @if($notificationUnreadCount > 0)
                <span class="mahline-notification-count" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
            @endif
        </a>
        <div class="mahline-notification-preview">
            <div class="mahline-notification-heading">
                <strong>Notifications récentes</strong>
                <a href="{{ route('notifications.index') }}">Tout voir</a>
            </div>
            @forelse($notificationPreview as $notification)
                <div class="mahline-notification-item {{ $notification->read_at ? '' : 'is-unread' }}">
                    <span class="mahline-notification-dot" aria-hidden="true"></span>
                    <div>
                        <a href="{{ data_get($notification->data, 'url', route('notifications.index')) }}">
                            {{ data_get($notification->data, 'title', data_get($notification->data, 'message', class_basename($notification->type))) }}
                        </a>
                        <small>{{ $notification->created_at?->diffForHumans() }}</small>
                    </div>
                </div>
            @empty
                <p class="mahline-notification-empty">Aucune notification pour le moment.</p>
            @endforelse
        </div>
    </div>
    <style>
        .mahline-notification{position:relative;display:inline-flex;align-items:center;z-index:40;flex:none}
        .mahline-notification-trigger{position:relative;display:inline-flex;width:42px;height:42px;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.4);border-radius:50%;color:inherit!important;text-decoration:none}
        .mahline-notification-trigger:hover,.mahline-notification-trigger:focus-visible{background:rgba(255,255,255,.12);outline:2px solid rgba(255,255,255,.65);outline-offset:2px}
        .mahline-notification-icon{width:22px;height:22px;display:block}
        .mahline-notification-count{position:absolute;top:-5px;right:-5px;display:inline-flex;min-width:19px;height:19px;align-items:center;justify-content:center;padding:0 5px;border:2px solid #153d34;border-radius:20px;background:#c83b45;color:#fff;font-size:11px;font-weight:700;line-height:1}
        .mahline-notification-preview{display:none;position:absolute;right:0;top:calc(100% + 8px);width:min(360px,calc(100vw - 24px));max-height:420px;overflow:auto;background:#fff;color:#20332d;border:1px solid #dce5e0;border-radius:10px;box-shadow:0 12px 30px rgba(0,0,0,.16)}
        .mahline-notification:hover .mahline-notification-preview,.mahline-notification:focus-within .mahline-notification-preview{display:block}
        .mahline-notification-heading{display:flex;justify-content:space-between;gap:12px;padding:14px;border-bottom:1px solid #e8eeeb}
        .mahline-notification-heading a,.mahline-notification-item a{color:#176b57!important}
        .mahline-notification-item{display:flex;gap:10px;padding:12px 14px;border-bottom:1px solid #edf1ef}
        .mahline-notification-item.is-unread{background:#f1f8f4}
        .mahline-notification-dot{width:8px;height:8px;flex:none;margin-top:6px;border-radius:50%;background:transparent}
        .is-unread .mahline-notification-dot{background:#176b57}
        .mahline-notification-item small{display:block;margin-top:4px;color:#65736e}
        .mahline-notification-empty{padding:16px;color:#65736e}
        @media(max-width:850px){.customer-header-actions{width:100%;justify-content:space-between;flex-wrap:wrap}}
    </style>
@endauth
