@extends('portal.layouts.app')

@section('title', 'Invoice '.$invoice->number)

@section('content')
    <section class="card stack">
        <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
            <div>
                <h3>Invoice</h3>
                <h2 style="margin:0;">{{ $invoice->number }}</h2>
                <p class="meta" style="margin:6px 0 0;">
                    Issued {{ $invoice->issue_date?->format('M j, Y') }}
                    @if ($invoice->due_date)
                        · Due {{ $invoice->due_date->format('M j, Y') }}
                    @endif
                </p>
                <span class="pill" style="margin-top:8px;">{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</span>
            </div>
            <div style="text-align:right;">
                <h3>Balance due</h3>
                <p style="margin:0; font-size:24px; font-weight:600;">${{ number_format($invoice->amountDueCents() / 100, 2) }}</p>
                <p class="meta" style="margin:4px 0 0;">of ${{ number_format($invoice->total_cents / 100, 2) }} total</p>
            </div>
        </div>

        <div>
            <h3>Line items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="num">Qty</th>
                        <th class="num">Unit</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lineItems as $item)
                        <tr>
                            <td data-label="Description">{{ $item->description }}</td>
                            <td class="num" data-label="Qty">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td class="num" data-label="Unit">${{ number_format($item->unit_price_cents / 100, 2) }}</td>
                            <td class="num" data-label="Total">${{ number_format($item->total_cents / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="num">Subtotal</td><td class="num">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td></tr>
                    @if ($invoice->discount_cents > 0)
                        <tr><td colspan="3" class="num">Discount</td><td class="num">−${{ number_format($invoice->discount_cents / 100, 2) }}</td></tr>
                    @endif
                    <tr><td colspan="3" class="num">Tax</td><td class="num">${{ number_format($invoice->tax_cents / 100, 2) }}</td></tr>
                    <tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>${{ number_format($invoice->total_cents / 100, 2) }}</strong></td></tr>
                    @if ($invoice->amount_paid_cents > 0)
                        <tr><td colspan="3" class="num">Paid</td><td class="num">−${{ number_format($invoice->amount_paid_cents / 100, 2) }}</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>

        @if ($invoice->installments->isNotEmpty())
            <div>
                <h3>Payment schedule</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Due</th>
                            <th class="num">Amount</th>
                            <th class="num">Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->installments as $inst)
                            <tr>
                                <td data-label="Label">{{ $inst->label ?: 'Installment '.$inst->sequence }}</td>
                                <td data-label="Due">{{ $inst->due_date?->format('M j, Y') ?: '—' }}</td>
                                <td class="num" data-label="Amount">${{ number_format($inst->amount_cents / 100, 2) }}</td>
                                <td class="num" data-label="Paid">${{ number_format($inst->amount_paid_cents / 100, 2) }}</td>
                                <td data-label="Status"><span class="pill">{{ ucfirst(str_replace('_', ' ', $inst->status)) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($invoice->payments->isNotEmpty())
            <div>
                <h3>Payment history</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Received</th>
                            <th>Method</th>
                            <th class="num">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->payments->where('status', \App\Models\Payment::STATUS_COMPLETED) as $payment)
                            <tr>
                                <td data-label="Received">{{ $payment->received_at?->format('M j, Y') ?: $payment->created_at?->format('M j, Y') }}</td>
                                <td data-label="Method">{{ \App\Models\Payment::gatewayOptions()[$payment->gateway] ?? $payment->gateway }}</td>
                                <td class="num" data-label="Amount">${{ number_format($payment->amount_cents / 100, 2) }}</td>
                                <td data-label="Status"><span class="pill">{{ ucfirst($payment->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($invoice->notes)
            <div>
                <h3>Notes</h3>
                <p style="margin:0; white-space:pre-line;">{{ $invoice->notes }}</p>
            </div>
        @endif

        @if ($invoice->terms)
            <div>
                <h3>Terms</h3>
                <p style="margin:0; white-space:pre-line;">{{ $invoice->terms }}</p>
            </div>
        @endif

        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            <a class="btn btn-secondary" href="{{ route('portal.invoices.pdf', ['invoice' => $invoice->uuid]) }}" target="_blank" rel="noopener">Download PDF</a>
            @if ($invoice->isVendorInvoice() && $invoice->amountDueCents() > 0 && $invoice->status !== \App\Models\Invoice::STATUS_VOID)
                <span class="btn btn-secondary" aria-disabled="true">Pay by check or ACH per terms{{ $invoice->net_terms ? ' ('.$invoice->net_terms.')' : '' }}</span>
            @elseif (! $squareEnabled && ! $paypalEnabled && $invoice->amountDueCents() > 0 && $invoice->status !== \App\Models\Invoice::STATUS_VOID)
                <span class="btn btn-secondary" aria-disabled="true" title="Online payments are not enabled yet">Online payments coming soon</span>
            @endif
        </div>
    </section>

    @if ($squareEnabled)
        <section class="card stack">
            <div>
                <h3>Pay with card</h3>
                <p style="margin:0;">
                    Pay <strong>${{ number_format($invoice->amountDueCents() / 100, 2) }}</strong> securely with Square. Cards are processed in their PCI-compliant iframe — your card details never touch our server.
                </p>
            </div>

            <div id="square-payment-form" data-app-id="{{ $squareApplicationId }}" data-location-id="{{ $squareLocationId }}">
                <div id="square-card-container" style="padding:14px; border:1px solid #d9c8b8; border-radius:8px; background:#fff; min-height:90px;"></div>
                <p id="square-error" style="color:#a03030; margin:12px 0 0; min-height:1em;"></p>
                <button id="square-pay-button" type="button" class="btn btn-primary" style="margin-top:14px; width:100%;" disabled>
                    Pay ${{ number_format($invoice->amountDueCents() / 100, 2) }}
                </button>
            </div>

            <form id="square-payment-form-post" method="POST" action="{{ route('portal.invoices.pay.square', ['invoice' => $invoice->uuid]) }}" style="display:none;">
                @csrf
                <input type="hidden" name="source_id" id="square-source-id">
                <input type="hidden" name="verification_token" id="square-verification-token">
            </form>
        </section>

        <script src="{{ $squareSdkUrl }}" defer></script>
        <script>
        (function () {
            var formEl = document.getElementById('square-payment-form');
            var btn = document.getElementById('square-pay-button');
            var errEl = document.getElementById('square-error');
            var postForm = document.getElementById('square-payment-form-post');
            var sourceField = document.getElementById('square-source-id');
            var verificationField = document.getElementById('square-verification-token');

            function init() {
                if (!window.Square) {
                    errEl.textContent = 'Card payment unavailable: Square SDK failed to load.';
                    return;
                }

                var payments = window.Square.payments(formEl.dataset.appId, formEl.dataset.locationId);

                payments.card().then(function (card) {
                    return card.attach('#square-card-container').then(function () {
                        btn.disabled = false;
                        btn.addEventListener('click', function () {
                            btn.disabled = true;
                            errEl.textContent = '';
                            card.tokenize().then(function (result) {
                                if (result.status !== 'OK') {
                                    errEl.textContent = (result.errors || []).map(function (e) { return e.message; }).join(' ') || 'Card could not be processed.';
                                    btn.disabled = false;
                                    return;
                                }
                                sourceField.value = result.token;
                                verificationField.value = result.verificationToken || '';
                                postForm.submit();
                            }).catch(function (err) {
                                errEl.textContent = err && err.message ? err.message : 'Card processing failed.';
                                btn.disabled = false;
                            });
                        });
                    });
                }).catch(function (err) {
                    errEl.textContent = 'Could not initialise the card form: ' + (err && err.message ? err.message : 'unknown error');
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
    @endif

    @if ($paypalEnabled)
        <section class="card stack">
            <div>
                <h3>Pay with PayPal</h3>
                <p style="margin:0;">
                    Pay <strong>${{ number_format($invoice->amountDueCents() / 100, 2) }}</strong> through PayPal — sign in with your PayPal account or pay as a guest with a card.
                </p>
            </div>

            <div id="paypal-button-container" style="min-height:48px;"></div>
            <p id="paypal-error" style="color:#a03030; margin:0; min-height:1em;"></p>
        </section>

        <script src="{{ $paypalSdkUrl }}" data-namespace="paypalNs" defer></script>
        <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            var errEl = document.getElementById('paypal-error');
            var createUrl = @json(route('portal.invoices.pay.paypal.create', ['invoice' => $invoice->uuid]));
            var captureUrl = @json(route('portal.invoices.pay.paypal.capture', ['invoice' => $invoice->uuid]));

            function init() {
                var ns = window.paypalNs || window.paypal;
                if (!ns || !ns.Buttons) {
                    setTimeout(init, 200);
                    return;
                }

                ns.Buttons({
                    style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },
                    createOrder: function () {
                        return fetch(createUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                        }).then(function (res) {
                            return res.json().then(function (data) {
                                if (!res.ok) {
                                    throw new Error(data.error || 'Could not start PayPal payment.');
                                }
                                return data.order_id;
                            });
                        });
                    },
                    onApprove: function (data) {
                        return fetch(captureUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ order_id: data.orderID }),
                        }).then(function (res) {
                            return res.json().then(function (body) {
                                if (!res.ok) {
                                    throw new Error(body.message || 'Capture failed.');
                                }
                                window.location = body.redirect;
                            });
                        });
                    },
                    onError: function (err) {
                        errEl.textContent = (err && err.message) ? err.message : 'PayPal payment failed.';
                    },
                }).render('#paypal-button-container').catch(function (err) {
                    errEl.textContent = 'Could not load PayPal buttons: ' + (err && err.message ? err.message : 'unknown error');
                });
            }

            init();
        })();
        </script>
    @endif
@endsection
