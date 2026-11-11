<?php

declare(strict_types=1);
namespace App\Service;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\When;

/** Production buffers can contain route parameters and exception messages. */
#[When(env: 'prod')]
#[AsMonologProcessor]
final class SensitiveLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: $this->text($record->message), context: $this->context($record->context));
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function context(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match('/token|password|authorization|cookie|secret|email|donor_name/i', $key)) {
                $data[$key] = '[redacted]';
            } elseif ($value instanceof \Throwable) {
                $data[$key] = ['error_class' => $value::class, 'file' => $value->getFile(), 'line' => $value->getLine()];
            } elseif (is_array($value)) {
                $data[$key] = $this->context($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->text($value);
            } elseif (is_object($value)) {
                $data[$key] = ['object_class' => $value::class];
            }
        }
        return $data;
    }

    private function text(string $text): string
    {
        $text = preg_replace('~((?:reset-password|newsletter/(?:confirm|unsubscribe|confirmer|desinscription))/)[^?\s"\']+~i', '$1[redacted]', $text) ?? '[redacted]';
        $text = preg_replace('/([?&](?:token|session_id|password|secret)=)[^&\s]+/i', '$1[redacted]', $text) ?? '[redacted]';
        return preg_replace('/[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,}/i', '[redacted-email]', $text) ?? '[redacted]';
    }
}
