@props(['calendar'])

@if ($calendar['ready'])
    <section
        class="availability"
        data-availability-calendar
        data-endpoint="{{ $calendar['endpoint'] }}"
        data-month="{{ $calendar['month'] }}"
        data-min-month="{{ $calendar['min_month'] }}"
        data-max-month="{{ $calendar['max_month'] }}"
        data-timezone="{{ $calendar['timezone'] }}"
        aria-labelledby="availability-heading"
    >
        <div data-availability-intro>
            <p class="eyebrow">Open dates</p>
            <h2 id="availability-heading" class="availability__title">See which dates are open.</h2>
            <p class="availability__copy">Choose an open, limited, or held date and I'll put it on your inquiry. I'll confirm it when I reply.</p>
        </div>

        <p class="availability__note" data-availability-fallback hidden role="status">Send an inquiry and I'll check your date by hand.</p>

        <div data-availability-panel>
            <div class="availability__toolbar">
                <button class="availability__nav" type="button" data-availability-prev aria-label="Previous month" aria-controls="availability-grid">
                    <span aria-hidden="true">‹</span>
                </button>
                <h3 class="availability__month" id="availability-month-label" data-availability-label>Loading dates</h3>
                <button class="availability__nav" type="button" data-availability-next aria-label="Next month" aria-controls="availability-grid">
                    <span aria-hidden="true">›</span>
                </button>
            </div>

            <p class="availability__loading" data-availability-loading>Checking open dates…</p>
            <div id="availability-grid" data-availability-grid></div>
            <p class="visually-hidden" data-availability-live aria-live="polite"></p>

            <ul class="availability__legend">
                <li><span class="availability__swatch availability__swatch--open" aria-hidden="true"></span> Open</li>
                <li><span class="availability__swatch availability__swatch--limited" aria-hidden="true"></span> Limited</li>
                <li><span class="availability__swatch availability__swatch--held" aria-hidden="true"></span> Held</li>
                <li><span class="availability__swatch availability__swatch--booked" aria-hidden="true"></span> Booked</li>
                <li><span class="availability__swatch availability__swatch--unavailable" aria-hidden="true"></span> Unavailable</li>
            </ul>
            <p class="availability__hint">Limited days still have some open time. Held days are penciled in. Booked and unavailable days can't be selected.</p>
        </div>

        <noscript>
            <p class="availability__note">Send an inquiry and I'll check your date by hand.</p>
        </noscript>
    </section>
@else
    <p class="availability__note">Send an inquiry and I'll check your date by hand.</p>
@endif
