<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class ExpenseParserAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function provider(): Lab
    {
        return Lab::Anthropic;
    }

    public function instructions(): string
    {
        return implode("\n", [
            'You are parsing expense messages from a property manager. Extract the amount, category, and description.',
            '',
            '## Valid Categories',
            '- cleaning (nettoyage, laundry, linge)',
            '- food (nourriture, market, alimentation, groceries)',
            '- maintenance (plumber, electrician, repair, réparation)',
            '- staff (salary, salaire, wages)',
            '- utilities (water, electricity, gas, eau, électricité)',
            '- other (anything that doesn\'t fit above)',
            '',
            '## Rules',
            '- The message may be in English, French, or Arabic. Parse regardless of language.',
            '- Accept flexible formats:',
            '  - "expense 500 food market" (structured)',
            '  - "paid 500 for market supplies" (natural language)',
            '  - "dépense 1200 nourriture marché" (French)',
            '  - "1200 MAD cleaning" (amount first)',
            '- Extract the numeric amount. Ignore currency symbols/words (MAD, DH, etc.).',
            '- If the category is not explicitly stated, infer it from the description and set category_inferred to true.',
            '- If you cannot determine the amount, return amount as 0.',
            '- Keep the description concise but include all relevant details from the message.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'amount' => $schema->number(),
            'category' => $schema->string()->enum(['cleaning', 'food', 'maintenance', 'staff', 'utilities', 'other']),
            'description' => $schema->string(),
            'category_inferred' => $schema->boolean(),
        ];
    }
}
