<?php

namespace App\Services\Messaging;

use App\Models\Member;
use App\Support\Notify;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp messages through the WhatsApp Business Platform (Meta's Cloud API).
 *
 * Messages a business starts must use a template WhatsApp has approved. Create one in WhatsApp
 * Manager (Utility category) whose body is just {{1}}, e.g. named "church_notice", and the app
 * puts each message's words in it. Meta charges per message the church starts (much less than
 * SMS); members can turn WhatsApp off in their account.
 */
class WhatsApp
{
    public function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id')) && filled(config('services.whatsapp.template'));
    }

    /** Switched on in Settings & roles, and set up. */
    public function ready(): bool
    {
        return Notify::on('whatsapp') && $this->configured();
    }

    /**
     * To one member, if WhatsApp is on and they haven't turned it off. Returns whether it went.
     */
    public function toMember(?Member $member, string $title, string $body, ?string $url = null): bool
    {
        if (! $member || ! $member->notify_sms || ! $this->ready()) {
            return false;
        }
        $number = Member::internationalPhone($member->phone);

        return $number ? $this->send([$number], $title, $body, $url) > 0 : false;
    }

    /**
     * @param  list<string>  $numbers  international digits, e.g. 2348031234567
     * @return int how many were accepted
     */
    public function send(array $numbers, string $title, string $body, ?string $url = null): int
    {
        if (! $this->configured()) {
            return 0;
        }
        // Template text can't contain new lines or long runs of spaces.
        $text = preg_replace('/\s+/', ' ', trim($title.': '.$body.($url ? ' '.$url : '')));
        $text = mb_substr($text, 0, 1000);
        $endpoint = 'https://graph.facebook.com/'.config('services.whatsapp.version', 'v21.0').'/'.config('services.whatsapp.phone_number_id').'/messages';

        $sent = 0;
        foreach (array_unique($numbers) as $number) {
            try {
                $response = Http::withToken((string) config('services.whatsapp.token'))->timeout(15)->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $number,
                    'type' => 'template',
                    'template' => [
                        'name' => config('services.whatsapp.template'),
                        'language' => ['code' => config('services.whatsapp.language') ?: 'en'],
                        'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $text]]]],
                    ],
                ]);
                if ($response->successful()) {
                    $sent++;
                } else {
                    Log::warning('WhatsApp message not accepted', ['status' => $response->status(), 'error' => $response->json('error.message')]);
                }
            } catch (Throwable $e) {
                Log::error('Could not reach WhatsApp', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }
}
