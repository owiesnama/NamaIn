<?php

namespace App\Http\Controllers;

use App\Actions\Bookings\CreateBookingAction;
use App\Enums\BookingStatus;
use App\Exceptions\BookingOverlapException;
use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Bookings\BookingScheduler;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BookingsController extends Controller
{
    public function __construct(
        private BookingScheduler $scheduler,
        private CreateBookingAction $createBooking,
    ) {}

    public function index()
    {
        return inertia('Bookings/Index', [
            'bookings' => Booking::query()
                ->with(['service:id,name,duration_minutes,on_site', 'customer:id,name', 'addons'])
                ->orderByDesc('starts_at')
                ->paginate(parent::ELEMENTS_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    public function store(BookingRequest $request): RedirectResponse
    {
        $service = Product::findOrFail($request->integer('service_product_id'));
        $customer = Customer::findOrFail($request->integer('customer_id'));

        $warnings = $this->guardSchedule($request, $service);

        if ($warnings !== null) {
            return back()->with('travel_buffer_warnings', $warnings);
        }

        $this->createBooking->handle(
            service: $service,
            customer: $customer,
            startsAt: Carbon::parse($request->input('starts_at')),
            addonIds: $request->input('addons', []),
            address: $request->input('address'),
            notes: $request->input('notes'),
            status: $request->enum('status', BookingStatus::class) ?? BookingStatus::Confirmed,
        );

        return redirect()->route('bookings.index')->with('success', __('Booking created successfully.'));
    }

    public function update(Booking $booking, BookingRequest $request): RedirectResponse
    {
        $service = Product::findOrFail($request->integer('service_product_id'));

        $warnings = $this->guardSchedule($request, $service, ignoreId: $booking->id);

        if ($warnings !== null) {
            return back()->with('travel_buffer_warnings', $warnings);
        }

        $booking->update([
            'service_product_id' => $service->id,
            'customer_id' => $request->integer('customer_id'),
            'starts_at' => Carbon::parse($request->input('starts_at')),
            'address' => $request->input('address'),
            'notes' => $request->input('notes'),
            'status' => $request->enum('status', BookingStatus::class) ?? $booking->status,
        ]);

        $this->resnapshotAddons($booking, $service, $request->input('addons', []));

        return redirect()->route('bookings.index')->with('success', __('Booking updated successfully.'));
    }

    /**
     * Merchant-only cancellation. Frees the slot immediately for rebooking.
     */
    public function cancel(Booking $booking): RedirectResponse
    {
        $booking->update(['status' => BookingStatus::Cancelled]);

        return back()->with('success', __('Booking cancelled.'));
    }

    /**
     * Run the scheduling engine: hard-block on overlap (422), otherwise return
     * the unacknowledged travel-buffer warnings for the UI to confirm, or null
     * when the placement is clear or the warnings were acknowledged.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function guardSchedule(BookingRequest $request, Product $service, ?int $ignoreId = null): ?array
    {
        $candidate = new Booking([
            'service_product_id' => $service->id,
            'starts_at' => Carbon::parse($request->input('starts_at')),
        ]);
        $candidate->setRelation('service', $service);

        try {
            $warnings = $this->scheduler->assertBookable($candidate, $ignoreId);
        } catch (BookingOverlapException $e) {
            throw ValidationException::withMessages([
                'starts_at' => __('The selected time overlaps an existing booking for this service.'),
            ]);
        }

        if ($warnings !== [] && ! $request->boolean('acknowledge_buffer')) {
            return array_map(fn ($warning) => $warning->jsonSerialize(), $warnings);
        }

        return null;
    }

    /**
     * Replace the booking's add-on snapshots with the currently-selected ones
     * and recompute the stored total (base price stays the original snapshot).
     *
     * @param  array<int>  $addonIds
     */
    private function resnapshotAddons(Booking $booking, Product $service, array $addonIds): void
    {
        $booking->addons()->delete();

        $addons = $service->serviceAddons()->whereKey($addonIds)->get();
        $total = $booking->base_price;

        foreach ($addons as $addon) {
            $booking->addons()->create([
                'service_addon_id' => $addon->id,
                'name' => $addon->name,
                'price_delta' => $addon->price_delta,
            ]);
            $total += $addon->price_delta;
        }

        $booking->update(['total' => $total]);
    }
}
