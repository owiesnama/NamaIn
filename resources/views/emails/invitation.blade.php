@extends('emails.layout', [
    'subject'  => __(':inviter invited you to join :tenant', ['inviter' => $inviterName, 'tenant' => $tenantName]),
    'preview'  => __(':inviter invited you to join :tenant as :role.', ['inviter' => $inviterName, 'tenant' => $tenantName, 'role' => $roleName]),
])

@section('icon')
    {{-- Envelope open --}}
    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"
         style="display:inline-block;vertical-align:middle;">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 2.51-4.66-2.51m0 0-1.023-.55a2.25 2.25 0 0 0-2.134 0l-1.022.55m0 0-4.661 2.51m16.5 1.615a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V8.844a2.25 2.25 0 0 1 1.183-1.981l7.5-4.04a2.25 2.25 0 0 1 2.134 0l7.5 4.04a2.25 2.25 0 0 1 1.183 1.98V19.5Z" />
    </svg>
@endsection

@section('heading')
    {{ __("You're invited!") }}
@endsection

@section('content')
    @php $fontStack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Cairo',sans-serif"; @endphp

    {{-- Body --}}
    <p style="margin:0 0 24px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __(':inviter has invited you to join :tenant as :role.', [
            'inviter' => $inviterName,
            'tenant'  => $tenantName,
            'role'    => $roleName,
        ]) }}
    </p>

    {{-- Org info card --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
        <tr>
            <td style="background-color:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td style="font-family:{{ $fontStack }};font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;padding-bottom:6px;">
                            {{ __('Organization') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-family:{{ $fontStack }};font-size:16px;font-weight:700;color:#0f172a;">
                            {{ $tenantName }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-family:{{ $fontStack }};font-size:13px;color:#64748b;padding-top:4px;">
                            {{ __('Role') }}: <strong>{{ $roleName }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA button --}}
    <table cellpadding="0" cellspacing="0" role="presentation" class="email-btn"
           style="margin:0 auto 28px;">
        <tr>
            <td style="background-color:#059669;border-radius:12px;">
                <a href="{{ $acceptUrl }}"
                   style="display:inline-block;padding:14px 32px;font-family:{{ $fontStack }};font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;white-space:nowrap;">
                    {{ __('Accept Invitation') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="border-top:1px solid #f1f5f9;padding-top:20px;">
                <p style="margin:0 0 6px;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('This invitation expires in 7 days.') }}
                </p>
                <p style="margin:0;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('If you did not expect this invitation, you may ignore this email.') }}
                </p>
            </td>
        </tr>
    </table>
@endsection

@section('footer-note')
    {{ __('You received this invitation from :inviter via :app.', ['inviter' => $inviterName, 'app' => config('app.name')]) }}
@endsection
