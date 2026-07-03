<?php

namespace App\Reports;

use Illuminate\Http\Request;

interface Report
{
    /**
     * The Inertia page component that renders this report.
     */
    public function page(): string;

    /**
     * Build the page props for the current request.
     *
     * @return array<string, mixed>
     */
    public function props(Request $request): array;
}
