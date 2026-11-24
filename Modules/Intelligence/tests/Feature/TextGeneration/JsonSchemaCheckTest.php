<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature\TextGeneration;

use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\TextGeneration\DecodesJsonAnswer;
use Modules\Intelligence\TextGeneration\Drivers\FakeTextGenerationDriver;
use Modules\Intelligence\TextGeneration\TextGenerationRequest;
use Tests\TestCase;

/**
 * CLA-714 (S8a): an answer to a schema request must match the schema, for the
 * documented subset: type, properties, required, items, enum, true/false
 * schemas, description/title. Native review findings R3-002, R4-002 and the
 * validator's CV-001..CV-006.
 */
final class JsonSchemaCheckTest extends TestCase
{
    private function decode(string $json, array $schema): ?array
    {
        $checker = new class
        {
            use DecodesJsonAnswer;

            public function run(string $json, array $schema): ?array
            {
                return $this->decodeIfSchema(new TextGenerationRequest(task: 't', prompt: 'p', jsonSchema: $schema), $json, 'test');
            }
        };

        return $checker->run($json, $schema);
    }

    private function assertRejected(string $json, array $schema, string $message = 'The test answer did not match the requested JSON schema.'): void
    {
        try {
            $this->decode($json, $schema);
            $this->fail("Expected rejection of {$json}");
        } catch (TextGenerationException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_an_empty_object_and_an_empty_list_are_told_apart(): void
    {
        $this->assertSame([], $this->decode('{}', ['type' => 'object']));
        $this->assertSame([], $this->decode('[]', ['type' => 'array']));
        $this->assertRejected('[]', ['type' => 'object']);
        $this->assertRejected('{}', ['type' => 'array']);
    }

    public function test_an_object_with_numeric_keys_is_still_an_object(): void
    {
        $this->assertSame(['0' => 'ok'], $this->decode('{"0":"ok"}', ['type' => 'object', 'properties' => ['0' => ['type' => 'string']]]));
        $this->assertRejected('["ok"]', ['type' => 'object']);
    }

    public function test_integers_accept_whole_floats_only(): void
    {
        $schema = ['type' => 'object', 'properties' => ['score' => ['type' => 'integer']]];

        $this->assertSame(['score' => 7.0], $this->decode('{"score":7.0}', $schema));
        $this->assertSame(['score' => 7], $this->decode('{"score":7}', $schema));
        $this->assertRejected('{"score":7.5}', $schema);
        $this->assertRejected('{"score":"7"}', $schema);
    }

    public function test_required_fields_and_nested_types_are_enforced(): void
    {
        $schema = ['type' => 'object', 'required' => ['summary'], 'properties' => [
            'summary' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]];

        $this->assertSame(['summary' => 'ok', 'tags' => ['a']], $this->decode('{"summary":"ok","tags":["a"]}', $schema));
        $this->assertRejected('{}', $schema);
        $this->assertRejected('{"summary":"ok","tags":[1]}', $schema);
    }

    public function test_enum_compares_objects_regardless_of_member_order(): void
    {
        $schema = ['type' => 'object', 'properties' => ['choice' => ['enum' => [['a' => 1, 'b' => 2], 'none']]]];

        $this->assertSame(['choice' => ['b' => 2, 'a' => 1]], $this->decode('{"choice":{"b":2,"a":1}}', $schema));
        $this->assertSame(['choice' => 'none'], $this->decode('{"choice":"none"}', $schema));
        $this->assertRejected('{"choice":{"a":1}}', $schema);
    }

    public function test_boolean_schemas_accept_or_reject_everything(): void
    {
        $this->assertSame([1, 'x'], $this->decode('[1,"x"]', ['type' => 'array', 'items' => true]));
        $this->assertSame([], $this->decode('[]', ['type' => 'array', 'items' => false]));
        $this->assertRejected('[1]', ['type' => 'array', 'items' => false]);
        $this->assertRejected('{"x":1}', ['type' => 'object', 'properties' => ['x' => false]]);
    }

    public function test_a_scalar_answer_is_rejected(): void
    {
        $this->assertRejected('"text"', ['type' => 'string']);
        $this->assertRejected('not json', ['type' => 'object']);
    }

    public function test_unsupported_keywords_and_types_are_refused_instead_of_ignored(): void
    {
        $this->assertRejected('{"a":1}', ['type' => 'object', 'minProperties' => 1], 'The JSON schema uses an unsupported keyword: minProperties.');
        $this->assertRejected('{"a":1}', ['type' => 'tuple'], 'The JSON schema uses an unsupported type: tuple.');
    }

    public function test_the_fake_keeps_json_for_queued_arrays_without_a_schema(): void
    {
        $fake = app(FakeTextGenerationDriver::class)->queueJson(['a' => 1]);

        $this->assertSame(['a' => 1], $fake->generate(new TextGenerationRequest(task: 't', prompt: 'p'))->json);
    }
}
