@php
    $isRtl = app()->getLocale() === 'ar';
    $dir    = $isRtl ? 'rtl' : 'ltr';
    $align  = $isRtl ? 'right' : 'left';
    $fontStack = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,'Cairo',sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? config('app.name') }}</title>

    <style>
        /* Cairo – Arabic subset */
        @font-face {
            font-family: 'Cairo';
            font-weight: 100 900;
            font-style: normal;
            src: url('{{ asset('fonts/cairo-arabic.woff2') }}') format('woff2');
            unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC;
        }
        /* Cairo – Latin subset */
        @font-face {
            font-family: 'Cairo';
            font-weight: 100 900;
            font-style: normal;
            src: url('{{ asset('fonts/cairo-latin.woff2') }}') format('woff2');
            unicode-range: U+0000-00FF, U+2000-206F, U+20AC, U+FEFF, U+FFFD;
        }

        /* ── Responsive ─────────────────────────── */
        @media screen and (max-width: 600px) {
            .email-card-td { padding: 28px 24px !important; }
            .email-btn a   { display: block !important; }
        }

        /* ── Dark mode ──────────────────────────── */
        @media (prefers-color-scheme: dark) {
            .email-bg    { background-color: #0f172a !important; }
            .email-card  { background-color: #1e293b !important; border-color: #334155 !important; }
            .email-footer-text { color: #64748b !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f0faf5;-webkit-text-size-adjust:100%;">

    {{-- Hidden preview text --}}
    @isset($preview)
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $preview }}&#8199;&#65279;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;&#847;</div>
    @endisset

    {{-- Outer wrapper --}}
    <table class="email-bg" width="100%" cellpadding="0" cellspacing="0" role="presentation"
           style="background-color:#f0faf5;">
        <tr>
            <td align="center" style="padding:48px 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                       style="max-width:560px;width:100%;">

                    {{-- ── Brand badge ───────────────────────── --}}
                    <tr>
                        <td align="center" style="padding-bottom:28px;">
                            <table cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td style="background-color:#059669;border-radius:10px;padding:8px 18px;">
                                        <span style="font-family:{{ $fontStack }};font-size:15px;font-weight:700;color:#ffffff;letter-spacing:-0.2px;white-space:nowrap;">
                                            {{ config('app.name') }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ── Card ──────────────────────────────── --}}
                    <tr>
                        <td class="email-card"
                            style="background-color:#ffffff;border-radius:20px;border:1px solid #e2e8f0;">

                            {{-- Icon row --}}
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="center" style="padding:40px 40px 0;">
                                        <table cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                <td style="width:64px;height:64px;background-color:#059669;border-radius:16px;text-align:center;vertical-align:middle;">
                                                    @yield('icon')
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Heading --}}
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="center" style="padding:20px 40px 4px;">
                                        <h1 style="margin:0;font-family:{{ $fontStack }};font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-0.4px;line-height:1.3;">
                                            @yield('heading')
                                        </h1>
                                    </td>
                                </tr>
                            </table>

                            {{-- Body content --}}
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td class="email-card-td"
                                        style="padding:24px 40px 40px;font-family:{{ $fontStack }};font-size:15px;color:#475569;line-height:1.7;text-align:{{ $isRtl ? 'right' : 'center' }};">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- ── Footer ─────────────────────────────── --}}
                    <tr>
                        <td align="center" style="padding-top:28px;">
                            <p class="email-footer-text"
                               style="margin:0;font-family:{{ $fontStack }};font-size:12px;color:#94a3b8;line-height:1.7;">
                                @yield('footer-note')
                            </p>
                            <p class="email-footer-text"
                               style="margin:8px 0 0;font-family:{{ $fontStack }};font-size:11px;color:#cbd5e1;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
