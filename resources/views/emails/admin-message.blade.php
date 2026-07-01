@extends('emails.layout', [
    'subject' => $subject,
    'preview' => $subject,
])

@section('icon')
    {{-- Envelope icon --}}
    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8"
         style="display:inline-block;vertical-align:middle;">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
    </svg>
@endsection

@section('heading')
    {{ $subject }}
@endsection

@section('content')
    @php $fontStack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Cairo',sans-serif"; @endphp

    {{-- Greeting --}}
    <p style="margin:0 0 16px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {{ __('Hi :name,', ['name' => $notifiable->name]) }}
    </p>

    {{-- Admin-authored body. Escaped with e() to prevent HTML/script injection; nl2br preserves line breaks. --}}
    <p style="margin:0 0 24px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;">
        {!! nl2br(e($body)) !!}
    </p>

    {{-- Divider --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td style="border-top:1px solid #f1f5f9;padding-top:20px;">
                <p style="margin:0;font-family:{{ $fontStack }};font-size:13px;color:#94a3b8;line-height:1.6;">
                    {{ __('If you have any questions, simply reply to this email.') }}
                </p>
            </td>
        </tr>
    </table>
@endsection

@section('footer-note')
    {{ __('You received this email because you have an account on :app.', ['app' => config('app.name')]) }}
@endsection
