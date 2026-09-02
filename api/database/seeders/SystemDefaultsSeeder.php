<?php

namespace Database\Seeders;

use App\Enums\TemplateKey;
use App\Enums\TemplateMode;
use App\Models\ContractTemplate;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * System rows with a null account_id: the message templates and the contract
 * wording every account starts from. Rows are matched on their natural key
 * and updated in place, so running the seeder again refreshes the wording
 * rather than duplicating it.
 */
class SystemDefaultsSeeder extends Seeder
{
    public const VERTICAL = 'wedding_makeup';

    public function run(): void
    {
        $this->seedMessageTemplates();
        $this->seedContractTemplate();
    }

    private function seedMessageTemplates(): void
    {
        foreach ($this->messageTemplates() as $index => $template) {
            MessageTemplate::withoutGlobalScope('account')->updateOrCreate(
                [
                    'account_id' => null,
                    'booking_id' => null,
                    'key' => $template['key'],
                    'locale' => 'en-GB',
                ],
                [
                    'vertical' => self::VERTICAL,
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'variants' => $template['variants'] ?? null,
                    'enabled' => true,
                    'mode' => TemplateMode::Copy,
                    'sort_order' => $index,
                ],
            );
        }
    }

    private function seedContractTemplate(): void
    {
        ContractTemplate::withoutGlobalScope('account')->updateOrCreate(
            [
                'account_id' => null,
                'market' => 'GB',
                'vertical' => self::VERTICAL,
                'version' => 1,
            ],
            [
                'name' => 'Standard booking agreement',
                'body' => $this->contractBody(),
                'effective_from' => today(),
            ],
        );
    }

    /**
     * @return array<int, array{key: TemplateKey, name: string, subject: string, body: string, variants?: array<string, string>}>
     */
    private function messageTemplates(): array
    {
        return [
            [
                'key' => TemplateKey::EnquiryAcknowledgement,
                'name' => 'Enquiry acknowledgement',
                'subject' => 'Thank you for getting in touch',
                'body' => <<<'TEXT'
                    Hi {{contact_first_name}},

                    Thank you so much for getting in touch about {{main_event_date}}. I would love to hear a little more about your day: roughly how many people would like makeup, where you are getting ready, and what time you need to be ready by.

                    Once I have that I will put together a quote for you. I will hold the date for you in the meantime.

                    {{sign_off}}
                    TEXT,
            ],
            [
                'key' => TemplateKey::Quote,
                'name' => 'Quote',
                'subject' => 'Your makeup quote for {{main_event_date}}',
                'body' => <<<'TEXT'
                    Hi {{contact_first_name}},

                    Lovely to hear from you. Here is your quote for {{main_event_day}} {{main_event_date}}.

                    The total comes to {{total}}, with a deposit of {{deposit}} to secure the date. The balance of {{balance}} is due on {{balance_due_on}}.

                    If you would like to go ahead, just let me know and I will send over the agreement and the deposit details. If anything needs changing, the numbers are easy to adjust.

                    {{sign_off}}
                    TEXT,
            ],
            [
                'key' => TemplateKey::BookingConfirmed,
                'name' => 'Booking confirmed',
                'subject' => 'You are booked in for {{main_event_date}}',
                'body' => <<<'TEXT'
                    Hi {{contact_first_name}},

                    Thank you, your deposit has arrived and {{main_event_day}} {{main_event_date}} is now firmly in my diary. I am really looking forward to it.

                    Nearer the time I will be in touch to arrange your trial and to run through timings for the morning. If anything changes before then, just drop me a message.

                    {{sign_off}}
                    TEXT,
            ],
            [
                'key' => TemplateKey::InvoiceDepositRequest,
                'name' => 'Deposit request',
                'subject' => 'Deposit to secure {{main_event_date}}',
                'body' => <<<'TEXT'
                    Hi {{contact_first_name}},

                    Thank you for choosing me for {{main_event_date}}. To secure the date, the deposit is {{deposit}}, and the details for paying it are below.

                    {{payment_instructions}}

                    The remaining balance of {{balance}} is due on {{balance_due_on}}. Once the deposit has arrived I will confirm everything in writing.

                    {{sign_off}}
                    TEXT,
            ],
            [
                'key' => TemplateKey::MainEventReminder,
                'name' => 'Reminder for the day',
                'subject' => 'Nearly here: {{main_event_day}} {{main_event_date}}',
                'body' => <<<'TEXT'
                    Hi {{contact_first_name}},

                    Not long now. Here is a quick reminder of the plan for {{main_event_day}}.

                    {{location_block}}

                    A couple of small things that make the morning run smoothly: please have everyone cleansed and moisturised before I arrive, and if you can, keep hair styling and makeup in separate spaces so nobody is waiting on anyone else.

                    See you very soon.

                    {{sign_off}}
                    TEXT,
                'variants' => [
                    'base' => 'I will see you at my studio at {{address}}, with a start time of {{start_time}}. There is parking right outside.',
                    'client' => 'I will be with you at {{address}} at {{start_time}}. If there is anything I should know about parking or access, let me know beforehand.',
                    'venue' => 'I will arrive at {{address}} at {{start_time}}. If the venue has a preferred entrance for suppliers, do let me know.',
                ],
            ],
            [
                'key' => TemplateKey::ThankYou,
                'name' => 'Thank you',
                'subject' => 'Thank you for having me',
                'body' => <<<'TEXT'
                    Hi {{contact_first_name}},

                    Thank you so much for having me as part of your day. It was a real pleasure, and I hope you had the most wonderful time.

                    If you have a spare moment once things have settled down, I would love to hear how it all went. And if any photographs come back that you are happy to share, I would be thrilled to see them.

                    Wishing you all the very best.

                    {{sign_off}}
                    TEXT,
            ],
        ];
    }

