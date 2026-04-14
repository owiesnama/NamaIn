@php
    $lang = once(fn() => preference('language', 'ar'));
    $dir = $lang == 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $title ?? 'NamaIn' }}</title>

    <style>
        /* ── Cairo font (local, no external request) ─────── */
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('fonts/cairo-arabic.woff2') }}') format('woff2');
            unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC;
        }
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('fonts/cairo-latin-ext.woff2') }}') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        @font-face {
            font-family: 'Cairo';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('{{ asset('fonts/cairo-latin.woff2') }}') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        /* ── Reset ──────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Cairo', sans-serif;
            font-size: 11px;
            color: #1f2937;
            background: #fff;
            line-height: 1.6;
        }

        /* ── Page ───────────────────────────────────────── */
        .page { max-width: 820px; margin: 0 auto; position: relative; }

        /* ── Layout ─────────────────────────────────────── */
        .row   { display: flex; align-items: flex-start; gap: 16px; }
        .col   { flex: 1; }
        .col-auto { flex: 0 0 auto; }
        .items-end   { align-items: flex-end; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .justify-end { justify-content: flex-end; }

        /* ── Typography ─────────────────────────────────── */
        .text-xs   { font-size: 9px; }
        .text-sm   { font-size: 10px; }
        .text-base { font-size: 11px; }
        .text-lg   { font-size: 14px; }
        .text-xl   { font-size: 18px; }
        .text-2xl  { font-size: 22px; }

        .font-bold     { font-weight: bold; }
        .font-semibold { font-weight: 600; }
        .uppercase     { text-transform: uppercase; }
        .tracking      { letter-spacing: 0.06em; }

        .text-gray-400 { color: #9ca3af; }
        .text-gray-500 { color: #6b7280; }
        .text-gray-600 { color: #4b5563; }
        .text-white    { color: #fff; }
        .text-emerald  { color: #059669; }
        .text-red      { color: #dc2626; }
        .text-amber    { color: #d97706; }

        .text-start  { text-align: start; }
        .text-end    { text-align: end; }
        .text-center { text-align: center; }

        /* ── Spacing ─────────────────────────────────────── */
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mb-8 { margin-bottom: 32px; }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .mt-6 { margin-top: 24px; }
        .mt-8 { margin-top: 32px; }
        .ms-auto { margin-inline-start: auto; }

        .p-3  { padding: 12px; }
        .p-4  { padding: 16px; }
        .p-6  { padding: 24px; }
        .px-4 { padding-inline: 16px; }
        .px-6 { padding-inline: 24px; }
        .py-2 { padding-block: 8px; }
        .py-3 { padding-block: 12px; }

        /* ── Borders & Backgrounds ───────────────────────── */
        .bg-white   { background: #fff; }
        .bg-gray-50 { background: #f9fafb; }
        .bg-gray-100 { background: #f3f4f6; }
        .bg-emerald { background: #059669; }
        .bg-dark    { background: #1f2937; }

        .border         { border: 1px solid #e5e7eb; }
        .border-t       { border-top: 1px solid #e5e7eb; }
        .border-b       { border-bottom: 1px solid #e5e7eb; }
        .border-emerald { border-color: #047857; }
        .rounded        { border-radius: 4px; }
        .rounded-lg     { border-radius: 8px; }
        .overflow-hidden { overflow: hidden; }

        /* ── Header Band ─────────────────────────────────── */
        .print-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            color: #fff;
        }

        .print-header.emerald { background: #059669; }
        .print-header.dark    { background: #1f2937; }

        .print-header .logo img {
            max-height: 48px;
            max-width: 140px;
            object-fit: contain;
        }

        .print-header .doc-title {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        /* ── Meta Strip ─────────────────────────────────── */
        .meta-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 32px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .meta-item { text-align: start; }
        .meta-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #9ca3af;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 11px;
            font-weight: 600;
            color: #1f2937;
        }

        /* ── Section ─────────────────────────────────────── */
        .section { padding: 20px 32px; }

        .section-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }

        /* ── Info Card ───────────────────────────────────── */
        .info-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 14px 16px;
        }

        .info-card strong { font-size: 12px; color: #1f2937; }
        .info-card .sub   { font-size: 9px; color: #6b7280; margin-top: 3px; line-height: 1.6; }

        /* ── Stats Row ───────────────────────────────────── */
        .stats-row {
            display: flex;
            gap: 12px;
            padding: 16px 32px;
        }

        .stat-card {
            flex: 1;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 14px;
            text-align: center;
        }

        .stat-card .stat-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #9ca3af;
            margin-bottom: 4px;
        }

        .stat-card .stat-value {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }

        .stat-card.accent      { border-color: #059669; background: #f0fdf4; }
        .stat-card.accent .stat-value { color: #059669; }
        .stat-card.danger      { border-color: #fca5a5; background: #fff5f5; }
        .stat-card.danger .stat-value { color: #dc2626; }
        .stat-card.neutral     { border-color: #d1d5db; background: #f9fafb; }

        /* ── Tables ──────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; border-spacing: 0; }

        .table-items thead th {
            background: #059669;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 10px 14px;
            border: 1px solid #047857;
            text-align: start;
        }

        .table-items tbody td {
            padding: 9px 14px;
            font-size: 10px;
            color: #1f2937;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .table-items tbody tr:nth-child(even) td { background: #f9fafb; }

        .table-items tfoot td {
            padding: 8px 14px;
            font-size: 10px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            font-weight: 600;
        }

        .table-ledger thead th {
            background: #1f2937;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 9px 12px;
            border: 1px solid #111827;
            text-align: start;
        }

        .table-ledger tbody td {
            padding: 8px 12px;
            font-size: 10px;
            border: 1px solid #e5e7eb;
        }

        .table-ledger tbody tr:nth-child(even) td { background: #f9fafb; }
        .table-ledger tbody tr.opening-row td { font-weight: 600; background: #f0fdf4; color: #065f46; }

        .table-ledger tfoot td {
            padding: 9px 12px;
            font-size: 10px;
            font-weight: bold;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
        }

        /* ── Summary Box ─────────────────────────────────── */
        .summary-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            min-width: 240px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 10px;
        }

        .summary-row:last-child { border-bottom: none; }
        .summary-row.total   { background: #059669; color: #fff; font-weight: bold; font-size: 11px; }
        .summary-row.balance { background: #1f2937; color: #fff; font-weight: bold; font-size: 11px; }
        .summary-row.discount .val { color: #dc2626; }

        /* ── Badges ──────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-paid     { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .badge-unpaid   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-partial  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-credit   { background: #ede9fe; color: #5b21b6; border: 1px solid #c4b5fd; }
        .badge-overdue  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* ── Watermark Stamp ─────────────────────────────── */
        .stamp {
            position: absolute;
            top: 42%;
            inset-inline-end: 60px;
            transform: translateY(-50%) rotate(-15deg);
            font-size: 42px;
            font-weight: 900;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            opacity: 0.08;
            border: 6px solid currentColor;
            padding: 6px 20px;
            border-radius: 8px;
            pointer-events: none;
            white-space: nowrap;
        }

        .stamp-paid   { color: #059669; }
        .stamp-unpaid { color: #dc2626; }

        /* ── Footer ──────────────────────────────────────── */
        .print-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 16px 32px 24px;
            border-top: 1px solid #e5e7eb;
            margin-top: 32px;
        }

        .print-footer .qr-wrap svg { width: 80px; height: 80px; display: block; }

        .signature-line {
            border-top: 1px solid #9ca3af;
            width: 180px;
            margin-top: 32px;
            padding-top: 4px;
            font-size: 9px;
            color: #6b7280;
            text-align: center;
        }

        hr.divider       { border: none; border-top: 1px solid #e5e7eb; }
        hr.divider-thick { border: none; border-top: 3px solid #059669; }

        .w-full { width: 100%; }
        .no-wrap { white-space: nowrap; }
    </style>
</head>

<body>
    {{ $slot }}
</body>

</html>
