<?php

namespace Database\Factories;

use App\Enums\TemplateKey;
use App\Enums\TemplateMode;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => TemplateKey::EnquiryAcknowledgement,
            'locale' => 'en-GB',
            'name' => 'Enquiry acknowledgement',
            'subject' => 'Thank you for getting in touch',
            'body' => 'Hi {{contact_first_name}}, thank you for your message.',
            'enabled' => true,
            'mode' => TemplateMode::Copy,
            'sort_order' => 0,
        ];
    }

    /**
     * A system default, visible to every account.
     */
    public function system(string $vertical = 'wedding_makeup'): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => null,
            'vertical' => $vertical,
        ]);
    }
}
