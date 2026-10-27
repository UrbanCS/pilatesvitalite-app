<?php

namespace Modules\NewsLetter\app\Jobs;

use App\Traits\MailSenderTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\NewsLetter\app\Emails\SendMailToNewsLetter;
use Modules\NewsLetter\app\Models\MailingList;
use Modules\NewsLetter\app\Models\NewsletterCampaign;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SendMailToNewsletterJob implements ShouldQueue
{
    use Dispatchable, MailSenderTrait, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign_id;

    public function __construct($campaign_id)
    {
        $this->campaign_id = $campaign_id;
    }

    public function handle(): void
    {
        $this->setMailConfig();

        try {
            $campaign = NewsletterCampaign::findOrFail($this->campaign_id);
        } catch (ModelNotFoundException $e) {
            Log::error('NewsletterCampaign ID ' . $this->campaign_id . ' not found. Aborting job.');
            return;
        }

        if (is_null($campaign->mailing_list_id)) {
            Log::error('Mailing list ID is NULL for Campaign ID: ' . $this->campaign_id . '. Aborting job.');
            $campaign->update(['status' => 'failed']);
            return;
        }

        $campaign->update(['status' => 'sending']);

        try {
            $mailingList = MailingList::with(['subscribers' => function ($query) {
                $query->where('status', 'verified');
            }])->findOrFail($campaign->mailing_list_id);

            $newsletters = $mailingList->subscribers;
            $emailContent = $this->absolutizeContentUrls((string) $campaign->content);
            Log::info('SendMailToNewsletterJob started. Campaign ID: ' . $campaign->id . ' | Subscribers count: ' . $newsletters->count());

            foreach ($newsletters as $item) {
                try {
                    if (!$item->verify_token) {
                        $item->update(['verify_token' => Str::random(100)]);
                    }

                    $unsubscribeUrl = route('newsletter-unsubscribe', ['token' => $item->verify_token]);
                    Mail::to($item->email)->send(new SendMailToNewsLetter(
                        $campaign->subject,
                        $emailContent,
                        $unsubscribeUrl
                    ));
                } catch (Exception $ex) {
                    Log::error('SendMailToNewsletterJob failed for subscriber ID ' . $item->id . ': ' . $ex->getMessage());
                }
            }

            $campaign->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (ModelNotFoundException $e) {
            Log::error('Mailing list ID ' . $campaign->mailing_list_id . ' not found during sending. Aborting job.');
            $campaign->update(['status' => 'failed']);
        } catch (Exception $ex) {
            Log::error('General failure in SendMailToNewsletterJob (Campaign ID: ' . $campaign->id . '): ' . $ex->getMessage());
            $campaign->update(['status' => 'failed']);
        }
    }

    private function absolutizeContentUrls(string $content): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return preg_replace_callback(
            '~\b(href|src)=(["\'])(?!https?://|//|mailto:|tel:|data:|cid:|#)([^"\']*)\2~i',
            static function (array $matches) use ($baseUrl): string {
                $path = preg_replace('~^(?:(?:\.\./)|(?:\./))+~', '', $matches[3]);
                $absoluteUrl = $baseUrl . '/' . ltrim((string) $path, '/');

                return $matches[1] . '=' . $matches[2] . $absoluteUrl . $matches[2];
            },
            $content
        ) ?? $content;
    }
}
