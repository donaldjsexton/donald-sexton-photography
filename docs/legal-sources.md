# Legal sources and fact basis

Reference for the privacy policy, terms, accessibility statement and data-deletion page. Each statement on those pages is tied to either site code or a public source below. Not legal advice. Links checked 2026-11-27 (PayPal's page blocks automated requests but is the vendor's own policy URL).

## What the code does (facts stated on the pages)

| Statement | Basis in repo |
| --- | --- |
| Inquiry fields collected (name, email, date, type, venue, source, message, phone, UTM) | `resources/views/inquiries/create.blade.php`, `InquiryController` |
| Quiz stores name, email, answers, consent time and IP, unsubscribe token; one result email with unsubscribe | `QuizController`, `quiz_leads` migration, `emails/quiz/result.blade.php` |
| Optional unchecked SMS box stores consent time and IP; only a "session texts" box exists on the form | `inquiries/create.blade.php`, `InquiryController` (`sms_consent_at`, `sms_consent_ip`) |
| Unsubscribes go to a suppression list; service mail for booked jobs is exempt | `Suppression` model, `EnforceSuppressions`, `Mail/Concerns/IsTransactional` |
| Payments via Square and PayPal (no Stripe) | `app/Services/Payments/SquareGateway.php`, `PayPalGateway.php`, `config/payments.php` |
| Email sent through Gmail API; calendar via Google Calendar | `Mail/GmailApiTransport`, `Services/GoogleCalendar.php` |
| Google Analytics loads after first interaction, only when a measurement ID is configured; no ad pixels | `layouts/app.blade.php`, `SiteSetting::analyticsMeasurementId` |
| Pic-Time content embedded/imported | `components/editorial/pictime-*.blade.php`, `Services/PicTime` |
| Anthropic API reads venue-forwarded referral emails and drafts SEO/alt text | `Services/VenueReferral/VenueReferralExtractor.php`, `Services/Media/AltTextGenerator.php`, `Services/Seo/*` |
| Portal passwords hashed; portal and contracts exist | `routes/web.php` portal routes, Laravel auth |
| Contact email, phone, address | `SiteSetting` (`business_email`, `business_phone`, `business_street_address`...), falling back to `config('payments.business.email')` then `mail.from.address`. Live site schema shows email donald@donaldsextonphotography.com, 1153 Lakeview Rd, Clearwater |
| No Twilio/SMS sending code exists, so no SMS vendor is named | `grep -ri twilio app config` returns nothing |
| No automatic pruning job exists; retention periods on the privacy page are stated policy | `routes/console.php` schedule list |

## Retention choices

- 7 years for client financial records: IRS guidance says to keep records until the period of limitations ends, up to 7 years in some cases. https://www.irs.gov/businesses/small-businesses-self-employed/how-long-should-i-keep-records
- Google Analytics retention is capped at 14 months in standard properties. https://support.google.com/analytics/answer/7667196
- 24 months for unconverted leads is a studio policy choice, not a legal requirement.

## Statutes and rules

- Florida data breach notice (FIPA), s. 501.171: https://www.flsenate.gov/Laws/Statutes/2024/501.171
- Florida Deceptive and Unfair Trade Practices Act, s. 501.204 (why policy claims must match practice): https://www.flsenate.gov/Laws/Statutes/2024/501.204
- CAN-SPAM, 15 U.S.C. 7704 (commercial email: opt-out, no deceptive headers, honor opt-out): https://www.law.cornell.edu/uscode/text/15/7704
- TCPA, 47 U.S.C. 227 (consent for texts) and FCC guide: https://www.law.cornell.edu/uscode/text/47/227 and https://www.fcc.gov/consumers/guides/stop-unwanted-robocalls-and-texts
- CTIA Messaging Principles (STOP/HELP, frequency and rates disclosures): https://www.ctia.org/the-wireless-association/initiatives/messaging-principles-and-best-practices
- WCAG 2.1: https://www.w3.org/TR/WCAG21/ and quick reference https://www.w3.org/WAI/WCAG21/quickref/
- Meta Platform Terms (data deletion instructions URL requirement): https://developers.facebook.com/terms/ and https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback

## Vendor privacy pages

- Google: https://policies.google.com/privacy
- Square: https://squareup.com/us/en/legal/general/privacy
- PayPal: https://www.paypal.com/us/legalhub/privacy-full
- Pic-Time: https://www.pic-time.com/legal/privacy-policy
- Anthropic: https://www.anthropic.com/legal/privacy
