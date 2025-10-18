<?php

declare(strict_types=1);

namespace Netfield\RagClient\Tests\Unit\Models\Request;

use PHPUnit\Framework\TestCase;
use Netfield\RagClient\Models\Request\AskRequest;
use Netfield\RagClient\Exception\RagApiException;

class AskRequestTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $question = "What is the meaning of life?";
        $limit = 5;
        $filters = ['type' => 'document'];

        $request = new AskRequest($question, $limit, $filters);

        $this->assertEquals($question, $request->getQuestion());
        $this->assertEquals($limit, $request->getLimit());
        $this->assertEquals($filters, $request->getFilters());
    }

    public function testConstructorWithDefaultValues(): void
    {
        $question = "Simple question";
        $request = new AskRequest($question);

        $this->assertEquals($question, $request->getQuestion());
        $this->assertEquals(10, $request->getLimit()); // Default limit
        $this->assertNull($request->getFilters()); // Default filters
    }

    public function testSetQuestionWithValidInput(): void
    {
        $request = new AskRequest("Initial question");
        $newQuestion = "Updated question";

        $request->setQuestion($newQuestion);

        $this->assertEquals($newQuestion, $request->getQuestion());
    }

    public function testSetQuestionWithShortInput(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('Question must be at least 3 characters long');

        $request = new AskRequest("Initial question");
        $request->setQuestion("Hi"); // Only 2 characters
    }

    public function testSetQuestionWithEmptyInput(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('Question must be at least 3 characters long');

        $request = new AskRequest("Initial question");
        $request->setQuestion("   "); // Only whitespace
    }

    public function testConstructorWithShortQuestion(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('Question must be at least 3 characters long');

        new AskRequest("No");
    }

    public function testSetLimitWithValidValues(): void
    {
        $request = new AskRequest("Test question");

        $request->setLimit(1);
        $this->assertEquals(1, $request->getLimit());

        $request->setLimit(25);
        $this->assertEquals(25, $request->getLimit());

        $request->setLimit(50);
        $this->assertEquals(50, $request->getLimit());
    }

    public function testSetLimitWithInvalidLowValue(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 50');

        $request = new AskRequest("Test question");
        $request->setLimit(0);
    }

    public function testSetLimitWithInvalidHighValue(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 50');

        $request = new AskRequest("Test question");
        $request->setLimit(51);
    }

    public function testConstructorWithInvalidLimit(): void
    {
        $this->expectException(RagApiException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 50');

        new AskRequest("Test question", 100);
    }

    public function testSetFilters(): void
    {
        $request = new AskRequest("Test question");
        $filters = [
            'type' => 'invoice',
            'date_from' => '2024-01-01',
            'department' => 'Finance'
        ];

        $request->setFilters($filters);

        $this->assertEquals($filters, $request->getFilters());
    }

    public function testSetFiltersToNull(): void
    {
        $request = new AskRequest("Test question", 10, ['initial' => 'filters']);

        $request->setFilters(null);

        $this->assertNull($request->getFilters());
    }

    public function testToArray(): void
    {
        $question = "How to calculate taxes?";
        $limit = 15;
        $filters = ['category' => 'tax', 'year' => 2024];

        $request = new AskRequest($question, $limit, $filters);
        $array = $request->toArray();

        $expectedArray = [
            'question' => $question,
            'limit' => $limit,
            'filters' => $filters
        ];

        $this->assertEquals($expectedArray, $array);
    }

    public function testToArrayWithoutFilters(): void
    {
        $question = "Simple question without filters";
        $limit = 5;

        $request = new AskRequest($question, $limit);
        $array = $request->toArray();

        $expectedArray = [
            'question' => $question,
            'limit' => $limit
        ];

        $this->assertEquals($expectedArray, $array);
        $this->assertArrayNotHasKey('filters', $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'question' => 'What are the company policies?',
            'limit' => 20,
            'filters' => ['type' => 'policy', 'active' => true]
        ];

        $request = AskRequest::fromArray($data);

        $this->assertEquals($data['question'], $request->getQuestion());
        $this->assertEquals($data['limit'], $request->getLimit());
        $this->assertEquals($data['filters'], $request->getFilters());
    }

    public function testFromArrayWithDefaults(): void
    {
        $data = [
            'question' => 'Minimum required data'
        ];

        $request = AskRequest::fromArray($data);

        $this->assertEquals($data['question'], $request->getQuestion());
        $this->assertEquals(10, $request->getLimit()); // Default
        $this->assertNull($request->getFilters()); // Default
    }

    public function testFromArrayWithInvalidData(): void
    {
        $this->expectException(RagApiException::class);

        $data = [
            'question' => 'OK',  // Too short
            'limit' => 10
        ];

        AskRequest::fromArray($data);
    }

    /**
     * @dataProvider questionValidationProvider
     */
    public function testQuestionValidation(string $question, bool $shouldPass): void
    {
        if (!$shouldPass) {
            $this->expectException(RagApiException::class);
        }

        $request = new AskRequest($question);

        if ($shouldPass) {
            $this->assertEquals(trim($question), $request->getQuestion());
        }
    }

    public static function questionValidationProvider(): array
    {
        return [
            'valid_short_question' => ['Why?', true],
            'valid_long_question' => [str_repeat('a', 1000), true],
            'valid_with_whitespace' => ['  Valid question  ', true],
            'invalid_too_short' => ['Hi', false],
            'invalid_empty' => ['', false],
            'invalid_only_spaces' => ['   ', false],
            'valid_exactly_3_chars' => ['Hi!', true],
        ];
    }

    /**
     * @dataProvider limitValidationProvider
     */
    public function testLimitValidation(int $limit, bool $shouldPass): void
    {
        if (!$shouldPass) {
            $this->expectException(RagApiException::class);
        }

        $request = new AskRequest("Test question", $limit);

        if ($shouldPass) {
            $this->assertEquals($limit, $request->getLimit());
        }
    }

    public static function limitValidationProvider(): array
    {
        return [
            'valid_min' => [1, true],
            'valid_max' => [50, true],
            'valid_middle' => [25, true],
            'invalid_zero' => [0, false],
            'invalid_negative' => [-5, false],
            'invalid_too_high' => [51, false],
            'invalid_very_high' => [1000, false],
        ];
    }
}
