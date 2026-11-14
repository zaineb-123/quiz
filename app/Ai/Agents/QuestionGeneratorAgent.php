<?php

namespace App\Ai\Agents;

use App\Enums\QuestionType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('qwen2.5:1.5b')]
#[MaxTokens(2048)]
#[Temperature(0.2)]
#[Timeout(180)]
class QuestionGeneratorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private readonly string $topic,
        private readonly string $type,
        private readonly int $count,
        private readonly int $difficulty,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $typeLabel = QuestionType::from($this->type)->label();
        $difficultyLabel = match ($this->difficulty) {
            1 => 'very easy (beginner)',
            2 => 'easy',
            3 => 'medium',
            4 => 'hard',
            5 => 'very hard (expert)',
            default => 'medium',
        };

        $optionsNote = QuestionType::from($this->type)->hasOptions()
            ? 'Each question must include an "options" array of 4 distinct answer choices. For True/False questions, options must be exactly ["True", "False"].'
            : 'Leave "options" as an empty array [] since this is a short answer question.';

        return <<<INSTRUCTIONS
        You are an expert exam author creating high-quality assessment questions.

        Generate exactly {$this->count} {$typeLabel} question(s) about the topic: "{$this->topic}".
        Difficulty level: {$difficultyLabel}.

               Rules:
        - Write all questions, answers, and explanations in French.
        - Use scientifically accurate SVT knowledge.
        - Each question must be clear, unambiguous, and test genuine understanding.
        - The correct_answer must match one of the options exactly for multiple choice and true/false.
        - Never embed the correct answer in the question text itself.
        - Keep explanations concise (2 to 3 sentences maximum).
        - Avoid repeating the same information.
        - Do not invent scientific facts.
        - Difficulty must be {$this->difficulty} (1=easiest, 5=hardest).
        - {$optionsNote}
        - Return exactly {$this->count} questions.
        - Respond ONLY with valid JSON matching the required schema.
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()
                ->items(
                    $schema->object([
                        'question' => $schema->string()->required(),
                        'type' => $schema->string()->required(),
                        'options' => $schema->array()->items($schema->string()),
                        'correct_answer' => $schema->string()->required(),
                        'explanation' => $schema->string()->required(),
                        'difficulty' => $schema->integer()->min(1)->max(5)->required(),
                    ])
                )
                ->required(),
        ];
    }
}
