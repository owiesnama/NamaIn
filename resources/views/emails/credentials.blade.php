@extends('emails.layout', [
    'subject' => __('Your account has been created on :tenant', ['tenant' => $tenantName]),
    'preview' => __('Your account on :tenant is ready. Here are your login credentials.', ['tenant' => $tenantName]),
])

@section('icon')
    {{-- User check icon --}}
    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"
         style="display:inline-block;vertical-align:middle;">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
    </svg>
@endsection

@section('heading')
    {{ __('Your Account is Ready') }}
@endsection

@section('content')
    @php $fontStack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Cairo',sans-serif"; @endphp

    {{-- Greeting --}}
    <p style="margin:0 0 16px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('Welcome, :name!', ['name' => $notifiable->name]) }}
    </p>

    {{-- Body --}}
    <p style="margin:0 0 24px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('An account has been created for you on :tenant.', ['tenant' => $tenantName]) }}
        {{ __('Here are your login credentials:') }}
    </p>

    {{-- Credentials box --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
        <tr>
            <td style="background-color:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;padding:20px;">

                {{-- Email row --}}
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;">
                    <tr>
                        <td style="font-family:{{ $fontStack }};font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;padding-bottom:4px;">
                            {{ __('Email') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-family:{{ $fontStack }};font-size:15px;font-weight:600;color:#0f172a;"
                            dir="ltr">
                            {{ $notifiable->email }}
                        </td>
                    </tr>
                </table>

                {{-- Divider --}}
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;">
                    <tr><td style="border-top:1px solid #e2e8f0;"></td></tr>
                </table>

                {{-- Password row --}}
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td style="font-family:{{ $fontStack }};font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;padding-bottom:4px;">
                            {{ __('Temporary Password') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-family:monospace,'Courier New',Courier;font-size:18px;font-weight:700;color:#059669;letter-spacing:0.1em;"
                            dir="ltr">
                            {{ $temporaryPassword }}
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
                <a href="{{ route('login') }}"
                   style="display:inline-block;padding:14px 32px;font-family:{{ $fontStack }};font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;white-space:nowrap;">
                    {{ __('Login Now') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="border-top:1px solid #f1f5f9;padding-top:20px;">
                <p style="margin:0 0 6px;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('You will be required to set a new password on your first login.') }}
                </p>
                <p style="margin:0;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('If you did not expect this email, please ignore it.') }}
                </p>
            </td>
        </tr>
    </table>
@endsection

@section('footer-note')
    {{ __('You received this email because an account was created for you on :app / :tenant.', ['app' => config('app.name'), 'tenant' => $tenantName]) }}
@endsection
