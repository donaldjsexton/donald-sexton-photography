<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use App\Models\Site;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractTemplateSeeder extends Seeder
{
    private const NAME = 'Wedding Photography Agreement';

    /**
     * Create (or refresh) the studio's main wedding photography template and
     * mark it as the default used for new contracts. Idempotent: re-running
     * updates the body in place and leaves exactly one default per site.
     */
    public function run(): void
    {
        $siteId = Site::default()?->id;

        DB::transaction(function () use ($siteId): void {
            ContractTemplate::query()
                ->where('is_default', true)
                ->update(['is_default' => false]);

            ContractTemplate::updateOrCreate(
                ['name' => self::NAME],
                [
                    'site_id' => $siteId,
                    'title' => self::NAME,
                    'description' => 'Default wedding photography agreement — online gallery delivery, signed online.',
                    'body' => $this->body(),
                    'is_default' => true,
                ],
            );
        });
    }

    private function body(): string
    {
        return <<<'BODY'
            This Wedding Photography Agreement ("Agreement") is made between {{photographer_name}} ("Photographer") and {{client_name}} ("Client") for the wedding photography services described below. It becomes binding when signed.

            EVENT DETAILS
            Event: {{event_name}}
            Date: {{event_date}}
            Location: {{event_location}}

            Coverage start and end times, getting-ready and reception locations, and any additional dates or times will be confirmed in writing with the Photographer before the event.

            WHAT'S INCLUDED
            - Preparation and getting-ready coverage
            - Ceremony coverage
            - Family and group portraits
            - Couple's portraits
            - Reception coverage
            - A private online gallery of edited, full-resolution images to view, download, and share with family and friends

            All images are delivered digitally through the online gallery. No physical media or prints (DVD, CD, USB, or printed photographs) are included unless agreed in writing.

            FEES AND PAYMENT
            The total photography fee is set out in the accompanying invoice. A non-refundable booking fee of 50% of the total fee is due when this Agreement is signed and reserves the Photographer for the event date. The remaining balance is due on or before the event date. The date is not held until the signed Agreement and booking fee are received. Any additional services agreed in writing will be added to the balance.

            EXPENSES
            Unless provided by the Client, the Client is responsible for reasonable travel, accommodation, meal, and transport costs related to the event. Any such costs will be agreed in advance.

            RESERVATION
            A signed Agreement and the booking fee are required to reserve the coverage described above.

            ENTIRE AGREEMENT
            This Agreement is the entire understanding between the Photographer and the Client and supersedes any prior or simultaneous agreements. It may be changed only in writing, signed by both parties. Waiving one provision does not waive any other.

            PRE-EVENT CONSULTATION
            The parties will hold a consultation two to three weeks before the event to confirm the schedule, locations, and the Client's written list of requested photographs. The Client agrees to set aside at least one hour, ending about thirty minutes before the ceremony, and a further thirty-minute window afterward, for photographs that cannot be taken during the event itself. If late arrivals shorten this time, the Photographer is not responsible for photographs that could not be taken as a result.

            COOPERATION
            Great results depend on friendly cooperation and clear communication. The Photographer recommends the Client appoint a guide to point out important people for candid and group photographs. The Photographer is not responsible for missed people or moments when no one is available to identify or gather them, when key individuals do not appear or cooperate, or when relevant details were not shared in advance.

            SCHEDULE AND PUNCTUALITY
            The shooting schedule and approach are designed to accomplish the Client's wishes in a relaxed and enjoyable way. Punctuality and cooperation from everyone involved are essential. Coverage begins at the agreed start time.

            VENUE RULES
            The Photographer must work within the guidelines of the ceremony officiant and venue management, and the Client accepts the technical results of any restrictions those guidelines impose. Negotiating any change to those guidelines is the Client's responsibility; the Photographer will offer technical recommendations only.

            COPYRIGHT AND USAGE
            Until the total fee is paid in full, all photographs remain the property of the Photographer and are protected by copyright; they may not be reproduced in any way without the Photographer's written permission. On final payment, the Client receives a personal-use license to the edited images: the Client may print, copy, and share them with family and friends for personal, non-commercial purposes. The Client must obtain the Photographer's written permission before the Client, family, or friends publish or sell any image for profit.

            PORTFOLIO AND PROMOTION
            The Client grants the Photographer permission to use selected images from the event for portfolio, website, social media, advertising, competitions, and other promotional purposes, and releases any claim to profits that may arise from such use.

            LIMIT OF LIABILITY
            If the Photographer is injured or becomes too ill to photograph the event, the Photographer will make every effort to arrange a suitable replacement. If no suitable replacement is found, the Photographer's liability is limited to a refund of all payments received for the event. The Photographer takes the utmost care in capturing, storing, and processing images. However, if images are lost, stolen, or destroyed for any reason within or beyond the Photographer's control, the Photographer's liability is limited to a refund of all payments received for the event. For a partial loss, liability is limited to a prorated portion of the fee based on the percentage of images affected.

            CANCELLATION
            The booking fee is non-refundable. If the Client cancels, the booking fee is retained as liquidated damages, and the Client remains responsible for any costs the Photographer has reasonably incurred up to the date of cancellation.

            DELIVERY
            Edited images are delivered through the private online gallery. Post-production generally takes around a month, though the timeline varies with the size of the gallery and the time of year; the Photographer will confirm an estimated delivery date during the pre-event consultation. The gallery remains available for download for the period communicated by the Photographer.

            ACKNOWLEDGMENT
            By signing, the Client confirms they have read, understood, and agree to the terms of this Agreement.

            Contract {{contract_number}} · Issued {{issue_date}}
            BODY;
    }
}
