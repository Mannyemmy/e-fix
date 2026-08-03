<x-master-layout>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="font-weight-bold mb-1">{{ __('messages.activity_log') }}</h5>
                    <small class="text-muted">
                        {{ $user->display_name ?: trim($user->first_name.' '.$user->last_name) }}
                        &mdash; {{ $user->email }}
                        <span class="badge badge-light">{{ $user->user_type }}</span>
                    </small>
                </div>
                <a href="{{ route('activity-log.index') }}" class="btn btn-sm btn-secondary">
                    {{ __('messages.all_activity') }}
                </a>
            </div>

            <div class="card-body">
                @if ($logs->isEmpty())
                    <p class="text-muted mb-0">
                        {{ __('messages.no_activity_recorded') }}
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped border">
                            <thead>
                                <tr>
                                    <th>{{ __('messages.event') }}</th>
                                    <th>{{ __('messages.ip_address') }}</th>
                                    <th>{{ __('messages.location') }}</th>
                                    <th>{{ __('messages.device') }}</th>
                                    <th>{{ __('messages.date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    @php
                                        $badge = [
                                            'register'     => 'badge-primary',
                                            'login'        => 'badge-success',
                                            'login_failed' => 'badge-danger',
                                        ][$log->event] ?? 'badge-secondary';
                                        $geo = $log->geolocation;
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge {{ $badge }}">{{ $log->event_label }}</span>
                                            @if (!empty($log->meta['reason']))
                                                <br><small class="text-muted">{{ str_replace('_', ' ', $log->meta['reason']) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <code>{{ $log->ip_address ?: '—' }}</code>
                                            @if ($geo && $geo->is_suspicious)
                                                <span class="badge badge-danger">{{ $geo->is_hosting ? 'datacentre' : 'proxy' }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($geo && $geo->lookup_status === 'success')
                                                {{ $geo->location_label ?: '—' }}
                                                @if ($geo->isp)
                                                    <br><small class="text-muted">{{ $geo->isp }}</small>
                                                @endif
                                            @elseif ($geo)
                                                <small class="text-muted">—</small>
                                            @else
                                                <small class="text-muted">{{ __('messages.not_resolved_yet') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($log->source)
                                                <span class="badge badge-light">{{ $log->source }}</span>
                                            @endif
                                            <small title="{{ $log->user_agent }}">
                                                {{ \Illuminate\Support\Str::limit($log->user_agent, 45) ?: '—' }}
                                            </small>
                                        </td>
                                        <td>
                                            <span title="{{ $log->created_at->toDateTimeString() }}">
                                                {{ $log->created_at->format('d M Y, h:i A') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">{{ __('messages.showing_recent_activity') }}</small>
                @endif
            </div>
        </div>
    </div>
</x-master-layout>
