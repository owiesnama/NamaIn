@extends('emails.layout', [
    'subject' => __('Verify Your Email Address'),
    'preview' => __('Please verify your email address to continue.'),
])

@section('icon')
    {{-- Envelope + checkmark --}}
    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"
         style="display:inline-block;vertical-align:middle;">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
    </svg>
@endsection

@section('heading')
    {{ __('Verify Your Email Address') }}
@endsection

@section('content')
    @php $fontStack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Cairo',sans-serif"; @endphp

    {{-- Greeting --}}
    <p style="margin:0 0 16px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('Welcome, :name!', ['name' => $notifiable->name]) }}
    </p>

    {{-- Body --}}
    <p style="margin:0 0 28px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('Please click the button below to verify your email address.') }}
    </p>

    {{-- CTA button --}}
    <table cellpadding="0" cellspacing="0" role="presentation" class="email-btn"
           style="margin:0 auto 28px;">
        <tr>
            <td style="background-color:#059669;border-radius:12px;">
                <a href="{{ $url }}"
                   style="display:inline-block;padding:14px 32px;font-family:{{ $fontStack }};font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;white-space:nowrap;">
                    {{ __('Verify Email Address') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Fallback URL --}}
    <p style="margin:0 0 20px;font-family:{{ $fontStack }};font-size:12px;color:#94a3b8;line-height:1.6;word-break:break-all;">
        {{ __("If the button doesn't work, copy and paste this link into your browser:") }}<br>
        <a href="{{ $url }}" style="color:#059669;text-decoration:none;">{{ $url }}</a>
    </p>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="border-top:1px solid #f1f5f9;padding-top:20px;">
                <p style="margin:0;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('If you did not create an account, no further action is required.') }}
                </p>
            </td>
        </tr>
    </table>
@endsection

@section('footer-note')
    {{ __('You received this email because an account was created with this address on :app.', ['app' => config('app.name')]) }}
@endsection