    private function contractBody(): string
    {
        return <<<'TEXT'
            PLACEHOLDER, NOT LAWYER REVIEWED

            BOOKING AGREEMENT

            This agreement is between {{business_name}} ("the artist") and {{contact_name}} ("the client") for makeup services on {{main_event_date}}.

            1. THE BOOKING
            The artist will provide the services listed on the accompanying quote at the agreed times and location. The total fee is {{total}}. A deposit of {{deposit}} secures the date and is payable within seven days of signing. The remaining balance of {{balance}} is due on {{balance_due_on}}.

            2. CHANGES
            Numbers and services may be adjusted up to twenty-eight days before the date. Reductions after that point remain chargeable at the quoted rate. Additions are welcome where time allows and are charged at the artist's current rate card.

            3. TRIALS
            Any trial is charged as listed on the quote, is scheduled by agreement, and takes place at the artist's premises unless otherwise arranged.

            4. TIMINGS AND ACCESS
            The client will ensure the artist has access to the agreed location at the agreed start time, with a table, a chair and natural light where possible. Delays caused by late access may reduce the time available for each person.

            5. CANCELLATION BY THE CLIENT
            The deposit is non-refundable in all circumstances, as it reserves a date the artist cannot then offer to anyone else. If the client cancels more than twenty-eight days before the date, no further payment is due. If the client cancels within twenty-eight days of the date, the full balance remains payable. Postponement to a new date is treated as a cancellation and a new booking unless the artist agrees otherwise in writing.

            6. CANCELLATION BY THE ARTIST
            If the artist is unable to attend through illness or circumstances beyond their control, they will make every reasonable effort to arrange a suitable replacement. If no replacement can be found, all money paid will be refunded in full. The artist's liability is limited to the amount paid.

            7. HEALTH AND ALLERGIES
            The client will make the artist aware of any known allergies or skin conditions for anyone receiving makeup. The artist uses professional products and hygienic practice but cannot be held responsible for reactions to products where no such information was given.

            8. PHOTOGRAPHS
            The artist may only use photographs of the client's party for their own promotion where the client has given consent, which can be withdrawn at any time.

            Signed on behalf of the client: {{contact_name}}
            Signed on behalf of the artist: {{business_name}}
            TEXT;
    }
}
