<?php
declare(strict_types=1);
namespace RCS\Json;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use JsonMapper\Handler\FactoryRegistry;

#[CoversClass(\RCS\Json\JsonDateTime::class)]
final class JsonDateTimeTest extends TestCase
{
    public function testJsonSerializeReturnsIso8601String(): void
    {
        $date = new JsonDateTime('2024-04-10 15:30:00', new \DateTimeZone('UTC'));
        $serialized = $date->jsonSerialize();

        // ISO8601 format check
        self::assertIsString($serialized);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+0000$/',
            $serialized
            );

        // Verify round-trip parsing
        $parsed = new \DateTime($serialized);
        self::assertSame($date->getTimestamp(), $parsed->getTimestamp());
    }

    public function testAddToFactoryRegistersCallbackWithCorrectBehavior(): void
    {
        // Create a PHPUnit mock for FactoryRegistry
        $mockFactory = $this->createMock(FactoryRegistry::class);

        // Expect `addFactory` to be called once with JsonDateTime::class
        $mockFactory
        ->expects($this->once())
        ->method('addFactory')
        ->with(
            JsonDateTime::class,
            $this->callback(function ($callback): bool {
                // Verify the closure returns a JsonDateTime instance
                $this->assertIsCallable($callback);

                $instance = $callback('2024-05-01');
                $this->assertInstanceOf(JsonDateTime::class, $instance);
                $this->assertSame('America/Los_Angeles', $instance->getTimezone()->getName());

                $instance2 = $callback('2024-05-01T12:00:00+00:00');
                $this->assertInstanceOf(JsonDateTime::class, $instance2);
                $this->assertNotSame('America/Los_Angeles', $instance2->getTimezone()->getName());

                return true;
            })
            );

        // Act
        JsonDateTime::addToFactory($mockFactory);
    }

    public function testJsonDateTimeExtendsDateTimeAndImplementsJsonSerializable(): void
    {
        $date = new JsonDateTime('2025-01-01T00:00:00Z');
        self::assertInstanceOf(\DateTime::class, $date);
        self::assertInstanceOf(\JsonSerializable::class, $date);
    }
}
