@extends('emails.layout', [
    'subject' => __('Reset Password Notification'),
    'preview' => __('We received a password reset request for your account.'),
])

@section('icon')
    {{-- Key icon --}}
    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"
         style="display:inline-block;vertical-align:middle;">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
    </svg>
@endsection

@section('heading')
    {{ __('Reset Password') }}
@endsection

@section('content')
    @php $fontStack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Cairo',sans-serif"; @endphp

    {{-- Greeting --}}
    <p style="margin:0 0 16px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('Welcome, :name!', ['name' => $notifiable->name]) }}
    </p>

    {{-- Body --}}
    <p style="margin:0 0 28px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('You are receiving this email because we received a password reset request for your account.') }}
    </p>

    {{-- CTA button --}}
    <table cellpadding="0" cellspacing="0" role="presentation" class="email-btn"
           style="margin:0 auto 28px;">
        <tr>
            <td style="background-color:#059669;border-radius:12px;">
                <a href="{{ $url }}"
                   style="display:inline-block;padding:14px 32px;font-family:{{ $fontStack }};font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;white-space:nowrap;">
                    {{ __('Reset Password') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Fallback URL --}}
    <p style="margin:0 0 20px;font-family:{{ $fontStack }};font-size:12px;color:#94a3b8;line-height:1.6;word-break:break-all;">
        {{ __("If the button doesn't work, copy and paste this link into your browser:") }}<br>
        <a href="{{ $url }}" style="color:#059669;text-decoration:none;">{{ $url }}</a>
    </p>

    {{-- Expiry notice --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="background-color:#fff7ed;border-radius:10px;border:1px solid #fed7aa;padding:14px 16px;margin-bottom:20px;">
                <p style="margin:0;font-family:{{ $fontStack }};font-size:13px;color:#9a3412;line-height:1.6;">
                    {{ __('This password reset link will expire in :count minutes.', ['count' => $expireMinutes]) }}
                </p>
            </td>
        </tr>
    </table>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:20px;">
        <tr>
            <td style="border-top:1px solid #f1f5f9;padding-top:20px;">
                <p style="margin:0;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('If you did not request a password reset, no further action is required.') }}
                </p>
            </td>
        </tr>
    </table>
@endsection

@section('footer-note')
    {{ __('You received this email because a password reset was requested for your account on :app.', ['app' => config('app.name')]) }}
@endsection
