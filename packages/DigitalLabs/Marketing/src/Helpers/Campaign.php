<?php

namespace DigitalLabs\Marketing\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use DigitalLabs\Core\Models\SubscribersList;
use DigitalLabs\Marketing\Mail\NewsletterMail;
use DigitalLabs\Marketing\Repositories\CampaignRepository;
use DigitalLabs\Marketing\Repositories\EventRepository;

class Campaign
{
    /**
     * Create a new helper instance.
     *
     *
     * @return void
     */
    public function __construct(
        protected EventRepository $eventRepository,
        protected CampaignRepository $campaignRepository
    ) {}

    /**
     * Process the email.
     */
    public function process(): void
    {
        $campaigns = $this->campaignRepository->getModel()
            ->leftJoin('marketing_events', 'marketing_campaigns.marketing_event_id', 'marketing_events.id')
            ->leftJoin('marketing_templates', 'marketing_campaigns.marketing_template_id', 'marketing_templates.id')
            ->select('marketing_campaigns.*')
            ->where('marketing_campaigns.status', 1)
            ->where('marketing_templates.status', 'active')
            ->where(function ($query) {
                $query->where('marketing_events.date', Carbon::now()->format('Y-m-d'))
                    ->orWhereNull('marketing_events.date');
            })
            ->get();

        foreach ($campaigns as $campaign) {
            if ($campaign->event->name == 'Birthday') {
                $emails = $this->getBirthdayEmails($campaign);
            } else {
                $emails = $this->getEmailAddresses($campaign);
            }

            foreach ($emails as $email) {
                Mail::queue(new NewsletterMail($email, $campaign));
            }
        }
    }

    /**
     * Get the email address.
     *
     * @param  \DigitalLabs\Marketing\Contracts\Campaign  $campaign
     * @return array
     */
    public function getEmailAddresses($campaign)
    {
        if ($campaign->customer_group->code === 'guest') {
            $customerGroupEmails = SubscribersList::whereNull('customer_id');
        } else {
            $customerGroupEmails = $campaign->customer_group->customers()->where('subscribed_to_news_letter', 1);
        }

        return array_unique($customerGroupEmails->pluck('email')->toArray());
    }

    /**
     * Return customer's emails who has a birthday today.
     *
     * @param  \DigitalLabs\Marketing\Contracts\Campaign  $campaign
     * @return array
     */
    public function getBirthdayEmails($campaign)
    {
        return $campaign->customer_group
            ->customers()
            ->whereRaw('DATE_FORMAT(date_of_birth, "%m-%d") = ?', [Carbon::now()->format('m-d')])
            ->where('subscribed_to_news_letter', 1)
            ->pluck('email')
            ->toArray();
    }
}
